<?php
// POST /api/v1/auth            -> login (returns token, or requires_2fa: true)
// DELETE /api/v1/auth          -> logout (revoke token)
// POST /api/v1/auth/fcm        -> update FCM device token

defined('FROM_API') || die();

require_once $DOCUMENT_ROOT . '/plugins/totp/totp.php';

// FCM token update
if ($method === 'POST' && !empty($segments[1]) && $segments[1] === 'fcm') {
    $authH = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        $authH = $authH ?: ($h['Authorization'] ?? $h['authorization'] ?? '');
    }
    if (!preg_match('/^Bearer\s+(\S+)$/i', $authH, $bm)) api_error(401, 'Unauthorized');
    $hash  = mysqli_real_escape_string($mysqli, hash('sha256', $bm[1]));
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $fcm   = mysqli_real_escape_string($mysqli, $body['fcm_token'] ?? '');
    mysqli_query($mysqli, "UPDATE api_tokens SET token_fcm_token = '$fcm' WHERE token_hash = '$hash'");
    api_response(200, ['ok' => true]);
}

// Revoke token
if ($method === 'DELETE') {
    $authH = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        $authH = $authH ?: ($h['Authorization'] ?? $h['authorization'] ?? '');
    }
    if (preg_match('/^Bearer\s+(\S+)$/i', $authH, $bm)) {
        $hash = mysqli_real_escape_string($mysqli, hash('sha256', $bm[1]));
        mysqli_query($mysqli, "DELETE FROM api_tokens WHERE token_hash = '$hash'");
    }
    api_response(200, ['ok' => true]);
}

if ($method !== 'POST') api_error(405, 'Method not allowed');

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$username = trim($body['username'] ?? '');
$password = trim($body['password'] ?? '');
$totp     = trim($body['totp_code'] ?? '');
$device   = trim($body['device_name'] ?? 'Mobile App');

if (!$username || !$password) api_error(400, 'username and password required');

$esc_user = mysqli_real_escape_string($mysqli, $username);
$sql      = mysqli_query($mysqli,
    "SELECT user_id, user_name, user_email, user_type, user_password,
            user_specific_encryption_ciphertext, user_token
     FROM users
     WHERE (user_name = '$esc_user' OR user_email = '$esc_user')
     AND user_archived_at IS NULL
     LIMIT 1"
);
$user = mysqli_fetch_assoc($sql);

if (!$user || !password_verify($password, $user['user_password'])) {
    api_error(401, 'Invalid credentials');
}

// Check if user has 2FA (TOTP) enabled
$totp_secret = $user['user_token'] ?? '';
if (!empty($totp_secret)) {
    if (empty($totp)) {
        // Tell the app to ask for TOTP
        api_response(200, ['requires_2fa' => true]);
    }
    // Verify the TOTP code
    if (!TokenAuth6238::verify($totp_secret, intval($totp))) {
        api_error(401, 'Invalid 2FA code');
    }
}

// Generate API token
$raw_token  = bin2hex(random_bytes(32));
$token_hash = hash('sha256', $raw_token);
$uid        = intval($user['user_id']);
$esc_hash   = mysqli_real_escape_string($mysqli, $token_hash);
$esc_device = mysqli_real_escape_string($mysqli, substr($device, 0, 100));

// Derive and store master encryption key
$master_key    = decryptUserSpecificKey($user['user_specific_encryption_ciphertext'] ?? '', $password);
$enc_key       = substr(hash('sha256', $raw_token . 'itflow_enc', true), 0, 16);
$enc_iv        = random_bytes(16);
$enc_key_str   = openssl_encrypt($master_key ?: '', 'aes-128-cbc', $enc_key, 0, $enc_iv);
$esc_enc_key   = mysqli_real_escape_string($mysqli, $enc_key_str);
$esc_enc_iv    = mysqli_real_escape_string($mysqli, bin2hex($enc_iv));

mysqli_query($mysqli,
    "INSERT INTO api_tokens (token_user_id, token_hash, token_name, token_enc_master_key, token_enc_master_iv, token_created_at)
     VALUES ($uid, '$esc_hash', '$esc_device', '$esc_enc_key', '$esc_enc_iv', NOW())"
);

api_response(200, [
    'token' => $raw_token,
    'user'  => [
        'id'    => $uid,
        'name'  => $user['user_name'],
        'email' => $user['user_email'],
        'type'  => intval($user['user_type']),
    ],
]);
