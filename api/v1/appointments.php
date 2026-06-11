<?php
// GET /api/v1/appointments?when=today|past|future&mine=1
defined('FROM_API') || die();
if ($method !== 'GET') api_error(405, 'Method not allowed');

$uid   = $api_user_id;
$mine  = intval($_GET['mine'] ?? 0);
$when  = $_GET['when'] ?? 'future'; // past, today, future

$where = ['t.ticket_schedule IS NOT NULL', 't.ticket_archived_at IS NULL'];
if ($mine) $where[] = "t.ticket_assigned_to = $uid";
if (isset($_GET['client_id'])) $where[] = "t.ticket_client_id = " . intval($_GET['client_id']);

switch ($when) {
    case 'past':
        $where[] = "DATE(t.ticket_schedule) < CURDATE()";
        break;
    case 'today':
        $where[] = "DATE(t.ticket_schedule) = CURDATE()";
        break;
    default: // future
        $where[] = "DATE(t.ticket_schedule) >= CURDATE()";
}

$w = implode(' AND ', $where);
$appts = [];
$sql = mysqli_query($mysqli,
    "SELECT t.ticket_id, t.ticket_number, t.ticket_subject, t.ticket_schedule,
            t.ticket_schedule_end, t.ticket_onsite, t.ticket_appointment_notes,
            t.ticket_priority, t.ticket_status,
            c.client_name, u.user_name AS assigned_to_name,
            ts.ticket_status_name, ts.ticket_status_color
     FROM tickets t
     LEFT JOIN clients c ON t.ticket_client_id = c.client_id
     LEFT JOIN users u ON t.ticket_assigned_to = u.user_id
     LEFT JOIN ticket_statuses ts ON t.ticket_status = ts.ticket_status_id
     WHERE $w
     ORDER BY t.ticket_schedule ASC
     LIMIT 100"
);
while ($row = mysqli_fetch_assoc($sql)) {
    $appts[] = [
        'id'          => intval($row['ticket_id']),
        'number'      => intval($row['ticket_number']),
        'subject'     => $row['ticket_subject'],
        'schedule'    => $row['ticket_schedule'],
        'schedule_end'=> $row['ticket_schedule_end'],
        'onsite'      => (bool)$row['ticket_onsite'],
        'notes'       => $row['ticket_appointment_notes'],
        'priority'    => $row['ticket_priority'],
        'status'      => $row['ticket_status_name'],
        'status_color'=> $row['ticket_status_color'],
        'client'      => $row['client_name'],
        'assigned_to' => $row['assigned_to_name'],
    ];
}
api_response(200, $appts);
