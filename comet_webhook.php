<?php
// Comet Backup webhook receiver
// Configure in Comet Server: Admin → Server Settings → Webhooks
//   URL: https://itflow.foleyit.com/comet_webhook.php
//   Events: Job Completed (SEVT_JOB_COMPLETED = 4201)
//   Custom Header: X-Comet-Secret: <your webhook secret>

ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/includes/load_global_settings.php';
require_once __DIR__ . '/includes/comet.php';
ob_end_clean();

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Verify secret header (if configured)
if (!empty($config_comet_webhook_secret)) {
    $received = $_SERVER['HTTP_X_COMET_SECRET'] ?? '';
    if (!hash_equals($config_comet_webhook_secret, $received)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$event_type = intval($body['Type'] ?? 0);

// We only care about job completed events
if ($event_type !== COMET_SEVT_JOB_COMPLETED) {
    echo json_encode(['ok' => true, 'note' => 'Event type ignored']);
    exit;
}

$job = $body['Data'] ?? null;
if (!$job || !is_array($job)) {
    echo json_encode(['ok' => true, 'note' => 'No job data']);
    exit;
}

$status   = intval($job['Status'] ?? 0);
$username = $job['Username'] ?? '';
$dev_name = $job['DeviceName'] ?? 'Unknown Device';
$err_msg  = $job['ErrorString'] ?? '';
$job_type = intval($job['Classification'] ?? 4001);

if (empty($username)) {
    echo json_encode(['ok' => true, 'note' => 'No username in job']);
    exit;
}

// Only process backup jobs (not restores)
if ($job_type !== 4001) {
    echo json_encode(['ok' => true, 'note' => 'Non-backup job ignored']);
    exit;
}

// Find linked ITFlow client
$u_safe = mysqli_real_escape_string($mysqli, $username);
$d_safe = mysqli_real_escape_string($mysqli, $dev_name);

$map = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT map_client_id FROM comet_client_map WHERE map_comet_username = '$u_safe' LIMIT 1"
));
$client_id = $map ? intval($map['map_client_id']) : 0;

if (comet_is_failed($status)) {
    // ── Backup failed ─────────────────────────────────────────────────────────
    // Check for existing open alert for this device
    $existing = mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT alert_id, alert_ticket_id FROM comet_backup_alerts
         WHERE alert_comet_username = '$u_safe'
           AND alert_device_name = '$d_safe'
           AND alert_resolved_at IS NULL
         LIMIT 1"
    ));

    if (!$existing) {
        // Get next ticket number
        $settings = mysqli_fetch_assoc(mysqli_query($mysqli,
            "SELECT config_ticket_prefix, config_ticket_next_number FROM settings WHERE company_id = 1"
        ));
        $prefix        = sanitizeInput($settings['config_ticket_prefix']);
        $ticket_number = intval($settings['config_ticket_next_number']);

        $status_label = comet_status_label($status);
        $subject_safe = sanitizeInput("Backup $status_label — $dev_name");
        $detail_safe  = sanitizeInput(
            "Comet Backup reported a failure for device **$dev_name** (user: $username).\n\n" .
            "Status: $status_label (code: $status)\n" .
            ($err_msg ? "Error: $err_msg\n\n" : "\n") .
            "Please check the device is online, the Comet agent is running, and there are no storage issues."
        );

        $url_key = bin2hex(random_bytes(16));
        mysqli_query($mysqli, "INSERT INTO tickets SET
            ticket_prefix = '$prefix',
            ticket_number = $ticket_number,
            ticket_source = 'Comet Backup',
            ticket_subject = '$subject_safe',
            ticket_details = '$detail_safe',
            ticket_priority = 'High',
            ticket_status = 1,
            ticket_client_id = $client_id,
            ticket_created_by = 0,
            ticket_url_key = '$url_key'
        ");
        $ticket_id = mysqli_insert_id($mysqli);
        mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = " . ($ticket_number + 1) . " WHERE company_id = 1");

        mysqli_query($mysqli, "INSERT INTO comet_backup_alerts SET
            alert_comet_username = '$u_safe',
            alert_device_name    = '$d_safe',
            alert_ticket_id      = $ticket_id
        ");

        $client_suffix = $client_id ? "&client_id=$client_id" : '';
        appNotify('Comet Backup', "Backup failed — $dev_name. Ticket #$ticket_id created.", "/agent/ticket.php?ticket_id=$ticket_id$client_suffix");
        logApp('Comet', 'info', "Webhook: backup failure ticket #$ticket_id created for $dev_name ($username)");

        echo json_encode(['ok' => true, 'action' => 'ticket_created', 'ticket_id' => $ticket_id]);
    } else {
        echo json_encode(['ok' => true, 'action' => 'existing_alert', 'ticket_id' => $existing['alert_ticket_id']]);
    }

} elseif (comet_is_success($status)) {
    // ── Backup succeeded ──────────────────────────────────────────────────────
    $open = mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT alert_id, alert_ticket_id FROM comet_backup_alerts
         WHERE alert_comet_username = '$u_safe'
           AND alert_device_name = '$d_safe'
           AND alert_resolved_at IS NULL
         LIMIT 1"
    ));

    if ($open) {
        $tid = intval($open['alert_ticket_id']);
        mysqli_query($mysqli, "UPDATE comet_backup_alerts SET alert_resolved_at = NOW() WHERE alert_id = {$open['alert_id']}");
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET
            ticket_reply = 'Backup succeeded — device is healthy again. Ticket auto-resolved by Comet integration.',
            ticket_reply_type = 'Internal',
            ticket_reply_by = 0,
            ticket_reply_ticket_id = $tid
        ");
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = $tid");
        logApp('Comet', 'info', "Webhook: backup recovered for $dev_name — ticket #$tid resolved");
        echo json_encode(['ok' => true, 'action' => 'ticket_resolved', 'ticket_id' => $tid]);
    } else {
        echo json_encode(['ok' => true, 'action' => 'no_open_alert']);
    }
} else {
    echo json_encode(['ok' => true, 'action' => 'status_ignored', 'status' => $status]);
}
