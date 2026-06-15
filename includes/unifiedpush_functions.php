<?php

// Send a plaintext JSON push message to a single UnifiedPush endpoint.
// Returns true on success (2xx). On 404/410 the endpoint is gone and is removed.
function unifiedpush_send(string $endpoint_url, string $title, string $body, array $data = []): bool {
    global $mysqli;

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'data'  => array_map('strval', $data),
    ]);

    // UnifiedPush v1 plaintext messages are limited to 4096 bytes
    if (strlen($payload) > 4096) {
        $data['truncated'] = '1';
        $body = mb_substr($body, 0, 200) . '…';
        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'data'  => array_map('strval', $data),
        ]);
    }

    $ch = curl_init($endpoint_url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_errno($ch);
    curl_close($ch);

    $esc = mysqli_real_escape_string($mysqli, $endpoint_url);

    if ($code === 404 || $code === 410) {
        mysqli_query($mysqli, "DELETE FROM push_endpoints WHERE push_endpoint_url = '$esc'");
        return false;
    }

    if ($err || $code < 200 || $code >= 300) {
        mysqli_query($mysqli, "UPDATE push_endpoints SET push_endpoint_last_failed_at = NOW() WHERE push_endpoint_url = '$esc'");
        return false;
    }

    return true;
}

// Send a push message to every UnifiedPush endpoint registered for a given user.
function unifiedpush_send_to_user(int $user_id, string $title, string $body, array $data = []): void {
    global $mysqli;

    $user_id = intval($user_id);
    if (!$user_id) {
        return;
    }

    $sql = mysqli_query($mysqli,
        "SELECT pe.push_endpoint_url FROM push_endpoints pe
         INNER JOIN api_tokens t ON t.token_id = pe.push_endpoint_token_id
         WHERE t.token_user_id = $user_id"
    );

    while ($row = mysqli_fetch_assoc($sql)) {
        unifiedpush_send($row['push_endpoint_url'], $title, $body, $data);
    }
}
