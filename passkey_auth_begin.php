<?php
// Returns WebAuthn PublicKeyCredentialRequestOptions as JSON
// Called unauthenticated from the login page with { email } JSON body

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/includes/webauthn.php';

header('Content-Type: application/json');

$body  = json_decode(file_get_contents('php://input'), true);
$email = trim($body['email'] ?? '');

if (empty($email)) {
    echo json_encode(['error' => 'Email required']);
    exit;
}

// Look up user by email (agent type only)
$emailSafe = mysqli_real_escape_string($mysqli, $email);
$userRow   = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT user_id FROM users WHERE user_email = '$emailSafe' AND user_type = 1 AND user_status = 1 AND user_archived_at IS NULL LIMIT 1"
));
if (!$userRow) {
    // Return empty options so the browser doesn't reveal whether the email exists
    echo json_encode(['error' => 'No passkeys found for this account']);
    exit;
}
$userId = intval($userRow['user_id']);

// Load their registered passkeys
$allowCredentials = [];
$sql_pk = mysqli_query($mysqli, "SELECT passkey_credential_id FROM user_passkeys WHERE passkey_user_id = $userId");
while ($pk = mysqli_fetch_assoc($sql_pk)) {
    $allowCredentials[] = [
        'type'       => 'public-key',
        'id'         => $pk['passkey_credential_id'],
        'transports' => ['internal', 'hybrid'],
    ];
}

if (empty($allowCredentials)) {
    echo json_encode(['error' => 'No passkeys registered for this account']);
    exit;
}

$challenge = random_bytes(32);
$_SESSION['passkey_auth_challenge'] = wa_b64u_encode($challenge);
$_SESSION['passkey_auth_user_id']   = $userId;

echo json_encode([
    'challenge'        => wa_b64u_encode($challenge),
    'timeout'          => 60000,
    'rpId'             => wa_rp_id(),
    'allowCredentials' => $allowCredentials,
    'userVerification' => 'preferred',
]);
