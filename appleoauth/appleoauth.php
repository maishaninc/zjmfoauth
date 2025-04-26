<?php

namespace oauth\appleoauth;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class appleoauth
{
    public function __construct()
    {
        if (!session_id()) session_start();
    }

    /**
     * 插件信息
     * @return array
     */
    public function meta()
    {
        return [
            'name'        => 'Apple登录',
            'description' => '使用 Apple ID 登录',
            'author'      => 'Maishan Inc', // 请替换为您的名称或公司
            'logo_url'    => 'apple.svg', // 确保 apple.svg 存在于同级目录
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
                'desc' => '即 Services ID Identifier, 在 Apple Developer 网站创建后获取'
            ],
            'Team ID' => [
                'type' => 'text',
                'name' => 'team_id',
                'desc' => '在 Apple Developer 网站获取'
            ],
            'Key ID' => [
                'type' => 'text',
                'name' => 'key_id',
                'desc' => '在 Apple Developer 网站创建 Sign in with Apple Key 后获取'
            ],
            'Private Key File (.p8)' => [
                'type' => 'file', // 或者 'textarea' 让用户粘贴内容
                'name' => 'private_key',
                'desc' => '下载的 .p8 私钥文件内容 (-----BEGIN PRIVATE KEY-----...-----END PRIVATE KEY-----)'
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
        $_SESSION['oauth_apple_state'] = $state;

        $queryData = [
            'client_id'     => $params['client_id'],
            'redirect_uri'  => $params['callback'],
            'response_type' => 'code id_token', // 请求 code 和 id_token
            'scope'         => 'name email', // 请求用户姓名和邮箱
            'response_mode' => 'form_post', // Apple 推荐使用 form_post
            'state'         => $state,
        ];
        $appleAuthUrl = 'https://appleid.apple.com/auth/authorize?' . http_build_query($queryData);
        return $appleAuthUrl;
    }

    /**
     * 回调处理
     * @param array $params 配置参数、回调地址及第三方返回参数 (通过 POST)
     * @return array 用户信息
     * @throws \Exception
     */
    public function callback($params)
    {
        // Apple 使用 form_post, 参数在 $_POST 中
        $requestData = $_POST;

        // 0. 验证 state
        if (!isset($requestData['state']) || !isset($_SESSION['oauth_apple_state']) || $_SESSION['oauth_apple_state'] !== $requestData['state']) {
            unset($_SESSION['oauth_apple_state']);
            throw new \Exception('无效的 state 参数，可能存在 CSRF 攻击');
        }
        unset($_SESSION['oauth_apple_state']); // 验证后立即销毁

        // 1. 检查 Apple 返回的错误
        if (isset($requestData['error'])) {
            throw new \Exception('Apple 授权失败: ' . $requestData['error']);
        }

        // 2. 检查是否收到 code 和 id_token
        if (!isset($requestData['code']) || !isset($requestData['id_token'])) {
            throw new \Exception('未收到 Apple 返回的 code 或 id_token');
        }

        $code = $requestData['code'];
        $idToken = $requestData['id_token'];

        // 3. 验证 id_token (可选但强烈推荐)
        //    - 使用 Apple 的公钥验证签名
        //    - 验证 issuer (iss) 是否为 https://appleid.apple.com
        //    - 验证 audience (aud) 是否为你的 client_id
        //    - 验证 token 是否过期 (exp)
        //    - 验证 nonce (如果请求时发送了)
        try {
            $this->validateIdToken($idToken, $params['client_id']);
        } catch (\Exception $e) {
            throw new \Exception('Apple id_token 验证失败: ' . $e->getMessage());
        }

        // 4. 使用 code 换取 access_token 和 refresh_token
        $tokenUrl = 'https://appleid.apple.com/auth/token';
        $clientSecret = $this->generateClientSecret($params);

        $tokenData = [
            'client_id'     => $params['client_id'],
            'client_secret' => $clientSecret,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $params['callback'], // Apple 要求此参数匹配
        ];

        try {
            $tokenInfo = $this->_request($tokenUrl, 'POST', $tokenData);
        } catch (\Exception $e) {
            throw new \Exception('获取 Apple Token 失败: ' . $e->getMessage());
        }

        if (isset($tokenInfo['error']) || !isset($tokenInfo['access_token'])) {
            $errorMsg = $tokenInfo['error_description'] ?? $tokenInfo['error'] ?? json_encode($tokenInfo);
            throw new \Exception('Apple 返回的 Token 信息无效: ' . $errorMsg);
        }

        // 5. 从 id_token 中解析用户信息 (Apple 不提供单独的用户信息接口)
        $claims = $this->decodeIdToken($idToken);
        if (!$claims || !isset($claims['sub'])) {
            throw new \Exception('无法从 id_token 解析用户信息');
        }

        // 首次登录时，Apple 可能在 POST 请求中包含 user 数据
        $userData = [];
        if (isset($requestData['user'])) {
            $userData = json_decode($requestData['user'], true);
        }

        $email = $claims['email'] ?? null;
        $isPrivateEmail = isset($claims['is_private_email']) && $claims['is_private_email'] === 'true';

        $username = '';
        if (isset($userData['name'])) {
            $firstName = $userData['name']['firstName'] ?? '';
            $lastName = $userData['name']['lastName'] ?? '';
            $username = trim($firstName . ' ' . $lastName);
        }
        if (empty($username) && $email) {
             // 如果没有姓名，尝试从邮箱生成用户名
             $username = explode('@', $email)[0];
        }
        if (empty($username)) {
            $username = 'Apple User ' . substr($claims['sub'], 0, 8); // Fallback username
        }

        // 6. 格式化结果
        $result = [
            'openid' => $claims['sub'], // Apple 的唯一用户标识符
            'unionid' => $claims['sub'], // Apple 没有单独的 UnionID 概念，使用 sub
            'access_token' => $tokenInfo['access_token'],
            'refresh_token' => $tokenInfo['refresh_token'] ?? null,
            'expires_in' => $tokenInfo['expires_in'] ?? null,
            'callbackBind' => 'all', // 或 'pc', 'mobile' 根据需要调整
            'data' => [
                'username' => $username,
                'avatar' => '', // Apple 不直接提供头像 URL
                'email' => $email,
                'is_private_email' => $isPrivateEmail, // 标记是否为私密中继邮箱
            ],
        ];

        $result['data'] = array_filter($result['data'], function($value) { return $value !== '' && $value !== null; });

        return $result;
    }

    /**
     * 生成 Client Secret (JWT)
     * @param array $params 配置参数
     * @return string JWT
     * @throws \Exception
     */
    private function generateClientSecret($params)
    {
        $privateKey = $params['private_key'];
        if (strpos($privateKey, '-----BEGIN PRIVATE KEY-----') === false) {
            // 假设用户直接粘贴了内容，而不是文件路径
             $privateKey = "-----BEGIN PRIVATE KEY-----\n" . wordwrap($privateKey, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
        } elseif (file_exists($privateKey)) {
             $privateKey = file_get_contents($privateKey);
             if ($privateKey === false) {
                 throw new \Exception('无法读取 Private Key 文件: ' . $params['private_key']);
             }
        } else {
             throw new \Exception('Private Key 文件不存在或无法读取: ' . $params['private_key']);
        }

        $payload = [
            'iss' => $params['team_id'],
            'iat' => time(),
            'exp' => time() + 86400 * 180, // 有效期最多 6 个月
            'aud' => 'https://appleid.apple.com',
            'sub' => $params['client_id'],
        ];

        $headers = [
            'kid' => $params['key_id'],
            'alg' => 'ES256'
        ];

        try {
            // 需要安装 firebase/php-jwt: composer require firebase/php-jwt
            $clientSecret = JWT::encode($payload, $privateKey, 'ES256', $params['key_id'], $headers);
        } catch (\Exception $e) {
            throw new \Exception('生成 Apple Client Secret (JWT) 失败: ' . $e->getMessage());
        }

        return $clientSecret;
    }

    /**
     * 验证 ID Token
     * @param string $idToken
     * @param string $clientId
     * @throws \Exception
     */
    private function validateIdToken($idToken, $clientId)
    {
        // 1. 获取 Apple 公钥
        $publicKeyData = $this->fetchApplePublicKeys();

        // 2. 解码 ID Token (不验证签名，仅获取 header)
        $tks = explode('.', $idToken);
        if (count($tks) !== 3) {
            throw new \Exception('错误的 ID Token 格式');
        }
        list($headb64, $bodyb64, $sigb64) = $tks;
        $headerRaw = JWT::urlsafeB64Decode($headb64);
        $header = JWT::jsonDecode($headerRaw);

        if (!isset($header->kid)) {
            throw new \Exception('ID Token header 中缺少 kid');
        }

        // 3. 查找对应的公钥
        $publicKeyDetails = null;
        foreach ($publicKeyData['keys'] as $key) {
            if ($key['kid'] == $header->kid) {
                $publicKeyDetails = $key;
                break;
            }
        }

        if ($publicKeyDetails === null) {
            throw new \Exception('无法找到与 ID Token 匹配的 Apple 公钥 (kid: ' . $header->kid . ')');
        }

        // 4. 使用公钥验证签名和声明
        try {
            // JWK::parseKeySet 需要公钥数组
            $publicKey = JWK::parseKeySet(['keys' => [$publicKeyDetails]]);
            $decoded = JWT::decode($idToken, $publicKey, ['ES256']); // 指定算法为 ES256
        } catch (\Exception $e) {
            throw new \Exception('ID Token 签名验证失败: ' . $e->getMessage());
        }

        // 5. 验证其他声明
        if ($decoded->iss !== 'https://appleid.apple.com') {
            throw new \Exception('ID Token issuer 不匹配');
        }
        if ($decoded->aud !== $clientId) {
            throw new \Exception('ID Token audience 不匹配');
        }
        if ($decoded->exp < time()) {
            throw new \Exception('ID Token 已过期');
        }

        // nonce 验证 (如果需要)
        // if (!isset($decoded->nonce) || $decoded->nonce !== $_SESSION['expected_nonce']) {
        //     throw new \Exception('ID Token nonce 不匹配');
        // }

        return (array)$decoded;
    }

    /**
     * 解码 ID Token (不验证)
     * @param string $idToken
     * @return array|null
     */
    private function decodeIdToken($idToken)
    {
        $tks = explode('.', $idToken);
        if (count($tks) !== 3) {
            return null;
        }
        list(, $bodyb64, ) = $tks;
        $bodyRaw = JWT::urlsafeB64Decode($bodyb64);
        return JWT::jsonDecode($bodyRaw);
    }

    /**
     * 获取 Apple 公钥
     * @return array
     * @throws \Exception
     */
    private function fetchApplePublicKeys()
    {
        $publicKeyUrl = 'https://appleid.apple.com/auth/keys';
        try {
            $keyData = $this->_request($publicKeyUrl, 'GET');
        } catch (\Exception $e) {
            throw new \Exception('获取 Apple 公钥失败: ' . $e->getMessage());
        }
        if (!isset($keyData['keys'])) {
             throw new \Exception('Apple 公钥响应格式无效');
        }
        return $keyData;
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // 强烈建议在生产环境中启用并配置 CA 证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $defaultHeaders = ['User-Agent: Zjmf-OAuth-Client/1.0'];
        $finalHeaders = array_merge($defaultHeaders, $headers);


        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            // Apple 的 token 端点需要 'Content-Type: application/x-www-form-urlencoded'
            $finalHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
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

        // Apple 在出错时也可能返回 200，但 body 中包含 error
        if ($jsonLastError === JSON_ERROR_NONE && isset($decodedResponse['error'])) {
             $errorDesc = $decodedResponse['error_description'] ?? $decodedResponse['error'];
             throw new \Exception('Apple API Error: ' . $errorDesc . ' (HTTP Code: ' . $httpCode . ')');
        }

        // 检查 HTTP 状态码 (对于非错误情况)
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception('HTTP Error ' . $httpCode . ': ' . $response);
        }

        // 检查 JSON 解码错误 (对于成功情况)
        if ($jsonLastError !== JSON_ERROR_NONE) {
             throw new \Exception('Failed to decode JSON response. HTTP Code: ' . $httpCode . '. Raw response: ' . $response);
        }

        return $decodedResponse;
    }
}