<?php

if (isset($_POST['add_contract'])) {
    validateCSRFToken($_POST['csrf_token']);
    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['contract_client_id']);
    $name = sanitizeInput($_POST['contract_name']);
    $type = sanitizeInput($_POST['contract_type']);
    $status = sanitizeInput($_POST['contract_status']);
    $value = is_numeric($_POST['contract_value'] ?? '') ? floatval($_POST['contract_value']) : 'NULL';
    $freq = sanitizeInput($_POST['contract_renewal_frequency'] ?? '');
    $start = sanitizeInput($_POST['contract_start_date']);
    $end = sanitizeInput($_POST['contract_end_date']);
    $renewal = sanitizeInput($_POST['contract_renewal_date']);
    $notes = sanitizeInput($_POST['contract_details']);
    $sla_lr = is_numeric($_POST['sla_low_response'] ?? '') ? intval($_POST['sla_low_response']) : 'NULL';
    $sla_lres = is_numeric($_POST['sla_low_resolution'] ?? '') ? intval($_POST['sla_low_resolution']) : 'NULL';
    $sla_mr = is_numeric($_POST['sla_medium_response'] ?? '') ? intval($_POST['sla_medium_response']) : 'NULL';
    $sla_mres = is_numeric($_POST['sla_medium_resolution'] ?? '') ? intval($_POST['sla_medium_resolution']) : 'NULL';
    $sla_hr = is_numeric($_POST['sla_high_response'] ?? '') ? intval($_POST['sla_high_response']) : 'NULL';
    $sla_hres = is_numeric($_POST['sla_high_resolution'] ?? '') ? intval($_POST['sla_high_resolution']) : 'NULL';

    $start_sql = $start ? "'$start'" : 'NULL';
    $end_sql = $end ? "'$end'" : 'NULL';
    $renewal_sql = $renewal ? "'$renewal'" : 'NULL';
    $value_sql = is_numeric($value) ? $value : 'NULL';
    $freq_sql = $freq ? "'$freq'" : 'NULL';

    mysqli_query($mysqli, "INSERT INTO contracts SET contract_client_id = $client_id, contract_name = '$name', contract_type = '$type', contract_status = '$status', contract_value = $value_sql, contract_renewal_frequency = $freq_sql, contract_start_date = $start_sql, contract_end_date = $end_sql, contract_renewal_date = $renewal_sql, contract_details = '$notes', contract_sla_low_response_time = $sla_lr, contract_sla_low_resolution_time = $sla_lres, contract_sla_medium_response_time = $sla_mr, contract_sla_medium_resolution_time = $sla_mres, contract_sla_high_response_time = $sla_hr, contract_sla_high_resolution_time = $sla_hres, contract_created_by = $session_user_id");

    logAction("Contract", "Add", "Added contract $name", $client_id);
    flash_alert("Contract <strong>$name</strong> created.");
    redirect();
}

if (isset($_POST['edit_contract'])) {
    validateCSRFToken($_POST['csrf_token']);
    enforceUserPermission('module_support', 2);

    $contract_id = intval($_POST['contract_id']);
    $client_id = intval($_POST['contract_client_id']);
    $name = sanitizeInput($_POST['contract_name']);
    $type = sanitizeInput($_POST['contract_type']);
    $status = sanitizeInput($_POST['contract_status']);
    $freq = sanitizeInput($_POST['contract_renewal_frequency'] ?? '');
    $start = sanitizeInput($_POST['contract_start_date']);
    $end = sanitizeInput($_POST['contract_end_date']);
    $renewal = sanitizeInput($_POST['contract_renewal_date']);
    $notes = sanitizeInput($_POST['contract_details']);
    $sla_lr = is_numeric($_POST['sla_low_response'] ?? '') ? intval($_POST['sla_low_response']) : 'NULL';
    $sla_lres = is_numeric($_POST['sla_low_resolution'] ?? '') ? intval($_POST['sla_low_resolution']) : 'NULL';
    $sla_mr = is_numeric($_POST['sla_medium_response'] ?? '') ? intval($_POST['sla_medium_response']) : 'NULL';
    $sla_mres = is_numeric($_POST['sla_medium_resolution'] ?? '') ? intval($_POST['sla_medium_resolution']) : 'NULL';
    $sla_hr = is_numeric($_POST['sla_high_response'] ?? '') ? intval($_POST['sla_high_response']) : 'NULL';
    $sla_hres = is_numeric($_POST['sla_high_resolution'] ?? '') ? intval($_POST['sla_high_resolution']) : 'NULL';

    $start_sql = $start ? "'$start'" : 'NULL';
    $end_sql = $end ? "'$end'" : 'NULL';
    $renewal_sql = $renewal ? "'$renewal'" : 'NULL';
    $value_sql = is_numeric($_POST['contract_value'] ?? '') ? floatval($_POST['contract_value']) : 'NULL';
    $freq_sql = $freq ? "'$freq'" : 'NULL';

    mysqli_query($mysqli, "UPDATE contracts SET contract_name = '$name', contract_type = '$type', contract_status = '$status', contract_value = $value_sql, contract_renewal_frequency = $freq_sql, contract_start_date = $start_sql, contract_end_date = $end_sql, contract_renewal_date = $renewal_sql, contract_details = '$notes', contract_sla_low_response_time = $sla_lr, contract_sla_low_resolution_time = $sla_lres, contract_sla_medium_response_time = $sla_mr, contract_sla_medium_resolution_time = $sla_mres, contract_sla_high_response_time = $sla_hr, contract_sla_high_resolution_time = $sla_hres WHERE contract_id = $contract_id");

    logAction("Contract", "Edit", "Updated contract $name", $client_id);
    flash_alert("Contract updated.");
    redirect();
}

if (isset($_GET['delete_contract'])) {
    validateCSRFToken($_GET['csrf_token']);
    enforceUserPermission('module_support', 2);

    $contract_id = intval($_GET['delete_contract']);
    $client_id = intval($_GET['client_id'] ?? 0);

    mysqli_query($mysqli, "UPDATE contracts SET contract_archived_at = NOW() WHERE contract_id = $contract_id");
    logAction("Contract", "Delete", "Deleted contract #$contract_id", $client_id);
    flash_alert("Contract deleted.");
    redirect();
}
