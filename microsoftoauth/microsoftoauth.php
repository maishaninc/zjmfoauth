<?php

namespace oauth\microsoftoauth;

class microsoftoauth
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
        // TODO: Implement meta() method.
        return [
            'name'        => 'Microsoft登录',
            'description' => '使用 Microsoft 账号登录',
            'author'      => 'Maishan Inc',
            'logo_url'    => 'microsoft.svg', // Placeholder, ensure this file exists or update path
            'version'     => '1.0.0',
        ];
    }

    /**
     * 插件接口配置信息
     * @return array
     */
    public function config()
    {
        // TODO: Implement config() method.
        return [
            'Client ID' => [
                'type' => 'text',
                'name' => 'client_id',
                'desc' => '在 Microsoft Azure Portal 创建应用后获取'
            ],
            'Client Secret' => [
                'type' => 'text',
                'name' => 'client_secret',
                'desc' => '在 Microsoft Azure Portal 创建应用后获取'
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
        // TODO: Implement url() method.
        // Construct the Microsoft OAuth URL using $params['client_id'] and $params['callback']
        $state = md5(uniqid(rand(), true));
        $_SESSION['oauth_microsoft_state'] = $state;

        $queryData = [
            'client_id'     => $params['client_id'],
            'redirect_uri'  => $params['callback'],
            'response_type' => 'code',
            'scope'         => 'openid email profile User.Read',
            'state'         => $state, // Add state parameter for CSRF protection
            'response_mode' => 'query', // Optional: specify response mode
            'prompt'        => 'select_account', // Optional: force user to select account
        ];
        $microsoftOAuthUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query($queryData);
        return $microsoftOAuthUrl;
    }

    /**
     * 回调处理
     * @param array $params 配置参数、回调地址及第三方返回参数
     * @return array 用户信息
     * @throws \Exception
     */
    public function callback($params)
    {
        // TODO: Implement callback() method.

        // 0. Verify state to prevent CSRF attacks
        if (!isset($params['state']) || !isset($_SESSION['oauth_microsoft_state']) || $_SESSION['oauth_microsoft_state'] !== $params['state']) {
            unset($_SESSION['oauth_microsoft_state']); // Clean up session state on error
            throw new \Exception('无效的 state 参数，可能存在 CSRF 攻击');
        }
        // State is valid, proceed.

        // 1. Check for errors from Microsoft (e.g., in $params['error'])
        if (isset($params['error'])) {
            throw new \Exception('Microsoft 授权失败: ' . $params['error']);
        }

        // 2. Exchange authorization code for access token
        if (!isset($params['code'])) {
             throw new \Exception('未收到 Microsoft 返回的授权码(code)');
        }

        $tokenUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
        $tokenData = [
            'code'          => $params['code'],
            'client_id'     => $params['client_id'],
            'client_secret' => $params['client_secret'],
            'redirect_uri'  => $params['callback'],
            'grant_type'    => 'authorization_code',
        ];

        // Use helper method for the POST request to get token
        try {
            $tokenInfo = $this->_request($tokenUrl, 'POST', $tokenData);
        } catch (\Exception $e) {
            // Clean up session state on error
            unset($_SESSION['oauth_microsoft_state']);
            throw new \Exception('获取 Microsoft Access Token 失败: ' . $e->getMessage());
        }

        if (!isset($tokenInfo['access_token'])) {
            // Clean up session state on error
            unset($_SESSION['oauth_microsoft_state']);
            throw new \Exception('Microsoft 返回的 Token 信息无效: ' . json_encode($tokenInfo));
        }

        // 3. Use access token to get user info
        $userInfoUrl = 'https://graph.microsoft.com/v1.0/me';
        $headers = ['Authorization: Bearer ' . $tokenInfo['access_token']];

        // Use helper method for the GET request to get user info
        try {
            $userInfo = $this->_request($userInfoUrl, 'GET', [], $headers);
        } catch (\Exception $e) {
            // Clean up session state on error
            unset($_SESSION['oauth_microsoft_state']);
            throw new \Exception('获取 Microsoft 用户信息失败: ' . $e->getMessage());
        }
        if (!isset($userInfo['id'])) {
             throw new \Exception('Microsoft 返回的用户信息无效: ' . json_encode($userInfo));
        }

        // 4. Format the result according to the documentation
        $result = [
            'openid' => $userInfo['id'], // Microsoft's unique user ID
            'callbackBind' => 'all', // Or 'login', 'bind_mobile', 'bind_email' based on your logic
            'data' => [
                'username' => $userInfo['displayName'] ?? ($userInfo['givenName'] ?? ''),
                // Microsoft doesn't typically provide gender, province, city directly in standard scopes
                // 'sex' => ?, // Map if available, otherwise omit
                // 'province' => ?,
                // 'city' => ?,
                'avatar' => '', // Microsoft Graph doesn't provide avatar URL in basic profile
                'email' => $userInfo['mail'] ?? $userInfo['userPrincipalName'] ?? '', // Include email if available and needed
            ],
        ];

        // Remove empty data fields
        $result['data'] = array_filter($result['data']);

        // Clean up session state after successful processing
        unset($_SESSION['oauth_microsoft_state']);

        return $result;
    }

    /**
     * Helper method to make HTTP requests using cURL.
     *
     * @param string $url URL to request
     * @param string $method HTTP method (GET, POST)
     * @param array $data Data for POST request (ignored for GET)
     * @param array $headers Additional HTTP headers
     * @return array Decoded JSON response
     * @throws \Exception On cURL error or non-200 HTTP status or JSON decode error or API error
     */
    private function _request($url, $method = 'GET', $data = [], $headers = [])
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // WARNING: Disabling SSL verification is insecure. Use in production ONLY if CA certs are properly configured.
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Set to 2 in production if VERIFYPEER is true
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Set timeout

        // Add default User-Agent header if not provided
        $defaultHeaders = ['User-Agent: Zjmf-OAuth-Client/1.0'];
        $finalHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);


        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            // Microsoft token endpoint expects form-encoded data
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            // Ensure Content-Type is set for POST if needed (usually handled by cURL with POSTFIELDS)
            // curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($finalHeaders, ['Content-Type: application/x-www-form-urlencoded']));
        } elseif (strtoupper($method) !== 'GET') {
             // Handle other methods if necessary, or throw error for unsupported ones
             curl_close($ch);
             throw new \Exception('Unsupported HTTP method: ' . $method);
        }


        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno) { // Check for cURL errors first (e.g., connection timeout)
            throw new \Exception('cURL Error (' . $errno . '): ' . $error);
        }

        // Attempt to decode response regardless of HTTP code to potentially get error details
        $decodedResponse = json_decode($response, true);
        $jsonLastError = json_last_error();

        if ($httpCode !== 200) { // Check HTTP status code after checking cURL errors
            $errorMessage = $response; // Default to raw response
            if ($jsonLastError === JSON_ERROR_NONE && $decodedResponse !== null) {
                // Try to extract error message from Microsoft's JSON error structure
                if (isset($decodedResponse['error_description'])) {
                    $errorMessage = $decodedResponse['error_description'];
                } elseif (isset($decodedResponse['error']['message'])) {
                     $errorMessage = $decodedResponse['error']['message'];
                } elseif (isset($decodedResponse['error'])) {
                    $errorMessage = is_string($decodedResponse['error']) ? $decodedResponse['error'] : json_encode($decodedResponse['error']);
                }
            }
            throw new \Exception('HTTP Error ' . $httpCode . ': ' . $errorMessage);
        }

        // If HTTP code is 200, but JSON decoding failed
        if ($jsonLastError !== JSON_ERROR_NONE) {
             // Microsoft API responses should be JSON. If not, it's an unexpected issue.
             throw new \Exception('Failed to decode JSON response. Raw response: ' . $response);
        }

        // Even with HTTP 200, Microsoft might return an error object in the JSON
        if (isset($decodedResponse['error'])) {
            $errorDetails = $decodedResponse['error'];
            $message = $errorDetails['message'] ?? (is_string($errorDetails) ? $errorDetails : json_encode($errorDetails));
            throw new \Exception('Microsoft API Error: ' . $message);
        }

        return $decodedResponse;
    }
}