<?php

if (isset($_GET['create_outtake'])) {
    validateCSRFToken($_GET['csrf_token']);
    enforceUserPermission('module_support', 2);

    $ticket_id = intval($_GET['create_outtake']);
    $client_id = intval($_GET['client_id'] ?? 0);
    $sign_token = bin2hex(random_bytes(32));

    mysqli_query($mysqli, "INSERT INTO ticket_outtake_forms SET outtake_ticket_id = $ticket_id, outtake_sign_token = '$sign_token', outtake_created_by = $session_user_id");
    $outtake_id = mysqli_insert_id($mysqli);

    $sql_t = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
    $t = mysqli_fetch_assoc($sql_t);
    logAction("Outtake", "Create", "Created outtake form for ticket {$t['ticket_prefix']}{$t['ticket_number']}", $client_id, $ticket_id);

    flash_alert("Outtake form created. <a href='outtake_form.php?outtake_id=$outtake_id&ticket_id=$ticket_id" . ($client_id ? "&client_id=$client_id" : '') . "'>Open form</a>");
    redirect();
}

if (isset($_POST['save_outtake_notes'])) {
    validateCSRFToken($_POST['csrf_token']);
    enforceUserPermission('module_support', 2);

    $outtake_id = intval($_POST['outtake_id']);
    $ticket_id  = intval($_POST['ticket_id']);
    $client_id  = intval($_POST['client_id'] ?? 0);
    $notes      = sanitizeInput($_POST['outtake_tech_notes']);

    mysqli_query($mysqli, "UPDATE ticket_outtake_forms SET outtake_tech_notes = '$notes' WHERE outtake_id = $outtake_id");
    flash_alert("Outtake form notes saved.");
    redirect();
}

if (isset($_GET['delete_outtake'])) {
    validateCSRFToken($_GET['csrf_token']);
    enforceUserPermission('module_support', 2);

    $outtake_id = intval($_GET['delete_outtake']);
    $ticket_id  = intval($_GET['ticket_id']);
    $client_id  = intval($_GET['client_id'] ?? 0);

    mysqli_query($mysqli, "DELETE FROM ticket_outtake_forms WHERE outtake_id = $outtake_id");
    logAction("Outtake", "Delete", "Deleted outtake form #$outtake_id", $client_id, $ticket_id);
    flash_alert("Outtake form deleted.");
    redirect();
}
