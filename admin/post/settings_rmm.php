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

// Save auto-ticket severity settings
if (isset($_POST['save_rmm_auto_ticket_settings'])) {
    validateCSRFToken($_POST['csrf_token']);
    $allowed_severities = ['critical', 'error', 'warning', 'info'];
    $selected = array_intersect($_POST['auto_ticket_severities'] ?? [], $allowed_severities);
    $severities = mysqli_real_escape_string($mysqli, implode(',', $selected));
    mysqli_query($mysqli, "UPDATE settings SET config_rmm_auto_ticket_severities='$severities' WHERE company_id=1");
    logAction('Settings', 'Edit', "$session_name updated RMM auto-ticket severities to: " . ($severities ?: 'none'));
    flash_alert('Auto-ticket settings saved');
    redirect();
}

// Save/create integration
if (isset($_POST['save_rmm_integration'])) {
    validateCSRFToken($_POST['csrf_token']);
    $integration_id = intval($_POST['integration_id'] ?? 0);
    $name    = mysqli_real_escape_string($mysqli, sanitizeInput($_POST['integration_name']));
    $type    = in_array($_POST['integration_type'] ?? '', ['tactical_rmm','level','action1']) ? $_POST['integration_type'] : 'tactical_rmm';
    $type    = mysqli_real_escape_string($mysqli, $type);
    $api_url = mysqli_real_escape_string($mysqli, rtrim(sanitizeInput($_POST['integration_api_url']), '/'));
    $web_url = mysqli_real_escape_string($mysqli, rtrim(sanitizeInput($_POST['integration_web_url'] ?? ''), '/'));
    $api_key = trim($_POST['integration_api_key'] ?? '');
    $client_secret = trim($_POST['integration_client_secret'] ?? '');
    $enabled = isset($_POST['integration_enabled']) ? 1 : 0;

    // Validate URL is HTTPS
    $raw_url = rtrim(sanitizeInput($_POST['integration_api_url']), '/');
    if (!filter_var($raw_url, FILTER_VALIDATE_URL) || strtolower(parse_url($raw_url, PHP_URL_SCHEME)) !== 'https') {
        flash_alert('API URL must be a valid HTTPS URL', 'warning');
        redirect();
    }

    if ($integration_id > 0) {
        $set = "name='$name', type='$type', api_url='$api_url', web_url='$web_url', enabled=$enabled";
        if ($type === 'action1') {
            if (!empty($api_key) || !empty($client_secret)) {
                $existing = ['client_id' => '', 'client_secret' => ''];
                $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT api_key_enc FROM rmm_integrations WHERE id=$integration_id"));
                if ($row) {
                    $decoded = json_decode(decryptSetting($row['api_key_enc'] ?? ''), true);
                    if (is_array($decoded)) { $existing = array_merge($existing, $decoded); }
                }
                $creds = [
                    'client_id'     => $api_key !== '' ? $api_key : $existing['client_id'],
                    'client_secret' => $client_secret !== '' ? $client_secret : $existing['client_secret'],
                ];
                $enc = mysqli_real_escape_string($mysqli, encryptSetting(json_encode($creds)));
                $set .= ", api_key_enc='$enc'";
            }
        } elseif (!empty($api_key)) {
            $enc = mysqli_real_escape_string($mysqli, encryptSetting($api_key));
            $set .= ", api_key_enc='$enc'";
        }
        mysqli_query($mysqli, "UPDATE rmm_integrations SET $set WHERE id=$integration_id");
        logAction('RMM Settings', 'Edit', "$session_name updated RMM integration $name");
        flash_alert('Integration updated');
    } else {
        if ($type === 'action1') {
            if (empty($api_key) || empty($client_secret)) {
                flash_alert('Client ID and Client Secret are required for new Action1 integrations', 'warning');
                redirect();
            }
            $enc = mysqli_real_escape_string($mysqli, encryptSetting(json_encode([
                'client_id'     => $api_key,
                'client_secret' => $client_secret,
            ])));
        } else {
            if (empty($api_key)) {
                flash_alert('API key is required for new integrations', 'warning');
                redirect();
            }
            $enc = mysqli_real_escape_string($mysqli, encryptSetting($api_key));
        }
        mysqli_query($mysqli, "INSERT INTO rmm_integrations SET name='$name', type='$type', api_url='$api_url', web_url='$web_url', api_key_enc='$enc', enabled=$enabled, created_by=$session_user_id");
        $new_id = intval(mysqli_insert_id($mysqli));
        if (!$config_rmm_default_integration_id) {
            mysqli_query($mysqli, "UPDATE settings SET config_rmm_default_integration_id=$new_id WHERE company_id=1");
        }
        logAction('RMM Settings', 'Create', "$session_name added $type RMM integration $name");
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

// AJAX test connection
if (isset($_POST['test_rmm_connection'])) {
    validateCSRFToken($_POST['csrf_token']);
    header('Content-Type: application/json');
    $integration_id = intval($_POST['integration_id'] ?? 0);
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/rmm_client_factory.php';
        $client = getRmmClient($integration_id);
        $ok     = $client->testConnection();
        echo json_encode(['success' => $ok]);
    } catch (RuntimeException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
