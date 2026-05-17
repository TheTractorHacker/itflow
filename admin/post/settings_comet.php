<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['save_comet_settings'])) {
    validateCSRFToken($_POST['csrf_token']);
    $enabled    = isset($_POST['config_comet_enabled']) ? 1 : 0;
    $url        = sanitizeInput($_POST['config_comet_server_url']);
    $user       = sanitizeInput($_POST['config_comet_admin_user']);
    $auto_ticket= isset($_POST['config_comet_auto_ticket']) ? 1 : 0;

    if (!empty(trim($_POST['config_comet_admin_pass']))) {
        $pass = sanitizeInput($_POST['config_comet_admin_pass']);
        mysqli_query($mysqli, "UPDATE settings SET config_comet_enabled=$enabled, config_comet_server_url='$url', config_comet_admin_user='$user', config_comet_admin_pass='$pass', config_comet_auto_ticket=$auto_ticket WHERE company_id=1");
    } else {
        mysqli_query($mysqli, "UPDATE settings SET config_comet_enabled=$enabled, config_comet_server_url='$url', config_comet_admin_user='$user', config_comet_auto_ticket=$auto_ticket WHERE company_id=1");
    }
    logAction('Settings', 'Edit', "$session_name updated Comet Backup settings");
    flash_alert('Comet settings saved');
    redirect();
}

if (isset($_POST['save_comet_maps'])) {
    validateCSRFToken($_POST['csrf_token']);
    // Delete all existing maps then re-insert
    mysqli_query($mysqli, "DELETE FROM comet_client_map");
    foreach (($_POST['comet_map'] ?? []) as $client_id => $comet_username) {
        $cid  = intval($client_id);
        $uname = sanitizeInput($comet_username);
        if ($cid > 0 && !empty($uname)) {
            mysqli_query($mysqli, "INSERT INTO comet_client_map SET map_client_id=$cid, map_comet_username='$uname' ON DUPLICATE KEY UPDATE map_comet_username='$uname'");
        }
    }
    logAction('Settings', 'Edit', "$session_name updated Comet client mappings");
    flash_alert('Client mappings saved');
    redirect();
}
