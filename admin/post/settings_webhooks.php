<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$ALL_EVENTS = ['ticket.created','ticket.replied','ticket.assigned','ticket.status_changed','ticket.resolved'];

if (isset($_POST['add_webhook'])) {

    validateCSRFToken($_POST['csrf_token']);

    $webhook_name    = sanitizeInput($_POST['webhook_name']);
    $webhook_url     = filter_var(trim($_POST['webhook_url']), FILTER_SANITIZE_URL);
    $webhook_secret  = sanitizeInput($_POST['webhook_secret'] ?? '');
    $webhook_enabled = isset($_POST['webhook_enabled']) ? 1 : 0;
    $raw_events      = $_POST['webhook_events'] ?? [];
    $valid_events    = array_intersect($raw_events, $ALL_EVENTS);
    $webhook_events  = sanitizeInput(implode(',', $valid_events));

    if (empty($webhook_name) || empty($webhook_url) || empty($valid_events)) {
        flash_alert("Name, URL, and at least one event are required.", 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO webhooks SET webhook_name = '$webhook_name', webhook_url = '$webhook_url', webhook_secret = '$webhook_secret', webhook_events = '$webhook_events', webhook_enabled = $webhook_enabled");

    logAction("Settings", "Webhook", "$session_name added webhook $webhook_name");

    flash_alert("Webhook <strong>$webhook_name</strong> added");
    redirect();
}

if (isset($_POST['edit_webhook'])) {

    validateCSRFToken($_POST['csrf_token']);

    $webhook_id      = intval($_POST['webhook_id']);
    $webhook_name    = sanitizeInput($_POST['webhook_name']);
    $webhook_url     = filter_var(trim($_POST['webhook_url']), FILTER_SANITIZE_URL);
    $webhook_enabled = isset($_POST['webhook_enabled']) ? 1 : 0;
    $raw_events      = $_POST['webhook_events'] ?? [];
    $valid_events    = array_intersect($raw_events, $ALL_EVENTS);
    $webhook_events  = sanitizeInput(implode(',', $valid_events));

    if (empty($webhook_name) || empty($webhook_url) || empty($valid_events)) {
        flash_alert("Name, URL, and at least one event are required.", 'error');
        redirect();
    }

    // Rotate secret only if a new one was provided
    $raw_secret = trim($_POST['webhook_secret'] ?? '');
    if (!empty($raw_secret)) {
        $webhook_secret = sanitizeInput($raw_secret);
        mysqli_query($mysqli, "UPDATE webhooks SET webhook_name = '$webhook_name', webhook_url = '$webhook_url', webhook_secret = '$webhook_secret', webhook_events = '$webhook_events', webhook_enabled = $webhook_enabled WHERE webhook_id = $webhook_id");
    } else {
        mysqli_query($mysqli, "UPDATE webhooks SET webhook_name = '$webhook_name', webhook_url = '$webhook_url', webhook_events = '$webhook_events', webhook_enabled = $webhook_enabled WHERE webhook_id = $webhook_id");
    }

    logAction("Settings", "Webhook", "$session_name edited webhook $webhook_name");

    flash_alert("Webhook <strong>$webhook_name</strong> updated");
    redirect();
}

if (isset($_GET['delete_webhook'])) {

    validateCSRFToken($_GET['csrf_token']);

    $webhook_id   = intval($_GET['delete_webhook']);
    $webhook_name = sanitizeInput(getFieldById('webhooks', $webhook_id, 'webhook_name'));

    mysqli_query($mysqli, "DELETE FROM webhook_queue WHERE queue_webhook_id = $webhook_id");
    mysqli_query($mysqli, "DELETE FROM webhooks WHERE webhook_id = $webhook_id");

    logAction("Settings", "Webhook", "$session_name deleted webhook $webhook_name");

    flash_alert("Webhook <strong>$webhook_name</strong> deleted", 'error');
    redirect();
}
