<?php
require_once "../includes/inc_all_admin.php";

// Add rule
if (isset($_POST['add_rule'])) {
    validateCSRFToken($_POST['csrf_token']);
    enforceUserPermission('user_type', 1);

    $name   = mysqli_real_escape_string($mysqli, trim($_POST['rule_name'] ?? ''));
    $field  = mysqli_real_escape_string($mysqli, $_POST['rule_cond_field'] ?? 'age_hours');
    $op     = mysqli_real_escape_string($mysqli, $_POST['rule_cond_op'] ?? 'greater_than');
    $val    = mysqli_real_escape_string($mysqli, trim($_POST['rule_cond_value'] ?? ''));
    $action = mysqli_real_escape_string($mysqli, $_POST['rule_action'] ?? '');
    $actval = mysqli_real_escape_string($mysqli, trim($_POST['rule_action_value'] ?? ''));
    $order  = intval($_POST['rule_order'] ?? 0);

    if ($name && $action) {
        mysqli_query($mysqli,
            "INSERT INTO ticket_automation_rules
             (rule_name, rule_cond_field, rule_cond_op, rule_cond_value, rule_action, rule_action_value, rule_order)
             VALUES ('$name', '$field', '$op', '$val', '$action', '$actval', $order)"
        );
        logAction("Automation", "Create", "Created ticket automation rule: $name");
        flash_alert("Automation rule <strong>$name</strong> created.");
    }
    redirect("/admin/ticket_automation.php");
}

// Toggle enable/disable
if (isset($_GET['toggle'])) {
    validateCSRFToken($_GET['csrf_token']);
    enforceUserPermission('user_type', 1);
    $id = intval($_GET['toggle']);
    mysqli_query($mysqli,
        "UPDATE ticket_automation_rules SET rule_enabled = NOT rule_enabled WHERE rule_id = $id"
    );
    redirect("/admin/ticket_automation.php");
}

// Delete
if (isset($_GET['delete'])) {
    validateCSRFToken($_GET['csrf_token']);
    enforceUserPermission('user_type', 1);
    $id = intval($_GET['delete']);
    mysqli_query($mysqli, "DELETE FROM ticket_automation_rules WHERE rule_id = $id");
    logAction("Automation", "Delete", "Deleted ticket automation rule #$id");
    flash_alert("Rule deleted.");
    redirect("/admin/ticket_automation.php");
}

redirect("/admin/ticket_automation.php");
