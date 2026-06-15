<?php
// GET    /api/v1/me                  - current user profile
// PUT    /api/v1/me                  - update profile
// POST   /api/v1/me/push-endpoint    - register/update this token's UnifiedPush endpoint
// DELETE /api/v1/me/push-endpoint    - unregister this token's UnifiedPush endpoint
defined('FROM_API') || die();

if ($sub === 'push-endpoint') {
    $token_id = intval($api_token_row['token_id']);

    if ($method === 'POST' || $method === 'PUT') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $url  = trim($body['endpoint_url'] ?? '');

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL) || stripos($url, 'https://') !== 0) {
            api_error(400, 'endpoint_url must be a valid https:// URL');
        }

        $esc = mysqli_real_escape_string($mysqli, $url);
        mysqli_query($mysqli, "DELETE FROM push_endpoints WHERE push_endpoint_token_id = $token_id");
        mysqli_query($mysqli, "INSERT INTO push_endpoints SET push_endpoint_token_id = $token_id, push_endpoint_url = '$esc'");

        api_response(200, ['ok' => true]);
    }

    if ($method === 'DELETE') {
        mysqli_query($mysqli, "DELETE FROM push_endpoints WHERE push_endpoint_token_id = $token_id");
        api_response(200, ['ok' => true]);
    }

    api_error(405, 'Method not allowed');
}

if ($method === 'GET') {
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT user_id, user_name, user_email, user_type, user_color, user_avatar
         FROM users WHERE user_id = $api_user_id LIMIT 1"));
    api_response(200, [
        'id'     => intval($row['user_id']),
        'name'   => $row['user_name'],
        'email'  => $row['user_email'],
        'type'   => intval($row['user_type']),
        'color'  => $row['user_color'],
        'avatar' => $row['user_avatar'],
    ]);
}

if ($method === 'PUT' || $method === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $name     = mysqli_real_escape_string($mysqli, trim($body['name'] ?? ''));
    $email    = mysqli_real_escape_string($mysqli, trim($body['email'] ?? ''));
    $cur_pass = trim($body['current_password'] ?? '');
    $new_pass = trim($body['new_password'] ?? '');

    if (!$name || !$email) api_error(400, 'name and email required');

    $updates = ["user_name = '$name'", "user_email = '$email'"];

    if ($new_pass) {
        if (strlen($new_pass) < 8) api_error(400, 'Password must be at least 8 characters');
        // Verify current password
        $user = mysqli_fetch_assoc(mysqli_query($mysqli,
            "SELECT user_password FROM users WHERE user_id = $api_user_id LIMIT 1"));
        if (!password_verify($cur_pass, $user['user_password'])) {
            api_error(401, 'Current password is incorrect');
        }
        $hash = password_hash($new_pass, PASSWORD_BCRYPT);
        $esc_hash = mysqli_real_escape_string($mysqli, $hash);
        $updates[] = "user_password = '$esc_hash'";
    }

    $set = implode(', ', $updates);
    mysqli_query($mysqli, "UPDATE users SET $set WHERE user_id = $api_user_id");
    api_response(200, ['ok' => true]);
}

api_error(405, 'Method not allowed');
