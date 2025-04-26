<?php

namespace oauth\slackoauth;

class slackoauth
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
            'name'        => 'Slack登录',
            'description' => '使用 Slack 账号登录',
            'author'      => 'Maishan Inc',
            'logo_url'    => 'slack.svg', // Ensure slack.svg exists
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
                'desc' => '在 Slack API 网站创建应用后获取'
            ],
            'Client Secret' => [
                'type' => 'text',
                'name' => 'client_secret',
                'desc' => '在 Slack API 网站创建应用后获取'
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
        $_SESSION['oauth_slack_state'] = $state;

        $queryData = [
            'client_id'     => $params['client_id'],
            'redirect_uri'  => $params['callback'],
            'state'         => $state,
            // 'scope'         => 'identity.basic,identity.email,identity.avatar', // Use user_scope for granular permissions
            'user_scope'    => 'identity.basic,identity.email,identity.avatar', // User scopes for granular permissions
        ];
        $slackOAuthUrl = 'https://slack.com/oauth/v2/authorize?' . http_build_query($queryData);
        return $slackOAuthUrl;
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
        if (!isset($params['state']) || !isset($_SESSION['oauth_slack_state']) || $_SESSION['oauth_slack_state'] !== $params['state']) {
            unset($_SESSION['oauth_slack_state']);
            throw new \Exception('无效的 state 参数，可能存在 CSRF 攻击');
        }

        // 1. Check for errors from Slack
        if (isset($params['error'])) {
            unset($_SESSION['oauth_slack_state']);
            throw new \Exception('Slack 授权失败: ' . $params['error']);
        }

        // 2. Exchange authorization code for access token
        if (!isset($params['code'])) {
             unset($_SESSION['oauth_slack_state']);
             throw new \Exception('未收到 Slack 返回的授权码(code)');
        }

        $tokenUrl = 'https://slack.com/api/oauth.v2.access';
        $tokenData = [
            'code'          => $params['code'],
            'client_id'     => $params['client_id'],
            'client_secret' => $params['client_secret'],
            'redirect_uri'  => $params['callback'], // Optional but good practice
        ];

        // Slack expects credentials in the Authorization header for token exchange
        $authHeader = 'Authorization: Basic ' . base64_encode($params['client_id'] . ':' . $params['client_secret']);
        $headers = [$authHeader, 'Content-Type: application/x-www-form-urlencoded'];

        try {
            // Slack token endpoint uses POST
            $tokenInfo = $this->_request($tokenUrl, 'POST', $tokenData, $headers);
        } catch (\Exception $e) {
            unset($_SESSION['oauth_slack_state']);
            throw new \Exception('获取 Slack Access Token 失败: ' . $e->getMessage());
        }

        // Slack returns 'ok: false' on error
        if (!isset($tokenInfo['ok']) || !$tokenInfo['ok'] || !isset($tokenInfo['authed_user']['access_token'])) {
            unset($_SESSION['oauth_slack_state']);
            $errorMsg = $tokenInfo['error'] ?? json_encode($tokenInfo);
            throw new \Exception('Slack 返回的 Token 信息无效: ' . $errorMsg);
        }

        $accessToken = $tokenInfo['authed_user']['access_token'];

        // 3. Use access token to get user info
        $userInfoUrl = 'https://slack.com/api/users.identity';
        $userInfoHeaders = ['Authorization: Bearer ' . $accessToken];

        try {
            $userInfo = $this->_request($userInfoUrl, 'GET', [], $userInfoHeaders);
        } catch (\Exception $e) {
            unset($_SESSION['oauth_slack_state']);
            throw new \Exception('获取 Slack 用户信息失败: ' . $e->getMessage());
        }

        if (!isset($userInfo['ok']) || !$userInfo['ok'] || !isset($userInfo['user']['id'])) {
            unset($_SESSION['oauth_slack_state']);
            $errorMsg = $userInfo['error'] ?? json_encode($userInfo);
            throw new \Exception('Slack 返回的用户信息无效: ' . $errorMsg);
        }

        $user = $userInfo['user'];

        // 4. Format the result
        $result = [
            'openid' => $user['id'], // Slack's unique user ID
            'callbackBind' => 'all',
            'data' => [
                'username' => $user['name'] ?? '',
                'avatar' => $user['image_512'] ?? $user['image_192'] ?? $user['image_72'] ?? $user['image_48'] ?? '',
                'email' => $user['email'] ?? '',
            ],
        ];

        $result['data'] = array_filter($result['data']);
        unset($_SESSION['oauth_slack_state']);
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
     * @throws \Exception On cURL error or non-200/non-ok response or JSON decode error
     */
    private function _request($url, $method = 'GET', $data = [], $headers = [])
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Consider enabling in production with proper CA setup
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Consider enabling in production
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $defaultHeaders = ['User-Agent: Zjmf-OAuth-Client/1.0'];
        $finalHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            // Slack token endpoint expects form-encoded data, even with Basic Auth sometimes
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            // Ensure Content-Type is set if needed (already added in headers for token request)
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
        if ($httpCode !== 200) {
            $errorMessage = $response; // Default to raw response
            if ($jsonLastError === JSON_ERROR_NONE && $decodedResponse !== null && isset($decodedResponse['error'])) {
                $errorMessage = $decodedResponse['error'];
            }
            throw new \Exception('HTTP Error ' . $httpCode . ': ' . $errorMessage);
        }

        // If HTTP 200, check JSON decoding
        if ($jsonLastError !== JSON_ERROR_NONE) {
             throw new \Exception('Failed to decode JSON response. Raw response: ' . $response);
        }

        // Even with HTTP 200, Slack API uses 'ok: false' to indicate errors
        if (isset($decodedResponse['ok']) && !$decodedResponse['ok']) {
            $errorDetails = $decodedResponse['error'] ?? 'unknown_error';
            throw new \Exception('Slack API Error: ' . $errorDetails);
        }

        // Check for 'error' field even if 'ok' is true or absent (belt and suspenders)
        if (isset($decodedResponse['error'])) {
             throw new \Exception('Slack API Error: ' . $decodedResponse['error']);
        }

        return $decodedResponse;
    }
}