<?php
if (defined('FROM_POST_HANDLER')) return;
/*
 * Fetch live data from Tactical RMM for a specific asset (software, services, WMI).
 * Called via AJAX from rmm_asset.php. API key never leaves this file.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/check_login.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_global_settings.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_user_session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/class_tactical_rmm.php';

header('Content-Type: application/json');

$link_id = intval($_POST['link_id'] ?? 0);
$type    = sanitizeInput($_POST['type'] ?? '');

if (!$link_id || !in_array($type, ['software', 'services', 'wmi'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

enforceUserPermission('module_rmm');

$link = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT arl.*, a.asset_client_id FROM asset_rmm_links arl
     JOIN assets a ON a.asset_id = arl.asset_id
     WHERE arl.id=$link_id"
));

if (!$link) {
    echo json_encode(['success' => false, 'error' => 'Link not found']);
    exit;
}

if ($link['asset_client_id']) { enforceClientAccess(intval($link['asset_client_id'])); }

try {
    $client   = new TacticalRmmClient(intval($link['integration_id']));
    $agent_id = $link['tactical_agent_id'];

    if ($type === 'software') {
        $data = $client->getAgentSoftware($agent_id);
    } elseif ($type === 'services') {
        $data = $client->getAgentServices($agent_id);
    } elseif ($type === 'wmi') {
        $data = $client->getAgentWmi($agent_id);
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
