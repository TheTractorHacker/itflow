<?php
/*
 * RMM Remote Connect handler
 * Constructs remote URL server-side, logs the session, returns redirect JSON.
 * The API key and URL are NEVER sent to the browser.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/check_login.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_global_settings.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_user_session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/class_tactical_rmm.php';

header('Content-Type: application/json');

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

enforceUserPermission('module_rmm_remote_connect');

$link_id         = intval($_POST['link_id'] ?? 0);
$connection_type = sanitizeInput($_POST['type'] ?? 'tactical');

if (!$link_id) {
    echo json_encode(['success' => false, 'error' => 'Missing link_id']);
    exit;
}

$link = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT arl.*, a.asset_client_id FROM asset_rmm_links arl
     JOIN assets a ON a.asset_id = arl.asset_id
     WHERE arl.id=$link_id"
));

if (!$link) {
    echo json_encode(['success' => false, 'error' => 'RMM link not found']);
    exit;
}

$asset_id  = intval($link['asset_id']);
$client_id = intval($link['asset_client_id']);

// Enforce client-level access
$_GET['client_id'] = $client_id;
enforceClientAccess($client_id);

try {
    $client = new TacticalRmmClient(intval($link['integration_id']));

    if ($connection_type === 'mesh' && !empty($link['mesh_node_id'])) {
        $url = $client->buildMeshUrl($link['mesh_node_id']);
        $type = 'meshcentral';
    } else {
        $url  = $client->buildDeviceUrl($link['tactical_agent_id']);
        $type = 'tactical';
    }

    // Log the session (URL stored server-side only for audit, not returned to browser as-is)
    $ip       = mysqli_real_escape_string($mysqli, getIP());
    $ua       = mysqli_real_escape_string($mysqli, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300));
    $url_log  = mysqli_real_escape_string($mysqli, $url);
    mysqli_query($mysqli,
        "INSERT INTO rmm_remote_sessions SET
         asset_id=$asset_id,
         client_id=$client_id,
         user_id=$session_user_id,
         connection_type='$type',
         connection_url='$url_log',
         source_ip='$ip',
         user_agent='$ua'"
    );

    logAction('RMM', 'Remote Connect',
        "$session_name initiated $type remote session on asset ID $asset_id",
        $client_id, $asset_id
    );

    echo json_encode(['success' => true, 'url' => $url]);
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
