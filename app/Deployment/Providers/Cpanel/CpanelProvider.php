<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Cpanel;

use App\Config\ProfileConfig;
use App\Config\ProjectConfig;
use App\Deployment\AbstractDeploymentProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

final class CpanelProvider extends AbstractDeploymentProvider
{
    private string $lastError = '';

    private ?CpanelApiClient $apiClient = null;

    public function getName(): string
    {
        return 'cpanel';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getApiClient(): CpanelApiClient
    {
        if ($this->apiClient === null) {
            $this->apiClient = new CpanelApiClient(
                $this->getHost(),
                $this->getPort(),
                $this->getUsername(),
                $this->getAuthType(),
                $this->getCredential(),
                $this->getOriginIp(),
            );
        }

        return $this->apiClient;
    }

    public function validate(ProjectConfig $project, ProfileConfig $profile): array
    {
        $errors = parent::validate($project, $profile);

        $host = $this->config['host'] ?? null;
        if (! \is_string($host) || $host === '') {
            $errors[] = 'cPanel host is required (e.g., cpanel.example.com)';
        }

        $port = $this->config['port'] ?? null;
        if (! \is_int($port) && (! \is_string($port) || $port === '')) {
            $errors[] = 'cPanel port is required (2083 for SSL, 2082 for non-SSL)';
        }

        $username = $this->config['username'] ?? null;
        if (! \is_string($username) || $username === '') {
            $errors[] = 'cPanel username is required';
        }

        $hasPassword = isset($this->config['password']) && \is_string($this->config['password']) && $this->config['password'] !== '';
        $hasToken = isset($this->config['api_token']) && \is_string($this->config['api_token']) && $this->config['api_token'] !== '';

        if (! $hasPassword && ! $hasToken) {
            $errors[] = 'cPanel password or API token is required';
        }

        $domain = $profile->get('domain');
        if ($domain === null || $domain === '') {
            $errors[] = "Domain is required for profile: {$profile->name()}";
        }

        return $errors;
    }

    public function plan(ProjectConfig $project, ProfileConfig $profile): array
    {
        $domainValue = $profile->get('domain');
        $domain = \is_string($domainValue) ? $domainValue : '';
        $repository = $project->repository();
        $repoProviderValue = $repository['provider'] ?? 'unknown';
        $repoProvider = \is_string($repoProviderValue) ? $repoProviderValue : 'unknown';
        $repoNameValue = $repository['name'] ?? 'unknown';
        $repoName = \is_string($repoNameValue) ? $repoNameValue : 'unknown';
        $branch = $profile->branch();
        $deploymentMethod = $this->resolveDeploymentMethod($project, $profile);

        $actions = [
            "Configure domain: {$domain}",
        ];

        if ($deploymentMethod === 'git' || $deploymentMethod === 'auto') {
            $actions[] = "Clone repository: {$repoProvider}:{$repoName} ({$branch})";
        }

        if ($deploymentMethod === 'fileman') {
            $actions[] = 'Package local deployment directory';
            $actions[] = 'Upload deployment archive via cPanel File Manager';
            $actions[] = 'Execute deployment extractor over HTTPS';
        }

        $databases = $project->databases();
        if (! empty($databases)) {
            foreach ($databases as $database) {
                $dbName = $this->interpolateDatabaseName($database->name(), $project->name(), $profile->name());
                $actions[] = "Create database: {$dbName}";
            }
        }

        $actions[] = $deploymentMethod === 'fileman'
            ? 'Deploy via cPanel File Manager fallback'
            : 'Deploy via cPanel Git Version Control';

        return [
            'provider' => $this->getName(),
            'project' => $project->name(),
            'profile' => $profile->name(),
            'branch' => $branch,
            'path' => $project->path(),
            'domain' => $domain,
            'repository' => "{$repoProvider}:{$repoName}",
            'deployment_method' => $deploymentMethod,
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
            'note' => 'This will configure deployment via cPanel UAPI on '.$this->getHost(),
        ];
    }

    public function apply(ProjectConfig $project, ProfileConfig $profile): bool
    {
        $deploymentMethod = $this->resolveDeploymentMethod($project, $profile);

        if ($deploymentMethod === 'fileman') {
            return $this->applyFilemanDeployment($project, $profile);
        }

        $domainValue = $profile->get('domain');
        $domain = \is_string($domainValue) ? $domainValue : '';
        $branch = $profile->branch();
        $repository = $project->repository();
        $repoUrlRaw = $repository['url'] ?? '';
        $repoUrl = \is_string($repoUrlRaw) ? $repoUrlRaw : '';

        if ($repoUrl === '') {
            $this->lastError = 'Repository URL is required';

            return false;
        }

        try {
            if ($this->applyGitDeployment($project->name(), $domain, $repoUrl)) {
                return true;
            }

            if ($deploymentMethod === 'auto' && $this->isGitUnavailableError($this->lastError)) {
                return $this->applyFilemanDeployment($project, $profile);
            }
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
        }

        return false;
    }

    public function destroy(ProjectConfig $project, ProfileConfig $profile): bool
    {
        return true;
    }

    private function getHost(): string
    {
        $host = $this->config['host'] ?? '';

        return \is_string($host) ? $host : '';
    }

    private function getPort(): int
    {
        $port = $this->config['port'] ?? 2083;

        if (\is_int($port)) {
            return $port;
        }

        if (\is_string($port) && \is_numeric($port)) {
            return (int) $port;
        }

        return 2083;
    }

    private function getUsername(): string
    {
        $username = $this->config['username'] ?? '';

        return \is_string($username) ? $username : '';
    }

    private function getAuthType(): string
    {
        $token = $this->config['api_token'] ?? null;

        return (\is_string($token) && $token !== '') ? 'api_token' : 'password';
    }

    private function getCredential(): string
    {
        $token = $this->config['api_token'] ?? null;
        if (\is_string($token) && $token !== '') {
            return $token;
        }

        $password = $this->config['password'] ?? '';

        return \is_string($password) ? $password : '';
    }

    private function getOriginIp(): ?string
    {
        $originIp = $this->config['origin_ip'] ?? null;

        if (! \is_string($originIp)) {
            return null;
        }

        $originIp = \trim($originIp);

        return $originIp === '' ? null : $originIp;
    }

    private function getRepositoryPath(string $domain): string
    {
        $basePath = $this->config['repository_path'] ?? '/';

        if (! \is_string($basePath)) {
            $basePath = '/';
        }

        return "/{$this->getUsername()}{$basePath}{$domain}";
    }

    private function getRepositoryName(string $projectName): string
    {
        return $projectName;
    }

    private function resolveDeploymentMethod(ProjectConfig $project, ProfileConfig $profile): string
    {
        $methodValue = $profile->get('deployment_method', $this->config['deployment_method'] ?? 'auto');
        $method = \is_string($methodValue) ? \strtolower($methodValue) : 'auto';

        if (! \in_array($method, ['auto', 'git', 'fileman'], true)) {
            return 'auto';
        }

        if ($method === 'auto' && $project->repository() === []) {
            return 'fileman';
        }

        return $method;
    }

    private function applyGitDeployment(string $projectName, string $domain, string $repoUrl): bool
    {
        $result = $this->getApiClient()->createGitRepository(
            $repoUrl,
            $this->getRepositoryPath($domain),
            $this->getRepositoryName($projectName),
        );

        if (! $result['success']) {
            $message = $result['message'] ?? 'Failed to create repository';
            $this->lastError = \is_string($message) ? $message : 'Failed to create repository';

            return false;
        }

        return true;
    }

    private function applyFilemanDeployment(ProjectConfig $project, ProfileConfig $profile): bool
    {
        $domainValue = $profile->get('domain');
        $domain = \is_string($domainValue) ? $domainValue : '';
        $deployPath = $this->getFilemanDeployPath($profile, $domain);
        $sourcePath = $this->resolveProjectPath($project->path());
        $mode = $this->resolveFilemanMode($project);
        $scriptName = 'shipper-deploy.php';
        $archiveName = 'shipper-deploy.zip';
        $manifestName = 'shipper-deploy.manifest.json';
        $chunkPrefix = 'shipper-deploy.zip.b64.';

        if (! \is_dir($sourcePath)) {
            $this->lastError = "Project path does not exist or is not a directory: {$sourcePath}";

            return false;
        }

        $workingDirectory = \sys_get_temp_dir().'/shipper-cpanel-'.\bin2hex(\random_bytes(8));
        if (! @\mkdir($workingDirectory, 0700, true) && ! \is_dir($workingDirectory)) {
            $this->lastError = "Unable to create temporary deployment directory: {$workingDirectory}";

            return false;
        }

        $archivePath = $workingDirectory.'/'.$archiveName;
        $scriptPath = $workingDirectory.'/'.$scriptName;

        try {
            $this->buildDeploymentArchive($sourcePath, $archivePath, $mode);
            \file_put_contents($scriptPath, $this->buildExtractorScript($mode, $project->webDirectory()));
            $archiveContents = \file_get_contents($archivePath);
            if (! \is_string($archiveContents)) {
                throw new RuntimeException("Unable to read deployment archive: {$archivePath}");
            }

            $encodedArchive = \base64_encode($archiveContents);
            $chunks = \str_split($encodedArchive, 100000);

            $client = $this->getApiClient();
            $manifestContents = \json_encode([
                'chunk_prefix' => $chunkPrefix,
                'chunk_count' => \count($chunks),
            ], JSON_THROW_ON_ERROR);
            $manifestSave = $client->saveFileContent($deployPath, $manifestName, $manifestContents);
            if (! $manifestSave['success']) {
                $this->lastError = \is_string($manifestSave['message'] ?? null)
                    ? $manifestSave['message']
                    : 'Failed to save deployment manifest';

                return false;
            }

            foreach ($chunks as $index => $chunk) {
                $chunkName = $chunkPrefix.\str_pad((string) $index, 6, '0', STR_PAD_LEFT);
                $chunkSave = $client->saveFileContent($deployPath, $chunkName, $chunk);
                if (! $chunkSave['success']) {
                    $this->lastError = \is_string($chunkSave['message'] ?? null)
                        ? $chunkSave['message']
                        : "Failed to save deployment chunk {$index}";

                    return false;
                }
            }

            $scriptContents = \file_get_contents($scriptPath);
            if (! \is_string($scriptContents)) {
                throw new RuntimeException("Unable to read deployment extractor: {$scriptPath}");
            }

            $scriptSave = $client->saveFileContent($deployPath, $scriptName, $scriptContents);
            if (! $scriptSave['success']) {
                $this->lastError = \is_string($scriptSave['message'] ?? null)
                    ? $scriptSave['message']
                    : 'Failed to save deployment extractor';

                return false;
            }

            return $this->triggerDeploymentExtractor($domain, $scriptName);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            return false;
        } finally {
            @\unlink($archivePath);
            @\unlink($scriptPath);
            @\rmdir($workingDirectory);
        }
    }

    private function resolveProjectPath(string $projectPath): string
    {
        if (\str_starts_with($projectPath, '/')) {
            return $projectPath;
        }

        $cwd = \getcwd();
        if ($cwd === false) {
            throw new RuntimeException('Unable to resolve current working directory');
        }

        return \rtrim($cwd, '/').'/'.$projectPath;
    }

    private function resolveFilemanMode(ProjectConfig $project): string
    {
        return $project->webDirectory() === '/' ? 'direct' : 'public_copy';
    }

    private function getFilemanDeployPath(ProfileConfig $profile, string $domain): string
    {
        $configuredPath = $profile->get('deploy_path');
        if (\is_string($configuredPath) && $configuredPath !== '') {
            return \str_starts_with($configuredPath, '/') ? $configuredPath : '/'.$configuredPath;
        }

        $parts = \array_values(\array_filter(\explode('.', $domain), static fn (string $part): bool => $part !== ''));
        if (\count($parts) <= 2) {
            return '/public_html';
        }

        return '/'.$parts[0];
    }

    private function buildDeploymentArchive(string $sourcePath, string $archivePath, string $mode): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Unable to create deployment archive: {$archivePath}");
        }

        $prefix = $mode === 'public_copy' ? 'app/' : '';
        $directory = new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            $pathname = $item->getPathname();
            $relativePath = \substr($pathname, \strlen(\rtrim($sourcePath, '/').'/'));
            if ($relativePath === false || $relativePath === '') {
                continue;
            }

            $entryName = $prefix.\str_replace('\\', '/', $relativePath);

            if ($item->isDir()) {
                $zip->addEmptyDir($entryName);

                continue;
            }

            $zip->addFile($pathname, $entryName);
        }

        $zip->close();
    }

    private function buildExtractorScript(string $mode, string $webDirectory): string
    {
        $trimmedWebDirectory = \trim($webDirectory, '/');
        $escapedWebDirectory = \var_export($trimmedWebDirectory, true);

        $copyPublicBlock = $mode === 'public_copy'
            ? <<<'PHP'
$publicDir = $appDir . '/' . WEB_DIRECTORY;
$publicItems = scandir($publicDir);
if ($publicItems !== false) {
    foreach ($publicItems as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        rcopy($publicDir . DIRECTORY_SEPARATOR . $item, __DIR__ . DIRECTORY_SEPARATOR . $item);
    }
}

$indexPath = __DIR__ . '/index.php';
if (file_exists($indexPath)) {
    $index = file_get_contents($indexPath);

    if (is_string($index)) {
        $index = str_replace("__DIR__.'/../vendor/autoload.php'", "__DIR__.'/app/vendor/autoload.php'", $index);
        $index = str_replace("__DIR__.'/../bootstrap/app.php'", "__DIR__.'/app/bootstrap/app.php'", $index);
        file_put_contents($indexPath, $index);
    }
}
PHP
            : '';

        return <<<PHP
<?php
declare(strict_types=1);

const WEB_DIRECTORY = {$escapedWebDirectory};

function rrmdir(string \$dir): void
{
    if (! is_dir(\$dir)) {
        return;
    }

    \$items = scandir(\$dir);
    if (\$items === false) {
        return;
    }

    foreach (\$items as \$item) {
        if (\$item === '.' || \$item === '..') {
            continue;
        }

        \$path = \$dir . DIRECTORY_SEPARATOR . \$item;

        if (is_dir(\$path) && ! is_link(\$path)) {
            rrmdir(\$path);

            continue;
        }

        @unlink(\$path);
    }

    @rmdir(\$dir);
}

function rcopy(string \$source, string \$destination): void
{
    if (is_dir(\$source)) {
        if (! is_dir(\$destination)) {
            mkdir(\$destination, 0755, true);
        }

        \$items = scandir(\$source);
        if (\$items === false) {
            return;
        }

        foreach (\$items as \$item) {
            if (\$item === '.' || \$item === '..') {
                continue;
            }

            rcopy(\$source . DIRECTORY_SEPARATOR . \$item, \$destination . DIRECTORY_SEPARATOR . \$item);
        }

        return;
    }

    \$parent = dirname(\$destination);
    if (! is_dir(\$parent)) {
        mkdir(\$parent, 0755, true);
    }

    copy(\$source, \$destination);
}

\$manifestPath = __DIR__ . '/shipper-deploy.manifest.json';
\$manifest = json_decode((string) file_get_contents(\$manifestPath), true);
if (! is_array(\$manifest)) {
    http_response_code(500);
    exit('manifest invalid');
}

\$chunkPrefix = isset(\$manifest['chunk_prefix']) && is_string(\$manifest['chunk_prefix']) ? \$manifest['chunk_prefix'] : '';
\$chunkCount = isset(\$manifest['chunk_count']) ? (int) \$manifest['chunk_count'] : 0;
if (\$chunkPrefix === '' || \$chunkCount < 1) {
    http_response_code(500);
    exit('manifest incomplete');
}

\$archive = __DIR__ . '/shipper-deploy.zip';
\$script = __FILE__;
\$appDir = __DIR__ . '/app';

\$encoded = '';
for (\$i = 0; \$i < \$chunkCount; \$i++) {
    \$chunkPath = __DIR__ . '/' . \$chunkPrefix . str_pad((string) \$i, 6, '0', STR_PAD_LEFT);

    if (! file_exists(\$chunkPath)) {
        http_response_code(500);
        exit('chunk missing');
    }

    \$chunk = file_get_contents(\$chunkPath);
    if (! is_string(\$chunk)) {
        http_response_code(500);
        exit('chunk unreadable');
    }

    \$encoded .= \$chunk;
}

\$decoded = base64_decode(\$encoded, true);
if (! is_string(\$decoded)) {
    http_response_code(500);
    exit('archive decode failed');
}

file_put_contents(\$archive, \$decoded);

if (! file_exists(\$archive) || filesize(\$archive) === 0) {
    http_response_code(500);
    exit('missing archive');
}

\$items = scandir(__DIR__);
if (\$items !== false) {
    foreach (\$items as \$item) {
        if (in_array(\$item, ['.', '..', 'shipper-deploy.php', 'shipper-deploy.zip'], true)) {
            continue;
        }

        \$path = __DIR__ . DIRECTORY_SEPARATOR . \$item;

        if (is_dir(\$path) && ! is_link(\$path)) {
            rrmdir(\$path);

            continue;
        }

        @unlink(\$path);
    }
}

rrmdir(\$appDir);

\$zip = new ZipArchive();
if (\$zip->open(\$archive) !== true) {
    http_response_code(500);
    exit('open failed');
}

if (! \$zip->extractTo(__DIR__)) {
    \$zip->close();
    http_response_code(500);
    exit('extract failed');
}

\$zip->close();

{$copyPublicBlock}

@unlink(\$archive);
@unlink(\$manifestPath);
for (\$i = 0; \$i < \$chunkCount; \$i++) {
    @unlink(__DIR__ . '/' . \$chunkPrefix . str_pad((string) \$i, 6, '0', STR_PAD_LEFT));
}
@unlink(\$script);

echo 'shipper deployment ok';
PHP;
    }

    private function triggerDeploymentExtractor(string $domain, string $scriptName): bool
    {
        $originIpValue = $this->config['origin_ip'] ?? null;
        $originIp = \is_string($originIpValue) ? \trim($originIpValue) : '';
        $url = "https://{$domain}/{$scriptName}";
        $command = $originIp !== ''
            ? \sprintf(
                '%s -skS --resolve %s %s',
                $this->resolveCurlBinary(),
                \escapeshellarg("{$domain}:443:{$originIp}"),
                \escapeshellarg($url),
            )
            : \sprintf(
                '%s -skS %s',
                $this->resolveCurlBinary(),
                \escapeshellarg($url),
            );

        $output = [];
        $exitCode = 0;
        \exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->lastError = \implode("\n", $output);

            return false;
        }

        $body = \implode("\n", $output);
        if (\str_contains($body, 'shipper deployment ok')) {
            return true;
        }

        $this->lastError = $body !== '' ? $body : 'Deployment extractor did not report success';

        return false;
    }

    private function isGitUnavailableError(string $message): bool
    {
        return \str_contains($message, 'Failed to load module “Git”')
            || \str_contains($message, 'could not find the function “create_repository”')
            || \str_contains($message, 'Cpanel::API::Git');
    }

    private function resolveCurlBinary(): string
    {
        $binary = \trim((string) \shell_exec('command -v curl'));

        if ($binary === '') {
            throw new RuntimeException('curl binary is required for cPanel deployment operations');
        }

        return \escapeshellcmd($binary);
    }

    private function interpolateDatabaseName(string $name, string $projectName, string $profileName): string
    {
        $name = \str_replace('${PROJECT_NAME}', $projectName, $name);
        $name = \str_replace('${PROFILE}', $profileName, $name);

        return $name;
    }
}
