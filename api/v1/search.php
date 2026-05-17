<?php
// GET /api/v1/search?q=   global search across tickets, clients, assets
defined('FROM_API') || die();
if ($method !== 'GET') api_error(405, 'Method not allowed');

$q = mysqli_real_escape_string($mysqli, trim($_GET['q'] ?? ''));
if (strlen($q) < 2) api_error(400, 'Query must be at least 2 characters');

// Tickets
$tickets = [];
$sql = mysqli_query($mysqli,
    "SELECT t.ticket_id, t.ticket_number, t.ticket_subject, t.ticket_priority,
            ts.ticket_status_name, c.client_name
     FROM tickets t
     LEFT JOIN ticket_statuses ts ON t.ticket_status = ts.ticket_status_id
     LEFT JOIN clients c ON t.ticket_client_id = c.client_id
     WHERE t.ticket_archived_at IS NULL
       AND (t.ticket_subject LIKE '%$q%' OR t.ticket_number LIKE '%$q%')
     ORDER BY t.ticket_created_at DESC LIMIT 8"
);
while ($row = mysqli_fetch_assoc($sql)) {
    $tickets[] = [
        'id'      => intval($row['ticket_id']),
        'number'  => intval($row['ticket_number']),
        'subject' => $row['ticket_subject'],
        'status'  => $row['ticket_status_name'],
        'client'  => $row['client_name'],
        'priority'=> $row['ticket_priority'],
    ];
}

// Clients
$clients = [];
$sql = mysqli_query($mysqli,
    "SELECT c.client_id, c.client_name, l.location_phone
     FROM clients c
     LEFT JOIN locations l ON l.location_client_id = c.client_id AND l.location_primary = 1
     WHERE c.client_archived_at IS NULL AND c.client_name LIKE '%$q%'
     ORDER BY c.client_name ASC LIMIT 5"
);
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[] = [
        'id'    => intval($row['client_id']),
        'name'  => $row['client_name'],
        'phone' => $row['location_phone'],
    ];
}

// Assets
$assets = [];
$sql = mysqli_query($mysqli,
    "SELECT a.asset_id, a.asset_name, a.asset_serial, a.asset_make, a.asset_model, c.client_name
     FROM assets a LEFT JOIN clients c ON a.asset_client_id = c.client_id
     WHERE a.asset_archived_at IS NULL
       AND (a.asset_name LIKE '%$q%' OR a.asset_serial LIKE '%$q%'
            OR a.asset_make LIKE '%$q%' OR a.asset_model LIKE '%$q%')
     ORDER BY a.asset_name ASC LIMIT 5"
);
while ($row = mysqli_fetch_assoc($sql)) {
    $assets[] = [
        'id'     => intval($row['asset_id']),
        'name'   => $row['asset_name'],
        'serial' => $row['asset_serial'],
        'make'   => $row['asset_make'],
        'model'  => $row['asset_model'],
        'client' => $row['client_name'],
    ];
}

api_response(200, ['tickets' => $tickets, 'clients' => $clients, 'assets' => $assets]);
