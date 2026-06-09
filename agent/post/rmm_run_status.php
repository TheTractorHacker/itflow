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

enforceUserPermission('module_rmm_scripts');

$run_id = intval($_POST['run_id'] ?? 0);
if (!$run_id) {
    echo json_encode(['success' => false, 'error' => 'Missing run_id']);
    exit;
}

$run = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT sr.*, a.asset_client_id FROM rmm_script_runs sr
     JOIN assets a ON a.asset_id = sr.asset_id
     WHERE sr.id=$run_id"
));
if (!$run) {
    echo json_encode(['success' => false, 'error' => 'Run not found']);
    exit;
}
if ($run['asset_client_id']) { enforceClientAccess(intval($run['asset_client_id'])); }

echo json_encode([
    'success' => true,
    'status'  => $run['status'],
    'output'  => $run['output'],
    'error'   => $run['error_message'],
]);
