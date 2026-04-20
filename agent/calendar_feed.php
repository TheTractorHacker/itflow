<?php
/**
 * iCal feed for Outlook / Apple Calendar / Google Calendar subscription.
 * URL: /agent/calendar_feed.php?token=USER_TOKEN
 * The token is the user's API token (user_token column).
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/plugins/zapcal/zapcallib.php';

$token = isset($_GET['token']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['token']) : '';

if (!$token) {
    http_response_code(401);
    exit('Unauthorized');
}

$token_escaped = mysqli_real_escape_string($mysqli, $token);
$user_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_token = '$token_escaped' AND user_status = 1 AND user_archived_at IS NULL LIMIT 1"));

if (!$user_row) {
    http_response_code(401);
    exit('Unauthorized');
}

$user_id   = intval($user_row['user_id']);
$user_name = $user_row['user_name'];

// Fetch scheduled tickets for this user (upcoming + past 30 days)
$cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
$sql = mysqli_query($mysqli,
    "SELECT t.ticket_id, t.ticket_prefix, t.ticket_number, t.ticket_subject,
            t.ticket_schedule, t.ticket_schedule_end, t.ticket_appointment_notes,
            t.ticket_onsite, t.ticket_priority,
            c.client_name,
            co.contact_name,
            l.location_address
     FROM tickets t
     LEFT JOIN clients c ON t.ticket_client_id = c.client_id
     LEFT JOIN contacts co ON t.ticket_contact_id = co.contact_id
     LEFT JOIN locations l ON co.contact_location_id = l.location_id
     WHERE t.ticket_schedule IS NOT NULL
       AND t.ticket_schedule >= '$cutoff'
       AND t.ticket_assigned_to = $user_id
       AND t.ticket_archived_at IS NULL
     ORDER BY t.ticket_schedule ASC"
);

$cal = new ZCiCal();
$cal->curnode->addNode(new ZCiCalDataNode("METHOD:PUBLISH"));
$cal->curnode->addNode(new ZCiCalDataNode("X-WR-CALNAME:" . $user_name . "'s Schedule"));
$cal->curnode->addNode(new ZCiCalDataNode("X-WR-TIMEZONE:UTC"));
$cal->curnode->addNode(new ZCiCalDataNode("REFRESH-INTERVAL;VALUE=DURATION:PT1H"));

while ($row = mysqli_fetch_assoc($sql)) {
    $start   = $row['ticket_schedule'];
    $end     = $row['ticket_schedule_end'] ?: $start;
    $subject = $row['ticket_prefix'] . $row['ticket_number'] . ': ' . $row['ticket_subject'];
    if ($row['client_name']) $subject .= ' – ' . $row['client_name'];
    $desc    = $row['ticket_appointment_notes'] ?: $row['ticket_subject'];
    $desc   .= "\nhttps://" . $config_base_url . "/agent/ticket.php?ticket_id=" . intval($row['ticket_id']);
    $loc     = $row['location_address'] ?: ($row['ticket_onsite'] ? 'Onsite' : 'Remote');

    $event = new ZCiCalNode("VEVENT", $cal->curnode);
    $event->addNode(new ZCiCalDataNode("SUMMARY:" . $subject));
    $event->addNode(new ZCiCalDataNode("DTSTART:" . ZCiCal::fromSqlDateTime($start)));
    $event->addNode(new ZCiCalDataNode("DTEND:" . ZCiCal::fromSqlDateTime($end)));
    $event->addNode(new ZCiCalDataNode("DTSTAMP:" . ZCiCal::fromSqlDateTime()));
    $uid = 'ticket-' . intval($row['ticket_id']) . '@' . $config_base_url;
    $event->addNode(new ZCiCalDataNode("UID:" . $uid));
    $event->addNode(new ZCiCalDataNode("LOCATION:" . $loc));
    $event->addNode(new ZCiCalDataNode("DESCRIPTION:" . str_replace("\n", "\\n", $desc)));
    if ($row['ticket_priority'] == 'High') {
        $event->addNode(new ZCiCalDataNode("PRIORITY:1"));
    }
}

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="schedule.ics"');
header('Cache-Control: no-cache, must-revalidate');
echo $cal->export();
