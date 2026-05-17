<?php
// Comet Backup Server API helper
// Docs: https://docs.cometbackup.com/latest/api/api-guide
// SDK:  https://github.com/CometBackup/comet-php-sdk

// ── HTTP client ───────────────────────────────────────────────────────────────
// Auth is sent as POST form fields (Username, AuthType, Password),
// NOT HTTP Basic Auth. Additional parameters merge with auth fields.

function comet_api(string $endpoint, array $extra_params = []): ?array {
    global $config_comet_server_url, $config_comet_admin_user, $config_comet_admin_pass;
    if (empty($config_comet_server_url) || empty($config_comet_admin_user)) return null;

    $url = rtrim($config_comet_server_url, '/') . $endpoint;

    $body = http_build_query(array_merge([
        'Username' => $config_comet_admin_user,
        'AuthType' => 'Password',
        'Password' => $config_comet_admin_pass,
    ], $extra_params));

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
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || $code !== 200 || !$resp) return null;
    return json_decode($resp, true);
}

// ── Convenience wrappers ──────────────────────────────────────────────────────

function comet_test(): bool {
    // GET /api/v1/admin/meta/version — server version info
    $r = comet_api('/api/v1/admin/meta/version');
    return is_array($r) && (isset($r['Version']) || isset($r['ServerVersion']));
}

function comet_get_users(): ?array {
    // Returns map of username → UserProfileConfig
    return comet_api('/api/v1/admin/list-users-full');
}

function comet_get_jobs_for_user(string $username, int $since_days = 7): ?array {
    // TargetUser is the correct param name (not Username)
    return comet_api('/api/v1/admin/get-jobs-for-user', [
        'TargetUser' => $username,
    ]);
}

function comet_get_jobs_recent(): ?array {
    // Recent + in-progress jobs across all users
    return comet_api('/api/v1/admin/get-jobs-recent');
}

function comet_get_jobs_all(): ?array {
    // All completed jobs (may be large on busy servers)
    return comet_api('/api/v1/admin/get-jobs-all');
}

// ── Display helpers ───────────────────────────────────────────────────────────

function comet_status_label(int $s): string {
    return match($s) {
        5001 => 'Success', 5002 => 'Running', 5003 => 'Warning',
        5004 => 'Error',   5005 => 'Skipped', 5007 => 'Warning',
        default => 'Unknown',
    };
}

function comet_status_class(int $s): string {
    return match($s) {
        5001 => 'success', 5002 => 'info',
        5003, 5007 => 'warning',
        5004 => 'danger',
        default => 'secondary',
    };
}

function comet_status_icon(int $s): string {
    return match($s) {
        5001 => 'check-circle', 5002 => 'spinner fa-spin',
        5003, 5007 => 'exclamation-triangle',
        5004 => 'times-circle', default => 'circle',
    };
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

// ── Per-user last-job summary ─────────────────────────────────────────────────
// Returns [ 'DeviceName' => job_array, ... ] — one entry per device (newest job)

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
