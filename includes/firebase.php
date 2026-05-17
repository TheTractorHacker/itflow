<?php

function _firebase_b64u(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function firebase_get_access_token(): ?string {
    $sa_file = $_SERVER['DOCUMENT_ROOT'] . '/config/firebase_service_account.json';
    if (!file_exists($sa_file)) return null;
    $sa = json_decode(file_get_contents($sa_file), true);

    $now = time();
    $header  = _firebase_b64u(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = _firebase_b64u(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'exp'   => $now + 3600,
        'iat'   => $now,
    ]));

    $to_sign = "$header.$payload";
    openssl_sign($to_sign, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256);

    $jwt = "$to_sign." . _firebase_b64u($sig);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $resp['access_token'] ?? null;
}

function firebase_send_push(string $fcm_token, string $title, string $body, array $data = []): bool {
    $token = firebase_get_access_token();
    if (!$token) return false;

    $project_id = 'itflow-msp';
    $payload = json_encode([
        'message' => [
            'token'        => $fcm_token,
            'notification' => ['title' => $title, 'body' => $body],
            'data'         => array_map('strval', $data),
        ],
    ]);

    $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token", 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $code = null;
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code === 200;
}

function firebase_send_push_to_user(int $user_id, string $title, string $body, array $data = []): void {
    global $mysqli;
    $uid = intval($user_id);
    $sql = mysqli_query($mysqli, "SELECT token_fcm_token FROM api_tokens WHERE token_user_id = $uid AND token_fcm_token IS NOT NULL");
    while ($row = mysqli_fetch_assoc($sql)) {
        firebase_send_push($row['token_fcm_token'], $title, $body, $data);
    }
}
