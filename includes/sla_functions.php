<?php
/*
 * SLA helpers (Phase 4).
 *
 * SLA due dates are derived from the ticket's linked contract's per-priority
 * response/resolution hour targets, anchored to ticket_created_at. Tickets
 * without a contract (or with a contract that has no hours set for the
 * relevant priority) have NULL SLA fields, meaning "no SLA tracked".
 */

function recalculateTicketSla($mysqli, int $ticket_id): void {
    $ticket = mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT ticket_contract_id, ticket_priority, ticket_created_at FROM tickets WHERE ticket_id = $ticket_id"
    ));
    if (!$ticket) return;

    $contract_id = intval($ticket['ticket_contract_id'] ?? 0);
    $sla_response_due   = 'NULL';
    $sla_resolution_due = 'NULL';

    if ($contract_id > 0) {
        $ct = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM contracts WHERE contract_id = $contract_id LIMIT 1"));
        if ($ct) {
            $p = strtolower($ticket['ticket_priority'] ?? '');
            $r_hours   = $p === 'low' ? intval($ct['contract_sla_low_response_time'])    : ($p === 'medium' ? intval($ct['contract_sla_medium_response_time'])    : intval($ct['contract_sla_high_response_time']));
            $res_hours = $p === 'low' ? intval($ct['contract_sla_low_resolution_time'])  : ($p === 'medium' ? intval($ct['contract_sla_medium_resolution_time'])  : intval($ct['contract_sla_high_resolution_time']));

            $created_ts = strtotime($ticket['ticket_created_at']);
            if ($r_hours > 0)   $sla_response_due   = "'" . date('Y-m-d H:i:s', $created_ts + ($r_hours * 3600))   . "'";
            if ($res_hours > 0) $sla_resolution_due = "'" . date('Y-m-d H:i:s', $created_ts + ($res_hours * 3600)) . "'";
        }
    }

    mysqli_query($mysqli,
        "UPDATE tickets SET ticket_sla_response_due = $sla_response_due, ticket_sla_resolution_due = $sla_resolution_due
         WHERE ticket_id = $ticket_id"
    );
}
