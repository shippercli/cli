<?php

declare(strict_types=1);

namespace App\Deployment\Providers\Cpanel;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class CpanelApiClient
{
    private ?Client $httpClient = null;

    private string $baseUrl;

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function formatResponse(array $result): array
    {
        return [
            'success' => ($result['status'] ?? 0) === 1,
            'message' => $this->extractMessage($result),
            'data' => $result['data'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function extractMessage(array $result): string
    {
        $errors = $result['errors'] ?? null;
        if (\is_array($errors) && isset($errors[0]) && \is_string($errors[0])) {
            return $errors[0];
        }

        $messages = $result['messages'] ?? null;
        if (\is_array($messages) && isset($messages[0]) && \is_string($messages[0])) {
            return $messages[0];
        }

        return 'OK';
    }

    public function __construct(
        string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $authType,
        private readonly string $credential,
    ) {
        $protocol = $this->port === 2096 || $this->port === 2095 ? 'http' : 'https';
        $this->baseUrl = "{$protocol}://{$host}:{$this->port}/execute";
    }

    /**
     * @return array<string, mixed>
     */
    public function createGitRepository(string $cloneUrl, string $repositoryPath, string $repositoryName): array
    {
        return $this->request('Git::create_repository', [
            'clone_url' => $cloneUrl,
            'repository_path' => $repositoryPath,
            'repository_name' => $repositoryName,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listGitRepositories(): array
    {
        return $this->request('Git::list_repositories', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function listFeatures(): array
    {
        return $this->request('Features::list_features', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteGitRepository(string $repositoryPath): array
    {
        return $this->request('Git::delete_repository', [
            'repository_path' => $repositoryPath,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function pullGitRepository(string $repositoryPath): array
    {
        return $this->request('Git::pull', [
            'repository_path' => $repositoryPath,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function deployGitRepository(string $repositoryPath): array
    {
        return $this->request('Git::deploy', [
            'repository_path' => $repositoryPath,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function createDatabase(string $databaseName): array
    {
        return $this->request('Database::create_database', [
            'name' => $databaseName,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function createDatabaseUser(string $databaseName, string $username, string $password): array
    {
        return $this->request('Database::create_user', [
            'database' => $databaseName,
            'username' => $username,
            'password' => $password,
        ]);
    }

    /**
     * @param array<string> $privileges
     *
     * @return array<string, mixed>
     */
    public function addDatabaseUserToDatabase(string $database, string $username, array $privileges): array
    {
        return $this->request('Database::add_user_database_privileges', [
            'database' => $database,
            'username' => $username,
            'privileges' => $privileges,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listDomains(): array
    {
        return $this->request('DomainInfo::list_domains', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function createSubdomain(string $domain, string $subdomain): array
    {
        return $this->request('SubDomain::addsubdomain', [
            'domain' => $domain,
            'subdomain' => $subdomain,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadFile(string $directory, string $localPath, string $remoteFilename, bool $overwrite = true): array
    {
        $handle = \fopen($localPath, 'rb');
        if ($handle === false) {
            return [
                'success' => false,
                'message' => "Unable to open file for upload: {$localPath}",
                'data' => [],
            ];
        }

        try {
            return $this->requestMultipart('Fileman::upload_files', [
                [
                    'name' => 'dir',
                    'contents' => $directory,
                ],
                [
                    'name' => 'overwrite',
                    'contents' => $overwrite ? '1' : '0',
                ],
                [
                    'name' => 'file-1',
                    'contents' => $handle,
                    'filename' => $remoteFilename,
                ],
            ]);
        } finally {
            if (\is_resource($handle)) {
                \fclose($handle);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getFileContent(string $directory, string $filename): array
    {
        return $this->request('Fileman::get_file_content', [
            'dir' => $directory,
            'file' => $filename,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function installSsl(string $domain, string $cert, string $key, string $ca = ''): array
    {
        $params = [
            'domain' => $domain,
            'cert' => $cert,
            'key' => $key,
        ];

        if ($ca !== '') {
            $params['ca_bundle'] = $ca;
        }

        return $this->request('SSL::install_ssl', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSslCertificate(string $domain): array
    {
        return $this->request('SSL::list_ssl', [
            'domain' => $domain,
        ]);
    }

    /**
     * @param array<string, array<string>|string> $params
     *
     * @return array<string, mixed>
     */
    private function request(string $moduleFunction, array $params): array
    {
        $client = $this->getHttpClient();

        [$module, $function] = \explode('::', $moduleFunction, 2);
        $url = "{$this->baseUrl}/{$module}/{$function}";

        try {
            $response = $client->request('GET', $url, $this->buildRequestOptions([
                'query' => $params,
            ]));

            /** @var string $body */
            $body = (string) $response->getBody();
            /** @var array<string, mixed> $data */
            $data = \json_decode($body, true) ?? [];

            return $this->formatResponse($data);
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $multipart
     *
     * @return array<string, mixed>
     */
    private function requestMultipart(string $moduleFunction, array $multipart): array
    {
        $client = $this->getHttpClient();

        [$module, $function] = \explode('::', $moduleFunction, 2);
        $url = "{$this->baseUrl}/{$module}/{$function}";

        try {
            $response = $client->request('POST', $url, $this->buildRequestOptions([
                'multipart' => $multipart,
            ]));

            /** @var string $body */
            $body = (string) $response->getBody();
            /** @var array<string, mixed> $data */
            $data = \json_decode($body, true) ?? [];

            return $this->formatResponse($data);
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    private function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client([
                'timeout' => 30,
                'verify' => true,
            ]);
        }

        return $this->httpClient;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function buildRequestOptions(array $options): array
    {
        if ($this->authType === 'api_token') {
            $options['headers'] = [
                'Authorization' => 'cpanel '.$this->username.':'.$this->credential,
            ];

            return $options;
        }

        $options['auth'] = [$this->username, $this->credential];

        return $options;
    }
}
