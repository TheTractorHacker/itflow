<?php
// Comet Backup Server API helper
// Ref: https://docs.cometbackup.com/latest/api/api-guide
// SDK: https://github.com/CometBackup/comet-php-sdk

// ── Job status constants (from Comet SDK Def.php) ─────────────────────────────
// Successful
define('COMET_JOB_SUCCESS',           5000);
// Running
define('COMET_JOB_RUNNING_ACTIVE',    6001);
define('COMET_JOB_RUNNING_REVIVED',   6002);
define('COMET_JOB_RUNNING_TRYAGAIN',  6003);
define('COMET_JOB_NOT_STARTED',       6004);
// Failed
define('COMET_JOB_FAILED_TIMEOUT',    7000);
define('COMET_JOB_FAILED_WARNING',    7001);
define('COMET_JOB_FAILED_ERROR',      7002);
define('COMET_JOB_FAILED_QUOTA',      7003);
define('COMET_JOB_FAILED_MISSED',     7004);
define('COMET_JOB_FAILED_CANCELLED',  7005);
define('COMET_JOB_FAILED_SKIP',       7006);
define('COMET_JOB_FAILED_ABANDONED',  7007);

// ── Webhook event types (from Comet SDK Def.php SEVT_) ────────────────────────
define('COMET_SEVT_JOB_NEW',       4200);
define('COMET_SEVT_JOB_COMPLETED', 4201);

// ── Session key cache (stored in DB for reuse across requests) ────────────────
// We cache the session key to avoid hitting TOTP on every API call.
function comet_get_session_key(): ?string {
    global $mysqli;
    // Use a dedicated settings row to store the cached session key + expiry
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT config_value, config_expires FROM comet_session_cache WHERE id = 1 LIMIT 1"
    ));
    if ($row && !empty($row['config_value']) && strtotime($row['config_expires']) > time() + 60) {
        return $row['config_value'];
    }
    return null;
}

function comet_store_session_key(string $key, int $expires_in = 3600): void {
    global $mysqli;
    $key_safe = mysqli_real_escape_string($mysqli, $key);
    $expires   = date('Y-m-d H:i:s', time() + $expires_in);
    mysqli_query($mysqli, "INSERT INTO comet_session_cache (id, config_value, config_expires) VALUES (1, '$key_safe', '$expires') ON DUPLICATE KEY UPDATE config_value='$key_safe', config_expires='$expires'");
}

// ── Core HTTP request ─────────────────────────────────────────────────────────
function comet_api(string $endpoint, array $extra = []): ?array {
    global $config_comet_server_url, $config_comet_admin_user,
           $config_comet_admin_pass, $config_comet_totp_secret;
    if (empty($config_comet_server_url) || empty($config_comet_admin_user)) return null;

    // Try cached session key first (avoids TOTP re-entry)
    $session_key = comet_get_session_key();
    if ($session_key) {
        $auth = ['Username' => $config_comet_admin_user, 'AuthType' => 'SessionKey', 'SessionKey' => $session_key];
    } elseif (!empty($config_comet_totp_secret)) {
        // Authenticate with password + TOTP code, then get and cache a session key
        require_once $_SERVER['DOCUMENT_ROOT'] . '/plugins/totp/totp.php';
        $code = TokenAuth6238::getTokenCode($config_comet_totp_secret);
        $auth = ['Username' => $config_comet_admin_user, 'AuthType' => 'PasswordTOTP',
                 'Password' => $config_comet_admin_pass, 'TOTP' => $code];
    } else {
        $auth = ['Username' => $config_comet_admin_user, 'AuthType' => 'Password',
                 'Password' => $config_comet_admin_pass];
    }

    $body = http_build_query(array_merge($auth, $extra));
    $url  = rtrim($config_comet_server_url, '/') . $endpoint;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$resp) return null;
    return json_decode($resp, true);
}

// ── Session key login (call once to populate cache) ───────────────────────────
function comet_start_session(): bool {
    global $config_comet_admin_user, $config_comet_admin_pass, $config_comet_totp_secret, $config_comet_server_url;
    if (empty($config_comet_server_url)) return false;

    if (!empty($config_comet_totp_secret)) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/plugins/totp/totp.php';
        $code = TokenAuth6238::getTokenCode($config_comet_totp_secret);
        $auth = ['Username' => $config_comet_admin_user, 'AuthType' => 'PasswordTOTP',
                 'Password' => $config_comet_admin_pass, 'TOTP' => $code];
    } else {
        $auth = ['Username' => $config_comet_admin_user, 'AuthType' => 'Password',
                 'Password' => $config_comet_admin_pass];
    }
    $body = http_build_query($auth);
    $url  = rtrim($config_comet_server_url, '/') . '/api/v1/admin/account/session-start';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 10,
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($resp['SessionKey'])) {
        comet_store_session_key($resp['SessionKey'], 3600);
        return true;
    }
    return false;
}

// ── Convenience wrappers ──────────────────────────────────────────────────────

function comet_test(): bool {
    $r = comet_api('/api/v1/admin/meta/version');
    return is_array($r) && (isset($r['Version']) || isset($r['ServerVersion']));
}

function comet_get_users(): ?array {
    return comet_api('/api/v1/admin/list-users-full');
}

function comet_get_jobs_for_user(string $username): ?array {
    return comet_api('/api/v1/admin/get-jobs-for-user', ['TargetUser' => $username]);
}

function comet_get_jobs_recent(): ?array {
    return comet_api('/api/v1/admin/get-jobs-recent');
}

// ── Status helpers ────────────────────────────────────────────────────────────

function comet_is_failed(int $s): bool {
    return $s >= 7000 && $s <= 7999;
}
function comet_is_running(int $s): bool {
    return $s >= 6000 && $s <= 6999;
}
function comet_is_success(int $s): bool {
    return $s >= 5000 && $s < 6000;
}

function comet_status_label(int $s): string {
    return match(true) {
        $s === COMET_JOB_SUCCESS          => 'Success',
        $s === COMET_JOB_RUNNING_ACTIVE   => 'Running',
        $s === COMET_JOB_RUNNING_REVIVED  => 'Running',
        $s === COMET_JOB_RUNNING_TRYAGAIN => 'Retrying',
        $s === COMET_JOB_NOT_STARTED      => 'Pending',
        $s === COMET_JOB_FAILED_WARNING   => 'Warning',
        $s === COMET_JOB_FAILED_TIMEOUT   => 'Timeout',
        $s === COMET_JOB_FAILED_ERROR     => 'Error',
        $s === COMET_JOB_FAILED_QUOTA     => 'Quota exceeded',
        $s === COMET_JOB_FAILED_MISSED    => 'Schedule missed',
        $s === COMET_JOB_FAILED_CANCELLED => 'Cancelled',
        $s === COMET_JOB_FAILED_ABANDONED => 'Abandoned',
        comet_is_failed($s)               => 'Failed',
        comet_is_running($s)              => 'Running',
        default                           => 'Unknown',
    };
}

function comet_status_class(int $s): string {
    if (comet_is_success($s)) return 'success';
    if (comet_is_running($s)) return 'info';
    if ($s === COMET_JOB_FAILED_WARNING) return 'warning';
    if (comet_is_failed($s)) return 'danger';
    return 'secondary';
}

function comet_status_icon(int $s): string {
    if (comet_is_success($s)) return 'check-circle';
    if (comet_is_running($s)) return 'spinner fa-spin';
    if ($s === COMET_JOB_FAILED_WARNING) return 'exclamation-triangle';
    if (comet_is_failed($s)) return 'times-circle';
    return 'circle';
}

function comet_job_type(int $c): string {
    return match($c) { 4001 => 'Backup', 4002 => 'Restore', default => 'Other' };
}

function comet_fmt_bytes(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 0) . ' KB';
    return $bytes . ' B';
}

function comet_last_jobs_per_device(string $username): array {
    $jobs = comet_get_jobs_for_user($username) ?: [];
    usort($jobs, fn($a, $b) => ($b['StartTime'] ?? 0) - ($a['StartTime'] ?? 0));
    $seen = [];
    foreach ($jobs as $j) {
        $dev = $j['DeviceName'] ?? 'Unknown';
        if (!isset($seen[$dev])) $seen[$dev] = $j;
    }
    return $seen;
}
