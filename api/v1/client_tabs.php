<?php
// GET /api/v1/clients/{id}/tickets
// GET /api/v1/clients/{id}/assets
// GET /api/v1/clients/{id}/locations
// GET /api/v1/clients/{id}/credentials
// GET /api/v1/clients/{id}/contracts
defined('FROM_API') || die();
if ($method !== 'GET') api_error(405, 'Method not allowed');

if (!$id) api_error(400, 'client_id required');

switch ($sub) {
    case 'tickets':
        $rows = []; $sql = mysqli_query($mysqli,
            "SELECT t.ticket_id, t.ticket_number, t.ticket_subject, t.ticket_priority,
                    t.ticket_created_at, t.ticket_resolved_at,
                    ts.ticket_status_name, ts.ticket_status_color, u.user_name AS assigned_to
             FROM tickets t
             LEFT JOIN ticket_statuses ts ON t.ticket_status = ts.ticket_status_id
             LEFT JOIN users u ON t.ticket_assigned_to = u.user_id
             WHERE t.ticket_client_id = $id AND t.ticket_archived_at IS NULL
             ORDER BY t.ticket_created_at DESC LIMIT 50");
        while ($r = mysqli_fetch_assoc($sql)) {
            $rows[] = ['id'=>intval($r['ticket_id']),'number'=>intval($r['ticket_number']),
                'subject'=>$r['ticket_subject'],'priority'=>$r['ticket_priority'],
                'status'=>$r['ticket_status_name'],'status_color'=>$r['ticket_status_color'],
                'assigned_to'=>$r['assigned_to'],'created_at'=>$r['ticket_created_at'],
                'resolved_at'=>$r['ticket_resolved_at']];
        }
        api_response(200, $rows);

    case 'assets':
        $rows = []; $sql = mysqli_query($mysqli,
            "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_serial, asset_status
             FROM assets WHERE asset_client_id = $id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
        while ($r = mysqli_fetch_assoc($sql)) {
            $rows[] = ['id'=>intval($r['asset_id']),'name'=>$r['asset_name'],'type'=>$r['asset_type'],
                'make'=>$r['asset_make'],'model'=>$r['asset_model'],'serial'=>$r['asset_serial'],'status'=>$r['asset_status']];
        }
        api_response(200, $rows);

    case 'locations':
        $rows = []; $sql = mysqli_query($mysqli,
            "SELECT location_id, location_name, location_address, location_city, location_state,
                    location_zip, location_phone, location_primary
             FROM locations WHERE location_client_id = $id AND location_archived_at IS NULL ORDER BY location_primary DESC, location_name ASC");
        while ($r = mysqli_fetch_assoc($sql)) {
            $rows[] = ['id'=>intval($r['location_id']),'name'=>$r['location_name'],
                'address'=>$r['location_address'],'city'=>$r['location_city'],'state'=>$r['location_state'],
                'zip'=>$r['location_zip'],'phone'=>$r['location_phone'],'primary'=>(bool)$r['location_primary']];
        }
        api_response(200, $rows);

    case 'credentials':
        $rows = []; $sql = mysqli_query($mysqli,
            "SELECT credential_id, credential_name, credential_username, credential_uri
             FROM credentials WHERE credential_client_id = $id AND credential_archived_at IS NULL ORDER BY credential_name ASC");
        while ($r = mysqli_fetch_assoc($sql)) {
            $rows[] = ['id'=>intval($r['credential_id']),'name'=>$r['credential_name'],
                'username'=>$r['credential_username'],'uri'=>$r['credential_uri']];
        }
        api_response(200, $rows);

    case 'contracts':
        $rows = []; $sql = mysqli_query($mysqli,
            "SELECT contract_id, contract_name, contract_status, contract_type
             FROM contracts WHERE contract_client_id = $id AND contract_archived_at IS NULL ORDER BY contract_name ASC");
        while ($r = mysqli_fetch_assoc($sql)) {
            $rows[] = ['id'=>intval($r['contract_id']),'name'=>$r['contract_name'],
                'status'=>$r['contract_status'],'type'=>$r['contract_type']];
        }
        api_response(200, $rows);

    default:
        api_error(404, 'Unknown tab');
}
