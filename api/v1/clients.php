<?php
// GET /api/v1/clients       list
// GET /api/v1/clients/{id}  detail + contacts
defined('FROM_API') || die();
if ($method !== 'GET') api_error(405, 'Method not allowed');

if ($id === null) {
    $page   = max(1, intval($_GET['page'] ?? 1));
    $limit  = min(100, max(1, intval($_GET['limit'] ?? 30)));
    $offset = ($page - 1) * $limit;
    $search = mysqli_real_escape_string($mysqli, $_GET['search'] ?? '');

    $where = ['client_archived_at IS NULL'];
    if ($search) $where[] = "client_name LIKE '%$search%'";
    $w = implode(' AND ', $where);

    $total = intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS c FROM clients WHERE $w"))['c']);
    $clients = [];
    $sql = mysqli_query($mysqli,
        "SELECT client_id, client_name, client_phone, client_city, client_state, client_website
         FROM clients WHERE $w ORDER BY client_name ASC LIMIT $limit OFFSET $offset"
    );
    while ($row = mysqli_fetch_assoc($sql)) {
        $clients[] = [
            'id'      => intval($row['client_id']),
            'name'    => $row['client_name'],
            'phone'   => $row['client_phone'],
            'city'    => $row['client_city'],
            'state'   => $row['client_state'],
            'website' => $row['client_website'],
        ];
    }
    api_response(200, ['data' => $clients, 'total' => $total]);
}

// Detail
$row = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT * FROM clients WHERE client_id = $id AND client_archived_at IS NULL LIMIT 1"
));
if (!$row) api_error(404, 'Client not found');

$contacts = [];
$csql = mysqli_query($mysqli,
    "SELECT contact_id, contact_name, contact_title, contact_email, contact_phone, contact_extension
     FROM contacts WHERE contact_client_id = $id AND contact_archived_at IS NULL ORDER BY contact_name ASC"
);
while ($c = mysqli_fetch_assoc($csql)) {
    $contacts[] = [
        'id'        => intval($c['contact_id']),
        'name'      => $c['contact_name'],
        'title'     => $c['contact_title'],
        'email'     => $c['contact_email'],
        'phone'     => $c['contact_phone'],
        'extension' => $c['contact_extension'],
    ];
}

$open_tickets = intval(mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT COUNT(*) AS c FROM tickets WHERE ticket_client_id = $id AND ticket_resolved_at IS NULL AND ticket_archived_at IS NULL"))['c']);

api_response(200, [
    'id'           => intval($row['client_id']),
    'name'         => $row['client_name'],
    'phone'        => $row['client_phone'],
    'address'      => $row['client_address'],
    'city'         => $row['client_city'],
    'state'        => $row['client_state'],
    'zip'          => $row['client_zip'],
    'website'      => $row['client_website'],
    'notes'        => $row['client_notes'] ?? '',
    'open_tickets' => $open_tickets,
    'contacts'     => $contacts,
]);
