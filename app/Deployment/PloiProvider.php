<?php

declare(strict_types=1);

namespace App\Deployment;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use Ploi\Ploi;

final class PloiProvider extends AbstractDeploymentProvider
{
    /**
     * Delay in seconds to wait after deployment completes before fetching logs.
     * This ensures Ploi has time to finalize log entries.
     */
    private const LOG_FETCH_DELAY_SECONDS = 5;

    /**
     * Maximum number of log entries to include in error messages.
     */
    private const MAX_ERROR_LOG_ENTRIES = 10;

    /**
     * Ploi status values that indicate an operation is still in progress.
     *
     * @var array<string>
     */
    private const PENDING_STATUSES = ['installing', 'deploying', 'queued', 'pending'];

    /**
     * Ploi status values that indicate the operation ended in failure.
     *   deploy-failed  — deploy script failed (git pull, composer, etc.)
     *   install-failed — initial site installation failed (e.g. "Clone Git repository failed")
     *   failed         — generic / unexpected failure
     *
     * @var array<string>
     */
    private const FAILURE_STATUSES = ['deploy-failed', 'install-failed', 'failed'];

    /**
     * Ploi status values that indicate the operation completed successfully.
     *
     * @var array<string>
     */
    private const SUCCESS_STATUSES = ['active', 'deployed', 'ready', 'installed'];

    private ?Ploi $client = null;

    private string $lastError = '';

    private int $lastSiteId = 0;

    public function getName(): string
    {
        return 'ploi';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        $errors = parent::validate($project, $profile);

        // Validate Ploi-specific configuration
        if (! isset($this->config['api_key']) || $this->config['api_key'] === '') {
            $errors[] = 'Ploi API key is required';
        }

        if (! isset($this->config['server_id']) || $this->config['server_id'] === '') {
            $errors[] = 'Ploi server ID is required';
        } else {
            $serverIdValue = $this->config['server_id'];
            \assert(\is_string($serverIdValue) || \is_int($serverIdValue));
            $serverIdString = \is_string($serverIdValue) ? $serverIdValue : (string) $serverIdValue;

            if (! \ctype_digit($serverIdString)) {
                $errors[] = 'Ploi server ID must contain only digits';
            }
        }

        $domain = $profile->get('domain');
        if ($domain === null || $domain === '') {
            $errors[] = "Domain is required for profile: {$profile->name()}";
        }

        // Validate repository configuration
        $repository = $project->repository();
        if (empty($repository)) {
            $errors[] = 'Repository configuration is required';
        } else {
            $providerMixed = $repository['provider'] ?? null;
            \assert(\is_string($providerMixed) || $providerMixed === null);
            if ($providerMixed === null || $providerMixed === '') {
                $errors[] = 'Repository provider is required (github, gitlab, bitbucket, or custom)';
            }
            $nameMixed = $repository['name'] ?? null;
            \assert(\is_string($nameMixed) || $nameMixed === null);
            if ($nameMixed === null || $nameMixed === '') {
                $errors[] = 'Repository name is required (e.g., username/repository)';
            }
        }

        return $errors;
    }

    public function plan(ProjectConfig $project, ProfileConfig $profile): array
    {
        $serverId = $this->getServerId();
        $domainValue = $profile->get('domain');
        $domain = \is_string($domainValue) ? $domainValue : '';
        $repository = $project->repository();
        $repoProviderValue = $repository['provider'] ?? 'unknown';
        $repoProvider = \is_string($repoProviderValue) ? $repoProviderValue : 'unknown';
        $repoNameValue = $repository['name'] ?? 'unknown';
        $repoName = \is_string($repoNameValue) ? $repoNameValue : 'unknown';

        $actions = [
            "Create or find site for domain: {$domain}",
            "Install repository: {$repoProvider}:{$repoName} ({$profile->branch()})",
        ];

        // Add database creation actions
        $databases = $project->databases();
        if (! empty($databases)) {
            foreach ($databases as $dbKey => $database) {
                $dbName = $this->interpolateDatabaseName($database->name(), $project->name(), $profile->name());
                $dbUser = $this->interpolateDatabaseName($database->user(), $project->name(), $profile->name());
                $actions[] = "Create or find database: {$dbName} (user: {$dbUser}, type: {$database->type()})";
            }
        }

        $actions[] = 'Deploy site via Ploi API';
        $actions[] = 'Run deployment script';

        return [
            'provider' => $this->getName(),
            'project' => $project->name(),
            'profile' => $profile->name(),
            'branch' => $profile->branch(),
            'path' => $project->path(),
            'server_id' => $serverId,
            'domain' => $domain,
            'repository' => "{$repoProvider}:{$repoName}",
            'web_directory' => $project->webDirectory(),
            'project_root' => $project->projectRoot(),
            'databases' => \array_map(
                fn ($db) => [
                    'name' => $this->interpolateDatabaseName($db->name(), $project->name(), $profile->name()),
                    'user' => $this->interpolateDatabaseName($db->user(), $project->name(), $profile->name()),
                    'type' => $db->type(),
                ],
                $databases,
            ),
            'actions' => $actions,
            'note' => 'This will create a real deployment on Ploi server '.$serverId,
        ];
    }

    public function apply(ProjectConfig $project, ProfileConfig $profile): bool
    {
        $this->lastError = '';
        $serverId = 0;
        $domain = '';

        try {
            $client = $this->getClient();
            $serverId = (int) $this->getServerId();
            $domainValue = $profile->get('domain');
            $domain = \is_string($domainValue) ? $domainValue : '';

            if ($domain === '') {
                $this->lastError = 'Domain is empty or invalid';

                return false;
            }

            // Get the server
            $server = $client->server($serverId);

            // Get repository configuration
            $repository = $project->repository();
            $repoProviderMixed = $repository['provider'] ?? null;
            \assert(\is_string($repoProviderMixed) || $repoProviderMixed === null);
            $repoProvider = \is_string($repoProviderMixed) ? $repoProviderMixed : '';
            $repoNameMixed = $repository['name'] ?? null;
            \assert(\is_string($repoNameMixed) || $repoNameMixed === null);
            $repoName = \is_string($repoNameMixed) ? $repoNameMixed : '';
            $branch = $profile->branch();

            // Check if site already exists
            $sites = $server->sites()->get();
            $existingSite = null;

            $siteData = $sites->getJson()->data ?? null;
            if ($siteData !== null && \is_array($siteData)) {
                foreach ($siteData as $site) {
                    if (\is_object($site) && \property_exists($site, 'domain') && $site->domain === $domain) {
                        $existingSite = $site;
                        break;
                    }
                }
            }

            // Create site if it doesn't exist
            if ($existingSite === null) {
                $response = $server->sites()->create(
                    $domain,
                    $project->webDirectory(),
                    $project->projectRoot(),
                );
                $responseData = $response->getJson()->data ?? null;
                if ($responseData === null || ! \property_exists($responseData, 'id')) {
                    $this->lastError = 'Failed to create site: Invalid response from Ploi API';

                    return false;
                }
                $siteId = (int) $responseData->id;

                // Install repository for new site
                $site = $server->sites($siteId);
                try {
                    $site->repository()->install($repoProvider, $branch, $repoName);
                } catch (\Exception $e) {
                    $this->lastError = "Failed to install repository: {$e->getMessage()}";

                    return false;
                }
            } else {
                if (! \property_exists($existingSite, 'id')) {
                    $this->lastError = 'Existing site found but has no ID';

                    return false;
                }
                $siteId = (int) $existingSite->id;
            }

            // Create or find databases for this project/profile
            $databases = $project->databases();
            if (! empty($databases)) {
                foreach ($databases as $dbKey => $database) {
                    $dbName = $this->interpolateDatabaseName($database->name(), $project->name(), $profile->name());
                    $dbUser = $this->interpolateDatabaseName($database->user(), $project->name(), $profile->name());

                    // Check if database already exists
                    $existingDatabases = $server->databases()->get();
                    $existingDb = null;

                    $dbData = $existingDatabases->getJson()->data ?? null;
                    if ($dbData !== null && \is_array($dbData)) {
                        foreach ($dbData as $db) {
                            if (\is_object($db) && \property_exists($db, 'name') && $db->name === $dbName) {
                                $existingDb = $db;
                                break;
                            }
                        }
                    }

                    // Create database if it doesn't exist
                    if ($existingDb === null) {
                        try {
                            $password = $this->generateDatabasePassword();
                            $server->databases()->create($dbName, $dbUser, $password, null, $siteId);
                        } catch (\Exception $e) {
                            $this->lastError = "Failed to create database {$dbName}: {$e->getMessage()}";

                            return false;
                        }
                    }
                }
            }

            // Deploy the site
            $site = $server->sites($siteId);
            $this->lastSiteId = $siteId;
            $deployResponse = $site->deployment()->deploy();

            // Check if deployment was successful
            $deployData = $deployResponse->getJson();
            if (isset($deployData->message) && \is_string($deployData->message)) {
                $this->lastError = "Ploi API message: {$deployData->message}";
            }

            // Wait for deployment to complete and check status
            $timeout = $this->getDeploymentTimeout();
            $pollInterval = 5; // Poll every 5 seconds
            $elapsed = 0;
            $initialCheck = true;

            while ($elapsed < $timeout) {
                if (! $initialCheck) {
                    \sleep($pollInterval);
                    $elapsed += $pollInterval;
                }
                $initialCheck = false;

                // Get site status
                $siteResponse = $server->sites($siteId)->get();
                $siteInfo = $siteResponse->getJson()->data ?? null;

                if ($siteInfo === null) {
                    continue;
                }

                $status = \property_exists($siteInfo, 'status') ? (string) $siteInfo->status : '';
                $isDeploying = \property_exists($siteInfo, 'deploying') ? (bool) $siteInfo->deploying : false;

                // Keep polling while the site is still in a transitional state.
                // Ploi uses 'deploying' flag and 'installing'/'queued' status values to
                // signal that an operation is still in progress.
                if ($isDeploying || \in_array($status, self::PENDING_STATUSES, true)) {
                    continue;
                }

                // The site has left its transitional state — check the outcome.

                if (\in_array($status, self::FAILURE_STATUSES, true)) {
                    $this->lastError = "Deployment failed on Ploi server (status: {$status})";

                    // Fetch logs to include actionable details in the error message
                    \sleep(self::LOG_FETCH_DELAY_SECONDS);
                    $logs = $this->getDeploymentLogs($serverId, $siteId);
                    if ($logs !== []) {
                        $this->lastError .= "\nRecent logs:\n".\implode("\n", \array_slice($logs, 0, self::MAX_ERROR_LOG_ENTRIES));
                    }

                    return false;
                }

                // Wait a moment to ensure logs are fully written before fetching them
                \sleep(self::LOG_FETCH_DELAY_SECONDS);
                $logs = $this->getDeploymentLogs($serverId, $siteId);

                // Known success statuses — trust the API and return success.
                if (\in_array($status, self::SUCCESS_STATUSES, true)) {
                    return true;
                }

                // Unknown status — fall back to inspecting the log descriptions. Ploi
                // appends " failed" to a step name when it fails (e.g. "Clone Git
                // repository failed"), so this covers any gaps in the status field.
                foreach ($logs as $log) {
                    if ($this->isFailureLog($log)) {
                        $this->lastError = "Deployment failed on Ploi server (log: {$log})";

                        return false;
                    }
                }

                // No failure indicators found — treat as success
                return true;
            }

            // Timeout reached - this could mean deployment is still running
            $this->lastError = "Deployment timeout after {$timeout} seconds. Deployment may still be running on Ploi.";

            return false;
        } catch (\Ploi\Exceptions\Http\Unauthenticated $e) {
            $this->lastError = "Authentication failed: Invalid Ploi API key. {$e->getMessage()}";

            return false;
        } catch (\Ploi\Exceptions\Http\NotFound $e) {
            $serverInfo = $serverId > 0 ? "Server ID {$serverId}" : 'The requested server';
            $this->lastError = "Resource not found: {$serverInfo} may not exist or you don't have access. {$e->getMessage()}";

            return false;
        } catch (\Ploi\Exceptions\Http\NotValid $e) {
            $this->lastError = "Validation error: {$e->getMessage()}";

            return false;
        } catch (\Exception $e) {
            $this->lastError = "Deployment error: {$e->getMessage()} (Type: ".\get_class($e).')';

            return false;
        }
    }

    public function destroy(ProjectConfig $project, ProfileConfig $profile): bool
    {
        $this->lastError = '';
        $serverId = 0;
        $domain = '';

        try {
            $client = $this->getClient();
            $serverId = (int) $this->getServerId();
            $domainValue = $profile->get('domain');
            $domain = \is_string($domainValue) ? $domainValue : '';

            if ($domain === '') {
                $this->lastError = 'Domain is empty or invalid';

                return false;
            }

            // Get the server
            $server = $client->server($serverId);

            // Find site by domain
            $sites = $server->sites()->get();
            $existingSite = null;

            $siteData = $sites->getJson()->data ?? null;
            if ($siteData !== null && \is_array($siteData)) {
                foreach ($siteData as $site) {
                    if (\is_object($site) && \property_exists($site, 'domain') && $site->domain === $domain) {
                        $existingSite = $site;
                        break;
                    }
                }
            }

            // If site doesn't exist, consider it already destroyed
            if ($existingSite === null) {
                return true;
            }

            // Get site ID
            if (! \property_exists($existingSite, 'id')) {
                $this->lastError = 'Site found but has no ID';

                return false;
            }
            $siteId = (int) $existingSite->id;

            // Delete databases associated with this project/profile
            $databases = $project->databases();
            if (! empty($databases)) {
                foreach ($databases as $dbKey => $database) {
                    $dbName = $this->interpolateDatabaseName($database->name(), $project->name(), $profile->name());

                    // Find and delete the database
                    $existingDatabases = $server->databases()->get();
                    $dbData = $existingDatabases->getJson()->data ?? null;

                    if ($dbData !== null && \is_array($dbData)) {
                        foreach ($dbData as $db) {
                            if (\is_object($db) && \property_exists($db, 'name') && $db->name === $dbName) {
                                if (\property_exists($db, 'id')) {
                                    try {
                                        $server->databases((int) $db->id)->delete();
                                    } catch (\Exception $e) {
                                        // Continue even if database deletion fails
                                        // We still want to delete the site
                                    }
                                }
                                break;
                            }
                        }
                    }
                }
            }

            // Delete the site
            $site = $server->sites($siteId);
            $deleteResponse = $site->delete();

            // Check if deletion was successful
            $deleteData = $deleteResponse->getJson();
            if (isset($deleteData->message) && \is_string($deleteData->message)) {
                // Check if message indicates success or failure
                $messageLower = \strtolower($deleteData->message);
                if (\str_contains($messageLower, 'error') || \str_contains($messageLower, 'failed')) {
                    $this->lastError = "Failed to delete site: {$deleteData->message}";

                    return false;
                }
            }

            return true;
        } catch (\Ploi\Exceptions\Http\Unauthenticated $e) {
            $this->lastError = "Authentication failed: Invalid Ploi API key. {$e->getMessage()}";

            return false;
        } catch (\Ploi\Exceptions\Http\NotFound $e) {
            // If site is not found, consider it already destroyed
            return true;
        } catch (\Ploi\Exceptions\Http\NotValid $e) {
            $this->lastError = "Validation error: {$e->getMessage()}";

            return false;
        } catch (\Exception $e) {
            $this->lastError = "Destroy error: {$e->getMessage()} (Type: ".\get_class($e).')';

            return false;
        }
    }

    public function getClient(): Ploi
    {
        if ($this->client === null) {
            $apiKey = $this->config['api_key'] ?? '';
            \assert(\is_string($apiKey));
            $this->client = new Ploi($apiKey);
        }

        return $this->client;
    }

    public function getServerId(): string
    {
        $serverId = $this->config['server_id'] ?? '';
        \assert(\is_string($serverId) || \is_int($serverId));

        return (string) $serverId;
    }

    private function getDeploymentTimeout(): int
    {
        $timeout = $this->config['deployment_timeout'] ?? 60;
        \assert(\is_int($timeout) || \is_numeric($timeout));

        return (int) $timeout;
    }

    /**
     * Fetch deployment logs for a site.
     *
     * @return array<string>
     */
    public function getDeploymentLogs(int $serverId, int $siteId): array
    {
        try {
            $client = $this->getClient();
            $server = $client->server($serverId);
            $site = $server->sites($siteId);

            $logsResponse = $site->logs();
            $logsData = $logsResponse->getData();

            if (! \is_array($logsData)) {
                return [];
            }

            $logs = [];
            foreach ($logsData as $log) {
                if (\is_object($log) && \property_exists($log, 'description')) {
                    $logs[] = (string) $log->description;
                }
            }

            return $logs;
        } catch (\Exception $e) {
            return ["Error fetching logs: {$e->getMessage()}"];
        }
    }

    /**
     * Get the last site ID that was deployed.
     */
    public function getLastSiteId(): int
    {
        return $this->lastSiteId;
    }

    /**
     * Determine whether a single deployment log entry indicates a failure.
     *
     * Ploi appends " failed" to the step name for any failed deployment step
     * (e.g. "Clone Git repository failed"), so we check for that in addition
     * to other well-known failure keywords.
     */
    public function isFailureLog(string $log): bool
    {
        $logLower = \strtolower($log);

        return \str_ends_with($logLower, 'failed') ||
            \str_contains($logLower, 'deployment failure') ||
            \str_contains($logLower, 'fatal error') ||
            \str_contains($logLower, 'critical error');
    }

    /**
     * Interpolate database name with project and profile placeholders.
     * Also interpolates any remaining environment variables.
     */
    private function interpolateDatabaseName(string $name, string $projectName, string $profileName): string
    {
        $name = \str_replace('${PROJECT_NAME}', $projectName, $name);
        $name = \str_replace('${PROFILE}', $profileName, $name);

        // Interpolate any remaining environment variables
        $name = \preg_replace_callback(
            '/\$\{([A-Z_][A-Z0-9_]*)\}/',
            function (array $matches): string {
                $envVar = $matches[1];
                $envValue = \getenv($envVar);

                // If env var is not set, remove the placeholder (use empty string)
                return $envValue !== false ? $envValue : '';
            },
            $name,
        ) ?? $name;

        // Clean up any trailing underscores or multiple consecutive underscores
        // that may result from missing environment variables
        $name = \preg_replace('/_+$/', '', $name) ?? $name; // Remove trailing underscores
        $name = \preg_replace('/_+/', '_', $name) ?? $name; // Replace multiple underscores with single

        return $name;
    }

    /**
     * Generate a secure random password for database.
     */
    private function generateDatabasePassword(): string
    {
        return \bin2hex(\random_bytes(16));
    }
}
