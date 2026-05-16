<?php
// Returns WebAuthn PublicKeyCredentialCreationOptions as JSON
// Must be called while authenticated

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_user_session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/webauthn.php';

header('Content-Type: application/json');

$challenge = random_bytes(32);
$_SESSION['passkey_register_challenge'] = wa_b64u_encode($challenge);

$rpId   = wa_rp_id();
$origin = wa_origin();

// Gather already-registered credential IDs to exclude (prevent duplicate registration)
$excludeCredentials = [];
$sql_exc = mysqli_query($mysqli, "SELECT passkey_credential_id FROM user_passkeys WHERE passkey_user_id = $session_user_id");
while ($exc = mysqli_fetch_assoc($sql_exc)) {
    $excludeCredentials[] = [
        'type' => 'public-key',
        'id'   => $exc['passkey_credential_id'],
    ];
}

echo json_encode([
    'rp'    => ['id' => $rpId, 'name' => 'ITFlow'],
    'user'  => [
        'id'          => wa_b64u_encode(pack('N', $session_user_id)),
        'name'        => $session_email,
        'displayName' => $session_name,
    ],
    'challenge'              => wa_b64u_encode($challenge),
    'pubKeyCredParams'       => [
        ['type' => 'public-key', 'alg' => -7],   // ES256
        ['type' => 'public-key', 'alg' => -257],  // RS256
    ],
    'timeout'                => 60000,
    'excludeCredentials'     => $excludeCredentials,
    'authenticatorSelection' => [
        'residentKey'        => 'preferred',
        'userVerification'   => 'preferred',
    ],
    'attestation'            => 'none',
]);
