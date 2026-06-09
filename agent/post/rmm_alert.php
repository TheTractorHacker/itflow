<?php
if (defined('FROM_POST_HANDLER')) return;
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/check_login.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_global_settings.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_user_session.php';

header('Content-Type: application/json');

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

enforceUserPermission('module_rmm_alerts');

$action   = sanitizeInput($_POST['action'] ?? '');
$alert_id = intval($_POST['alert_id'] ?? 0);

if (!$alert_id) {
    echo json_encode(['success' => false, 'error' => 'Missing alert_id']);
    exit;
}

$alert = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM rmm_alerts WHERE id=$alert_id"));
if (!$alert) {
    echo json_encode(['success' => false, 'error' => 'Alert not found']);
    exit;
}

$client_id = intval($alert['client_id']);
if ($client_id) { enforceClientAccess($client_id); }

if ($action === 'acknowledge') {
    enforceUserPermission('module_rmm_alerts_ack');
    mysqli_query($mysqli, "UPDATE rmm_alerts SET status='acknowledged', acknowledged_by=$session_user_id, acknowledged_at=NOW() WHERE id=$alert_id");
    logAction('RMM', 'Alert Acknowledged', "$session_name acknowledged RMM alert ID $alert_id", $client_id, intval($alert['asset_id']));
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'resolve') {
    enforceUserPermission('module_rmm_alerts_ack');
    mysqli_query($mysqli, "UPDATE rmm_alerts SET status='resolved', resolved_at=NOW() WHERE id=$alert_id");
    logAction('RMM', 'Alert Resolved', "$session_name resolved RMM alert ID $alert_id", $client_id, intval($alert['asset_id']));
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'create_ticket') {
    enforceUserPermission('module_support', 2);
    $subject = 'RMM Alert: ' . substr($alert['message'] ?? '', 0, 200);
    $subject_esc = mysqli_real_escape_string($mysqli, $subject);
    $details_esc = mysqli_real_escape_string($mysqli, "RMM Alert\nSeverity: {$alert['severity']}\nMessage: {$alert['message']}\nAlert ID: {$alert['tactical_alert_id']}");
    $asset_id_val = intval($alert['asset_id']);

    // Get next ticket number
    $settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_ticket_prefix, config_ticket_next_number FROM settings WHERE company_id=1"));
    $prefix    = $settings['config_ticket_prefix'];
    $number    = intval($settings['config_ticket_next_number']);
    mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number=config_ticket_next_number+1 WHERE company_id=1");
    $url_key   = randomString(32);

    mysqli_query($mysqli,
        "INSERT INTO tickets SET
         ticket_prefix='$prefix',
         ticket_number=$number,
         ticket_subject='$subject_esc',
         ticket_details='$details_esc',
         ticket_status=1,
         ticket_priority='Medium',
         ticket_source='In-App',
         ticket_client_id=$client_id,
         ticket_asset_id=$asset_id_val,
         ticket_created_by=$session_user_id,
         ticket_url_key='$url_key',
         ticket_created_at=NOW()"
    );
    $ticket_id = intval(mysqli_insert_id($mysqli));

    // Link alert to ticket
    mysqli_query($mysqli, "UPDATE rmm_alerts SET status='acknowledged', acknowledged_by=$session_user_id, acknowledged_at=NOW() WHERE id=$alert_id");

    logAction('RMM', 'Alert Ticket Created', "$session_name created ticket $prefix$number from RMM alert $alert_id", $client_id, $asset_id_val);

    echo json_encode(['success' => true, 'ticket_id' => $ticket_id, 'redirect' => "/agent/ticket.php?ticket_id=$ticket_id"]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
