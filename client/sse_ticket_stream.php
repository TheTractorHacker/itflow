<?php

/*
 * ITFlow - SSE wrapper for live ticket updates (client portal)
 */

require_once '../config.php';
require_once '../includes/load_global_settings.php';
require_once '../functions.php';
require_once 'includes/check_login.php';
require_once 'functions.php';
require_once '../includes/redis_functions.php';

$ticket_id = intval($_GET['ticket_id'] ?? 0);

// Read-only access check: same ownership rules as verifyContactTicketAccess(),
// without the open/closed restriction - live updates are useful regardless of state.
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_contact_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id LIMIT 1"));

if (!$ticket_id || !$row || !($session_contact_id == $row['ticket_contact_id'] || $session_contact_primary == 1 || $session_contact_is_technical_contact)) {
    http_response_code(404);
    exit;
}

require '../includes/sse_ticket_stream.php';
