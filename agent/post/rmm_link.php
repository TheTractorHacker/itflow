<?php
/*
 * RMM manual asset link/unlink handler
 */

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

enforceUserPermission('module_rmm');

$action = sanitizeInput($_POST['action'] ?? '');

// Unlink an asset from its RMM agent
if ($action === 'unlink') {
    $link_id = intval($_POST['link_id'] ?? 0);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT arl.*, a.asset_name FROM asset_rmm_links arl JOIN assets a ON a.asset_id = arl.asset_id WHERE arl.id=$link_id"));
    if ($row) {
        mysqli_query($mysqli, "DELETE FROM asset_rmm_links WHERE id=$link_id");
        logAction('RMM', 'Asset Unlinked', "$session_name unlinked RMM agent {$row['hostname']} from asset {$row['asset_name']}");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Link not found']);
    }
    exit;
}

// Manually link an existing ITFlow asset to a Tactical agent ID
if ($action === 'link') {
    $asset_id        = intval($_POST['asset_id'] ?? 0);
    $integration_id  = intval($_POST['integration_id'] ?? $config_rmm_default_integration_id);
    $tactical_agent_id = sanitizeInput($_POST['tactical_agent_id'] ?? '');

    if (!$asset_id || !$integration_id || empty($tactical_agent_id)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    // Remove any existing link for this asset+integration
    mysqli_query($mysqli, "DELETE FROM asset_rmm_links WHERE asset_id=$asset_id AND integration_id=$integration_id");

    $hostname = sanitizeInput($_POST['hostname'] ?? '');
    mysqli_query($mysqli, "INSERT INTO asset_rmm_links SET
        asset_id=$asset_id,
        integration_id=$integration_id,
        tactical_agent_id='$tactical_agent_id',
        hostname='$hostname',
        last_sync=NOW()
    ");

    $asset_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_name FROM assets WHERE asset_id=$asset_id"));
    logAction('RMM', 'Asset Linked',
        "$session_name manually linked asset {$asset_row['asset_name']} to Tactical agent $tactical_agent_id",
        0, $asset_id);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
