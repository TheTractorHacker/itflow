<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// Save module on/off toggle
if (isset($_POST['save_rmm_module_settings'])) {
    validateCSRFToken($_POST['csrf_token']);
    $enabled = isset($_POST['config_module_enable_rmm']) ? 1 : 0;
    mysqli_query($mysqli, "UPDATE settings SET config_module_enable_rmm=$enabled WHERE company_id=1");
    logAction('Settings', 'Edit', "$session_name " . ($enabled ? 'enabled' : 'disabled') . " RMM module");
    flash_alert($enabled ? 'RMM module enabled' : 'RMM module disabled');
    redirect();
}

// Save/create integration
if (isset($_POST['save_rmm_integration'])) {
    validateCSRFToken($_POST['csrf_token']);
    $integration_id = intval($_POST['integration_id'] ?? 0);
    $name    = mysqli_real_escape_string($mysqli, sanitizeInput($_POST['integration_name']));
    $api_url = mysqli_real_escape_string($mysqli, rtrim(sanitizeInput($_POST['integration_api_url']), '/'));
    $api_key = trim($_POST['integration_api_key'] ?? '');
    $enabled = isset($_POST['integration_enabled']) ? 1 : 0;

    // Validate URL is HTTPS
    $raw_url = rtrim(sanitizeInput($_POST['integration_api_url']), '/');
    if (!filter_var($raw_url, FILTER_VALIDATE_URL) || strtolower(parse_url($raw_url, PHP_URL_SCHEME)) !== 'https') {
        flash_alert('API URL must be a valid HTTPS URL', 'warning');
        redirect();
    }

    if ($integration_id > 0) {
        $set = "name='$name', api_url='$api_url', enabled=$enabled";
        if (!empty($api_key)) {
            $enc = mysqli_real_escape_string($mysqli, encryptSetting($api_key));
            $set .= ", api_key_enc='$enc'";
        }
        mysqli_query($mysqli, "UPDATE rmm_integrations SET $set WHERE id=$integration_id");
        logAction('RMM Settings', 'Edit', "$session_name updated RMM integration $name");
        flash_alert('Integration updated');
    } else {
        if (empty($api_key)) {
            flash_alert('API key is required for new integrations', 'warning');
            redirect();
        }
        $enc = mysqli_real_escape_string($mysqli, encryptSetting($api_key));
        mysqli_query($mysqli, "INSERT INTO rmm_integrations SET name='$name', api_url='$api_url', api_key_enc='$enc', enabled=$enabled, created_by=$session_user_id");
        $new_id = intval(mysqli_insert_id($mysqli));
        if (!$config_rmm_default_integration_id) {
            mysqli_query($mysqli, "UPDATE settings SET config_rmm_default_integration_id=$new_id WHERE company_id=1");
        }
        logAction('RMM Settings', 'Create', "$session_name added RMM integration $name");
        flash_alert('Integration added');
    }
    redirect();
}

// Delete integration
if (isset($_POST['delete_rmm_integration'])) {
    validateCSRFToken($_POST['csrf_token']);
    $integration_id = intval($_POST['integration_id']);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT name FROM rmm_integrations WHERE id=$integration_id"));
    if ($row) {
        mysqli_query($mysqli, "DELETE FROM rmm_integrations WHERE id=$integration_id");
        $log_name = mysqli_real_escape_string($mysqli, $row['name']);
        logAction('RMM Settings', 'Delete', "$session_name deleted RMM integration $log_name");
        flash_alert('Integration deleted');
    }
    redirect();
}
