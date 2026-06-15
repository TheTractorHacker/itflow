<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// Save module on/off toggle
if (isset($_POST['save_unifi_module_settings'])) {
    validateCSRFToken($_POST['csrf_token']);
    $enabled = isset($_POST['config_module_enable_unifi']) ? 1 : 0;
    mysqli_query($mysqli, "UPDATE settings SET config_module_enable_unifi=$enabled WHERE company_id=1");
    logAction('Settings', 'Edit', "$session_name " . ($enabled ? 'enabled' : 'disabled') . " UniFi module");
    flash_alert($enabled ? 'UniFi module enabled' : 'UniFi module disabled');
    redirect();
}

// Save/create integration
if (isset($_POST['save_unifi_integration'])) {
    validateCSRFToken($_POST['csrf_token']);
    $integration_id = intval($_POST['integration_id'] ?? 0);
    $name       = mysqli_real_escape_string($mysqli, sanitizeInput($_POST['integration_name']));
    $host       = mysqli_real_escape_string($mysqli, sanitizeInput($_POST['integration_host']));
    $port       = max(1, min(65535, intval($_POST['integration_port'] ?? 443)));
    $verify_ssl = isset($_POST['integration_verify_ssl']) ? 1 : 0;
    $enabled    = isset($_POST['integration_enabled']) ? 1 : 0;
    $api_key    = trim($_POST['integration_api_key'] ?? '');

    if (empty($name) || empty($host)) {
        flash_alert('Name and host are required', 'warning');
        redirect();
    }

    if ($integration_id > 0) {
        $set = "name='$name', host='$host', port=$port, verify_ssl=$verify_ssl, enabled=$enabled";
        if (!empty($api_key)) {
            $enc = mysqli_real_escape_string($mysqli, encryptSetting($api_key));
            $set .= ", api_key_enc='$enc'";
        }
        mysqli_query($mysqli, "UPDATE unifi_integrations SET $set WHERE id=$integration_id");
        logAction('UniFi Settings', 'Edit', "$session_name updated UniFi controller $name");
        flash_alert('Controller updated');
    } else {
        if (empty($api_key)) {
            flash_alert('API key is required for new controllers', 'warning');
            redirect();
        }
        $enc = mysqli_real_escape_string($mysqli, encryptSetting($api_key));
        mysqli_query($mysqli, "INSERT INTO unifi_integrations SET name='$name', host='$host', port=$port, verify_ssl=$verify_ssl, api_key_enc='$enc', enabled=$enabled, created_by=$session_user_id");
        $new_id = intval(mysqli_insert_id($mysqli));
        if (!$config_unifi_default_integration_id) {
            mysqli_query($mysqli, "UPDATE settings SET config_unifi_default_integration_id=$new_id WHERE company_id=1");
        }
        logAction('UniFi Settings', 'Create', "$session_name added UniFi controller $name");
        flash_alert('Controller added');
    }
    redirect();
}

// Delete integration
if (isset($_POST['delete_unifi_integration'])) {
    validateCSRFToken($_POST['csrf_token']);
    $integration_id = intval($_POST['integration_id']);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT name FROM unifi_integrations WHERE id=$integration_id"));
    if ($row) {
        mysqli_query($mysqli, "DELETE FROM unifi_integrations WHERE id=$integration_id");
        $log_name = mysqli_real_escape_string($mysqli, $row['name']);
        logAction('UniFi Settings', 'Delete', "$session_name deleted UniFi controller $log_name");
        flash_alert('Controller deleted');
    }
    redirect();
}

// AJAX test connection
if (isset($_POST['test_unifi_connection'])) {
    validateCSRFToken($_POST['csrf_token']);
    header('Content-Type: application/json');
    $integration_id = intval($_POST['integration_id'] ?? 0);
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/class_unifi.php';
        $client = new UnifiClient($integration_id);
        $ok     = $client->testConnection();
        echo json_encode(['success' => $ok]);
    } catch (RuntimeException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX: load/refresh sites from the controller and return current mappings
if (isset($_POST['load_unifi_sites'])) {
    validateCSRFToken($_POST['csrf_token']);
    header('Content-Type: application/json');
    $integration_id = intval($_POST['integration_id'] ?? 0);
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/class_unifi.php';
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/class_unifi_sync_mapper.php';

        $client = new UnifiClient($integration_id);
        $mapper = new UnifiSyncMapper($mysqli, $integration_id, $session_user_id);
        $sites  = $mapper->syncSiteMappings($client->getSites());

        echo json_encode(['success' => true, 'sites' => $sites]);
    } catch (RuntimeException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX: save site -> client mapping overrides
if (isset($_POST['save_unifi_site_mapping'])) {
    validateCSRFToken($_POST['csrf_token']);
    header('Content-Type: application/json');
    $integration_id = intval($_POST['integration_id'] ?? 0);
    $mappings = $_POST['mapping'] ?? [];

    if (!is_array($mappings)) {
        echo json_encode(['success' => false, 'error' => 'Invalid mapping data']);
        exit;
    }

    foreach ($mappings as $site_id => $value) {
        $site_id_esc = mysqli_real_escape_string($mysqli, sanitizeInput((string) $site_id));

        if ($value === 'auto') {
            $client_sql = 'NULL';
        } elseif ($value === 'skip') {
            $client_sql = '0';
        } else {
            $client_sql = (string) intval($value);
        }

        mysqli_query($mysqli,
            "UPDATE unifi_site_mappings SET client_id=$client_sql WHERE integration_id=$integration_id AND unifi_site_id='$site_id_esc'"
        );
    }

    logAction('UniFi Settings', 'Edit', "$session_name updated UniFi site mappings for integration $integration_id");

    echo json_encode(['success' => true]);
    exit;
}

// AJAX manual sync
if (isset($_POST['sync_unifi_now'])) {
    validateCSRFToken($_POST['csrf_token']);
    header('Content-Type: application/json');
    $integration_id = intval($_POST['integration_id'] ?? 0);

    // Rate-limit: one sync per integration per 60 seconds
    $recent = mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT id FROM unifi_sync_log WHERE integration_id=$integration_id
         AND started_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) AND status='running' LIMIT 1"
    ));
    if ($recent) {
        echo json_encode(['success' => false, 'error' => 'A sync is already running. Please wait 60 seconds.']);
        exit;
    }

    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/class_unifi.php';
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/class_unifi_sync_mapper.php';

        $client = new UnifiClient($integration_id);
        $mapper = new UnifiSyncMapper($mysqli, $integration_id, $session_user_id);
        $log_id = $mapper->startSyncLog();
        $stats  = $mapper->sync($client);
        $mapper->finishSyncLog($log_id, $stats);

        logAction('UniFi', 'Import',
            "$session_name synced UniFi: {$stats['devices_created']} devices created, {$stats['devices_updated']} updated, " .
            "{$stats['wifi_created']} Wi-Fi creds created, {$stats['networks_created']} networks created"
        );

        echo json_encode(['success' => true] + $stats);
    } catch (RuntimeException $e) {
        if (isset($log_id)) {
            mysqli_query($mysqli, "UPDATE unifi_sync_log SET finished_at=NOW(), status='failed', errors='" .
                mysqli_real_escape_string($mysqli, $e->getMessage()) . "' WHERE id=$log_id");
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
