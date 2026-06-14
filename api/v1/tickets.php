<?php
// GET    /api/v1/tickets              list
// GET    /api/v1/tickets/{id}         detail
// POST   /api/v1/tickets/{id}/reply   add reply
// POST   /api/v1/tickets/{id}/time    log time
defined('FROM_API') || die();

$uid = $api_user_id;

// Saves uploaded files (multipart field "files[]" for multiple, or "file" for a single
// upload) into uploads/tickets/{ticket_id}/ and records them in ticket_attachments --
// the same table/storage the agent UI and client portal use.
function api_save_ticket_attachments($mysqli, int $ticket_id, ?int $reply_id, string $document_root): array {
    $allowed = ['jpg','jpeg','gif','png','webp','pdf','txt','md','doc','docx','odt','csv','xls','xlsx','ods','pptx','odp','zip','tar','gz','xml','msg','json','wav','mp3','ogg','mov','mp4','av1','ovpn'];

    $files = [];
    if (!empty($_FILES['files']) && is_array($_FILES['files']['name'] ?? null)) {
        for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
            $files[] = [
                'name'     => $_FILES['files']['name'][$i],
                'tmp_name' => $_FILES['files']['tmp_name'][$i],
                'error'    => $_FILES['files']['error'][$i],
                'size'     => $_FILES['files']['size'][$i],
            ];
        }
    } elseif (!empty($_FILES['file'])) {
        $files[] = $_FILES['file'];
    }

    $saved = [];
    if (empty($files)) return $saved;

    $upload_dir = "$document_root/uploads/tickets/$ticket_id/";
    mkdirMissing("$document_root/uploads/tickets/");
    mkdirMissing($upload_dir);

    $reply_sql = $reply_id !== null ? intval($reply_id) : 'NULL';

    foreach ($files as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

        $ref_name = checkFileUpload($file, $allowed);
        if (!is_string($ref_name) || !preg_match('/^[a-zA-Z0-9]+\.[a-zA-Z0-9]+$/', $ref_name)) continue;

        move_uploaded_file($file['tmp_name'], $upload_dir . $ref_name);

        $name = sanitizeInput($file['name']);
        $ref  = mysqli_real_escape_string($mysqli, $ref_name);
        mysqli_query($mysqli,
            "INSERT INTO ticket_attachments SET ticket_attachment_name='$name', ticket_attachment_reference_name='$ref', ticket_attachment_reply_id=$reply_sql, ticket_attachment_ticket_id=$ticket_id"
        );
        $saved[] = ['name' => $file['name'], 'filename' => $ref_name, 'url' => "/uploads/tickets/$ticket_id/$ref_name"];
    }
    return $saved;
}

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
    $contact_id = isset($_GET['contact_id']) ? intval($_GET['contact_id']) : 0;
    $client_id  = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

    $where = ['t.ticket_archived_at IS NULL'];
    if ($status === 'open')   $where[] = 't.ticket_resolved_at IS NULL';
    if ($status === 'closed') $where[] = 't.ticket_resolved_at IS NOT NULL';
    if ($mine)                $where[] = "t.ticket_assigned_to = $uid";
    if ($search)              $where[] = "(t.ticket_subject LIKE '%$search%' OR t.ticket_number LIKE '%$search%')";
    if ($priority)            $where[] = "LOWER(t.ticket_priority) = '$priority'";
    if ($onsite !== -1)       $where[] = "t.ticket_onsite = $onsite";
    if ($contact_id)          $where[] = "t.ticket_contact_id = $contact_id";
    if ($client_id)           $where[] = "t.ticket_client_id = $client_id";

    $where_sql = implode(' AND ', $where);

    $total = intval(mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT COUNT(*) AS c FROM tickets t WHERE $where_sql"))['c']);

    $tickets = [];
    $sql = mysqli_query($mysqli,
        "SELECT t.ticket_id, t.ticket_number, t.ticket_subject, t.ticket_priority,
                t.ticket_created_at, t.ticket_due_at, t.ticket_resolved_at, t.ticket_updated_at,
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
            'updated_at'   => $row['ticket_updated_at'],
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

    // Attachments on the ticket itself (not tied to a reply)
    $ticket_attachments = [];
    $asql = mysqli_query($mysqli,
        "SELECT ticket_attachment_name, ticket_attachment_reference_name FROM ticket_attachments
         WHERE ticket_attachment_ticket_id = $id AND ticket_attachment_reply_id IS NULL"
    );
    while ($row = mysqli_fetch_assoc($asql)) {
        $ticket_attachments[] = [
            'name' => $row['ticket_attachment_name'],
            'url'  => "/uploads/tickets/$id/{$row['ticket_attachment_reference_name']}",
        ];
    }

    // Replies
    $replies = [];
    $rsql = mysqli_query($mysqli,
        "SELECT r.*, u.user_name FROM ticket_replies r
         LEFT JOIN users u ON r.ticket_reply_by = u.user_id
         WHERE r.ticket_reply_ticket_id = $id AND r.ticket_reply_archived_at IS NULL
         ORDER BY r.ticket_reply_created_at ASC"
    );
    while ($row = mysqli_fetch_assoc($rsql)) {
        $reply_attachments = [];
        $rasql = mysqli_query($mysqli,
            "SELECT ticket_attachment_name, ticket_attachment_reference_name FROM ticket_attachments
             WHERE ticket_attachment_reply_id = " . intval($row['ticket_reply_id'])
        );
        while ($arow = mysqli_fetch_assoc($rasql)) {
            $reply_attachments[] = [
                'name' => $arow['ticket_attachment_name'],
                'url'  => "/uploads/tickets/$id/{$arow['ticket_attachment_reference_name']}",
            ];
        }

        $replies[] = [
            'id'           => intval($row['ticket_reply_id']),
            'body'         => $row['ticket_reply'],
            'type'         => $row['ticket_reply_type'],
            'time_worked'  => $row['ticket_reply_time_worked'],
            'onsite'       => (bool)($row['ticket_reply_onsite'] ?? false),
            'by'           => $row['user_name'],
            'created_at'   => $row['ticket_reply_created_at'],
            'attachments'  => $reply_attachments,
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
        'attachments'  => $ticket_attachments,
        'replies'      => $replies,
    ]);
}

// ADD REPLY
if ($method === 'POST' && $id !== null && $sub === 'reply') {
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($content_type, 'multipart/form-data') === 0) {
        $body = $_POST;
    } else {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
    }
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

    publishTicketEvent($id, 'reply', ['reply_id' => $reply_id, 'reply_type' => $type, 'by' => $session_name ?? 'API', 'by_type' => 'agent']);

    $response = ['id' => $reply_id];
    $attachments = api_save_ticket_attachments($mysqli, $id, $reply_id, $DOCUMENT_ROOT);
    if ($attachments) $response['attachments'] = $attachments;

    api_response(201, $response);
}

// DELETE REPLY
if ($method === 'DELETE' && $id !== null && $sub === 'reply') {
    $reply_id = isset($segments[3]) && is_numeric($segments[3]) ? intval($segments[3]) : null;
    if (!$reply_id) api_error(400, 'reply_id required');

    $reply = mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT ticket_reply_id FROM ticket_replies WHERE ticket_reply_id = $reply_id AND ticket_reply_ticket_id = $id LIMIT 1"
    ));
    if (!$reply) api_error(404, 'Reply not found');

    mysqli_query($mysqli, "DELETE FROM ticket_attachments WHERE ticket_attachment_reply_id = $reply_id");
    mysqli_query($mysqli, "DELETE FROM ticket_replies WHERE ticket_reply_id = $reply_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $id");

    api_response(200, ['ok' => true]);
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

// CREATE TICKET
if ($method === 'POST' && $id === null) {
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($content_type, 'multipart/form-data') === 0) {
        $body = $_POST;
    } else {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
    }
    $subject  = mysqli_real_escape_string($mysqli, trim($body['subject'] ?? ''));
    $details  = mysqli_real_escape_string($mysqli, trim($body['details'] ?? ''));
    $client   = isset($body['client_id']) ? intval($body['client_id']) : 0;
    $priority_in = strtolower(trim($body['priority'] ?? ''));
    $priority = in_array($priority_in, ['low','medium','high','critical'])
                ? ucfirst($priority_in) : 'Low';
    $assigned = isset($body['assigned_to']) ? intval($body['assigned_to']) : 0;
    $contact  = isset($body['contact_id']) ? intval($body['contact_id']) : 0;
    $category = isset($body['category_id']) ? intval($body['category_id']) : 0;
    $hostname = mysqli_real_escape_string($mysqli, trim($body['hostname'] ?? ''));

    if (!$subject) api_error(400, 'subject required');

    // Validate category against ticket categories
    if ($category) {
        $cat_row = mysqli_fetch_assoc(mysqli_query($mysqli,
            "SELECT category_id FROM categories WHERE category_id = $category AND category_type = 'Ticket' AND category_archived_at IS NULL LIMIT 1"));
        if (!$cat_row) $category = 0;
    }

    // Auto-link to a matching asset for this client by hostname/asset name
    $asset_id = 0;
    if ($hostname && $client) {
        $asset_row = mysqli_fetch_assoc(mysqli_query($mysqli,
            "SELECT asset_id FROM assets WHERE asset_client_id = $client AND asset_archived_at IS NULL
             AND (asset_name = '$hostname' OR asset_tag = '$hostname') LIMIT 1"));
        if ($asset_row) $asset_id = intval($asset_row['asset_id']);
    }

    $next_num = intval(mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT COALESCE(MAX(ticket_number), 0) + 1 AS n FROM tickets"))['n']);
    $status_row = mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT ticket_status_id FROM ticket_statuses WHERE ticket_status_name = 'New' AND ticket_status_active = 1 LIMIT 1"));
    if (!$status_row) {
        $status_row = mysqli_fetch_assoc(mysqli_query($mysqli,
            "SELECT ticket_status_id FROM ticket_statuses WHERE ticket_status_active = 1 ORDER BY ticket_status_order ASC, ticket_status_id ASC LIMIT 1"));
    }
    $status = intval($status_row['ticket_status_id']);

    mysqli_query($mysqli,
        "INSERT INTO tickets (ticket_subject, ticket_details, ticket_client_id, ticket_contact_id, ticket_priority,
         ticket_status, ticket_assigned_to, ticket_created_by, ticket_source, ticket_number, ticket_category, ticket_asset_id, ticket_created_at, ticket_updated_at)
         VALUES ('$subject', '$details', $client, $contact, '$priority', $status, $assigned, $uid, 'API', $next_num, $category, $asset_id, NOW(), NOW())"
    );
    $new_id = mysqli_insert_id($mysqli);

    $response = ['id' => $new_id, 'number' => $next_num];
    $attachments = api_save_ticket_attachments($mysqli, $new_id, null, $DOCUMENT_ROOT);
    if ($attachments) $response['attachments'] = $attachments;

    api_response(201, $response);
}

// UPLOAD ATTACHMENT
if ($method === 'POST' && $id !== null && $sub === 'attachments') {
    $saved = api_save_ticket_attachments($mysqli, $id, null, $DOCUMENT_ROOT);
    if (empty($saved)) api_error(400, 'File upload failed or file type not allowed');

    mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $id");

    api_response(201, count($saved) === 1 ? $saved[0] : ['attachments' => $saved]);
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

    $api_status_info = getTicketStatusInfo($mysqli, $status);
    publishTicketEvent($id, 'status', ['status_id' => $api_status_info['id'], 'status_name' => $api_status_info['name'], 'status_color' => $api_status_info['color'], 'by' => $session_name ?? 'API']);

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
