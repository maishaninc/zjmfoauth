<?php
namespace oauth\metamaskoauth;

// IMPORTANT: You will likely need a library to verify Ethereum signatures.
// Example: composer require kornrunner/keccak , scurest/php-elliptic-curve
// The verification logic below is a placeholder and needs proper implementation.
use Elliptic\EC;
use kornrunner\Keccak;

class metamaskoauth
{
    public function __construct()
    {
        if (!session_id()) {
            session_start();
        }
    }

    public function meta()
    {
        return [
            'name'        => 'MetaMask 登录',
            'description' => '使用 MetaMask 钱包进行登录',
            'author'      => 'Maishan Inc', // 您可以修改为您的名字
            'logo_url'    => 'metamask.svg',
            'version'     => '1.0.0',
        ];
    }

    public function config()
    {
        // MetaMask login typically doesn't require backend API keys like traditional OAuth.
        // Configuration might involve defining the message to sign, but often handled frontend.
        return [
             'Sign Message Prefix' => [
                 'type' => 'text',
                 'name' => 'sign_message_prefix',
                 'desc' => '签名消息的前缀文本 (例如 "请签名以登录:")。Nonce会自动附加。默认为 "Login nonce:"',
                 'default' => 'Login nonce:' // 添加默认值
             ]
        ];
    }

    public function url($params)
    {
        // Generate a secure random nonce for CSRF protection and replay prevention
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['oauth_metamask_nonce'] = $nonce;
        $_SESSION['oauth_metamask_callback'] = $params['callback']; // Store callback URL if needed later
        $_SESSION['oauth_metamask_message_prefix'] = $params['sign_message_prefix'] ?? 'Login nonce:'; // Store message prefix

        // This URL itself doesn't trigger MetaMask.
        // Frontend JavaScript needs to:
        // 1. Detect this plugin type.
        // 2. Call an endpoint (or read from page data) to get the nonce.
        // 3. Construct the message: $params['sign_message_prefix'] . $nonce
        // 4. Use `ethereum.request({ method: 'personal_sign', params: [message, address] })`
        // 5. POST the address, signature, and nonce to the callback URL ($params['callback']).
        // We return the callback URL mainly for framework consistency.
        // Optionally, pass nonce to frontend via query param if needed, but session is safer.
         // Return JSON containing nonce and callback, so frontend JS can use it
         // This deviates from returning a simple URL string, adjust based on how the core system handles it.
         // If the system strictly expects a URL, returning $params['callback'] is the fallback.
         // Let's try returning JSON for better frontend integration.
         // Note: The core system might need adjustment to handle non-URL returns from this method.
         // Fallback: return $params['callback'];
         return json_encode([
             'action' => 'metamask_sign',
             'nonce' => $nonce,
             'message_prefix' => $params['sign_message_prefix'] ?? 'Login nonce:',
             'callback_url' => $params['callback']
         ]);
         // If the above JSON approach doesn't work with the system, revert to:
         // return $params['callback'];
    }

    public function callback($params)
    {
        // MetaMask callback data comes via POST from frontend JavaScript
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // Maybe check if GET request contains error info from frontend?
             $error = $_GET['error'] ?? null;
             if ($error) {
                 return "MetaMask login error: " . htmlspecialchars($error);
             }
            return 'Invalid request method. Expected POST.';
        }

        $address = $_POST['address'] ?? null;
        $signature = $_POST['signature'] ?? null;
        $nonce = $_POST['nonce'] ?? null; // Frontend MUST send the nonce it used for signing

        if (!$address || !$signature || !$nonce) {
            return 'Missing required data (address, signature, or nonce).';
        }

        // Validate received address format (basic check)
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
             return 'Invalid address format.';
        }
         // Validate received signature format (basic check)
         if (!preg_match('/^0x[a-fA-F0-9]{130}$/', $signature)) {
             return 'Invalid signature format.';
         }


        // Retrieve expected nonce and message prefix from session
        $expectedNonce = $_SESSION['oauth_metamask_nonce'] ?? null;
        $messagePrefix = $_SESSION['oauth_metamask_message_prefix'] ?? 'Login nonce:';

        // Verify the nonce received from POST matches the one in session
        if (!$expectedNonce || $nonce !== $expectedNonce) {
            unset($_SESSION['oauth_metamask_nonce']); // Clean up used/invalid nonce
            unset($_SESSION['oauth_metamask_message_prefix']);
            return 'Invalid or expired session state (nonce mismatch).';
        }

        // Construct the exact message that should have been signed
        $messageToSign = $messagePrefix . $nonce;

        // ---== Secure Signature Verification ==---
        // This is CRITICAL for security. The verifyEthereumSignature function
        // MUST correctly implement EIP-191 signature verification.
        $isValidSignature = $this->verifyEthereumSignature($messageToSign, $signature, $address);
        // ---==================================---

        // Clean up session data regardless of verification result (nonce is single-use)
        unset($_SESSION['oauth_metamask_nonce']);
        unset($_SESSION['oauth_metamask_message_prefix']);
        unset($_SESSION['oauth_metamask_callback']);


        if (!$isValidSignature) {
            return 'Invalid signature. Verification failed.';
        }

        // Signature is valid, user is authenticated
        $generatedUsername = 'Wallet-' . substr($address, 2, 4) . '...' . substr($address, -4);

        return [
            'openid' => strtolower($address), // Ensure address is lowercase for consistency
            'data'   => [
                'username' => $generatedUsername,
                // 'avatar' => '', // No standard avatar URL from MetaMask/Ethereum address
                // 'email' => '', // No email available
            ],
            // 'callbackBind' => 'login', // Or 'all', 'bind_mobile', etc. depending on desired flow
        ];
    }

    /**
     * Verifies an Ethereum signed message (EIP-191).
     * IMPORTANT: This requires appropriate cryptographic libraries.
     * Example using kornrunner/keccak and scurest/php-elliptic-curve
     *
     * @param string $message The original message that was signed.
     * @param string $signature The signature hex string (prefixed with 0x).
     * @param string $address The Ethereum address hex string (prefixed with 0x).
     * @return bool True if the signature is valid, false otherwise.
     */
    private function verifyEthereumSignature(string $message, string $signature, string $address): bool
    {
         // Ensure libraries are loaded (use Composer's autoload)
         if (!class_exists('\kornrunner\Keccak') || !class_exists('\Elliptic\EC')) {
              error_log("MetaMask Plugin Error: Missing required crypto libraries (Keccak or EC).");
              return false; // Cannot verify without libraries
         }

        try {
            $messageLength = strlen($message);
            // EIP-191 prefix: "\x19Ethereum Signed Message:\n"
            $hash = Keccak::hash("\x19Ethereum Signed Message:\n{$messageLength}{$message}", 256);

            // Deconstruct signature
            $signature = substr($signature, 2); // Remove 0x prefix
            $r = substr($signature, 0, 64);
            $s = substr($signature, 64, 64);
            $v = hexdec(substr($signature, 128, 2)); // Recovery ID

            // Adjust v value for Ethereum
            if ($v >= 27) {
                $v -= 27;
            }

            $ec = new EC('secp256k1');
            $publicKey = $ec->recoverPubKey($hash, ['r' => $r, 's' => $s], $v);

            // Derive address from public key
            $derivedAddress = '0x' . substr(Keccak::hash(substr(hex2bin($publicKey->encode('hex', false)), 1), 256), -40);

            // Compare derived address with the provided address (case-insensitive)
            return strtolower($derivedAddress) === strtolower($address);

        } catch (\Throwable $e) {
             // Log the error securely
             error_log("MetaMask Signature Verification Error: " . $e->getMessage());
             return false;
        }
         // Placeholder - REMOVE THIS IN PRODUCTION
         // return (strpos($signature, '0x') === 0 && strpos($address, '0x') === 0);
    }
}