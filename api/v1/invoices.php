<?php
// GET /api/v1/invoices        list
// GET /api/v1/invoices/{id}   detail with line items
defined('FROM_API') || die();
if ($method !== 'GET') api_error(405, 'Method not allowed');

if ($id === null) {
    $page      = max(1, intval($_GET['page'] ?? 1));
    $limit     = min(50, max(1, intval($_GET['limit'] ?? 20)));
    $offset    = ($page - 1) * $limit;
    $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : null;
    $status    = mysqli_real_escape_string($mysqli, $_GET['status'] ?? '');

    $where = ['i.invoice_archived_at IS NULL'];
    if ($client_id) $where[] = "i.invoice_client_id = $client_id";
    if ($status)    $where[] = "i.invoice_status = '$status'";
    $w = implode(' AND ', $where);

    $total    = intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS c FROM invoices i WHERE $w"))['c']);
    $invoices = [];
    $sql      = mysqli_query($mysqli,
        "SELECT i.invoice_id, i.invoice_number, i.invoice_date, i.invoice_due_date,
                i.invoice_status, i.invoice_subtotal, i.invoice_tax, i.invoice_total,
                i.invoice_url_key, c.client_name
         FROM invoices i LEFT JOIN clients c ON i.invoice_client_id = c.client_id
         WHERE $w ORDER BY i.invoice_date DESC LIMIT $limit OFFSET $offset"
    );
    while ($row = mysqli_fetch_assoc($sql)) {
        $invoices[] = [
            'id'       => intval($row['invoice_id']),
            'number'   => $row['invoice_number'],
            'date'     => $row['invoice_date'],
            'due_date' => $row['invoice_due_date'],
            'status'   => $row['invoice_status'],
            'total'    => floatval($row['invoice_total']),
            'client'   => $row['client_name'],
            'guest_url'=> $row['invoice_url_key'] ? '/invoice.php?key=' . $row['invoice_url_key'] : null,
        ];
    }
    api_response(200, ['data' => $invoices, 'total' => $total]);
}

$row = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT i.*, c.client_name, ct.contact_name FROM invoices i
     LEFT JOIN clients c ON i.invoice_client_id = c.client_id
     LEFT JOIN contacts ct ON i.invoice_contact_id = ct.contact_id
     WHERE i.invoice_id = $id AND i.invoice_archived_at IS NULL LIMIT 1"
));
if (!$row) api_error(404, 'Invoice not found');

$items = [];
$isql  = mysqli_query($mysqli,
    "SELECT * FROM invoice_items WHERE invoice_item_invoice_id = $id ORDER BY invoice_item_order ASC"
);
while ($item = mysqli_fetch_assoc($isql)) {
    $items[] = [
        'description' => $item['invoice_item_description'],
        'quantity'    => floatval($item['invoice_item_quantity']),
        'unit_price'  => floatval($item['invoice_item_unit_price']),
        'total'       => floatval($item['invoice_item_total']),
        'taxable'     => (bool)$item['invoice_item_taxable'],
    ];
}

api_response(200, [
    'id'           => intval($row['invoice_id']),
    'number'       => $row['invoice_number'],
    'date'         => $row['invoice_date'],
    'due_date'     => $row['invoice_due_date'],
    'status'       => $row['invoice_status'],
    'subtotal'     => floatval($row['invoice_subtotal']),
    'tax'          => floatval($row['invoice_tax']),
    'total'        => floatval($row['invoice_total']),
    'balance'      => floatval($row['invoice_balance'] ?? $row['invoice_total']),
    'client'       => $row['client_name'],
    'contact_name' => $row['contact_name'],
    'notes'        => $row['invoice_notes'] ?? '',
    'guest_url'    => $row['invoice_url_key'] ? '/invoice.php?key=' . $row['invoice_url_key'] : null,
    'items'        => $items,
]);
