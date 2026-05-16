<?php
// Verifies and stores a newly-registered passkey
// Expects JSON body: { id, response: { clientDataJSON, attestationObject }, passkeyName }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_user_session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/webauthn.php';

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { echo json_encode(['ok' => false, 'error' => 'Invalid JSON']); exit; }

$challenge = $_SESSION['passkey_register_challenge'] ?? null;
if (!$challenge) { echo json_encode(['ok' => false, 'error' => 'No registration challenge in session']); exit; }
unset($_SESSION['passkey_register_challenge']);

try {
    $result = wa_verify_registration(
        $body['response']['clientDataJSON']    ?? '',
        $body['response']['attestationObject'] ?? '',
        $challenge
    );

    $credId    = $result['credId'];
    $pubKey    = mysqli_real_escape_string($mysqli, $result['pubKeyPem']);
    $signCount = intval($result['signCount']);
    $aaguid    = sanitizeInput($result['aaguid']);
    $name      = sanitizeInput($body['passkeyName'] ?? 'Passkey');
    $uid       = $session_user_id;

    // Prevent duplicate credential ID
    $dup = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT passkey_id FROM user_passkeys WHERE passkey_credential_id = '$credId' LIMIT 1"));
    if ($dup) { echo json_encode(['ok' => false, 'error' => 'Passkey already registered']); exit; }

    mysqli_query($mysqli, "INSERT INTO user_passkeys SET
        passkey_user_id = $uid,
        passkey_name = '$name',
        passkey_credential_id = '$credId',
        passkey_public_key = '$pubKey',
        passkey_sign_count = $signCount,
        passkey_aaguid = '$aaguid'
    ");

    logAction("Passkey", "Register", "$session_name registered passkey '$name'", 0, $uid);

    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    logAction("Passkey", "Register Failed", "Registration failed: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
