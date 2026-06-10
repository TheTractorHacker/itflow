<?php
/*
 * Ticket automation engine helpers (Phase 6).
 *
 * Rules support either the legacy single condition/action columns
 * (rule_cond_field/op/value, rule_action/value) or the newer
 * rule_conditions_json / rule_actions_json columns, each holding a JSON
 * array of {field,op,value} / {action,value} objects. All conditions in
 * a rule are AND-ed together; actions run in order.
 */

function automationGetConditions(array $rule): array {
    if (!empty($rule['rule_conditions_json'])) {
        $decoded = json_decode($rule['rule_conditions_json'], true);
        if (is_array($decoded) && !empty($decoded)) {
            return $decoded;
        }
    }
    if (!empty($rule['rule_cond_field'])) {
        return [[
            'field' => $rule['rule_cond_field'],
            'op'    => $rule['rule_cond_op'],
            'value' => $rule['rule_cond_value'],
        ]];
    }
    return [];
}

function automationGetActions(array $rule): array {
    if (!empty($rule['rule_actions_json'])) {
        $decoded = json_decode($rule['rule_actions_json'], true);
        if (is_array($decoded) && !empty($decoded)) {
            return $decoded;
        }
    }
    if (!empty($rule['rule_action'])) {
        return [[
            'action' => $rule['rule_action'],
            'value'  => $rule['rule_action_value'],
        ]];
    }
    return [];
}

function automationConditionsMatch(array $conditions, array $context): bool {
    if (empty($conditions)) return false;

    foreach ($conditions as $cond) {
        $field = $cond['field'] ?? '';
        $op    = $cond['op'] ?? 'equals';
        $rval  = $cond['value'] ?? '';

        if (!array_key_exists($field, $context)) return false;
        $tval = $context[$field];

        if ($op === 'contains') {
            if (stripos((string) $tval, (string) $rval) === false) return false;
            continue;
        }

        $tval_cmp = is_numeric($tval) ? floatval($tval) : strtolower((string) $tval);
        $rval_cmp = is_numeric($rval) ? floatval($rval) : strtolower((string) $rval);

        switch ($op) {
            case 'greater_than': $match = ($tval_cmp > $rval_cmp);  break;
            case 'less_than':    $match = ($tval_cmp < $rval_cmp);  break;
            case 'not_equals':   $match = ($tval_cmp != $rval_cmp); break;
            case 'equals':
            default:             $match = ($tval_cmp == $rval_cmp); break;
        }

        if (!$match) return false;
    }

    return true;
}

function automationLogRun($mysqli, array $rule, string $trigger_type, array $context, string $summary): void {
    $rule_id   = intval($rule['rule_id']);
    $rule_name = mysqli_real_escape_string($mysqli, $rule['rule_name']);
    $tt        = mysqli_real_escape_string($mysqli, $trigger_type);
    $ticket_id = isset($context['tid']) && $context['tid']           ? intval($context['tid'])     : 'NULL';
    $asset_id  = isset($context['asset_id']) && $context['asset_id'] ? intval($context['asset_id']) : 'NULL';
    $alert_id  = isset($context['alert_id']) && $context['alert_id'] ? intval($context['alert_id']) : 'NULL';
    $client_id = isset($context['client_id']) && $context['client_id'] ? intval($context['client_id']) : 'NULL';
    $summary_esc = mysqli_real_escape_string($mysqli, $summary);

    mysqli_query($mysqli,
        "INSERT INTO ticket_automation_runs
            (rule_id, rule_name, trigger_type, ticket_id, asset_id, alert_id, client_id, summary, created_at)
         VALUES
            ($rule_id, '$rule_name', '$tt', $ticket_id, $asset_id, $alert_id, $client_id, '$summary_esc', NOW())"
    );
}

/*
 * Executes a single automation action. May mutate $context (e.g.
 * create_ticket_from_alert sets $context['tid'] for later actions).
 * Returns a short human-readable summary string, or null if the action
 * was skipped/no-op.
 */
function automationExecuteAction($mysqli, array $action, array &$context, array $rule): ?string {
    $act       = $action['action'] ?? '';
    $aval      = (string) ($action['value'] ?? '');
    $rule_name = $rule['rule_name'];
    $tid       = intval($context['tid'] ?? 0);
    $client_id = intval($context['client_id'] ?? 0);

    switch ($act) {
        case 'set_priority':
            if (!$tid) return null;
            $p = mysqli_real_escape_string($mysqli, strtolower($aval));
            mysqli_query($mysqli, "UPDATE tickets SET ticket_priority = '$p' WHERE ticket_id = $tid");
            logAction("Automation", "Update", "Rule '$rule_name': set priority=$p on ticket $tid", $client_id, $tid);
            return "set priority=$p on ticket #$tid";

        case 'assign_to':
            if (!$tid) return null;
            $uid = intval($aval);
            mysqli_query($mysqli, "UPDATE tickets SET ticket_assigned_to = $uid WHERE ticket_id = $tid");
            logAction("Automation", "Update", "Rule '$rule_name': assigned ticket $tid to user $uid", $client_id, $tid);
            return "assigned ticket #$tid to user $uid";

        case 'set_status':
            if (!$tid) return null;
            $sid = intval($aval);
            mysqli_query($mysqli, "UPDATE tickets SET ticket_status = $sid WHERE ticket_id = $tid");
            logAction("Automation", "Update", "Rule '$rule_name': set status=$sid on ticket $tid", $client_id, $tid);
            return "set status=$sid on ticket #$tid";

        case 'add_note':
            if (!$tid) return null;
            $note = mysqli_real_escape_string($mysqli, "🤖 Automation: $aval");
            mysqli_query($mysqli,
                "INSERT INTO ticket_replies (ticket_reply, ticket_reply_type, ticket_reply_ticket_id, ticket_reply_created_at)
                 VALUES ('$note', 'Automation', $tid, NOW())"
            );
            return "added note to ticket #$tid";

        case 'notify_assignee':
            if ($tid) {
                appNotify("Automation", "Rule '$rule_name' triggered on ticket #$tid", "/agent/ticket.php?ticket_id=$tid", $client_id);
                return "notified for ticket #$tid";
            }
            if (!empty($context['asset_id'])) {
                $asset_id = intval($context['asset_id']);
                appNotify("Automation", "Rule '$rule_name' triggered on asset #$asset_id", "/agent/asset_details.php?asset_id=$asset_id", $client_id);
                return "notified for asset #$asset_id";
            }
            return null;

        case 'close_ticket':
            if (!$tid) return null;
            $assigned_to = intval($context['assigned_to'] ?? 0);
            $closer = $assigned_to > 0 ? $assigned_to : 1;
            mysqli_query($mysqli,
                "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW(), ticket_closed_by = $closer
                 WHERE ticket_id = $tid AND ticket_resolved_at IS NULL"
            );
            logAction("Automation", "Close", "Rule '$rule_name': auto-closed ticket $tid", $client_id, $tid);
            return "closed ticket #$tid";

        case 'add_worksheet':
            if (!$tid) return null;
            $template_id = intval($aval);
            if ($template_id <= 0) return null;
            $exists = mysqli_fetch_assoc(mysqli_query($mysqli,
                "SELECT worksheet_id FROM ticket_worksheets
                 WHERE worksheet_ticket_id = $tid AND worksheet_template_id = $template_id LIMIT 1"
            ));
            if ($exists) return null;
            mysqli_query($mysqli,
                "INSERT INTO ticket_worksheets (worksheet_ticket_id, worksheet_template_id, worksheet_created_by)
                 VALUES ($tid, $template_id, 1)"
            );
            logAction("Automation", "Worksheet", "Rule '$rule_name': added worksheet template $template_id to ticket $tid", $client_id, $tid);
            return "added worksheet template $template_id to ticket #$tid";

        case 'create_ticket_from_alert':
            if (empty($context['alert'])) return null;
            require_once __DIR__ . '/rmm_functions.php';
            $result = createTicketFromRmmAlert($mysqli, $context['alert'], 0, "Automation: $rule_name");
            $context['tid'] = intval($result['ticket_id']);
            $verb = $result['existing'] ? 'linked existing ticket' : 'created ticket';
            return "$verb #{$context['tid']} from alert";

        case 'acknowledge_alert':
            if (empty($context['alert_id'])) return null;
            $aid = intval($context['alert_id']);
            mysqli_query($mysqli,
                "UPDATE rmm_alerts SET status = 'acknowledged', acknowledged_at = NOW() WHERE id = $aid AND status = 'new'"
            );
            return "acknowledged alert #$aid";

        case 'run_script':
            $script_id = intval($aval);
            $asset_id  = intval($context['asset_id'] ?? 0);
            if ($script_id <= 0 || $asset_id <= 0) return null;

            $script = mysqli_fetch_assoc(mysqli_query($mysqli,
                "SELECT * FROM rmm_scripts WHERE id = $script_id AND enabled = 1 LIMIT 1"
            ));
            if (!$script || empty($script['tactical_script_id'])) return null;

            $link = mysqli_fetch_assoc(mysqli_query($mysqli,
                "SELECT arl.* FROM asset_rmm_links arl
                 JOIN rmm_integrations ri ON ri.id = arl.integration_id
                 WHERE arl.asset_id = $asset_id AND ri.type = 'tactical_rmm' AND ri.enabled = 1 LIMIT 1"
            ));
            if (!$link || empty($link['tactical_agent_id'])) return null;

            try {
                $client = getRmmClient(intval($link['integration_id']));
                $resp   = $client->runScript($link['tactical_agent_id'], intval($script['tactical_script_id']));
            } catch (\Throwable $e) {
                logApp("Automation", "error", "Rule '$rule_name': run_script failed for asset $asset_id: " . $e->getMessage());
                return null;
            }

            $job_id = mysqli_real_escape_string($mysqli, (string) ($resp['id'] ?? ''));
            $script_name_esc = mysqli_real_escape_string($mysqli, $script['name']);
            mysqli_query($mysqli,
                "INSERT INTO rmm_script_runs (script_id, asset_id, user_id, status, tactical_job_id, started_at)
                 VALUES ($script_id, $asset_id, 0, 'running', '$job_id', NOW())"
            );
            logAction("Automation", "Script", "Rule '$rule_name': ran script '$script_name_esc' on asset $asset_id", $client_id, $tid ?: 0);
            return "ran script '{$script['name']}' on asset #$asset_id";

        default:
            return null;
    }
}
