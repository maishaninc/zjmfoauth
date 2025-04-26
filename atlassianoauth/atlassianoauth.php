<?php

namespace oauth\atlassianoauth;

class atlassianoauth
{
    public function __construct()
    {
        if(!session_id()) session_start();
    }

    /**
     * 插件信息
     * @return array
     */
    public function meta()
    {
        return [
            'name'        => 'Atlassian登录',
            'description' => '使用 Atlassian 账号登录 (Jira, Confluence 等)',
            'author'      => 'Maishan Inc',
            'logo_url'    => 'atlassian.svg', // Ensure atlassian.svg exists
            'version'     => '1.0.0',
        ];
    }

    /**
     * 插件接口配置信息
     * @return array
     */
    public function config()
    {
        return [
            'Client ID' => [
                'type' => 'text',
                'name' => 'client_id',
                'desc' => '在 Atlassian Developer Console 创建应用后获取'
            ],
            'Client Secret' => [
                'type' => 'text',
                'name' => 'client_secret',
                'desc' => '在 Atlassian Developer Console 创建应用后获取'
            ],
        ];
    }

    /**
     * 生成授权地址
     * @param array $params 配置参数及回调地址
     * @return string 授权地址
     */
    public function url($params)
    {
        $state = md5(uniqid(rand(), true));
        $_SESSION['oauth_atlassian_state'] = $state;

        $queryData = [
            'audience'      => 'api.atlassian.com',
            'client_id'     => $params['client_id'],
            'scope'         => 'read:me read:account', // Request necessary scopes
            'redirect_uri'  => $params['callback'],
            'state'         => $state,
            'response_type' => 'code',
            'prompt'        => 'consent', // Force user consent screen
        ];
        $atlassianOAuthUrl = 'https://auth.atlassian.com/authorize?' . http_build_query($queryData);
        return $atlassianOAuthUrl;
    }

    /**
     * 回调处理
     * @param array $params 配置参数、回调地址及第三方返回参数
     * @return array 用户信息
     * @throws \Exception
     */
    public function callback($params)
    {
        // 0. Verify state
        if (!isset($params['state']) || !isset($_SESSION['oauth_atlassian_state']) || $_SESSION['oauth_atlassian_state'] !== $params['state']) {
            unset($_SESSION['oauth_atlassian_state']);
            throw new \Exception('无效的 state 参数，可能存在 CSRF 攻击');
        }

        // 1. Check for errors from Atlassian
        if (isset($params['error'])) {
            unset($_SESSION['oauth_atlassian_state']);
            throw new \Exception('Atlassian 授权失败: ' . ($params['error_description'] ?? $params['error']));
        }

        // 2. Exchange authorization code for access token
        if (!isset($params['code'])) {
             unset($_SESSION['oauth_atlassian_state']);
             throw new \Exception('未收到 Atlassian 返回的授权码(code)');
        }

        $tokenUrl = 'https://auth.atlassian.com/oauth/token';
        $tokenData = [
            'grant_type'    => 'authorization_code',
            'client_id'     => $params['client_id'],
            'client_secret' => $params['client_secret'],
            'code'          => $params['code'],
            'redirect_uri'  => $params['callback'],
        ];

        // Atlassian token endpoint expects JSON payload
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        try {
            // Use POST request with JSON data
            $tokenInfo = $this->_request($tokenUrl, 'POST', $tokenData, $headers, true); // Pass true for JSON payload
        } catch (\Exception $e) {
            unset($_SESSION['oauth_atlassian_state']);
            throw new \Exception('获取 Atlassian Access Token 失败: ' . $e->getMessage());
        }

        if (!isset($tokenInfo['access_token'])) {
            unset($_SESSION['oauth_atlassian_state']);
            $errorMsg = $tokenInfo['error_description'] ?? $tokenInfo['error'] ?? json_encode($tokenInfo);
            throw new \Exception('Atlassian 返回的 Token 信息无效: ' . $errorMsg);
        }

        $accessToken = $tokenInfo['access_token'];

        // 3. Use access token to get user info
        $userInfoUrl = 'https://api.atlassian.com/me';
        $userInfoHeaders = ['Authorization: Bearer ' . $accessToken, 'Accept: application/json'];

        try {
            $userInfo = $this->_request($userInfoUrl, 'GET', [], $userInfoHeaders);
        } catch (\Exception $e) {
            unset($_SESSION['oauth_atlassian_state']);
            throw new \Exception('获取 Atlassian 用户信息失败: ' . $e->getMessage());
        }

        // Verify user info structure
        if (!isset($userInfo['account_id'])) {
            unset($_SESSION['oauth_atlassian_state']);
            throw new \Exception('Atlassian 返回的用户信息无效: ' . json_encode($userInfo));
        }

        // 4. Format the result
        $result = [
            // Use account_id as the unique identifier (openid)
            'openid' => $userInfo['account_id'],
            'callbackBind' => 'all',
            'data' => [
                'username' => $userInfo['name'] ?? '',
                'avatar' => $userInfo['picture'] ?? '',
                'email' => $userInfo['email'] ?? '',
                // Atlassian API might provide nickname or zoneinfo
                // 'nickname' => $userInfo['nickname'] ?? '',
                // 'zoneinfo' => $userInfo['zoneinfo'] ?? '',
            ],
        ];

        $result['data'] = array_filter($result['data']);
        unset($_SESSION['oauth_atlassian_state']);
        return $result;
    }

    /**
     * Helper method to make HTTP requests using cURL.
     *
     * @param string $url URL to request
     * @param string $method HTTP method (GET, POST)
     * @param array $data Data for POST request
     * @param array $headers Additional HTTP headers
     * @param bool $jsonEncodeData Whether to JSON encode POST data
     * @return array Decoded JSON response
     * @throws \Exception On cURL error or non-200 response or JSON decode error or API error
     */
    private function _request($url, $method = 'GET', $data = [], $headers = [], $jsonEncodeData = false)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Consider enabling in production
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Consider enabling in production
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $defaultHeaders = ['User-Agent: Zjmf-OAuth-Client/1.0'];
        $finalHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($jsonEncodeData) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                // Ensure Content-Type: application/json is in headers
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                // Ensure Content-Type: application/x-www-form-urlencoded is in headers if needed
            }
        } elseif (strtoupper($method) !== 'GET') {
             curl_close($ch);
             throw new \Exception('Unsupported HTTP method: ' . $method);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno) {
            throw new \Exception('cURL Error (' . $errno . '): ' . $error);
        }

        $decodedResponse = json_decode($response, true);
        $jsonLastError = json_last_error();

        // Check HTTP status code first
        if ($httpCode < 200 || $httpCode >= 300) { // Check for non-2xx status codes
            $errorMessage = $response; // Default to raw response
            if ($jsonLastError === JSON_ERROR_NONE && $decodedResponse !== null) {
                 // Try to extract error message from Atlassian's JSON error structure
                if (isset($decodedResponse['error_description'])) {
                    $errorMessage = $decodedResponse['error_description'];
                } elseif (isset($decodedResponse['error'])) {
                    $errorMessage = $decodedResponse['error'];
                } elseif (isset($decodedResponse['message'])) { // Sometimes error is in 'message'
                    $errorMessage = $decodedResponse['message'];
                }
            }
            throw new \Exception('HTTP Error ' . $httpCode . ': ' . $errorMessage);
        }

        // If HTTP 2xx, check JSON decoding
        if ($jsonLastError !== JSON_ERROR_NONE) {
             // Atlassian API responses should be JSON. If not, it's unexpected.
             throw new \Exception('Failed to decode JSON response. Raw response: ' . $response);
        }

        // Even with HTTP 2xx, Atlassian might return an error object in the JSON
        if (isset($decodedResponse['error'])) {
            $errorDetails = $decodedResponse['error_description'] ?? $decodedResponse['error'];
            throw new \Exception('Atlassian API Error: ' . $errorDetails);
        }

        return $decodedResponse;
    }
}