<?php

if (isset($_POST['save_outlook_cal_settings'])) {
    validateCSRFToken($_POST['csrf_token']);
    enforceUserPermission('admin', 3);

    $tenant_id     = sanitizeInput($_POST['outlook_cal_tenant_id']);
    $client_id     = sanitizeInput($_POST['outlook_cal_client_id']);
    $client_secret = sanitizeInput($_POST['outlook_cal_client_secret']);

    if ($client_secret) {
        mysqli_query($mysqli, "UPDATE settings SET
            config_outlook_cal_tenant_id     = '$tenant_id',
            config_outlook_cal_client_id     = '$client_id',
            config_outlook_cal_client_secret = '$client_secret'
            WHERE company_id = 1");
    } else {
        // Don't overwrite existing secret if field left blank
        mysqli_query($mysqli, "UPDATE settings SET
            config_outlook_cal_tenant_id = '$tenant_id',
            config_outlook_cal_client_id = '$client_id'
            WHERE company_id = 1");
    }

    logAction("Settings", "Edit", "Updated Outlook Calendar Sync credentials");
    flash_alert("Outlook Calendar Sync credentials saved.");
    redirect();
}

if (isset($_GET['clear_outlook_cal_settings'])) {
    validateCSRFToken($_GET['csrf_token']);
    enforceUserPermission('admin', 3);

    mysqli_query($mysqli, "UPDATE settings SET
        config_outlook_cal_tenant_id     = NULL,
        config_outlook_cal_client_id     = NULL,
        config_outlook_cal_client_secret = NULL
        WHERE company_id = 1");

    logAction("Settings", "Edit", "Cleared Outlook Calendar Sync credentials");
    flash_alert("Outlook Calendar Sync credentials cleared.");
    redirect();
}
