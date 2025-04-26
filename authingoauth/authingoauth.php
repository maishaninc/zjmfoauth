<?php

namespace oauth\authingoauth;

class authingoauth
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
            'name'        => 'Authing登录',
            'description' => '使用 Authing 账号登录',
            'author'      => 'Maishan Inc', // Or your name/company
            'logo_url'    => 'authing.svg', // Ensure authing.svg exists
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
            'App ID' => [
                'type' => 'text',
                'name' => 'client_id',
                'desc' => '在 Authing 控制台创建应用后获取'
            ],
            'App Secret' => [
                'type' => 'text',
                'name' => 'client_secret',
                'desc' => '在 Authing 控制台创建应用后获取'
            ],
            'Issuer URL' => [
                'type' => 'text',
                'name' => 'issuer_url',
                'desc' => 'Authing 应用的 OIDC Issuer URL (例如: https://your-app-domain.authing.cn/oidc)'
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
        $_SESSION['oauth_authing_state'] = $state;
        $_SESSION['oauth_authing_callback'] = $params['callback']; // Store callback for later use if needed
        $_SESSION['oauth_authing_issuer'] = $params['issuer_url']; // Store issuer URL

        // Construct the OIDC discovery URL
        $discoveryUrl = rtrim($params['issuer_url'], '/') . '/.well-known/openid-configuration';

        try {
            $config = $this->_request($discoveryUrl);
            if (!isset($config['authorization_endpoint'])) {
                throw new \Exception('无法从 OIDC 配置中获取 authorization_endpoint');
            }
            $_SESSION['oauth_authing_token_endpoint'] = $config['token_endpoint'] ?? null;
            $_SESSION['oauth_authing_userinfo_endpoint'] = $config['userinfo_endpoint'] ?? null;
            $authorizationEndpoint = $config['authorization_endpoint'];

        } catch (\Exception $e) {
            // Fallback or rethrow - For simplicity, rethrowing here
            throw new \Exception('获取 Authing OIDC 配置失败: ' . $e->getMessage());
        }

        $queryData = [
            'client_id'     => $params['client_id'],
            'redirect_uri'  => $params['callback'],
            'state'         => $state,
            'response_type' => 'code',
            'scope'         => 'openid profile email phone', // Standard OIDC scopes
            'prompt'        => 'consent' // Optional: force user consent screen
        ];
        $authingOAuthUrl = $authorizationEndpoint . '?' . http_build_query($queryData);
        return $authingOAuthUrl;
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
        if (!isset($params['state']) || !isset($_SESSION['oauth_authing_state']) || $_SESSION['oauth_authing_state'] !== $params['state']) {
            $this->_cleanupSession();
            throw new \Exception('无效的 state 参数，可能存在 CSRF 攻击');
        }

        // 1. Check for errors from Authing
        if (isset($params['error'])) {
            $errorDesc = $params['error_description'] ?? $params['error'];
            $this->_cleanupSession();
            throw new \Exception('Authing 授权失败: ' . $errorDesc);
        }

        // 2. Exchange authorization code for access token
        if (!isset($params['code'])) {
             $this->_cleanupSession();
             throw new \Exception('未收到 Authing 返回的授权码(code)');
        }

        $tokenEndpoint = $_SESSION['oauth_authing_token_endpoint'] ?? null;
        $callbackUrl = $_SESSION['oauth_authing_callback'] ?? $params['callback']; // Use stored or passed callback

        if (!$tokenEndpoint) {
            // Attempt to refetch from issuer if session lost
            $issuerUrl = $_SESSION['oauth_authing_issuer'] ?? $params['issuer_url'] ?? null;
            if ($issuerUrl) {
                 try {
                    $discoveryUrl = rtrim($issuerUrl, '/') . '/.well-known/openid-configuration';
                    $config = $this->_request($discoveryUrl);
                    $tokenEndpoint = $config['token_endpoint'] ?? null;
                 } catch (\Exception $e) {
                    // Ignore refetch error, proceed to fail
                 }
            }
            if (!$tokenEndpoint) {
                $this->_cleanupSession();
                throw new \Exception('无法确定 Authing Token Endpoint 地址');
            }
        }

        $tokenData = [
            'code'          => $params['code'],
            'client_id'     => $params['client_id'],
            'client_secret' => $params['client_secret'],
            'redirect_uri'  => $callbackUrl,
            'grant_type'    => 'authorization_code',
        ];

        try {
            // Authing token endpoint uses POST with form-encoded data
            $tokenInfo = $this->_request($tokenEndpoint, 'POST', $tokenData);
        } catch (\Exception $e) {
            $this->_cleanupSession();
            throw new \Exception('获取 Authing Access Token 失败: ' . $e->getMessage());
        }

        if (!isset($tokenInfo['access_token']) || !isset($tokenInfo['id_token'])) {
            $this->_cleanupSession();
            $errorMsg = $tokenInfo['error_description'] ?? $tokenInfo['error'] ?? json_encode($tokenInfo);
            throw new \Exception('Authing 返回的 Token 信息无效: ' . $errorMsg);
        }

        $accessToken = $tokenInfo['access_token'];
        // $idToken = $tokenInfo['id_token']; // Can be decoded to get user info without another request

        // 3. Use access token to get user info from userinfo endpoint
        $userInfoEndpoint = $_SESSION['oauth_authing_userinfo_endpoint'] ?? null;
        if (!$userInfoEndpoint) {
             // Attempt to refetch from issuer if session lost
            $issuerUrl = $_SESSION['oauth_authing_issuer'] ?? $params['issuer_url'] ?? null;
            if ($issuerUrl) {
                 try {
                    $discoveryUrl = rtrim($issuerUrl, '/') . '/.well-known/openid-configuration';
                    $config = $this->_request($discoveryUrl);
                    $userInfoEndpoint = $config['userinfo_endpoint'] ?? null;
                 } catch (\Exception $e) {
                    // Ignore refetch error, proceed to fail
                 }
            }
             if (!$userInfoEndpoint) {
                $this->_cleanupSession();
                throw new \Exception('无法确定 Authing UserInfo Endpoint 地址');
            }
        }

        $userInfoHeaders = ['Authorization: Bearer ' . $accessToken];

        try {
            $userInfo = $this->_request($userInfoEndpoint, 'GET', [], $userInfoHeaders);
        } catch (\Exception $e) {
            $this->_cleanupSession();
            throw new \Exception('获取 Authing 用户信息失败: ' . $e->getMessage());
        }

        // Authing userinfo response structure based on OIDC standard claims
        if (!isset($userInfo['sub'])) { // 'sub' is the standard unique identifier
            $this->_cleanupSession();
            $errorMsg = $userInfo['error_description'] ?? $userInfo['error'] ?? json_encode($userInfo);
            throw new \Exception('Authing 返回的用户信息无效 (缺少 sub): ' . $errorMsg);
        }

        // 4. Format the result
        $result = [
            'openid' => $userInfo['sub'], // Use 'sub' as the unique ID
            'callbackBind' => 'all',
            'data' => [
                'username' => $userInfo['preferred_username'] ?? $userInfo['name'] ?? $userInfo['nickname'] ?? '',
                'avatar' => $userInfo['picture'] ?? '',
                'email' => $userInfo['email'] ?? '',
                // Add other relevant fields if needed, e.g., phone_number
                'phone' => $userInfo['phone_number'] ?? '',
            ],
        ];

        $result['data'] = array_filter($result['data']); // Remove empty values
        $this->_cleanupSession();
        return $result;
    }

    /**
     * Helper method to make HTTP requests using cURL.
     *
     * @param string $url URL to request
     * @param string $method HTTP method (GET, POST)
     * @param array $data Data for POST request (form-encoded)
     * @param array $headers Additional HTTP headers
     * @return array Decoded JSON response
     * @throws \Exception On cURL error or non-200 response or JSON decode error
     */
    private function _request($url, $method = 'GET', $data = [], $headers = [])
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Consider enabling in production with proper CA setup
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Consider enabling in production
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects if any

        $defaultHeaders = ['User-Agent: Zjmf-OAuth-Client/1.0', 'Accept: application/json'];
        $finalHeaders = array_merge($defaultHeaders, $headers);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            // Ensure Content-Type for form data if not already set
            if (!in_array('Content-Type: application/x-www-form-urlencoded', $finalHeaders)) {
                 $finalHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
            }
        } elseif (strtoupper($method) !== 'GET') {
             curl_close($ch);
             throw new \Exception('Unsupported HTTP method: ' . $method);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);

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

        // Check HTTP status code first (OIDC often uses 4xx/5xx for errors)
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMessage = $response; // Default to raw response
            if ($jsonLastError === JSON_ERROR_NONE && $decodedResponse !== null && (isset($decodedResponse['error_description']) || isset($decodedResponse['error']))) {
                $errorMessage = $decodedResponse['error_description'] ?? $decodedResponse['error'];
            }
            throw new \Exception('HTTP Error ' . $httpCode . ': ' . $errorMessage);
        }

        // If HTTP 2xx, check JSON decoding
        if ($jsonLastError !== JSON_ERROR_NONE) {
             throw new \Exception('Failed to decode JSON response. Raw response: ' . $response);
        }

        // Check for standard OIDC error fields even with HTTP 2xx
        if (isset($decodedResponse['error'])) {
             $errorDetails = $decodedResponse['error_description'] ?? $decodedResponse['error'];
             throw new \Exception('Authing API Error: ' . $errorDetails);
        }

        return $decodedResponse;
    }

    /**
     * Cleans up session variables used during the OAuth flow.
     */
    private function _cleanupSession()
    {
        unset($_SESSION['oauth_authing_state']);
        unset($_SESSION['oauth_authing_callback']);
        unset($_SESSION['oauth_authing_issuer']);
        unset($_SESSION['oauth_authing_token_endpoint']);
        unset($_SESSION['oauth_authing_userinfo_endpoint']);
    }
}