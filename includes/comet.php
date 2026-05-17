<?php
// Comet Backup Server API helper

// ── HTTP client ───────────────────────────────────────────────────────────────

function comet_api(string $method, array $params = []): ?array {
    global $config_comet_server_url, $config_comet_admin_user, $config_comet_admin_pass;
    if (empty($config_comet_server_url) || empty($config_comet_admin_user)) return null;

    $url = rtrim($config_comet_server_url, '/') . '/api/v1/admin/' . $method;
    if ($params) $url .= '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $config_comet_admin_user . ':' . $config_comet_admin_pass,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $resp  = curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err   = curl_error($ch);
    curl_close($ch);

    if ($err || $code !== 200 || !$resp) return null;
    return json_decode($resp, true);
}

function comet_test(): bool {
    $r = comet_api('AdminMetaServerInfo');
    return is_array($r) && isset($r['ServerVersion']);
}

function comet_get_users(): ?array {
    return comet_api('AdminListUsersFull');
}

function comet_get_jobs_for_user(string $username, int $since_days = 7): ?array {
    return comet_api('AdminGetJobsForUser', [
        'Username'  => $username,
        'StartTime' => time() - $since_days * 86400,
    ]);
}

function comet_get_all_jobs(int $since_hours = 25): ?array {
    return comet_api('AdminGetJobsAll', [
        'StartTime' => time() - $since_hours * 3600,
    ]);
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
        5001 => 'success', 5002 => 'info', 5003 => 'warning', 5007 => 'warning',
        5004 => 'danger',  default => 'secondary',
    };
}

function comet_status_icon(int $s): string {
    return match($s) {
        5001 => 'check-circle', 5002 => 'spinner fa-spin', 5003 => 'exclamation-triangle',
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
// Returns [ 'device_name' => ['status'=>int,'start'=>int,'error'=>str,...], ... ]

function comet_last_jobs_per_device(string $username): array {
    $jobs = comet_get_jobs_for_user($username, 30) ?: [];
    // Sort newest-first
    usort($jobs, fn($a, $b) => ($b['StartTime'] ?? 0) - ($a['StartTime'] ?? 0));
    $seen = [];
    foreach ($jobs as $j) {
        $dev = $j['DeviceName'] ?? 'Unknown';
        if (!isset($seen[$dev])) {
            $seen[$dev] = $j;
        }
    }
    return $seen;
}
