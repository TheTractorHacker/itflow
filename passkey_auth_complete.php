<?php
// Verifies a passkey assertion and creates a session
// Expects JSON body: { id, response: { clientDataJSON, authenticatorData, signature, userHandle } }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/includes/webauthn.php';
require_once __DIR__ . '/includes/load_global_settings.php';

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { echo json_encode(['ok' => false, 'error' => 'Invalid request']); exit; }

$challenge = $_SESSION['passkey_auth_challenge'] ?? null;
$userId    = intval($_SESSION['passkey_auth_user_id'] ?? 0);

if (!$challenge || !$userId) {
    echo json_encode(['ok' => false, 'error' => 'No auth challenge in session — please start over']);
    exit;
}

unset($_SESSION['passkey_auth_challenge'], $_SESSION['passkey_auth_user_id']);

$credId = $body['id'] ?? '';
if (empty($credId)) { echo json_encode(['ok' => false, 'error' => 'Missing credential ID']); exit; }

// Load the stored passkey
$credSafe = mysqli_real_escape_string($mysqli, $credId);
$pkRow    = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT * FROM user_passkeys WHERE passkey_credential_id = '$credSafe' AND passkey_user_id = $userId LIMIT 1"
));
if (!$pkRow) {
    echo json_encode(['ok' => false, 'error' => 'Passkey not found']);
    exit;
}

// Load user
$userRow = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT * FROM users
     LEFT JOIN user_settings ON users.user_id = user_settings.user_id
     WHERE users.user_id = $userId AND users.user_type = 1 AND users.user_status = 1 AND users.user_archived_at IS NULL
     LIMIT 1"
));
if (!$userRow) {
    echo json_encode(['ok' => false, 'error' => 'Account not found or inactive']);
    exit;
}

try {
    $newSignCount = wa_verify_assertion(
        $credId,
        $body['response']['clientDataJSON']    ?? '',
        $body['response']['authenticatorData'] ?? '',
        $body['response']['signature']         ?? '',
        $pkRow['passkey_public_key'],
        intval($pkRow['passkey_sign_count']),
        $challenge
    );

    // Update sign count and last used timestamp
    $passkey_id = intval($pkRow['passkey_id']);
    mysqli_query($mysqli, "UPDATE user_passkeys SET passkey_sign_count = $newSignCount, passkey_last_used_at = NOW() WHERE passkey_id = $passkey_id");

    // ── Create session ───────────────────────────────────────────────────────

    $_SESSION['user_id']    = $userId;
    $_SESSION['csrf_token'] = randomString(32);
    $_SESSION['logged']     = true;

    $session_user_id = $userId;
    $user_name       = sanitizeInput($userRow['user_name']);
    $user_email      = sanitizeInput($userRow['user_email']);

    // Suspicious login detection
    $session_ip         = sanitizeInput(getIP());
    $session_user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT'] ?? '');

    $ip_prev = intval(mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT COUNT(log_id) AS c FROM logs WHERE log_type = 'Login' AND log_action = 'Success' AND log_ip = '$session_ip' AND log_user_id = $userId"
    ))['c']);
    $ua_prev = intval(mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT COUNT(log_id) AS c FROM logs WHERE log_type = 'Login' AND log_action = 'Success' AND log_user_agent = '$session_user_agent' AND log_user_id = $userId"
    ))['c']);

    if ($ip_prev === 0 && $ua_prev === 0 && !empty($config_smtp_host)) {
        $subject = "New login to your ITFlow account";
        $body_email = "Hi $user_name,<br><br>A login using a passkey was detected from a new device or location.<br><br>IP: $session_ip<br>Browser: $session_user_agent<br><br>If this wasn't you, your account may be compromised.";
        $data = [[
            'from' => $config_mail_from_email, 'from_name' => $config_mail_from_name,
            'recipient' => $user_email, 'recipient_name' => $user_name,
            'subject' => $subject, 'body' => $body_email,
        ]];
        addToMailQueue($data);
    }

    logAction("Login", "Success", "$user_name logged in via passkey");

    // Determine start page
    $config_start_page = mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT config_start_page FROM settings WHERE company_id = 1 LIMIT 1"
    ))['config_start_page'] ?? 'dashboard.php';

    echo json_encode(['ok' => true, 'redirect' => "/agent/$config_start_page"]);

} catch (Throwable $e) {
    logAction("Login", "Failed", "Passkey auth failed for user $userId: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Authentication failed: ' . $e->getMessage()]);
}
