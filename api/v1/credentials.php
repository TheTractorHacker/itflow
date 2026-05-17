<?php
// GET /api/v1/credentials           list (usernames only, no secrets)
// GET /api/v1/credentials/{id}      detail with decrypted secrets — requires X-Biometric: 1
defined('FROM_API') || die();
if ($method !== 'GET') api_error(405, 'Method not allowed');

// Helper: decrypt a credential field using the token's stored master key
function _api_decrypt(string $ciphertext, string $raw_token, array $token_row): string {
    if (!$ciphertext || !$token_row['token_enc_master_key']) return '';
    $enc_key    = substr(hash('sha256', $raw_token . 'itflow_enc', true), 0, 16);
    $enc_iv     = hex2bin($token_row['token_enc_master_iv']);
    $master_key = openssl_decrypt($token_row['token_enc_master_key'], 'aes-128-cbc', $enc_key, 0, $enc_iv);
    if (!$master_key) return '';
    $cred_iv    = substr($ciphertext, 0, 16);
    $cred_ct    = substr($ciphertext, 16);
    return openssl_decrypt($cred_ct, 'aes-128-cbc', $master_key, 0, $cred_iv) ?: '';
}

// Extract raw token from Authorization header for decryption
$raw_token = '';
if (preg_match('/^Bearer\s+(\S+)$/i', $_SERVER['HTTP_AUTHORIZATION'] ?? '', $bm)) {
    $raw_token = $bm[1];
}

if ($id === null) {
    $page      = max(1, intval($_GET['page'] ?? 1));
    $limit     = min(50, max(1, intval($_GET['limit'] ?? 20)));
    $offset    = ($page - 1) * $limit;
    $search    = mysqli_real_escape_string($mysqli, $_GET['search'] ?? '');
    $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : null;

    $where = ['credential_archived_at IS NULL'];
    if ($client_id) $where[] = "credential_client_id = $client_id";
    if ($search)    $where[] = "(credential_name LIKE '%$search%' OR credential_uri LIKE '%$search%')";
    $w = implode(' AND ', $where);

    $total = intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS c FROM credentials WHERE $w"))['c']);
    $creds = [];
    $sql   = mysqli_query($mysqli,
        "SELECT cr.credential_id, cr.credential_name, cr.credential_username, cr.credential_uri,
                c.client_name
         FROM credentials cr LEFT JOIN clients c ON cr.credential_client_id = c.client_id
         WHERE $w ORDER BY cr.credential_name ASC LIMIT $limit OFFSET $offset"
    );
    while ($row = mysqli_fetch_assoc($sql)) {
        $creds[] = [
            'id'       => intval($row['credential_id']),
            'name'     => $row['credential_name'],
            'username' => _api_decrypt($row['credential_username'], $raw_token, $api_token_row),
            'uri'      => $row['credential_uri'],
            'client'   => $row['client_name'],
        ];
    }
    api_response(200, ['data' => $creds, 'total' => $total]);
}

// Detail — requires biometric confirmation from device
$biometric = $_SERVER['HTTP_X_BIOMETRIC'] ?? '0';
if ($biometric !== '1') {
    api_error(403, 'Biometric verification required');
}

$row = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT cr.*, c.client_name FROM credentials cr
     LEFT JOIN clients c ON cr.credential_client_id = c.client_id
     WHERE cr.credential_id = $id AND cr.credential_archived_at IS NULL LIMIT 1"
));
if (!$row) api_error(404, 'Credential not found');

api_response(200, [
    'id'         => intval($row['credential_id']),
    'name'       => $row['credential_name'],
    'username'   => _api_decrypt($row['credential_username'], $raw_token, $api_token_row),
    'password'   => _api_decrypt($row['credential_password'], $raw_token, $api_token_row),
    'uri'        => $row['credential_uri'],
    'uri_2'      => $row['credential_uri_2'] ?? '',
    'otp_secret' => $row['credential_otp_secret'] ?? '',
    'note'       => $row['credential_note'] ?? '',
    'client'     => $row['client_name'],
]);
