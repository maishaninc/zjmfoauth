<?php

namespace oauth\gitlabcnoauth;

class gitlabcnoauth
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
            'name'        => '极狐GitLab登录',
            'description' => '使用 极狐 GitLab (jihulab.com) 账号登录',
            'author'      => 'Maishan Inc', // Or your name/company
            'logo_url'    => 'gitlabcn.svg', // Ensure this file exists in the same directory
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
                'desc' => '在 极狐 GitLab (jihulab.com) 创建应用后获取 Application ID'
            ],
            'Client Secret' => [
                'type' => 'text',
                'name' => 'client_secret',
                'desc' => '在 极狐 GitLab (jihulab.com) 创建应用后获取 Secret'
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
        $_SESSION['oauth_gitlabcn_state'] = $state;

        $queryData = [
            'client_id'     => $params['client_id'],
            'redirect_uri'  => $params['callback'],
            'response_type' => 'code',
            'scope'         => 'read_user openid profile email', // Standard GitLab scopes
            'state'         => $state,
        ];
        $gitlabOAuthUrl = 'https://jihulab.com/oauth/authorize?' . http_build_query($queryData);
        return $gitlabOAuthUrl;
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
        if (!isset($params['state']) || !isset($_SESSION['oauth_gitlabcn_state']) || $_SESSION['oauth_gitlabcn_state'] !== $params['state']) {
            unset($_SESSION['oauth_gitlabcn_state']);
            throw new \Exception('无效的 state 参数，可能存在 CSRF 攻击');
        }

        // 1. Check for errors from GitLab
        if (isset($params['error'])) {
            throw new \Exception('极狐 GitLab 授权失败: ' . $params['error'] . ' - ' . ($params['error_description'] ?? 'No description'));
        }

        // 2. Exchange authorization code for access token
        if (!isset($params['code'])) {
             throw new \Exception('未收到 极狐 GitLab 返回的授权码(code)');
        }

        $tokenUrl = 'https://jihulab.com/oauth/token';
        $tokenData = [
            'code'          => $params['code'],
            'client_id'     => $params['client_id'],
            'client_secret' => $params['client_secret'],
            'redirect_uri'  => $params['callback'],
            'grant_type'    => 'authorization_code',
        ];
        // GitLab requires standard POST data, no special headers needed here usually
        $tokenHeaders = ['Accept: application/json']; // Still good practice

        try {
            $tokenInfo = $this->_request($tokenUrl, 'POST', $tokenData, $tokenHeaders);
        } catch (\Exception $e) {
            unset($_SESSION['oauth_gitlabcn_state']);
            throw new \Exception('获取 极狐 GitLab Access Token 失败: ' . $e->getMessage());
        }

        if (isset($tokenInfo['error'])) {
             unset($_SESSION['oauth_gitlabcn_state']);
             throw new \Exception('极狐 GitLab Token API 错误: ' . $tokenInfo['error'] . ' - ' . ($tokenInfo['error_description'] ?? 'No description'));
        }

        if (!isset($tokenInfo['access_token'])) {
            unset($_SESSION['oauth_gitlabcn_state']);
            throw new \Exception('极狐 GitLab 返回的 Token 信息无效: ' . json_encode($tokenInfo));
        }

        // 3. Use access token to get user info
        $userInfoUrl = 'https://jihulab.com/api/v4/user';
        // GitLab API requires Authorization header
        $userInfoHeaders = [
            'Authorization: Bearer ' . $tokenInfo['access_token'],
            'User-Agent: Zjmf-OAuth-Client/1.0' // Good practice to include User-Agent
        ];

        try {
            $userInfo = $this->_request($userInfoUrl, 'GET', [], $userInfoHeaders);
        } catch (\Exception $e) {
            unset($_SESSION['oauth_gitlabcn_state']);
            throw new \Exception('获取 极狐 GitLab 用户信息失败: ' . $e->getMessage());
        }

        if (!isset($userInfo['id'])) {
             throw new \Exception('极狐 GitLab 返回的用户信息无效: ' . json_encode($userInfo));
        }

        // 4. Format the result (Adjust based on GitLab API response structure)
        $result = [
            'openid' => (string)$userInfo['id'], // Ensure openid is a string
            'callbackBind' => 'all',
            'data' => [
                'username' => $userInfo['username'], // GitLab username
                'nickname' => $userInfo['name'] ?? $userInfo['username'], // Use display name if available, fallback to username
                'avatar' => $userInfo['avatar_url'] ?? '',
                'email' => $userInfo['email'] ?? null, // GitLab might provide email directly if scope allows and verified
                // GitLab doesn't provide gender, province, city directly
            ],
        ];

        // Remove empty data fields
        $result['data'] = array_filter($result['data'], function($value) { return $value !== null && $value !== ''; });

        // Clean up session state
        unset($_SESSION['oauth_gitlabcn_state']);

        return $result;
    }

    /**
     * Helper method to make HTTP requests using cURL.
     *
     * @param string $url URL to request
     * @param string $method HTTP method (GET, POST)
     * @param array $data Data for POST request
     * @param array $headers Additional HTTP headers
     * @return array Decoded JSON response
     * @throws \Exception On cURL error or non-200 HTTP status or JSON decode error or API error
     */
    private function _request($url, $method = 'GET', $data = [], $headers = [])
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Consider enabling in production with proper CA setup
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Consider enabling in production
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Ensure a default User-Agent if none is provided in headers
        $hasUserAgent = false;
        foreach ($headers as $header) {
            if (stripos($header, 'User-Agent:') === 0) {
                $hasUserAgent = true;
                break;
            }
        }
        if (!$hasUserAgent) {
            $headers[] = 'User-Agent: Zjmf-OAuth-Client/1.0'; // Default User-Agent
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            // Send data as form-encoded for GitLab token endpoint
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
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

        // Handle non-200 responses
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMessage = $response; // Default to raw response
            if ($jsonLastError === JSON_ERROR_NONE && $decodedResponse !== null) {
                // Try to extract error message from GitLab's JSON error structure
                if (isset($decodedResponse['error_description'])) {
                    $errorMessage = $decodedResponse['error_description'];
                } elseif (isset($decodedResponse['message'])) {
                     $errorMessage = $decodedResponse['message']; // Common in GitLab API errors
                } elseif (isset($decodedResponse['error'])) {
                    $errorMessage = is_string($decodedResponse['error']) ? $decodedResponse['error'] : json_encode($decodedResponse['error']);
                }
            }
            throw new \Exception('HTTP Error ' . $httpCode . ': ' . $errorMessage);
        }

        // Handle JSON decoding errors for 2xx responses
        if ($jsonLastError !== JSON_ERROR_NONE) {
             // GitLab API responses should be JSON. If not, it's unexpected.
             throw new \Exception('Failed to decode JSON response. Raw response: ' . $response);
        }

        // Check for application-level errors within the JSON (sometimes GitLab uses 'message' or 'error')
        if (isset($decodedResponse['error']) || (isset($decodedResponse['message']) && $httpCode >= 400)) {
            $message = $decodedResponse['error_description'] ?? $decodedResponse['message'] ?? $decodedResponse['error'] ?? 'Unknown API error';
            throw new \Exception('极狐 GitLab API Error: ' . (is_string($message) ? $message : json_encode($message)));
        }

        return $decodedResponse;
    }
}