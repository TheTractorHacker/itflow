<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_ai_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    // Company-wide AI enable flag
    $config_ai_enable = (intval($_POST['config_ai_enable']) === 1) ? 1 : 0;

    // Input character cap (floor to a sane minimum)
    $config_ai_max_input_chars = intval($_POST['config_ai_max_input_chars']);
    if ($config_ai_max_input_chars < 1000) {
        $config_ai_max_input_chars = 1000;
    }

    // Request timeout in seconds (clamped to a sane range)
    $config_ai_timeout_seconds = intval($_POST['config_ai_timeout_seconds']);
    if ($config_ai_timeout_seconds < 5) {
        $config_ai_timeout_seconds = 5;
    } elseif ($config_ai_timeout_seconds > 300) {
        $config_ai_timeout_seconds = 300;
    }

    mysqli_query($mysqli, "UPDATE settings SET config_ai_enable = $config_ai_enable, config_ai_max_input_chars = $config_ai_max_input_chars, config_ai_timeout_seconds = $config_ai_timeout_seconds WHERE company_id = 1");

    logAction("Settings", "Edit", "$session_name edited AI settings");

    flash_alert("AI Settings updated");

    redirect();

}
