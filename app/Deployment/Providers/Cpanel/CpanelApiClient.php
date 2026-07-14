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
        private readonly ?string $originIp = null,
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
        if (! \is_file($localPath)) {
            return [
                'success' => false,
                'message' => "Unable to open file for upload: {$localPath}",
                'data' => [],
            ];
        }

        $authorization = $this->authType === 'api_token'
            ? '-H '.\escapeshellarg('Authorization: cpanel '.$this->username.':'.$this->credential)
            : '-u '.\escapeshellarg($this->username.':'.$this->credential);

        $command = \sprintf(
            '%s -skS %s%s -F %s -F %s -F %s %s',
            $this->resolveCurlBinary(),
            $authorization,
            $this->buildCurlResolveArgument(),
            \escapeshellarg("dir={$directory}"),
            \escapeshellarg('overwrite='.($overwrite ? '1' : '0')),
            \escapeshellarg("file-1=@{$localPath};filename={$remoteFilename}"),
            \escapeshellarg("{$this->baseUrl}/Fileman/upload_files"),
        );

        $output = [];
        $exitCode = 0;
        \exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            return [
                'success' => false,
                'message' => \implode("\n", $output),
                'data' => [],
            ];
        }

        $body = \implode("\n", $output);
        /** @var array<string, mixed> $data */
        $data = \json_decode($body, true) ?? [];
        if ($data === []) {
            return $this->formatUnexpectedResponse($body);
        }

        return $this->formatResponse($data);
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
    public function saveFileContent(string $directory, string $filename, string $content): array
    {
        $authorization = $this->authType === 'api_token'
            ? '-H '.\escapeshellarg('Authorization: cpanel '.$this->username.':'.$this->credential)
            : '-u '.\escapeshellarg($this->username.':'.$this->credential);

        $command = \sprintf(
            '%s -skS %s%s -X POST --data-urlencode %s --data-urlencode %s --data-urlencode %s --data-urlencode %s --data-urlencode %s %s',
            $this->resolveCurlBinary(),
            $authorization,
            $this->buildCurlResolveArgument(),
            \escapeshellarg("dir={$directory}"),
            \escapeshellarg("file={$filename}"),
            \escapeshellarg("content={$content}"),
            \escapeshellarg('from_charset=utf-8'),
            \escapeshellarg('to_charset=utf-8'),
            \escapeshellarg("{$this->baseUrl}/Fileman/save_file_content"),
        );

        $output = [];
        $exitCode = 0;
        \exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            return [
                'success' => false,
                'message' => \implode("\n", $output),
                'data' => [],
            ];
        }

        $body = \implode("\n", $output);
        /** @var array<string, mixed> $data */
        $data = \json_decode($body, true) ?? [];
        if ($data === []) {
            return $this->formatUnexpectedResponse($body);
        }

        return $this->formatResponse($data);
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
            if ($data === []) {
                return $this->formatUnexpectedResponse($body);
            }

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
            if ($data === []) {
                return $this->formatUnexpectedResponse($body);
            }

            return $this->formatResponse($data);
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    private function resolveCurlBinary(): string
    {
        $binary = \trim((string) \shell_exec('command -v curl'));

        if ($binary === '') {
            throw new \RuntimeException('curl binary is required for cPanel file uploads');
        }

        return \escapeshellcmd($binary);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUnexpectedResponse(string $body): array
    {
        $snippet = \trim($body);
        if ($snippet === '') {
            $snippet = 'Empty response body from cPanel API';
        } else {
            $snippet = \preg_replace('/\s+/', ' ', $snippet) ?? $snippet;
            if (\strlen($snippet) > 280) {
                $snippet = \substr($snippet, 0, 280).'...';
            }
        }

        return [
            'success' => false,
            'message' => $snippet,
            'data' => [],
        ];
    }

    private function buildCurlResolveArgument(): string
    {
        $originIp = $this->normalizedOriginIp();
        if ($originIp === null) {
            return '';
        }

        return ' --resolve '.\escapeshellarg("{$this->hostFromBaseUrl()}:{$this->port}:{$originIp}");
    }

    private function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $config = [
                'timeout' => 30,
                'verify' => true,
            ];

            $originIp = $this->normalizedOriginIp();
            if ($originIp !== null) {
                $config['curl'] = [
                    \CURLOPT_RESOLVE => [
                        "{$this->hostFromBaseUrl()}:{$this->port}:{$originIp}",
                    ],
                ];
            }

            $this->httpClient = new Client($config);
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

    private function normalizedOriginIp(): ?string
    {
        if (! \is_string($this->originIp)) {
            return null;
        }

        $originIp = \trim($this->originIp);

        return $originIp === '' ? null : $originIp;
    }

    private function hostFromBaseUrl(): string
    {
        $host = \parse_url($this->baseUrl, PHP_URL_HOST);

        return \is_string($host) ? $host : '';
    }
}
