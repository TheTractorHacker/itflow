<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_notification_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_enable_cron                          = intval($_POST['config_enable_cron'] ?? 0);
    $config_enable_alert_domain_expire           = intval($_POST['config_enable_alert_domain_expire'] ?? 0);
    $config_send_invoice_reminders               = intval($_POST['config_send_invoice_reminders'] ?? 0);
    $config_invoice_overdue_reminders            = intval($_POST['config_invoice_overdue_reminders'] ?? 0);
    $config_recurring_auto_send_invoice          = intval($_POST['config_recurring_auto_send_invoice'] ?? 0);
    $config_ticket_client_general_notifications  = intval($_POST['config_ticket_client_general_notifications'] ?? 0);
    $config_ticket_new_ticket_notification_email = sanitizeInput(filter_var($_POST['config_ticket_new_ticket_notification_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '');
    $config_invoice_paid_notification_email      = sanitizeInput(filter_var($_POST['config_invoice_paid_notification_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '');
    $config_quote_notification_email             = sanitizeInput(filter_var($_POST['config_quote_notification_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '');

    mysqli_query($mysqli, "UPDATE settings SET
        config_enable_cron = $config_enable_cron,
        config_enable_alert_domain_expire = $config_enable_alert_domain_expire,
        config_send_invoice_reminders = $config_send_invoice_reminders,
        config_invoice_overdue_reminders = $config_invoice_overdue_reminders,
        config_recurring_auto_send_invoice = $config_recurring_auto_send_invoice,
        config_ticket_client_general_notifications = $config_ticket_client_general_notifications,
        config_ticket_new_ticket_notification_email = '$config_ticket_new_ticket_notification_email',
        config_invoice_paid_notification_email = '$config_invoice_paid_notification_email',
        config_quote_notification_email = '$config_quote_notification_email'
        WHERE company_id = 1");

    logAction("Settings", "Edit", "$session_name edited notification settings");
    flash_alert("Notification Settings updated");
    redirect();

}

if (isset($_POST['save_firebase_config'])) {

    validateCSRFToken($_POST['csrf_token']);

    $json_raw = trim($_POST['firebase_service_account_json'] ?? '');

    if (empty($json_raw)) {
        flash_alert("Service account JSON cannot be empty.", "error");
        redirect();
    }

    $decoded = json_decode($json_raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        flash_alert("Invalid JSON: " . json_last_error_msg(), "error");
        redirect();
    }

    foreach (['type', 'project_id', 'private_key', 'client_email'] as $field) {
        if (empty($decoded[$field])) {
            flash_alert("Missing required Firebase field: <code>$field</code>", "error");
            redirect();
        }
    }

    if ($decoded['type'] !== 'service_account') {
        flash_alert("JSON must be a Firebase service account (\"type\" must be \"service_account\").", "error");
        redirect();
    }

    $config_path = __DIR__ . '/../../config/firebase_service_account.json';
    if (file_put_contents($config_path, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
        flash_alert("Could not write configuration file — check server file permissions on config/.", "error");
        redirect();
    }

    logAction("Settings", "Edit", "$session_name configured Firebase push notifications");
    flash_alert("Firebase service account saved. Push notifications are now active.");
    redirect();

}

if (isset($_POST['remove_firebase_config'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_path = __DIR__ . '/../../config/firebase_service_account.json';
    if (file_exists($config_path)) {
        unlink($config_path);
    }

    logAction("Settings", "Edit", "$session_name removed Firebase push notification configuration");
    flash_alert("Firebase configuration removed.");
    redirect();

}
