<?php

class GitHubClient
{
    private $config;
    private $accessToken;

    public function __construct($accessToken = null)
    {
        $this->config = require __DIR__ . '/../config/github.php';
        $this->accessToken = $accessToken;
    }

    public function exchangeOAuthCode($code)
    {
        return $this->requestForm('https://github.com/login/oauth/access_token', [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'code' => $code,
            'redirect_uri' => $this->config['redirect_uri'],
        ]);
    }

    public function getAuthenticatedUser()
    {
        return $this->request('GET', 'https://api.github.com/user');
    }

    public function getAuthenticatedUserEmails()
    {
        return $this->request('GET', 'https://api.github.com/user/emails');
    }

    public function searchRepositories($query, $page = 1, $perPage = 10)
    {
        return $this->request('GET', 'https://api.github.com/search/repositories?' . http_build_query([
            'q' => $query,
            'sort' => 'updated',
            'order' => 'desc',
            'page' => $page,
            'per_page' => $perPage,
        ]));
    }

    public function getRepository($owner, $repo)
    {
        return $this->request('GET', 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo));
    }

    public function getRepositoryLanguages($owner, $repo)
    {
        return $this->request('GET', 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/languages');
    }

    public function getRepositoryTopics($owner, $repo)
    {
        return $this->request('GET', 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/topics');
    }

    public function getRepositoryIssues($owner, $repo, array $params = [])
    {
        $params = array_merge([
            'state' => 'open',
            'per_page' => 20,
        ], $params);

        return $this->request('GET', 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/issues?' . http_build_query($params));
    }

    public function getRepositoryContributorsCount($owner, $repo)
    {
        $response = $this->requestWithMeta('GET', 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/contributors?' . http_build_query([
            'per_page' => 1,
        ]));

        $lastPage = $this->lastPageFromLinkHeader($this->headerValue($response['headers'], 'link'));
        if ($lastPage !== null) {
            return $lastPage;
        }

        return count($response['data']);
    }

    public function getRepositoryLabels($owner, $repo)
    {
        return $this->request('GET', 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/labels?per_page=100');
    }

    public function getRepositoryContents($owner, $repo, $path = '')
    {
        $url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/contents';
        if ($path !== '') {
            $url .= '/' . str_replace('%2F', '/', rawurlencode($path));
        }

        return $this->request('GET', $url);
    }

    public function getRateLimit()
    {
        return $this->request('GET', 'https://api.github.com/rate_limit');
    }

    public function revokeOAuthToken($accessToken)
    {
        if (!$accessToken || empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            return false;
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensao cURL nao esta disponivel.');
        }

        $url = 'https://api.github.com/applications/' . rawurlencode($this->config['client_id']) . '/token';
        $headers = [
            'Accept: application/vnd.github+json',
            'Content-Type: application/json',
            'User-Agent: ' . $this->config['user_agent'],
            'X-GitHub-Api-Version: ' . $this->config['api_version'],
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->config['connect_timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config['client_id'] . ':' . $this->config['client_secret']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['access_token' => $accessToken]));

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Falha ao revogar token GitHub: ' . $error);
        }

        if (in_array($status, [200, 204, 404], true)) {
            return true;
        }

        $decoded = json_decode($raw, true);
        $message = is_array($decoded) && isset($decoded['message']) ? $decoded['message'] : 'Erro ao revogar token GitHub.';

        throw new RuntimeException('GitHub HTTP ' . $status . ': ' . $message, $status);
    }

    private function request($method, $url, $payload = null, $useBearer = true)
    {
        $response = $this->requestWithMeta($method, $url, $payload, $useBearer);
        return $response['data'];
    }

    private function requestWithMeta($method, $url, $payload = null, $useBearer = true)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensao cURL nao esta disponivel.');
        }

        $responseHeaders = [];
        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: ' . $this->config['user_agent'],
            'X-GitHub-Api-Version: ' . $this->config['api_version'],
        ];

        if ($useBearer && $this->accessToken) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }

        if (!$useBearer) {
            $headers[] = 'Accept: application/json';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->config['connect_timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeaders) {
            $length = strlen($header);
            $header = trim($header);

            if ($header === '' || strpos($header, ':') === false) {
                return $length;
            }

            [$name, $value] = explode(':', $header, 2);
            $name = strtolower(trim($name));
            $value = trim($value);

            if (isset($responseHeaders[$name])) {
                if (!is_array($responseHeaders[$name])) {
                    $responseHeaders[$name] = [$responseHeaders[$name]];
                }

                $responseHeaders[$name][] = $value;
            } else {
                $responseHeaders[$name] = $value;
            }

            return $length;
        });

        if ($payload !== null) {
            $body = json_encode($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
        }

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Falha ao chamar GitHub: ' . $error);
        }

        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? $decoded : [];

        if ($status >= 400) {
            $message = isset($data['message']) ? $data['message'] : 'Erro na API do GitHub.';
            throw new RuntimeException('GitHub HTTP ' . $status . ': ' . $message, $status);
        }

        return [
            'data' => $data,
            'headers' => $responseHeaders,
            'status' => $status,
        ];
    }

    private function headerValue(array $headers, $name)
    {
        $name = strtolower($name);
        $value = $headers[$name] ?? null;

        if (is_array($value)) {
            $value = end($value);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function lastPageFromLinkHeader($linkHeader)
    {
        if (!$linkHeader) {
            return null;
        }

        foreach (explode(',', $linkHeader) as $link) {
            if (strpos($link, 'rel="last"') === false) {
                continue;
            }

            if (preg_match('/[?&]page=(\d+)/', $link, $matches)) {
                return max(1, (int) $matches[1]);
            }
        }

        return null;
    }

    private function requestForm($url, array $payload)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensao cURL nao esta disponivel.');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: ' . $this->config['user_agent'],
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->config['connect_timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Falha ao chamar GitHub: ' . $error);
        }

        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? $decoded : [];

        if ($status >= 400) {
            $message = isset($data['error_description']) ? $data['error_description'] : 'Erro no OAuth GitHub.';
            throw new RuntimeException('GitHub OAuth HTTP ' . $status . ': ' . $message, $status);
        }

        return $data;
    }
}
