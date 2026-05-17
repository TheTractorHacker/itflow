<?php
// GET    /api/v1/tickets              list
// GET    /api/v1/tickets/{id}         detail
// POST   /api/v1/tickets/{id}/reply   add reply
// POST   /api/v1/tickets/{id}/time    log time
defined('FROM_API') || die();

$uid = $api_user_id;

// LIST
if ($method === 'GET' && $id === null) {
    $page    = max(1, intval($_GET['page'] ?? 1));
    $limit   = min(50, max(1, intval($_GET['limit'] ?? 20)));
    $offset  = ($page - 1) * $limit;
    $search   = mysqli_real_escape_string($mysqli, $_GET['search'] ?? '');
    $status   = $_GET['status'] ?? 'open'; // open|closed|all
    $mine     = isset($_GET['mine']) ? intval($_GET['mine']) : 0;
    $priority = mysqli_real_escape_string($mysqli, strtolower($_GET['priority'] ?? ''));
    $onsite   = isset($_GET['onsite']) && $_GET['onsite'] !== '' ? intval($_GET['onsite']) : -1;

    $where = ['t.ticket_archived_at IS NULL'];
    if ($status === 'open')   $where[] = 't.ticket_resolved_at IS NULL';
    if ($status === 'closed') $where[] = 't.ticket_resolved_at IS NOT NULL';
    if ($mine)                $where[] = "t.ticket_assigned_to = $uid";
    if ($search)              $where[] = "(t.ticket_subject LIKE '%$search%' OR t.ticket_number LIKE '%$search%')";
    if ($priority)            $where[] = "LOWER(t.ticket_priority) = '$priority'";
    if ($onsite !== -1)       $where[] = "t.ticket_onsite = $onsite";

    $where_sql = implode(' AND ', $where);

    $total = intval(mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT COUNT(*) AS c FROM tickets t WHERE $where_sql"))['c']);

    $tickets = [];
    $sql = mysqli_query($mysqli,
        "SELECT t.ticket_id, t.ticket_number, t.ticket_subject, t.ticket_priority,
                t.ticket_created_at, t.ticket_due_at, t.ticket_resolved_at,
                c.client_name, ts.ticket_status_name, ts.ticket_status_color,
                u.user_name AS assigned_to_name
         FROM tickets t
         LEFT JOIN clients c ON t.ticket_client_id = c.client_id
         LEFT JOIN ticket_statuses ts ON t.ticket_status = ts.ticket_status_id
         LEFT JOIN users u ON t.ticket_assigned_to = u.user_id
         WHERE $where_sql
         ORDER BY t.ticket_created_at DESC
         LIMIT $limit OFFSET $offset"
    );
    while ($row = mysqli_fetch_assoc($sql)) {
        $tickets[] = [
            'id'           => intval($row['ticket_id']),
            'number'       => intval($row['ticket_number']),
            'subject'      => $row['ticket_subject'],
            'priority'     => $row['ticket_priority'],
            'status'       => $row['ticket_status_name'],
            'status_color' => $row['ticket_status_color'],
            'client'       => $row['client_name'],
            'assigned_to'  => $row['assigned_to_name'],
            'created_at'   => $row['ticket_created_at'],
            'due_at'       => $row['ticket_due_at'],
            'resolved_at'  => $row['ticket_resolved_at'],
        ];
    }
    api_response(200, ['data' => $tickets, 'total' => $total, 'page' => $page, 'limit' => $limit]);
}

// DETAIL
if ($method === 'GET' && $id !== null && $sub === null) {
    $sql = mysqli_query($mysqli,
        "SELECT t.*, c.client_name, ts.ticket_status_name, ts.ticket_status_color,
                u.user_name AS assigned_to_name, ct.contact_name, ct.contact_email, ct.contact_phone
         FROM tickets t
         LEFT JOIN clients c ON t.ticket_client_id = c.client_id
         LEFT JOIN ticket_statuses ts ON t.ticket_status = ts.ticket_status_id
         LEFT JOIN users u ON t.ticket_assigned_to = u.user_id
         LEFT JOIN contacts ct ON t.ticket_contact_id = ct.contact_id
         WHERE t.ticket_id = $id LIMIT 1"
    );
    $ticket = mysqli_fetch_assoc($sql);
    if (!$ticket) api_error(404, 'Ticket not found');

    // Replies
    $replies = [];
    $rsql = mysqli_query($mysqli,
        "SELECT r.*, u.user_name FROM ticket_replies r
         LEFT JOIN users u ON r.ticket_reply_by = u.user_id
         WHERE r.ticket_reply_ticket_id = $id AND r.ticket_reply_archived_at IS NULL
         ORDER BY r.ticket_reply_created_at ASC"
    );
    while ($row = mysqli_fetch_assoc($rsql)) {
        $replies[] = [
            'id'           => intval($row['ticket_reply_id']),
            'body'         => $row['ticket_reply'],
            'type'         => $row['ticket_reply_type'],
            'time_worked'  => $row['ticket_reply_time_worked'],
            'onsite'       => (bool)($row['ticket_reply_onsite'] ?? false),
            'by'           => $row['user_name'],
            'created_at'   => $row['ticket_reply_created_at'],
        ];
    }

    api_response(200, [
        'id'           => intval($ticket['ticket_id']),
        'number'       => intval($ticket['ticket_number']),
        'subject'      => $ticket['ticket_subject'],
        'details'      => $ticket['ticket_details'],
        'priority'     => $ticket['ticket_priority'],
        'status'       => $ticket['ticket_status_name'],
        'status_color' => $ticket['ticket_status_color'],
        'client'       => $ticket['client_name'],
        'assigned_to'  => $ticket['assigned_to_name'],
        'contact_name' => $ticket['contact_name'],
        'contact_email'=> $ticket['contact_email'],
        'contact_phone'=> $ticket['contact_phone'],
        'billable'     => (bool)$ticket['ticket_billable'],
        'created_at'   => $ticket['ticket_created_at'],
        'due_at'       => $ticket['ticket_due_at'],
        'resolved_at'  => $ticket['ticket_resolved_at'],
        'replies'      => $replies,
    ]);
}

// ADD REPLY
if ($method === 'POST' && $id !== null && $sub === 'reply') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $reply   = mysqli_real_escape_string($mysqli, trim($body['reply'] ?? ''));
    $type    = in_array($body['type'] ?? 'reply', ['reply', 'note']) ? ($body['type'] ?? 'reply') : 'reply';
    $time_w  = mysqli_real_escape_string($mysqli, trim($body['time_worked'] ?? ''));
    $onsite  = isset($body['onsite']) ? intval($body['onsite']) : 0;

    if (!$reply) api_error(400, 'reply is required');

    $time_sql = $time_w ? "'$time_w'" : 'NULL';
    mysqli_query($mysqli,
        "INSERT INTO ticket_replies (ticket_reply, ticket_reply_type, ticket_reply_time_worked, ticket_reply_onsite, ticket_reply_by, ticket_reply_ticket_id, ticket_reply_created_at)
         VALUES ('$reply', '$type', $time_sql, $onsite, $uid, $id, NOW())"
    );
    $reply_id = mysqli_insert_id($mysqli);
    mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $id");

    api_response(201, ['id' => $reply_id]);
}

// LOG TIME
if ($method === 'POST' && $id !== null && $sub === 'time') {
    $body       = json_decode(file_get_contents('php://input'), true) ?? [];
    $time_worked = mysqli_real_escape_string($mysqli, trim($body['time_worked'] ?? ''));
    $note        = mysqli_real_escape_string($mysqli, trim($body['note'] ?? 'Time logged via mobile app'));

    if (!$time_worked) api_error(400, 'time_worked required (HH:MM:SS)');

    mysqli_query($mysqli,
        "INSERT INTO ticket_replies (ticket_reply, ticket_reply_type, ticket_reply_time_worked, ticket_reply_by, ticket_reply_ticket_id, ticket_reply_created_at)
         VALUES ('$note', 'note', '$time_worked', $uid, $id, NOW())"
    );

    api_response(201, ['ok' => true]);
}

api_error(404, 'Not found');

// CREATE TICKET
if ($method === 'POST' && $id === null) {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $subject  = mysqli_real_escape_string($mysqli, trim($body['subject'] ?? ''));
    $details  = mysqli_real_escape_string($mysqli, trim($body['details'] ?? ''));
    $client   = isset($body['client_id']) ? intval($body['client_id']) : 'NULL';
    $priority = in_array($body['priority'] ?? '', ['low','medium','high','critical'])
                ? $body['priority'] : 'low';
    $assigned = isset($body['assigned_to']) ? intval($body['assigned_to']) : 'NULL';

    if (!$subject) api_error(400, 'subject required');

    $next_num = intval(mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT COALESCE(MAX(ticket_number), 0) + 1 AS n FROM tickets"))['n']);
    $status   = intval(mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT ticket_status_id FROM ticket_statuses ORDER BY ticket_status_order ASC LIMIT 1"))['ticket_status_id']);

    mysqli_query($mysqli,
        "INSERT INTO tickets (ticket_subject, ticket_details, ticket_client_id, ticket_priority,
         ticket_status, ticket_assigned_to, ticket_number, ticket_created_at, ticket_updated_at)
         VALUES ('$subject', '$details', $client, '$priority', $status, $assigned, $next_num, NOW(), NOW())"
    );
    $new_id = mysqli_insert_id($mysqli);
    api_response(201, ['id' => $new_id, 'number' => $next_num]);
}

// UPLOAD ATTACHMENT
if ($method === 'POST' && $id !== null && $sub === 'attachments') {
    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) api_error(400, 'File upload failed');
    if ($file['size'] > 10 * 1024 * 1024) api_error(400, 'File too large (max 10 MB)');

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $mime    = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed)) api_error(400, 'File type not allowed');

    $dir = $DOCUMENT_ROOT . "/uploads/mobile_attachments/$id";
    if (!is_dir($dir)) mkdir($dir, 0750, true);

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(8)) . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
    $dest     = "$dir/$filename";
    move_uploaded_file($file['tmp_name'], $dest);

    // Store in DB — create table lazily
    mysqli_query($mysqli,
        "CREATE TABLE IF NOT EXISTS mobile_ticket_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100),
            created_by INT,
            created_at DATETIME DEFAULT NOW()
        )"
    );
    mysqli_query($mysqli,
        "INSERT INTO mobile_ticket_attachments (ticket_id, filename, mime_type, created_by)
         VALUES ($id, '$filename', '$mime', $uid)"
    );

    $url_path = "/uploads/mobile_attachments/$id/$filename";
    api_response(201, ['url' => $url_path, 'filename' => $filename]);
}

// ── Additional POST routes ───────────────────────────────────────────────────

// UPDATE TICKET STATUS
if ($method === 'POST' && $id !== null && $sub === 'status') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $status = intval($body['status_id'] ?? 0);
    if (!$status) api_error(400, 'status_id required');
    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = $status, ticket_updated_at = NOW() WHERE ticket_id = $id");
    // If status resolves the ticket
    $resolved_status = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_status_id FROM ticket_statuses WHERE ticket_status_name IN ('Resolved','Closed','Complete') LIMIT 1"));
    if ($resolved_status && $status == intval($resolved_status['ticket_status_id'])) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_resolved_at = NOW() WHERE ticket_id = $id AND ticket_resolved_at IS NULL");
    }
    api_response(200, ['ok' => true]);
}

// GET TICKET STATUSES
if ($method === 'GET' && $resource === 'statuses' || ($method === 'GET' && $id === null && isset($_GET['statuses']))) {
    $statuses = [];
    $sql = mysqli_query($mysqli, "SELECT * FROM ticket_statuses WHERE ticket_status_active = 1 ORDER BY ticket_status_order ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $statuses[] = ['id' => intval($row['ticket_status_id']), 'name' => $row['ticket_status_name'], 'color' => $row['ticket_status_color']];
    }
    api_response(200, $statuses);
}

api_error(404, 'Not found');
