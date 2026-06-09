<?php
/*
 * RMM Sync + Test Connection handler
 * Called from:
 *   - admin/settings_rmm.php (AJAX test)
 *   - agent/rmm_assets.php (manual sync trigger)
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/check_login.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_global_settings.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_user_session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/class_tactical_rmm.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/class_rmm_asset_mapper.php';

header('Content-Type: application/json');

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if (!lookupUserPermission('module_rmm')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$action         = sanitizeInput($_POST['action'] ?? 'sync');
$integration_id = intval($_POST['integration_id'] ?? $config_rmm_default_integration_id);

if ($integration_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'No integration configured']);
    exit;
}

// ---- Test connection ----
if ($action === 'test') {
    try {
        $client = new TacticalRmmClient($integration_id);
        $ok     = $client->testConnection();
        echo json_encode(['success' => $ok, 'error' => $ok ? null : 'Could not reach Tactical RMM API']);
    } catch (RuntimeException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ---- Full sync ----
if ($action === 'sync') {
    if (!lookupUserPermission('module_rmm_sync')) {
        echo json_encode(['success' => false, 'error' => 'No sync permission']);
        exit;
    }

    // Rate-limit: one sync per integration per 60 seconds
    $recent = mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT id FROM rmm_sync_log WHERE integration_id=$integration_id
         AND started_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) AND status='running' LIMIT 1"
    ));
    if ($recent) {
        echo json_encode(['success' => false, 'error' => 'A sync is already running. Please wait 60 seconds.']);
        exit;
    }

    try {
        $client  = new TacticalRmmClient($integration_id);
        $mapper  = new RmmAssetMapper($mysqli, $integration_id, $session_user_id);
        $log_id  = $mapper->startSyncLog();

        $agents = $client->getAgents();
        $stats  = $mapper->syncAgents($agents);
        $mapper->finishSyncLog($log_id, $stats);

        logAction('RMM', 'Import',
            "$session_name synced RMM assets: {$stats['created']} created, {$stats['updated']} updated, {$stats['matched']} matched"
        );

        echo json_encode([
            'success'  => true,
            'created'  => $stats['created'],
            'updated'  => $stats['updated'],
            'matched'  => $stats['matched'],
            'skipped'  => $stats['skipped'],
            'errors'   => $stats['errors'],
        ]);
    } catch (RuntimeException $e) {
        if (isset($log_id)) {
            mysqli_query($mysqli, "UPDATE rmm_sync_log SET finished_at=NOW(), status='failed', errors='" .
                mysqli_real_escape_string($mysqli, $e->getMessage()) . "' WHERE id=$log_id");
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
