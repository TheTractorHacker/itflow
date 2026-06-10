<?php

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_url = '';
}

// Ticket client access overide - This is the only way to show tickets without a client to agents with restricted client access
$access_permission_query_overide = '';
if ($client_access_string) {
    $access_permission_query_overide = "AND ticket_client_id IN (0,$client_access_string)";
}

// Perms
enforceUserPermission('module_support');

// Initialize the HTML Purifier to prevent XSS
require_once "../plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

if (isset($_GET['ticket_id'])) {
    $ticket_id = intval($_GET['ticket_id']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN users ON ticket_assigned_to = user_id
        LEFT JOIN locations ON ticket_location_id = location_id
        LEFT JOIN assets ON ticket_asset_id = asset_id
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
        LEFT JOIN vendors ON ticket_vendor_id = vendor_id
        LEFT JOIN projects ON ticket_project_id = project_id
        LEFT JOIN quotes ON ticket_quote_id = quote_id
        LEFT JOIN invoices ON ticket_invoice_id = invoice_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        LEFT JOIN categories ON ticket_category = category_id
        WHERE ticket_id = $ticket_id
        $access_permission_query_overide
        LIMIT 1"
    );

    if (mysqli_num_rows($sql) == 0) {
        echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='tickets.php'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

        require_once "../includes/footer.php";
    } else {

        $row = mysqli_fetch_assoc($sql);
        $client_id = intval($row['client_id']);
        $client_name = nullable_htmlentities($row['client_name']);
        $client_type = nullable_htmlentities($row['client_type']);
        $client_website = nullable_htmlentities($row['client_website']);

        $client_net_terms = intval($row['client_net_terms']);
        if ($client_net_terms == 0) {
            $client_net_terms = $config_default_net_terms;
        }

        $client_rate = floatval($row['client_rate']);

        $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_source = nullable_htmlentities($row['ticket_source']);
        $ticket_category = intval($row['ticket_category']);
        $ticket_category_display = nullable_htmlentities($row['category_name']);
        $ticket_subject = nullable_htmlentities($row['ticket_subject']);
        $ticket_details = $purifier->purify($row['ticket_details']);
        $ticket_priority = nullable_htmlentities($row['ticket_priority']);
        $ticket_billable = intval($row['ticket_billable']);
        $ticket_scheduled_for = nullable_htmlentities($row['ticket_schedule']);
        $ticket_schedule_end  = nullable_htmlentities($row['ticket_schedule_end'] ?? '');
        $ticket_appt_notes    = nullable_htmlentities($row['ticket_appointment_notes'] ?? '');
        $ticket_onsite = intval($row['ticket_onsite']);
        if ($ticket_scheduled_for) {
            $sched_ts  = strtotime($ticket_scheduled_for);
            $sched_fmt = date('D, M j · g:i A', $sched_ts);
            if ($ticket_schedule_end) {
                $end_ts = strtotime($ticket_schedule_end);
                $dur_mins = round(($end_ts - $sched_ts) / 60);
                $dur_label = $dur_mins < 60 ? "{$dur_mins}m" : round($dur_mins/60, 1).'h';
                $sched_fmt .= ' – ' . date('g:i A', $end_ts) . " ({$dur_label})";
            }
            $ticket_scheduled_wording = $sched_fmt;
        } else {
            $ticket_scheduled_wording = "Add";
        }

        //Set Ticket Badge Color based of priority
        if ($ticket_priority == "High") {
            $ticket_priority_display = "<span class='p-2 badge badge-pill badge-danger'>$ticket_priority</span>";
        } elseif ($ticket_priority == "Medium") {
            $ticket_priority_display = "<span class='p-2 badge badge-pill badge-warning'>$ticket_priority</span>";
        } elseif ($ticket_priority == "Low") {
            $ticket_priority_display = "<span class='p-2 badge badge-pill badge-info'>$ticket_priority</span>";
        } else {
            $ticket_priority_display = "";
        }
        $ticket_feedback = nullable_htmlentities($row['ticket_feedback']);

        $ticket_status = intval($row['ticket_status_id']);
        $ticket_status_id = intval($row['ticket_status_id']);
        $ticket_status_name = nullable_htmlentities($row['ticket_status_name']);
        $ticket_status_color = nullable_htmlentities($row['ticket_status_color']);

        $ticket_vendor_ticket_number = nullable_htmlentities($row['ticket_vendor_ticket_number']);
        $ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
        $ticket_created_at_ago = timeAgo($row['ticket_created_at']);
        $ticket_created_by = intval($row['ticket_created_by']);
        $ticket_date = date('Y-m-d', strtotime($ticket_created_at));
        $ticket_updated_at = nullable_htmlentities($row['ticket_updated_at']);
        $ticket_updated_at_ago = timeAgo($row['ticket_updated_at']);
        $ticket_first_response_at = nullable_htmlentities($row['ticket_first_response_at']);
        $ticket_resolved_at = nullable_htmlentities($row['ticket_resolved_at']);
        $ticket_resolved_at_ago = timeAgo($row['ticket_resolved_at']);
        $ticket_resolved_date = date('Y-m-d', strtotime($ticket_resolved_at));
        $ticket_closed_at = nullable_htmlentities($row['ticket_closed_at']);
        $ticket_closed_at_ago = timeAgo($row['ticket_closed_at']);
        $ticket_closed_date = date('Y-m-d', strtotime($ticket_closed_at));
        $ticket_closed_by = intval($row['ticket_closed_by']);

        $ticket_assigned_to = intval($row['ticket_assigned_to']);
        if (empty($ticket_assigned_to)) {
            $ticket_assigned_to_display = "<span class='badge badge-pill badge-light'>Unassigned</span>";
        } else {
            $ticket_assigned_to_display = nullable_htmlentities($row['user_name']);
        }

        $ticket_contract_id = intval($row['ticket_contract_id'] ?? 0);
        $ticket_sla_response_due = $row['ticket_sla_response_due'] ?? null;
        $ticket_sla_resolution_due = $row['ticket_sla_resolution_due'] ?? null;
        $now = new DateTime();
        $sla_contract_name = '';
        if ($ticket_contract_id) {
            $sql_ct = mysqli_query($mysqli, "SELECT contract_name FROM contracts WHERE contract_id = $ticket_contract_id LIMIT 1");
            if ($ctr = mysqli_fetch_assoc($sql_ct)) $sla_contract_name = nullable_htmlentities($ctr['contract_name']);
        }

        // Tab Title // No Sanitizing needed
        $page_title = $row['ticket_subject'];
        $tab_title = "{$row['ticket_prefix']}{$row['ticket_number']}";

        $contact_id = intval($row['contact_id']);
        $contact_name = nullable_htmlentities($row['contact_name']);
        $contact_title = nullable_htmlentities($row['contact_title']);
        $contact_email = nullable_htmlentities($row['contact_email']);
        $contact_phone_country_code = nullable_htmlentities($row['contact_phone_country_code']);
        $contact_phone = nullable_htmlentities(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
        $contact_extension = nullable_htmlentities($row['contact_extension']);
        $contact_mobile_country_code = nullable_htmlentities($row['contact_mobile_country_code']);
        $contact_mobile = nullable_htmlentities(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));

        $asset_id = intval($row['asset_id']);
        $asset_ip = nullable_htmlentities($row['interface_ip']);
        $asset_name = nullable_htmlentities($row['asset_name']);
        $asset_type = nullable_htmlentities($row['asset_type']);
        $asset_uri = nullable_htmlentities($row['asset_uri']);
        $asset_make = nullable_htmlentities($row['asset_make']);
        $asset_model = nullable_htmlentities($row['asset_model']);
        $asset_serial = nullable_htmlentities($row['asset_serial']);
        $asset_os = nullable_htmlentities($row['asset_os']);
        $asset_warranty_expire = nullable_htmlentities($row['asset_warranty_expire']);
        $asset_icon = getAssetIcon($asset_type);

        $vendor_id = intval($row['ticket_vendor_id']);
        $vendor_name = nullable_htmlentities($row['vendor_name']);
        $vendor_description = nullable_htmlentities($row['vendor_description']);
        $vendor_account_number = nullable_htmlentities($row['vendor_account_number']);
        $vendor_contact_name = nullable_htmlentities($row['vendor_contact_name']);
        $vendor_phone_country_code = nullable_htmlentities($row['vendor_phone_country_code']);
        $vendor_phone = nullable_htmlentities(formatPhoneNumber($row['vendor_phone'], $vendor_phone_country_code));
        $vendor_extension = nullable_htmlentities($row['vendor_extension']);
        $vendor_email = nullable_htmlentities($row['vendor_email']);
        $vendor_website = nullable_htmlentities($row['vendor_website']);
        $vendor_hours = nullable_htmlentities($row['vendor_hours']);
        $vendor_sla = nullable_htmlentities($row['vendor_sla']);
        $vendor_code = nullable_htmlentities($row['vendor_code']);
        $vendor_notes = nullable_htmlentities($row['vendor_notes']);

        $location_id = intval($row['location_id']);
        $location_name = nullable_htmlentities($row['location_name']);
        $location_address = nullable_htmlentities($row['location_address']);
        $location_city = nullable_htmlentities($row['location_city']);
        $location_state = nullable_htmlentities($row['location_state']);
        $location_zip = nullable_htmlentities($row['location_zip']);
        $location_phone = formatPhoneNumber($row['location_phone']);

        $quote_id = intval($row['ticket_quote_id']);
        $quote_prefix = nullable_htmlentities($row['quote_prefix']);
        $quote_number = intval($row['quote_number']);
        $quote_created_at = nullable_htmlentities($row['quote_created_at']);

        $invoice_id = intval($row['ticket_invoice_id']);
        $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $invoice_created_at = nullable_htmlentities($row['invoice_created_at']);

        $project_id = intval($row['project_id']);
        $project_prefix = nullable_htmlentities($row['project_prefix']);
        $project_number = intval($row['project_number']);
        $project_name = nullable_htmlentities($row['project_name']);
        $project_description = nullable_htmlentities($row['project_description']);
        $project_due = nullable_htmlentities($row['project_due']);
        $project_manager = nullable_htmlentities($row['project_manager']);

        if($project_manager) {
            $sql_project_manager = mysqli_query($mysqli,"SELECT * FROM users WHERE user_id = $project_manager");
            $row = mysqli_fetch_assoc($sql_project_manager);
            $project_manager_name = nullable_htmlentities($row['user_name']);
        }

        if ($contact_id) {
            //Get Contact Ticket Stats
            $ticket_related_open = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_related_open FROM tickets WHERE ticket_status != 'Closed' AND ticket_contact_id = $contact_id ");
            $row = mysqli_fetch_assoc($ticket_related_open);
            $ticket_related_open = intval($row['ticket_related_open']);

            $ticket_related_closed = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_related_closed  FROM tickets WHERE ticket_status = 'Closed' AND ticket_contact_id = $contact_id ");
            $row = mysqli_fetch_assoc($ticket_related_closed);
            $ticket_related_closed = intval($row['ticket_related_closed']);

            $ticket_related_total = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_related_total FROM tickets WHERE ticket_contact_id = $contact_id ");
            $row = mysqli_fetch_assoc($ticket_related_total);
            $ticket_related_total = intval($row['ticket_related_total']);
        }

        //Get Total Ticket Time
        $ticket_total_reply_time = mysqli_query($mysqli, "SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(ticket_reply_time_worked))) AS ticket_total_reply_time FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_assoc($ticket_total_reply_time);
        $ticket_total_reply_time = nullable_htmlentities($row['ticket_total_reply_time']);

        // Get the number of ticket Responses
        $ticket_responses_sql = mysqli_query($mysqli, "SELECT COUNT(ticket_reply_id) AS ticket_responses FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_assoc($ticket_responses_sql);
        $ticket_responses = intval($row['ticket_responses']);

        $ticket_all_comments_sql = mysqli_query($mysqli, "SELECT COUNT(ticket_reply_id) AS ticket_all_comments_count FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_assoc($ticket_all_comments_sql);
        $ticket_all_comments_count = intval($row['ticket_all_comments_count']);

        $ticket_internal_notes_sql = mysqli_query($mysqli, "SELECT COUNT(ticket_reply_id) AS ticket_internal_notes_count FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_type IN ('Internal','System','Automation','RMM Alert','Labor') AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_assoc($ticket_internal_notes_sql);
        $ticket_internal_notes_count = intval($row['ticket_internal_notes_count']);

        $ticket_public_comments_sql = mysqli_query($mysqli, "SELECT COUNT(ticket_reply_id) AS ticket_public_comments_count FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND (ticket_reply_type = 'Public' OR ticket_reply_type = 'Client') AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_assoc($ticket_public_comments_sql);
        $ticket_public_comments_count = intval($row['ticket_public_comments_count']);

        $ticket_events_sql = mysqli_query($mysqli, "SELECT COUNT(log_id) AS ticket_events_count FROM logs WHERE log_type = 'Ticket' AND  log_entity_id = $ticket_id");
        $row = mysqli_fetch_assoc($ticket_events_sql);
        $ticket_events_count = intval($row['ticket_events_count']);


        // Get & format asset warranty expiry
        $date = date('Y-m-d H:i:s');
        $dt_value = $asset_warranty_expire; //sample date
        $warranty_check = date('m/d/Y', strtotime('-8 hours'));
        if ($dt_value <= $date) {
            $dt_value = "Expired on $asset_warranty_expire";
            $warranty_status_color = 'red';
        } else {
            $warranty_status_color = 'green';
        }

        if ($asset_warranty_expire == "NULL") {
            $dt_value = "None";
            $warranty_status_color = 'red';
        }


        // Get ticket replies
        $sql_ticket_replies = mysqli_query($mysqli, "SELECT * FROM ticket_replies
            LEFT JOIN users ON ticket_reply_by = user_id
            LEFT JOIN contacts ON ticket_reply_by = contact_id
            WHERE ticket_reply_ticket_id = $ticket_id
            AND ticket_reply_archived_at IS NULL
            ORDER BY ticket_reply_id DESC"
        );

        // Get ticket Events
        $sql_ticket_events = mysqli_query($mysqli, "SELECT * FROM ticket_history
            WHERE ticket_history_ticket_id = $ticket_id
            ORDER BY ticket_history_id DESC"
        );

        // Get Technicians to assign the ticket to
        $sql_assign_to_select = mysqli_query(
            $mysqli,
            "SELECT user_id, user_name FROM users
            WHERE user_role_id > 1
            AND user_type = 1
            AND user_status = 1
            AND user_archived_at IS NULL
            ORDER BY user_name ASC"
        );


        // Get Watchers
        $sql_ticket_watchers = mysqli_query($mysqli, "SELECT * FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id ORDER BY watcher_email DESC");

        // Get Additional Assets
        $sql_additional_assets = mysqli_query($mysqli, "SELECT * FROM assets, ticket_assets
            WHERE assets.asset_id = ticket_assets.asset_id
            AND ticket_id = $ticket_id
            AND assets.asset_id != $asset_id"
        );

        // Get Ticket Attachments
        $sql_ticket_attachments = mysqli_query(
            $mysqli,
            "SELECT * FROM ticket_attachments
            WHERE ticket_attachment_reply_id IS NULL
            AND ticket_attachment_ticket_id = $ticket_id"
        );


        // Get Charges
        $sql_charges = mysqli_query($mysqli, "SELECT tc.*, t.tax_percent, t.tax_name, lt.labor_type_name, lt.labor_type_color FROM ticket_charges tc LEFT JOIN taxes t ON tc.charge_tax_id = t.tax_id LEFT JOIN labor_types lt ON tc.charge_labor_type_id = lt.labor_type_id WHERE tc.charge_ticket_id = $ticket_id AND tc.charge_archived_at IS NULL ORDER BY tc.charge_id ASC");
        $charge_rows = [];
        $charges_subtotal = 0.00;
        while ($cr = mysqli_fetch_assoc($sql_charges)) {
            $charges_subtotal += floatval($cr['charge_total']);
            $charge_rows[] = $cr;
        }

        // Get Tasks
        $sql_tasks = mysqli_query( $mysqli, "SELECT * FROM tasks WHERE task_ticket_id = $ticket_id ORDER BY task_order ASC, task_id ASC");
        $task_count = mysqli_num_rows($sql_tasks);

        // Get Completed Task Count
        $sql_tasks_completed = mysqli_query($mysqli,
            "SELECT * FROM tasks
            WHERE task_ticket_id = $ticket_id
            AND task_completed_at IS NOT NULL"
        );
        $completed_task_count = mysqli_num_rows($sql_tasks_completed);

        // Tasks Completed Percent
        if ($task_count) {
            $tasks_completed_percent = round(($completed_task_count / $task_count) * 100);
        }

        // Get all Assigned ticket Users as a comma-separated string
        $sql_ticket_collaborators = mysqli_query($mysqli, "
            SELECT GROUP_CONCAT(DISTINCT user_name SEPARATOR ', ') AS user_names
            FROM users
            LEFT JOIN ticket_replies ON user_id = ticket_reply_by
            WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id
        ");

        // Fetch the result
        $row = mysqli_fetch_assoc($sql_ticket_collaborators);

        // The user names in a comma-separated string
        $ticket_collaborators = nullable_htmlentities($row['user_names']);

        ?>

        <!-- Breadcrumbs-->
        <ol class="breadcrumb d-print-none">
             <li class="breadcrumb-item">
                <a href="tickets.php">All Tickets</a>
            </li>
            <?php if ($client_url) { ?>
            <li class="breadcrumb-item">
                <a href="tickets.php?client_id=<?php echo $client_id; ?>"><?= $client_name ?> Tickets</a>
            </li>
            <?php } ?>
            <li class="breadcrumb-item active"><?php echo "$ticket_prefix$ticket_number";?></li>
        </ol>

        <div class="card">
            <div class="card-header pb-2">
                <div class="card-title">
                    <div class="media">
                        <i class="fa fa-fw fa-2x fa-life-ring mr-2"></i>
                        <div class="media-body">
                            <div class="text-bold">Ticket <?= "$ticket_prefix$ticket_number" ?>
                                <span class='badge badge-pill text-light ml-1 p-2' style="background-color: <?= $ticket_status_color ?>">
                                    <?= $ticket_status_name ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (lookupUserPermission("module_support") >= 2) { ?>
                    <div class="card-tools d-print-none">
                        <div class="btn-toolbar">

                            <?php if ($config_module_enable_accounting && $ticket_billable == 1 && empty($quote_id) && empty($invoice_id) && lookupUserPermission("module_sales") >= 2) { ?>
                            <a href="#" class="btn btn-light btn-sm ml-3 ajax-modal" href="#" data-modal-url="modals/ticket/ticket_quote_add.php?ticket_id=<?= $ticket_id ?>" data-modal-size="lg">
                                <i class="fas fa-fw fa-comment-dollar mr-2"></i>Quote
                            </a>
                            <?php }

                            if ($config_module_enable_accounting && $ticket_billable == 1 && empty($invoice_id) && lookupUserPermission("module_sales") >= 2) { ?>
                                <a href="#" class="btn btn-light btn-sm ml-3 ajax-modal" href="#" data-modal-url="modals/ticket/ticket_invoice_add.php?ticket_id=<?= $ticket_id ?>" data-modal-size="lg">
                                    <i class="fas fa-fw fa-file-invoice mr-2"></i>Invoice
                                </a>
                            <?php }

                            if (empty($ticket_closed_at)) { ?>

                                <?php if (empty($ticket_closed_at) && !empty($ticket_resolved_at)) { ?>
                                    <a href="post.php?reopen_ticket=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-light btn-sm ml-3">
                                        <i class="fas fa-fw fa-redo mr-2"></i>Reopen
                                    </a>
                                <?php } ?>

                                <?php if (empty($ticket_resolved_at) && $task_count == $completed_task_count) { ?>
                                    <a href="post.php?resolve_ticket=<?php echo $ticket_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>" class="btn btn-dark btn-sm confirm-link ml-3" id="ticket_close">
                                        <i class="fas fa-fw fa-check mr-2"></i>Resolve
                                    </a>
                                <?php } ?>

                                <?php if (!empty($ticket_resolved_at) && $task_count == $completed_task_count) { ?>
                                    <a href="post.php?close_ticket=<?php echo $ticket_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>" class="btn btn-dark btn-sm confirm-link ml-3" id="ticket_close">
                                        <i class="fas fa-fw fa-gavel mr-2"></i>Close
                                    </a>
                                <?php } ?>

                                <div class="dropdown dropleft text-center ml-3 mr-2">
                                    <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                                        <i class="fas fa-fw fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_summary.php?ticket_id=<?= $ticket_id ?>" data-modal-size="lg">
                                            <i class="fas fa-fw fa-lightbulb mr-2"></i>Summarize
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_merge.php?ticket_id=<?= $ticket_id ?>">
                                            <i class="fas fa-fw fa-clone mr-2"></i>Merge Ticket
                                        </a>
                                        <?php if (empty($ticket_closed_at) && $client_id) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item ajax-modal" href="#"
                                                data-modal-url="modals/ticket/ticket_contact.php?id=<?= $ticket_id ?>">
                                                <i class="fa fa-fw fa-user mr-2"></i>Add Contact
                                            </a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_edit_asset.php?id=<?= $ticket_id ?>">
                                                <i class="fas fa-fw fa-desktop mr-2"></i>Add Asset
                                            </a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_edit_vendor.php?ticket_id=<?= $ticket_id ?>">
                                                <i class="fas fa-fw fa-building mr-2"></i>Add Vendor
                                            </a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_add_watcher.php?ticket_id=<?= $ticket_id ?>">
                                                <i class="fas fa-fw fa-users mr-2"></i>Add Watcher
                                            </a>
                                        <?php } ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item ajax-modal" href="#" id="clientChangeTicketModalLoad" data-modal-url="modals/ticket/ticket_change_client.php?ticket_id=<?= $ticket_id ?>">
                                            <i class="fas fa-fw fa-people-carry mr-2"></i>Change Client
                                        </a>
                                        <?php if (lookupUserPermission("module_support") == 3) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_ticket=<?php echo $ticket_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

            </div> <!-- Card Header -->
        </div> <!-- End Card -->

        <div class="card-group mb-3">

            <div class="card card-body">

                <?php if ($ticket_updated_at) { ?>
                <div title="<?= $ticket_updated_at ?>">
                    <i class="fa fa-fw fa-history text-secondary mr-2"></i>Updated: <strong><?= date('M d, Y • g:i A', strtotime($ticket_updated_at)) . "</strong> <span class='text-muted small'>($ticket_updated_at_ago)</span>" ?>
                </div>
                <?php } ?>
                <!-- Ticket assign -->
                <div class="mt-1 d-flex align-items-center">
                    <i class="fas fa-fw fa-user-tie mr-2 text-secondary"></i>
                    <?php if (empty($ticket_closed_at) && lookupUserPermission("module_support") >= 2) { ?>
                    <select id="quickAssignSelect" class="form-control form-control-sm" style="max-width:180px;" data-ticket-id="<?= $ticket_id ?>" data-csrf="<?= $_SESSION['csrf_token'] ?>">
                        <option value="0" <?= !$ticket_assigned_to ? 'selected' : '' ?>>— Unassigned —</option>
                        <?php
                        mysqli_data_seek($sql_assign_to_select, 0);
                        while ($u = mysqli_fetch_assoc($sql_assign_to_select)) {
                            $uid = intval($u['user_id']);
                            $uname = nullable_htmlentities($u['user_name']);
                            echo "<option value=\"$uid\"" . ($uid === $ticket_assigned_to ? ' selected' : '') . ">$uname</option>";
                        }
                        ?>
                    </select>
                    <span id="quickAssignStatus" class="ml-2" style="font-size:13px;"></span>
                    <?php } else { ?>
                    <span><?= $ticket_assigned_to_display ?></span>
                    <?php } ?>
                </div>
                <!-- End ticket assign -->
                <div class="mt-1">
                    <span class="text-info" id="ticket_collision_viewing"></span>
                </div>
            </div>

            <div class="card card-body">
                <div>
                    <a href="#" title="Priority"
                        <?php if (lookupUserPermission("module_support") >= 2 && empty($ticket_closed_at)) { ?>
                            class="ajax-modal"
                            data-modal-url="modals/ticket/ticket_priority.php?id=<?= $ticket_id ?>"
                        <?php } ?>
                    >
                        <?= $ticket_priority_display ?>
                    </a>
                </div>

                <!-- Ticket scheduling -->
                <?php if (empty($ticket_closed_at)) { ?>
                <div class="mt-1">
                    <i class="fa fa-fw fa-calendar-check text-secondary mr-1"></i>
                    <?php if ($ticket_scheduled_for) { ?>
                        <a class="ajax-modal font-weight-bold" href="#" data-modal-url="modals/ticket/ticket_edit_schedule.php?ticket_id=<?= $ticket_id ?>"><?= $ticket_scheduled_wording ?></a>
                        <?php if ($ticket_onsite) { ?><span class="badge badge-warning ml-1">Onsite</span><?php } else { ?><span class="badge badge-secondary ml-1">Remote</span><?php } ?>
                        <?php if ($ticket_appt_notes) { ?><br><small class="text-muted ml-4"><?= $ticket_appt_notes ?></small><?php } ?>
                    <?php } else { ?>
                        <a class="ajax-modal text-muted" href="#" data-modal-url="modals/ticket/ticket_edit_schedule.php?ticket_id=<?= $ticket_id ?>"><i class="fa fa-plus mr-1"></i>Schedule</a>
                    <?php } ?>
                </div>
                <?php } ?>
                <!-- End ticket scheduling -->

                <!-- SLA -->
                <?php if ($ticket_contract_id && ($ticket_sla_response_due || $ticket_sla_resolution_due)) { ?>
                <div class="mt-2 border-top pt-2">
                    <div class="mb-1"><i class="fas fa-fw fa-stopwatch text-secondary mr-1"></i><small class="text-uppercase font-weight-bold" style="letter-spacing:.4px;">SLA</small>
                        <?php if ($sla_contract_name) { ?><small class="text-muted ml-1">(<?= $sla_contract_name ?>)</small><?php } ?>
                    </div>
                    <?php
                    $sla_items = [
                        'Response'   => $ticket_sla_response_due,
                        'Resolution' => $ticket_sla_resolution_due,
                    ];
                    foreach ($sla_items as $sla_label => $sla_dt) {
                        if (!$sla_dt) continue;
                        $due = new DateTime($sla_dt);
                        $diff = $now->diff($due);
                        $breached = $sla_dt < date('Y-m-d H:i:s');
                        $color = $breached ? 'danger' : ($diff->days == 0 && $diff->h < 2 ? 'warning' : 'success');
                        $label = $breached ? 'Breached' : ($diff->days > 0 ? "in {$diff->days}d {$diff->h}h" : "in {$diff->h}h {$diff->i}m");
                    ?>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted"><?= $sla_label ?>:</small>
                        <span class="badge badge-<?= $color ?> ml-1" title="<?= date('M j, Y g:i A', strtotime($sla_dt)) ?>"><?= $label ?></span>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
                <!-- End SLA -->

                <!-- Billable -->
                <?php if ($config_module_enable_accounting && lookupUserPermission("module_sales") >= 1) { ?>

                    <?php if ($quote_id) { ?>
                        <div class="mt-1">
                            <i class="fa fa-fw fa-comment-dollar text-secondary mr-2"></i>Quoted: <a href="quote.php?quote_id=<?php echo $quote_id ?>"><?php echo "$quote_prefix$quote_number"; ?></a>
                        </div>
                    <?php } ?>

                    <?php if ($invoice_id) { ?>
                        <div class="mt-1">
                            <i class="fa fa-fw fa-dollar-sign text-secondary mr-2"></i>Invoiced: <a href="invoice.php?invoice_id=<?php echo $invoice_id ?>"><?php echo "$invoice_prefix$invoice_number"; ?></a>
                        </div>
                    <?php } else { ?>
                        <div class="mt-1">
                            <i class="fa fa-fw fa-dollar-sign text-secondary mr-2"></i>Billable:
                            <a class="ajax-modal" href="#"
                               data-modal-url="modals/ticket/ticket_billable.php?id=<?= $ticket_id ?>">
                                <?php
                                if ($ticket_billable == 1) {
                                    echo "<span class='text-bold text-dark'>Yes</span>";
                                } else {
                                    echo "<span class='text-muted'>No</span>";
                                }
                                ?>
                            </a>
                        </div>
                    <?php } ?>

                <?php } ?>
                <!-- End billable options -->

            </div>

            <div class="card card-body">
                <?php if ($task_count) { ?>
                    <div><strong>Tasks</strong> <?= "$completed_task_count/$task_count ($tasks_completed_percent%)" ?></div>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar" style="width: <?php echo $tasks_completed_percent; ?>%;"></div>
                    </div>
                <?php } ?>
            </div>

        </div>

        <div class="row">

            <div class="col-md-9">

                <div class="card card-dark mb-3">

                    <div class="card-header px-3 py-2">
                        <h5 class="card-title mt-1"><?= $ticket_subject ?></h5>
                        <?php if (empty($ticket_closed_at)) { ?>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool ajax-modal" data-modal-url="modals/ticket/ticket_edit.php?id=<?= $ticket_id ?>" data-modal-size="lg"><i class="fas fa-edit"></i></button>
                        </div>
                        <?php } ?>
                    </div>

                    <div class="card-body p-3 prettyContent" id="ticketDetails">
                        <?php echo $ticket_details; ?>

                        <?php
                        while ($ticket_attachment = mysqli_fetch_assoc($sql_ticket_attachments)) {
                            $name = nullable_htmlentities($ticket_attachment['ticket_attachment_name']);
                            $ref_name = nullable_htmlentities($ticket_attachment['ticket_attachment_reference_name']);
                            echo "<hr class=''><i class='fas fa-fw fa-paperclip text-secondary mr-1'></i>$name <a target='_blank' class='mr-1 ml-1' href='../uploads/tickets/$ticket_id/$ref_name'>[View]</a><a href='../uploads/tickets/$ticket_id/$ref_name' download='$name'>[Download]</a>";
                        }
                        ?>
                    </div>

                </div>

                <!-- Only show ticket reply modal if status is not closed -->
                <?php if (lookupUserPermission("module_support") >= 2 && empty($ticket_resolved_at) && empty($ticket_closed_at)) { ?>

                        <form action="post.php" method="post" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="ticket_id" id="ticket_id" value="<?php echo $ticket_id; ?>">
                            <input type="hidden" name="client_id" id="client_id" value="<?php echo $client_id; ?>">

                            <div class="card card-body d-print-none p-3">

                                <div class="form-group mb-0">
                                    <div class="btn-group btn-block btn-group-toggle" data-toggle="buttons">
                                        <label class="btn btn-outline-dark active">
                                            <input type="radio" name="public_reply_type" value="0" checked>Internal
                                        </label>
                                        <?php if ($contact_email) { ?>
                                        <label class="btn btn-outline-info">
                                            <input type="radio" name="public_reply_type" value="2">Public + Email
                                        </label>
                                        <?php } ?>
                                        <label class="btn btn-outline-info">
                                            <input type="radio" name="public_reply_type" value="1">Public
                                        </label>
                                    </div>
                                </div>

                            </div>

                            <div class="form-group">
                                <div id="ticket-reply-draft-banner" class="alert alert-warning mb-2" style="display:none;border-radius:6px;padding:8px 14px;align-items:center;justify-content:space-between;">
                                    <span><i class="fas fa-history mr-2"></i>You have an unsaved draft for this ticket.</span>
                                    <div>
                                        <button type="button" id="draft-restore-btn" class="btn btn-sm btn-warning mr-2">Restore Draft</button>
                                        <button type="button" id="draft-discard-btn" class="btn btn-sm btn-outline-secondary">Discard</button>
                                    </div>
                                </div>
                                <?php
                                $sql_canned_responses = mysqli_query($mysqli, "SELECT canned_response_id, canned_response_name, canned_response_message FROM canned_responses WHERE canned_response_archived_at IS NULL ORDER BY canned_response_name ASC");
                                $canned_responses_list = [];
                                while ($cr = mysqli_fetch_assoc($sql_canned_responses)) $canned_responses_list[] = $cr;
                                ?>
                                <?php if ($canned_responses_list) { ?>
                                <div class="dropdown mb-2">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="cannedResponseDropdown" data-toggle="dropdown">
                                        <i class="fas fa-fw fa-comment-dots mr-1"></i>Insert Canned Response
                                    </button>
                                    <div class="dropdown-menu">
                                        <?php foreach ($canned_responses_list as $cr) {
                                            $cr_name = nullable_htmlentities($cr['canned_response_name']);
                                            $cr_message_json = json_encode($cr['canned_response_message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                        ?>
                                            <a class="dropdown-item insert-canned-response" href="#" data-message='<?= $cr_message_json ?>'><?= $cr_name ?></a>
                                        <?php } ?>
                                    </div>
                                </div>
                                <?php } ?>
                                <textarea
                                    id="ticket-reply-editor"
                                    class="form-control tinymceTicket" name="ticket_reply"
                                    placeholder="Type a response">
                                </textarea>
                            </div>

                            <?php
                            $sql_lt_reply = mysqli_query($mysqli, "SELECT labor_type_id, labor_type_name, labor_type_color FROM labor_types WHERE labor_type_archived_at IS NULL ORDER BY labor_type_order ASC, labor_type_name ASC");
                            $lt_reply_rows = [];
                            while ($lt = mysqli_fetch_assoc($sql_lt_reply)) $lt_reply_rows[] = $lt;
                            ?>
                            <div class="form-row align-items-center" style="background:#f8f9fa;border-top:1px solid #dee2e6;margin:0 -20px;padding:10px 20px 4px;">

                                <!-- Labor Type (most prominent, first) -->
                                <?php if ($lt_reply_rows) { ?>
                                <div class="col-auto">
                                    <div class="form-group mb-2">
                                        <select class="form-control font-weight-bold" name="reply_labor_type_id" id="reply_labor_type_id"
                                                style="border:2px solid #343a40;border-radius:6px;background:#343a40;color:#fff;min-width:150px;cursor:pointer;">
                                            <option value="0" style="background:#fff;color:#343a40;">— Labor Type —</option>
                                            <?php foreach ($lt_reply_rows as $lt) { ?>
                                            <option value="<?= intval($lt['labor_type_id']) ?>"
                                                    data-color="<?= nullable_htmlentities($lt['labor_type_color']) ?>"
                                                    style="background:#fff;color:#343a40;">
                                                <?= nullable_htmlentities($lt['labor_type_name']) ?>
                                            </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <?php } ?>

                                <!-- Time tracking -->
                                <div class="col-auto">
                                    <div class="form-group mb-2">
                                        <div class="input-group">
                                            <input type="text" class="form-control" inputmode="numeric" id="hours" name="hours" placeholder="Hrs" style="width:60px;max-width:60px;">
                                            <input type="text" class="form-control" inputmode="numeric" id="minutes" name="minutes" placeholder="Mins" style="width:60px;max-width:60px;">
                                            <input type="text" class="form-control" inputmode="numeric" id="seconds" name="seconds" placeholder="Secs" style="width:60px;max-width:60px;">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-light" id="startStopTimer"><i class="fas fa-play"></i></button>
                                                <button type="button" class="btn btn-light" id="resetTimer"><i class="fas fa-redo-alt"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Charge now -->
                                <?php if ($config_module_enable_accounting && $lt_reply_rows) { ?>
                                <div class="col-auto">
                                    <div class="form-group mb-2">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="reply_charge_now" name="reply_charge_now" value="1" checked>
                                            <label class="custom-control-label font-weight-600" for="reply_charge_now">Charge now</label>
                                        </div>
                                    </div>
                                </div>
                                <?php } ?>

                                <!-- Status -->
                                <div class="col-auto flex-grow-1">
                                    <div class="form-group mb-2">
                                        <select class="form-control select2" name="status" required>
                                            <?php
                                            $status_snippet = '';
                                            if ($task_count !== $completed_task_count) {
                                                $status_snippet = "AND ticket_status_id != 4";
                                            }
                                            $sql_ticket_status = mysqli_query($mysqli, "SELECT * FROM ticket_statuses WHERE ticket_status_id != 1 AND ticket_status_id != 5 AND ticket_status_active = 1 $status_snippet ORDER BY ticket_status_order");
                                            while ($row = mysqli_fetch_assoc($sql_ticket_status)) {
                                                $ticket_status_id_select = intval($row['ticket_status_id']);
                                                $ticket_status_name_select = nullable_htmlentities($row['ticket_status_name']); ?>
                                                <option value="<?= $ticket_status_id_select ?>" <?php if ($ticket_status == $ticket_status_id_select) echo 'selected'; ?>><?= $ticket_status_name_select ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Submit -->
                                <div class="col-auto">
                                    <div class="form-group mb-2">
                                        <button type="submit" id="ticket_add_reply" name="add_ticket_reply" class="btn btn-success"><i class="fas fa-check mr-2"></i>Submit</button>
                                    </div>
                                </div>

                            </div>

                        </form>

                    <!-- End IF for reply modal -->
                <?php } ?>

                <!-- Ticket replies -->
                <?php

                while ($row = mysqli_fetch_assoc($sql_ticket_replies)) {
                    $ticket_reply_id = intval($row['ticket_reply_id']);
                    $ticket_reply = $purifier->purify($row['ticket_reply']);
                    $ticket_reply_type = nullable_htmlentities($row['ticket_reply_type']);
                    $ticket_reply_type_border = ['Internal' => 'dark', 'Public' => 'warning', 'Client' => 'warning', 'System' => 'secondary', 'Automation' => 'primary', 'RMM Alert' => 'danger', 'Labor' => 'success'][$ticket_reply_type] ?? 'info';
                    $ticket_reply_type_label = ['Internal' => 'Internal Note', 'Public' => 'Client Reply', 'Client' => 'Client Reply', 'System' => 'System Note', 'Automation' => 'Automation Note', 'RMM Alert' => 'RMM Alert Note', 'Labor' => 'Labor Note'][$ticket_reply_type] ?? $ticket_reply_type;
                    $ticket_reply_created_at = nullable_htmlentities($row['ticket_reply_created_at']);
                    $ticket_reply_created_at_ago = timeAgo($row['ticket_reply_created_at']);
                    $ticket_reply_updated_at = nullable_htmlentities($row['ticket_reply_updated_at']);
                    $ticket_reply_updated_at_ago = timeAgo($row['ticket_reply_updated_at']);
                    $ticket_reply_by = intval($row['ticket_reply_by']);

                    if ($ticket_reply_type == "Client") {
                        $ticket_reply_by_display = nullable_htmlentities($row['contact_name']);
                        $user_initials = initials($row['contact_name']);
                        $user_avatar = nullable_htmlentities($row['contact_photo']);
                        $avatar_link = "../uploads/clients/$client_id/$user_avatar";
                    } else {
                        $ticket_reply_by_display = nullable_htmlentities($row['user_name']);
                        $user_id = intval($row['user_id']);
                        $user_avatar = nullable_htmlentities($row['user_avatar']);
                        $user_initials = initials($row['user_name']);
                        $avatar_link = "../uploads/users/$user_id/$user_avatar";
                        $ticket_reply_time_worked = $row['ticket_reply_time_worked'];
                    }

                    $sql_ticket_reply_attachments = mysqli_query(
                        $mysqli,
                        "SELECT * FROM ticket_attachments
                        WHERE ticket_attachment_reply_id = $ticket_reply_id
                        AND ticket_attachment_ticket_id = $ticket_id"
                    );

                    ?>

                    <!-- Begin ticket reply card -->
                    <div class="card border-left border-<?= $ticket_reply_type_border ?> mb-3" style="border-left-width: 8px !important;">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center w-100">
                                <!-- Left side content -->
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($user_avatar)) { ?>
                                        <img src="<?php echo $avatar_link; ?>" alt="User Avatar" class="img-size-50 mr-3 img-circle">
                                    <?php } else { ?>
                                        <span class="fa-stack fa-2x">
                                            <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                                            <span class="fa fa-stack-1x text-white"><?php echo $user_initials; ?></span>
                                        </span>
                                    <?php } ?>

                                    <div class="ml-2">
                                        <h3 class="card-title"><?php echo $ticket_reply_by_display; ?></h3>
                                        <div>
                                            <?php if ($ticket_reply_type !== "Client" && $ticket_reply_time_worked !== "00:00:00") { ?>
                                                <div>
                                                    <br>
                                                    <small>
                                                        <i class="far fa-fw fa-clock text-secondary"></i>
                                                        Time worked:
                                                        <span class="text-muted">
                                                            <?= formatDuration($ticket_reply_time_worked) ?>
                                                        </span>
                                                    </small>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right-side content -->
                                <div class="text-right d-flex flex-column align-items-end">
                                    <div class="card-tools d-print-none mb-2">
                                        <div class="dropdown dropleft">
                                            <?php if (lookupUserPermission("module_support") >= 2) { ?>
                                                <button class="btn btn-sm btn-tool" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                                                    <i class="fas fa-fw fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a href="#" class="dropdown-item ajax-modal"
                                                       data-modal-size = "lg"
                                                       data-modal-url="modals/ticket/ticket_reply_redact.php?id=<?= $ticket_reply_id ?>">
                                                        <i class="fas fa-fw fa-pen text-danger mr-2"></i>Redact
                                                    </a>
                                                    <?php if ($ticket_reply_type !== "Client" && empty($ticket_closed_at)) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <?php if (in_array($ticket_reply_type, ['Internal', 'Public'])) { ?>
                                                    <a href="#" class="dropdown-item ajax-modal"
                                                       data-modal-size = "lg"
                                                       data-modal-url="modals/ticket/ticket_reply_edit.php?id=<?=$ticket_reply_id ?>">
                                                        <i class="fas fa-fw fa-edit text-secondary mr-2"></i>Edit
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <?php } ?>
                                                    <a class="dropdown-item text-danger confirm-link" href="post.php?archive_ticket_reply=<?= $ticket_reply_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                    </a>
                                                    <a class="dropdown-item text-danger confirm-link" href="post.php?delete_ticket_reply=<?= $ticket_reply_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                    </a>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <small class="text-muted">
                                        <div title="Created: <?php echo $ticket_reply_created_at; if ($ticket_reply_updated_at) { echo '. Edited: ' . $ticket_reply_updated_at; } ?>">
                                            <?php echo $ticket_reply_type_label . " - " .  $ticket_reply_created_at_ago; if ($ticket_reply_updated_at) { echo '*'; } ?>
                                        </div>
                                    </small>

                                </div>
                            </div>
                        </div>

                        <div class="card-body prettyContent">
                            <?php echo $ticket_reply; ?>

                            <?php
                            while ($ticket_attachment = mysqli_fetch_assoc($sql_ticket_reply_attachments)) {
                                $name = nullable_htmlentities($ticket_attachment['ticket_attachment_name']);
                                $ref_name = nullable_htmlentities($ticket_attachment['ticket_attachment_reference_name']);
                                echo "<hr><i class='fas fa-fw fa-paperclip text-secondary mr-1'></i>$name | <a href='../uploads/tickets/$ticket_id/$ref_name' download='$name'><i class='fas fa-fw fa-download mr-1'></i>Download</a> | <a target='_blank' href='../uploads/tickets/$ticket_id/$ref_name'><i class='fas fa-fw fa-external-link-alt mr-1'></i>View</a>";
                            }
                            ?>
                        </div>
                    </div>
                    <!-- End ticket reply card -->

                    <?php

                }

                ?>

            </div>

            <div class="col-md-3">

                <!-- Ticket activity right card -->
                <div class="card">
                    <div class="card-header px-3 py-2">
                        <h5 class="card-title mt-1"><i class="fas fa-fw fa-history mr-2"></i>Activity Summary</h5>

                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-3 ">

                        <!-- Created -->
                        <div>
                            <i class="fas fa-fw fa-calendar-alt text-secondary mr-1"></i><strong class="mr-1">Created:</strong><?= date('M d, Y', strtotime($ticket_date)) ?>
                            <span class="text-muted small">(<?= $ticket_created_at_ago ?>)</span>
                        </div>

                        <!-- Created by -->
                        <?php if ($ticket_created_by) {
                            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = $ticket_created_by"));
                            $ticket_created_by_display = nullable_htmlentities($row['user_name']);
                            ?>

                            <div class="mt-2">
                                <i class="far fa-fw fa-user text-secondary mr-1"></i><strong class="mr-1">Created by:</strong><?= $ticket_created_by_display ?>
                            </div>
                        <?php } ?>

                        <!-- Source -->
                        <?php if ($ticket_source) { ?>
                            <div class="mt-2">
                                <i class="fas fa-fw fa-inbox text-secondary mr-1"></i><strong class="mr-1">Source:</strong><?= $ticket_source ?>
                            </div>
                        <?php } ?>

                        <!-- Category -->
                        <?php if ($ticket_category) { ?>
                            <div class="mt-2">
                                <i class="fas fa-fw fa-layer-group text-secondary mr-1"></i><strong class="mr-1">Category:</strong><?= $ticket_category_display ?>
                            </div>
                        <?php } ?>

                        <!-- First response (for SLA) -->
                        <?php if ($ticket_first_response_at) { ?>
                            <div class="mt-2">
                                <i class="fas fa-fw fa-reply-all text-secondary mr-1"></i><strong class="mr-1">1st  resp:</strong><?= date('M d • g:i A', strtotime($ticket_first_response_at)) ?>
                            </div>
                        <?php } ?>

                        <!-- Time tracking -->
                        <?php if ($ticket_total_reply_time) { ?>
                            <div class="mt-2">
                                <i class="fas fa-fw fa-stopwatch text-secondary mr-1"></i><strong class="mr-1">Total time:</strong><?= formatDuration($ticket_total_reply_time) ?>
                            </div>
                        <?php } ?>

                        <!-- Internal collaborators -->
                        <!-- Commented - there is still something wrong with this -->
<!--                        --><?php //if ($ticket_collaborators) { ?>
<!--                            <div class="mt-1">-->
<!--                                <i class="fas fa-fw fa-users mr-2 text-secondary"></i><strong>Collaborators: </strong>--><?php //echo $ticket_collaborators; ?>
<!--                            </div>-->
<!--                        --><?php //} ?>

                        <!-- Resolved -->
                        <?php if ($ticket_resolved_at) { ?>
                            <hr>
                            <div class="mt-2" title="<?= $ticket_resolved_at ?>">
                                <i class="fas fa-fw fa-check text-secondary mr-1"></i><strong class="mr-1">Resolved:</strong><?= date('M d, Y • g:i A', strtotime($ticket_resolved_at)) . " ($ticket_resolved_at_ago)" ?>
                            </div>
                        <?php } ?>

                        <!-- Ticket closure info -->
                        <?php if ($ticket_closed_at) {

                            $ticket_closed_by_display = 'User';
                            if (!empty($ticket_closed_by)) {
                                $sql_closed_by = mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = $ticket_closed_by");
                                $row = mysqli_fetch_assoc($sql_closed_by);
                                $ticket_closed_by_display = nullable_htmlentities($row['user_name']);
                            }
                            ?>
                            <div class="mt-2">
                                <i class="fas fa-fw fa-user text-secondary mr-1"></i><strong class="mr-1">Closed by:</strong><?= ucwords($ticket_closed_by_display) ?>
                            </div>

                            <div class="mt-2">
                                <i class="fas fa-fw fa-clock text-secondary mr-1"></i><strong class="mr-1">Closed:</strong><?= date('M d, Y • g:i A', strtotime($ticket_closed_at)) . " ($ticket_closed_at_ago)" ?>
                            </div>

                            <?php if ($ticket_feedback) { ?>
                                <div class="mt-2">
                                    <i class="fa fa-fw fa-comment-dots text-secondary mr-1"></i><strong>Feedback: </strong><?php echo $ticket_feedback; ?>
                                </div>
                            <?php } ?>

                        <?php } ?>
                        <!-- END Ticket closure info -->

                    </div>
                </div>
                <!-- End details card -->

                <!-- Asset card -->
                <?php if ($asset_id) { ?>
                    <div class="card">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-desktop mr-2"></i>Assets</h5>
                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                            <div class="card-tools">
                                <a class="btn btn-tool ajax-modal" href="#" data-modal-url="modals/ticket/ticket_edit_asset.php?id=<?= $ticket_id ?>">
                                    <i class="fas fa-fw fa-edit"></i>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-3">
                            <div>
                                <a class="ajax-modal" href="#" data-modal-size="lg"
                                    data-modal-url="modals/asset/asset_details.php?<?= $client_url ?>&id=<?= $asset_id ?>">
                                    <i class="fa fa-fw fa-<?php echo $asset_icon; ?> text-secondary mr-2"></i><strong><?php echo $asset_name; ?></strong>
                                </a>
                            </div>

                            <?php
                            // RMM status for the primary asset (Syncro-Beta)
                            if ($config_module_enable_rmm && lookupUserPermission("module_rmm") >= 1) {
                                $rmm_tklink = mysqli_fetch_assoc(mysqli_query($mysqli,
                                    "SELECT arl.id, arl.rmm_status, arl.hostname, arl.last_seen, arl.os_name, arl.logged_in_user
                                      FROM asset_rmm_links arl WHERE arl.asset_id=$asset_id LIMIT 1"
                                ));
                                if ($rmm_tklink) {
                                    $rmm_badge = $rmm_tklink['rmm_status'] === 'online' ? 'badge-success' : ($rmm_tklink['rmm_status'] === 'offline' ? 'badge-danger' : 'badge-secondary');
                                    ?>
                                    <div class="mt-2 pt-2 border-top small">
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="fas fa-fw fa-server text-secondary mr-2"></i>
                                            <span class="mr-2"><?= nullable_htmlentities($rmm_tklink['hostname']) ?></span>
                                            <span class="badge <?= $rmm_badge ?>"><?= ucfirst($rmm_tklink['rmm_status']) ?></span>
                                        </div>
                                        <div class="text-muted">
                                            OS: <?= nullable_htmlentities($rmm_tklink['os_name']) ?>
                                            &nbsp;&middot;&nbsp; Last seen: <?= nullable_htmlentities($rmm_tklink['last_seen']) ?>
                                            <?php if ($rmm_tklink['logged_in_user']): ?>
                                            &nbsp;&middot;&nbsp; User: <?= nullable_htmlentities($rmm_tklink['logged_in_user']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <a href="/agent/asset_details.php?asset_id=<?= $asset_id ?>" class="btn btn-xs btn-info mt-2">
                                            <i class="fas fa-desktop mr-1"></i>View Asset
                                        </a>
                                    </div>
                                    <?php
                                }
                            }
                            ?>

                            <?php
                            while ($row = mysqli_fetch_assoc($sql_additional_assets)) {
                                $additional_asset_id = intval($row['asset_id']);
                                $additional_asset_name = nullable_htmlentities($row['asset_name']);
                                $additional_asset_type = nullable_htmlentities($row['asset_type']);
                                $additional_asset_icon = getAssetIcon($additional_asset_type);
                                ?>
                                <div class="mt-1">
                                    <a class="ajax-modal" href="#" data-modal-size="lg"
                                        data-modal-url="modals/asset/asset_details.php?<?= $client_url ?>&id=<?= $additional_asset_id ?>">
                                        <i class="fa fa-fw fa-<?php echo $additional_asset_icon; ?> text-secondary mr-2"></i><?php echo $additional_asset_name; ?>
                                    </a>
                                    <?php if (empty($ticket_closed_at)) { ?>
                                        <a class="confirm-link float-right" href="post.php?delete_ticket_additional_asset=<?= $additional_asset_id; ?>&ticket_id=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Remove asset from ticket">
                                            <i class="fas fa-fw fa-times text-secondary"></i>
                                        </a>
                                    <?php } ?>
                                </div>
                            <?php

                            }
                            ?>
                        </div>
                    </div>
                <?php } // End if asset_id ?>
                <!-- End Asset card -->

                <!-- Tasks Card -->
                <?php if (empty($ticket_resolved_at) || (!empty($ticket_resolved_at) && $task_count > 0)) { ?>
                    <div class="card">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-tasks mr-2"></i>Tasks</h5>
                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                            <div class="card-tools">
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-tool" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item text-success" href="post.php?complete_all_tasks=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-check-double mr-2"></i>Mark All Complete
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="post.php?undo_complete_all_tasks=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="far fa-fw fa-square mr-2"></i>Mark All Incomplete
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="#">
                                            <i class="fas fa-fw fa-trash-alt mr-2"></i>Delete All
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-0">

                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                                <form action="post.php" method="post" autocomplete="off">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                                    <div class="form-group px-2 pt-3">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control" name="name" placeholder="Create Task" required maxlength="255">
                                            <div class="input-group-append">
                                                <button type="submit" name="add_task" class="btn btn-outline-primary">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            <?php } ?>

                            <table class="table table-sm" id="tasks">
                                <?php
                                while ($row = mysqli_fetch_assoc($sql_tasks)) {
                                    $task_id = intval($row['task_id']);
                                    $task_name = nullable_htmlentities($row['task_name']);
                                    $task_completion_estimate = intval($row['task_completion_estimate']);
                                    $task_completed_at = nullable_htmlentities($row['task_completed_at']);

                                    // Check for approvals
                                    $task_needs_approval = false;
                                    $task_needs_approval = mysqli_num_rows(mysqli_query(
                                            $mysqli,
                                            "SELECT 1 FROM task_approvals
                                                 WHERE approval_task_id = $task_id
                                                   AND approval_status IN ('pending','declined')
                                                 LIMIT 1"
                                        )) > 0;

                                    $approval_id = 0;
                                    $user_can_approve = false;
                                    $approval_rows = mysqli_query($mysqli, "
                                        SELECT approval_id, approval_scope, approval_type, approval_required_user_id, approval_created_by
                                        FROM task_approvals WHERE approval_task_id = $task_id AND approval_status = 'pending'
                                    ");

                                    while ($approval = mysqli_fetch_assoc($approval_rows)) {

                                        $scope = nullable_htmlentities($approval['approval_scope']);
                                        $type = nullable_htmlentities($approval['approval_type']);
                                        $required_user = intval($approval['approval_required_user_id']);
                                        $created_by = intval($approval['approval_created_by']);

                                        // Named, specific user?
                                        if ($scope == 'internal' && $type == 'specific' && $required_user == $session_user_id) {
                                            $user_can_approve = true;
                                            $approval_id = intval($approval['approval_id']);
                                            continue;
                                        }

                                        // Any internal user, but the one who created the task
                                        if ($scope == 'internal' && $type == 'any' && $created_by !== $session_user_id) {
                                            $user_can_approve = true;
                                            $approval_id = intval($approval['approval_id']);
                                            continue;
                                        }

                                    }

                                    ?>
                                    <tr data-task-id="<?= $task_id ?>">
                                        <td class="px-3">
                                            <?php if ($task_completed_at) { ?>
                                                <i class="far fa-check-square text-success"></i>
                                            <?php } elseif (lookupUserPermission("module_support") >= 2) { ?>

                                                <?php if ($task_needs_approval) { ?>
                                                    <i class="fas fa-shield-alt text-warning"
                                                       data-toggle="tooltip"
                                                       data-placement="top"
                                                       title="Approval required"></i>

                                                    <?php if ($user_can_approve) { ?>
                                                        <a class="confirm-link" href="post.php?approve_ticket_task=<?= $task_id ?>&approval_id=<?= $approval_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                            <i class="fas fa-thumbs-up text-green" title="Approve task"></i>
                                                        </a>
                                                    <?php } ?>

                                                <?php } else { ?>
                                                    <a href="post.php?complete_task=<?= $task_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="far fa-square text-dark"></i>
                                                    </a>
                                                <?php } ?>

                                            <?php } ?>
                                            <span class="text-dark ml-2"><?= $task_name ?></span>
                                        </td>
                                        <td class="px-2">
                                            <div class="float-right">

                                                <div class="btn-group">

                                                    <button class="btn btn-sm btn-link drag-handle"><i class="fas fa-bars text-muted mr-1"></i></button>

                                                    <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>

                                                        <div class="dropdown dropleft text-center">
                                                            <button class="btn btn-light text-secondary btn-sm" type="button" data-toggle="dropdown">
                                                                <i class="fas fa-ellipsis-v"></i>
                                                            </button>
                                                            <div class="dropdown-menu">
                                                                <a class="dropdown-item ajax-modal" href="#"
                                                                   data-modal-url="modals/ticket/ticket_task_edit.php?id=<?= $task_id ?>">
                                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                                </a>
                                                                <?php if (!$task_completed_at) { ?>
                                                                    <a class="dropdown-item ajax-modal" href="#"
                                                                       data-modal-url="modals/ticket/ticket_task_approver_add.php?id=<?= $task_id ?>">
                                                                        <i class="fas fa-fw fa-shield-alt mr-2"></i>Add Approvers
                                                                    </a>
                                                                <?php } ?>
                                                                <?php if ($task_completed_at) { ?>
                                                                    <a class="dropdown-item" href="post.php?undo_complete_task=<?= $task_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                                        <i class="fas fa-fw fa-arrow-circle-left mr-2"></i>Mark incomplete
                                                                    </a>
                                                                <?php } ?>
                                                                <div class="dropdown-divider"></div>
                                                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_task=<?php echo $task_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                                                    <i class="fas fa-fw fa-trash-alt mr-2"></i>Delete
                                                                </a>
                                                            </div>
                                                        </div>

                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </table>
                        </div>
                    </div>
                <?php } ?>
                <!-- End Tasks Card -->

                <!-- Worksheets Card -->
                <?php
                $sql_worksheets = mysqli_query($mysqli, "SELECT tw.*, wt.worksheet_template_name, wt.worksheet_template_id FROM ticket_worksheets tw JOIN worksheet_templates wt ON tw.worksheet_template_id = wt.worksheet_template_id WHERE tw.worksheet_ticket_id = $ticket_id ORDER BY tw.worksheet_created_at ASC");
                $worksheet_count = mysqli_num_rows($sql_worksheets);
                if (empty($ticket_resolved_at) || $worksheet_count > 0) {
                ?>
                <div class="card">
                    <div class="card-header px-3 py-2">
                        <h5 class="card-title mt-1"><i class="fas fa-fw fa-clipboard-list mr-2"></i>Worksheets</h5>
                        <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                        <div class="card-tools">
                            <a href="#" class="btn btn-tool ajax-modal" data-modal-url="modals/ticket/ticket_worksheet_add.php?ticket_id=<?= $ticket_id ?>">
                                <i class="fas fa-plus"></i>
                            </a>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($worksheet_count == 0) { ?>
                            <p class="text-secondary text-center p-3 mb-0">No worksheets yet.</p>
                        <?php } ?>

                        <?php while ($ws_row = mysqli_fetch_assoc($sql_worksheets)) {
                            $ws_id = intval($ws_row['worksheet_id']);
                            $ws_tmpl_id = intval($ws_row['worksheet_template_id']);
                            $ws_name = nullable_htmlentities($ws_row['worksheet_template_name']);
                            $ws_outtake = intval($ws_row['worksheet_is_outtake']);
                            $ws_completed = $ws_row['worksheet_completed_at'];
                            $ws_signed = $ws_row['worksheet_signed_at'];
                            $ws_signed_name = nullable_htmlentities($ws_row['worksheet_signed_name']);
                            $ws_token = nullable_htmlentities($ws_row['worksheet_sign_token']);

                            // Load fields + responses
                            $ws_fields = mysqli_query($mysqli, "SELECT f.*, COALESCE(r.response_value,'') AS response_value FROM worksheet_template_fields f LEFT JOIN ticket_worksheet_responses r ON r.response_field_id = f.field_id AND r.response_worksheet_id = $ws_id WHERE f.field_template_id = $ws_tmpl_id ORDER BY f.field_order");
                            $ws_field_count = mysqli_num_rows($ws_fields);
                            $ws_total = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM worksheet_template_fields WHERE field_template_id = $ws_tmpl_id AND field_type != 'heading'"))[0]);
                            $ws_filled = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM ticket_worksheet_responses r JOIN worksheet_template_fields f ON r.response_field_id = f.field_id WHERE r.response_worksheet_id = $ws_id AND f.field_type != 'heading' AND r.response_value != ''"))[0]);
                            $ws_pct = $ws_total > 0 ? round(($ws_filled / $ws_total) * 100) : ($ws_completed ? 100 : 0);
                            $ws_is_locked = !empty($ws_completed) && !$ws_outtake;
                        ?>

                        <!-- Worksheet: <?= $ws_name ?> -->
                        <div class="border-bottom">
                            <!-- Worksheet Header -->
                            <div class="d-flex align-items-center px-3 py-2" style="cursor:pointer;" data-toggle="collapse" data-target="#ws_body_<?= $ws_id ?>">
                                <i class="fas fa-clipboard-list text-secondary mr-2"></i>
                                <strong class="flex-grow-1"><?= $ws_name ?></strong>
                                <?php if ($ws_signed) { ?>
                                    <span class="badge badge-success mr-2"><i class="fas fa-check mr-1"></i>Signed by <?= $ws_signed_name ?></span>
                                <?php } elseif ($ws_completed) { ?>
                                    <span class="badge badge-secondary mr-2"><i class="fas fa-lock mr-1"></i>Finalized</span>
                                <?php } else { ?>
                                    <span class="badge badge-<?= $ws_pct == 100 ? 'success' : 'primary' ?> mr-2"><?= $ws_pct ?>%</span>
                                <?php } ?>
                                <?php if ($ws_completed && !$ws_signed && lookupUserPermission("module_support") >= 2) { ?>
                                    <a href="post.php?unfinalize_worksheet=<?= $ws_id ?>&ticket_id=<?= $ticket_id ?>&client_id=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                       class="btn btn-xs btn-warning ml-1" title="Unfinalize" onclick="event.stopPropagation();">
                                        <i class="fas fa-lock-open mr-1"></i>Unfinalize
                                    </a>
                                <?php } ?>
                                <?php if (lookupUserPermission("module_support") >= 2 && empty($ticket_resolved_at)) { ?>
                                    <a href="post.php?delete_ticket_worksheet=<?= $ws_id ?>&ticket_id=<?= $ticket_id ?>&client_id=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-xs btn-danger confirm-link ml-1" title="Delete" onclick="event.stopPropagation();"><i class="fas fa-trash"></i></a>
                                <?php } ?>
                                <i class="fas fa-chevron-down text-secondary ml-2"></i>
                            </div>

                            <!-- Worksheet Body (collapsible) -->
                            <div class="collapse show" id="ws_body_<?= $ws_id ?>">
                                <form action="post.php" method="post" autocomplete="off" class="px-3 pb-3 pt-2">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="worksheet_id" value="<?= $ws_id ?>">
                                    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                                    <input type="hidden" name="client_id" value="<?= $client_id ?>">

                                    <?php while ($frow = mysqli_fetch_assoc($ws_fields)) {
                                        $fid = intval($frow['field_id']);
                                        $fname = nullable_htmlentities($frow['field_name']);
                                        $ftype = $frow['field_type'];
                                        $fopts = $frow['field_options'];
                                        $freq = intval($frow['field_required']);
                                        $fval = nullable_htmlentities($frow['response_value']);
                                        $fdisabled = $ws_is_locked ? 'disabled' : '';
                                    ?>

                                    <?php if ($ftype === 'heading') { ?>
                                        <div class="mt-3 mb-2 mx-n3 px-3 py-2" style="background:#e9ecef;border-top:1px solid #dee2e6;border-bottom:1px solid #dee2e6;">
                                            <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#495057;"><?= $fname ?></span>
                                        </div>
                                    <?php } elseif ($ftype === 'checkbox') { ?>
                                        <div class="d-flex align-items-center py-1 border-bottom">
                                            <span class="flex-grow-1 text-sm"><?= $fname ?></span>
                                            <input type="checkbox" name="field_<?= $fid ?>" value="1" <?= $fval == '1' ? 'checked' : '' ?> <?= $fdisabled ?> style="width:18px;height:18px;">
                                        </div>
                                    <?php } elseif ($ftype === 'textarea') { ?>
                                        <div class="form-group mt-2 mb-1">
                                            <label class="mb-0 small text-secondary"><?= $fname ?></label>
                                            <textarea class="form-control form-control-sm" name="field_<?= $fid ?>" rows="2" <?= $freq ? 'required' : '' ?> <?= $fdisabled ?>><?= $fval ?></textarea>
                                        </div>
                                    <?php } elseif ($ftype === 'select') { ?>
                                        <div class="form-group mt-2 mb-1">
                                            <label class="mb-0 small text-secondary"><?= $fname ?></label>
                                            <select class="form-control form-control-sm" name="field_<?= $fid ?>" <?= $fdisabled ?>>
                                                <option value="">- Select -</option>
                                                <?php foreach (array_filter(explode("
", $fopts)) as $opt) {
                                                    $opt = trim($opt);
                                                    echo "<option" . ($fval === $opt ? ' selected' : '') . ">" . htmlspecialchars($opt) . "</option>";
                                                } ?>
                                            </select>
                                        </div>
                                    <?php } elseif ($ftype === 'signature') { ?>
                                        <div class="mt-2 mb-1">
                                            <label class="mb-0 small text-secondary"><?= $fname ?></label>
                                            <?php if ($fval) { ?>
                                                <div><img src="<?= $fval ?>" style="max-height:70px;border:1px solid #ccc;border-radius:4px;background:#fff;display:block;"></div>
                                            <?php } elseif (!$ws_is_locked) { ?>
                                                <canvas id="sig_c_<?= $ws_id ?>_<?= $fid ?>" style="border:1px solid #ccc;border-radius:4px;background:#fff;touch-action:none;display:block;width:100%;height:80px;"></canvas>
                                                <input type="hidden" name="field_<?= $fid ?>" id="sig_d_<?= $ws_id ?>_<?= $fid ?>">
                                                <button type="button" class="btn btn-xs btn-outline-secondary mt-1" onclick="clearWsSig('<?= $ws_id ?>','<?= $fid ?>')">Clear</button>
                                            <?php } ?>
                                        </div>
                                    <?php } else { ?>
                                        <div class="form-group mt-2 mb-1">
                                            <label class="mb-0 small text-secondary"><?= $fname ?></label>
                                            <input type="text" class="form-control form-control-sm" name="field_<?= $fid ?>" value="<?= $fval ?>" <?= $freq ? 'required' : '' ?> <?= $fdisabled ?>>
                                        </div>
                                    <?php } ?>

                                    <?php } // end field loop ?>

                                    <?php if (!$ws_is_locked) { ?>
                                    <div class="d-flex mt-3">
                                        <button type="submit" name="save_worksheet" class="btn btn-sm btn-outline-primary mr-2"><i class="fas fa-save mr-1"></i>Save</button>
                                        <button type="submit" name="complete_worksheet" class="btn btn-sm btn-dark"><i class="fas fa-check mr-1"></i>Finalize</button>
                                    </div>
                                    <?php } ?>

                                </form>
                            </div>
                        </div>

                        <?php } // end worksheet loop ?>
                    </div>
                </div>
                <?php } ?>
                <!-- End Worksheets Card -->

                <!-- Outtake Forms Card -->
                <?php
                $sql_outtakes = mysqli_query($mysqli, "SELECT * FROM ticket_outtake_forms WHERE outtake_ticket_id = $ticket_id ORDER BY outtake_created_at DESC");
                $outtake_count = mysqli_num_rows($sql_outtakes);
                if (empty($ticket_resolved_at) || $outtake_count > 0) {
                ?>
                <div class="card">
                    <div class="card-header px-3 py-2">
                        <h5 class="card-title mt-1"><i class="fas fa-fw fa-file-signature mr-2"></i>Outtake Forms</h5>
                        <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                        <div class="card-tools">
                            <a href="post.php?create_outtake=<?= $ticket_id ?>&client_id=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-tool" title="Create Outtake Form">
                                <i class="fas fa-plus"></i>
                            </a>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($outtake_count == 0) { ?>
                            <p class="text-secondary text-center p-3 mb-0">No outtake forms yet.</p>
                        <?php } ?>
                        <?php while ($ot = mysqli_fetch_assoc($sql_outtakes)) {
                            $ot_id      = intval($ot['outtake_id']);
                            $ot_token   = nullable_htmlentities($ot['outtake_sign_token']);
                            $ot_signed  = $ot['outtake_signed_at'];
                            $ot_signer  = nullable_htmlentities($ot['outtake_signed_name']);
                            $ot_date    = date('M j, Y', strtotime($ot['outtake_created_at']));
                            $ot_sig     = $ot['outtake_signature'];
                            $ot_notes   = nullable_htmlentities($ot['outtake_tech_notes']);
                        ?>
                        <div class="border-bottom px-3 py-2 d-flex align-items-center">
                            <i class="fas fa-file-signature text-secondary mr-2"></i>
                            <span class="flex-grow-1">
                                <strong>Outtake Form</strong> <small class="text-secondary"><?= $ot_date ?></small>
                                <?php if ($ot_signed) { ?>
                                    <span class="badge badge-success ml-1"><i class="fas fa-check mr-1"></i>Signed by <?= $ot_signer ?></span>
                                <?php } else { ?>
                                    <span class="badge badge-warning text-dark ml-1">Awaiting Signature</span>
                                <?php } ?>
                            </span>
                            <div class="ml-2">
                                <?php if (!empty($ot_token) && !empty($config_base_url) && !$ot_signed) {
                                    $ot_sign_url = "https://$config_base_url/guest/outtake_sign.php?token=$ot_token";
                                ?>
                                <button type="button" class="btn btn-xs btn-success mr-1" title="Open signing page now for in-person signature"
                                    onclick="window.open('<?= $ot_sign_url ?>', '_blank', 'noopener,noreferrer')">
                                    <i class="fas fa-pen-nib mr-1"></i>Sign In-Person
                                </button>
                                <button type="button" class="btn btn-xs btn-warning mr-1" title="Copy link to send to customer remotely"
                                    onclick="navigator.clipboard.writeText('<?= $ot_sign_url ?>').then(function(){alert('Signing link copied to clipboard!');})">
                                    <i class="fas fa-copy mr-1"></i>Copy Link
                                </button>
                                <?php } ?>
                                <a href="outtake_form.php?outtake_id=<?= $ot_id ?>&ticket_id=<?= $ticket_id ?><?= $client_id ? "&client_id=".$client_id : "" ?>" class="btn btn-xs btn-secondary mr-1" title="View/Edit"><i class="fas fa-edit"></i></a>
                                <?php if (lookupUserPermission("module_support") >= 2 && empty($ticket_resolved_at)) { ?>
                                <a href="post.php?delete_outtake=<?= $ot_id ?>&ticket_id=<?= $ticket_id ?>&client_id=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-xs btn-danger confirm-link" title="Delete"><i class="fas fa-trash"></i></a>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
                <!-- End Outtake Forms Card -->

                <!-- Charges Card -->
                <?php if ($config_module_enable_accounting && (empty($ticket_resolved_at) || !empty($charge_rows))) { ?>
                <div class="card">
                    <div class="card-header px-3 py-2">
                        <h5 class="card-title mt-1">
                            <i class="fas fa-fw fa-dollar-sign mr-2"></i>Charges
                            <?php if ($charge_rows) { ?>
                                <span class="badge badge-secondary ml-1">$<?= number_format($charges_subtotal, 2) ?></span>
                            <?php } ?>
                        </h5>
                        <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                        <div class="card-tools">
                            <a href="#" class="btn btn-tool ajax-modal"
                               data-modal-url="modals/ticket/ticket_charge_add.php?ticket_id=<?= $ticket_id ?>&client_id=<?= $client_id ?>">
                                <i class="fas fa-plus"></i>
                            </a>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($charge_rows)) { ?>
                            <p class="text-secondary text-center p-3 mb-0">No charges yet.</p>
                        <?php } else { ?>
                        <table class="table table-sm mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Item</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Unit Price</th>
                                    <th class="text-right">Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($charge_rows as $cr) {
                                    $cr_id    = intval($cr['charge_id']);
                                    $cr_name  = nullable_htmlentities($cr['charge_name']);
                                    $cr_desc  = nullable_htmlentities($cr['charge_description']);
                                    $cr_qty   = floatval($cr['charge_quantity']);
                                    $cr_price = floatval($cr['charge_unit_price']);
                                    $cr_total = floatval($cr['charge_total']);
                                    $cr_tax      = $cr['tax_name'] ? nullable_htmlentities($cr['tax_name']) . ' ' . floatval($cr['tax_percent']) . '%' : '';
                                    $cr_lt_name  = nullable_htmlentities($cr['labor_type_name']);
                                    $cr_lt_color = nullable_htmlentities($cr['labor_type_color'] ?? '#6c757d');
                                    $cr_invoiced = !empty($cr['charge_invoiced_at']);
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($cr_lt_name) { ?>
                                            <span class="badge badge-pill text-white mr-1" style="background:<?= $cr_lt_color ?>;"><?= $cr_lt_name ?></span>
                                        <?php } ?>
                                        <strong><?= $cr_name ?></strong>
                                        <?php if ($cr_desc) { ?><br><small class="text-muted"><?= $cr_desc ?></small><?php } ?>
                                        <?php if ($cr_tax) { ?><br><small class="text-muted"><?= $cr_tax ?></small><?php } ?>
                                    </td>
                                    <td class="text-right"><?= $cr_qty ?></td>
                                    <td class="text-right">$<?= number_format($cr_price, 2) ?></td>
                                    <td class="text-right"><strong>$<?= number_format($cr_total, 2) ?></strong></td>
                                    <td class="text-right" style="white-space:nowrap;">
                                        <?php if (!$cr_invoiced && empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                                        <a href="#" class="btn btn-xs btn-secondary ajax-modal"
                                           data-modal-url="modals/ticket/ticket_charge_edit.php?charge_id=<?= $cr_id ?>"
                                           title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="post.php?delete_ticket_charge=<?= $cr_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                           class="btn btn-xs btn-danger confirm-link ml-1"
                                           title="Delete"><i class="fas fa-trash"></i></a>
                                        <?php } elseif ($cr_invoiced) { ?>
                                        <span class="badge badge-success">Invoiced</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-right"><strong>Subtotal</strong></td>
                                    <td class="text-right"><strong>$<?= number_format($charges_subtotal, 2) ?></strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
                <!-- End Charges Card -->

                <!-- Contact card -->
                <?php if ($contact_id) { ?>
                    <div class="card">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-user-check mr-2"></i>Contact</h5>
                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                            <div class="card-tools">
                                <a class="btn btn-tool ajax-modal" href="#"
                                    data-modal-url="modals/ticket/ticket_contact.php?id=<?= $ticket_id ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-3">

                            <div>
                                <i class="fa fa-fw fa-user text-secondary mr-2"></i><a href="#" class="ajax-modal"
                                   data-modal-size="lg"
                                   data-modal-url="modals/contact/contact_details.php?id=<?= $contact_id ?>"><strong><?= $contact_name ?></strong>
                                </a>
                            </div>

                            <?php

                            if (!empty($location_name)) { ?>
                                <div class="mt-2">
                                    <i class="fa fa-fw fa-map-marker-alt text-secondary mr-2"></i><?php echo $location_name; ?>
                                </div>
                            <?php }

                            if (!empty($contact_email)) { ?>
                                <div class="mt-2">
                                    <i class="fa fa-fw fa-envelope text-secondary mr-2"></i><a href="mailto:<?php echo $contact_email; ?>"><?php echo $contact_email; ?></a>
                                </div>
                            <?php }

                            if (!empty($contact_phone)) { ?>
                                <div class="mt-2">
                                    <i class="fa fa-fw fa-phone text-secondary mr-2"></i><a href="tel:<?php echo $contact_phone; ?>"><?php echo $contact_phone; ?></a>
                                </div>
                            <?php }

                            if (!empty($contact_mobile)) { ?>
                                <div class="mt-2">
                                    <i class="fa fa-fw fa-mobile-alt text-secondary mr-2"></i><a href="tel:<?php echo $contact_mobile; ?>"><?php echo $contact_mobile; ?></a>
                                </div>
                            <?php } ?>

                        </div>
                    </div>
                <?php } ?>
                <!-- End contact card -->

                <!-- Ticket watchers card -->
                <?php if (empty($ticket_closed_at) && mysqli_num_rows($sql_ticket_watchers) > 0) { ?>

                    <div class="card">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-eye mr-2"></i>Watchers</h5>
                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                            <div class="card-tools">
                                <a class="btn btn-tool ajax-modal" href="#" data-modal-url="modals/ticket/ticket_add_watcher.php?ticket_id=<?= $ticket_id ?>">
                                    <i class="fas fa-fw fa-user-plus"></i>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-3">

                            <?php
                            // Get Watchers
                            while ($row = mysqli_fetch_assoc($sql_ticket_watchers)) {
                                $watcher_id = intval($row['watcher_id']);
                                $ticket_watcher_email = nullable_htmlentities($row['watcher_email']);
                                ?>
                                <div class='mt-1'>
                                    <i class="fa fa-fw fa-envelope text-secondary mr-2"></i><?php echo $ticket_watcher_email; ?>
                                    <?php if (empty($ticket_closed_at)) { ?>
                                        <a class="confirm-link float-right" href="post.php?delete_ticket_watcher=<?= $watcher_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-times text-secondary"></i>
                                        </a>
                                    <?php } ?>
                                </div>

                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
                <!-- End Ticket watchers card -->

                <!-- Ticket tags card -->
                <div class="card">
                    <div class="card-header px-3 py-2">
                        <h5 class="card-title mt-1"><i class="fas fa-fw fa-tags mr-2"></i>Tags</h5>
                        <?php if (lookupUserPermission("module_support") >= 2) { ?>
                        <div class="card-tools">
                            <a class="btn btn-tool ajax-modal" href="#" data-modal-url="modals/ticket/ticket_tags.php?id=<?= $ticket_id ?>">
                                <i class="fas fa-edit"></i>
                            </a>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="card-body p-3">
                        <?php
                        $sql_ticket_tags_display = mysqli_query($mysqli, "SELECT * FROM ticket_tags LEFT JOIN tags ON ticket_tag_tag_id = tag_id WHERE ticket_tag_ticket_id = $ticket_id ORDER BY tag_name ASC");
                        if (mysqli_num_rows($sql_ticket_tags_display) > 0) {
                            while ($tag_row = mysqli_fetch_assoc($sql_ticket_tags_display)) {
                                $ticket_tag_name = nullable_htmlentities($tag_row['tag_name']);
                                $ticket_tag_color = nullable_htmlentities($tag_row['tag_color']) ?: 'dark';
                                $ticket_tag_icon = nullable_htmlentities($tag_row['tag_icon']) ?: 'tag';
                                ?>
                                <span class='badge text-light p-1 mr-1' style='background-color: <?= $ticket_tag_color ?>;'><i class='fa fa-fw fa-<?= $ticket_tag_icon ?> mr-1'></i><?= $ticket_tag_name ?></span>
                            <?php }
                        } else { ?>
                            <span class="text-muted">No tags</span>
                        <?php } ?>
                    </div>
                </div>
                <!-- End Ticket tags card -->

                <!-- Linked RMM Alerts card (Syncro-Beta) -->
                <?php
                if ($config_module_enable_rmm && lookupUserPermission("module_rmm_alerts") >= 1) {
                    $sql_linked_alerts = mysqli_query($mysqli, "SELECT * FROM rmm_alerts WHERE ticket_id = $ticket_id ORDER BY created_at DESC");
                    if (mysqli_num_rows($sql_linked_alerts) > 0):
                ?>
                <div class="card mb-3" style="border-top:2px solid #17a2b8">
                    <div class="card-header px-3 py-2">
                        <h5 class="card-title mt-1"><i class="fas fa-fw fa-bell mr-2"></i>Linked RMM Alerts</h5>
                    </div>
                    <div class="card-body p-3 small">
                        <?php while ($linked_alert = mysqli_fetch_assoc($sql_linked_alerts)):
                            $la_id = intval($linked_alert['id']);
                            $la_sev_color = ['critical'=>'danger','error'=>'danger','warning'=>'warning','info'=>'info'][$linked_alert['severity']] ?? 'secondary';
                            $la_status_color = ['new'=>'danger','acknowledged'=>'warning','resolved'=>'success'][$linked_alert['status']] ?? 'secondary';
                        ?>
                        <div class="border-bottom pb-2 mb-2" id="linked-alert-<?= $la_id ?>">
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge badge-<?= $la_sev_color ?> mr-1"><?= ucfirst($linked_alert['severity']) ?></span>
                                <span class="badge badge-<?= $la_status_color ?>"><?= ucfirst($linked_alert['status']) ?></span>
                                <span class="text-muted ml-auto"><?= nullable_htmlentities($linked_alert['created_at']) ?></span>
                            </div>
                            <div class="mb-1"><?= nullable_htmlentities($linked_alert['message']) ?></div>
                            <?php if ($linked_alert['status'] !== 'resolved' && lookupUserPermission('module_rmm_alerts_ack') >= 1): ?>
                            <div>
                                <?php if ($linked_alert['status'] === 'new'): ?>
                                <button class="btn btn-xs btn-outline-warning" onclick="ticketAlertAction(<?= $la_id ?>, 'acknowledge')">Acknowledge</button>
                                <?php endif; ?>
                                <button class="btn btn-xs btn-outline-success" onclick="ticketAlertAction(<?= $la_id ?>, 'resolve')">Resolve</button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <script>
                function ticketAlertAction(alertId, action) {
                    fetch('/agent/post/rmm_alert.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `csrf_token=<?= urlencode($_SESSION['csrf_token']) ?>&action=${action}&alert_id=${alertId}`
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            window.location.reload();
                        } else {
                            alert('Failed: ' + (d.error || 'Unknown error'));
                        }
                    });
                }
                </script>
                <?php
                    endif;
                }
                ?>

                <!-- Vendor card -->
                <?php if ($vendor_id) { ?>
                    <div class="card mb-3">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-building mr-2"></i>Vendor</h5>
                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                            <div class="card-tools">
                                <a class="btn btn-tool ajax-modal" href="#" data-modal-url="modals/ticket/ticket_edit_vendor.php?ticket_id=<?= $ticket_id ?>">
                                    <i class="fas fa-fw fa-edit"></i>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-3">

                            <div>
                                <i class="fa fa-fw fa-building text-secondary mr-2"></i><strong><?php echo $vendor_name; ?></strong>
                            </div>
                            <?php

                            if (!empty($vendor_contact_name)) { ?>
                                <div class="mt-1">
                                    <i class="fa fa-fw fa-user text-secondary mr-2"></i><?php echo $vendor_contact_name; ?>
                                </div>
                            <?php }

                            if (!empty($ticket_vendor_ticket_number)) { ?>
                                <div class="mt-1">
                                    <i class="fa fa-fw fa-tag text-secondary mr-2"></i><?php echo $ticket_vendor_ticket_number; ?>
                                </div>
                            <?php }

                            if (!empty($vendor_email)) { ?>
                                <div class="mt-1">
                                    <i class="fa fa-fw fa-envelope text-secondary mr-2"></i><a href="mailto:<?php echo $vendor_email; ?>"><?php echo $vendor_email; ?></a>
                                </div>
                            <?php }

                            if (!empty($vendor_phone)) { ?>
                                <div class="mt-1">
                                    <i class="fa fa-fw fa-phone text-secondary mr-2"></i><?php echo $vendor_phone; ?>
                                </div>
                            <?php }

                            if (!empty($vendor_website)) { ?>
                                <div class="mt-1">
                                    <i class="fa fa-fw fa-globe text-secondary mr-2"></i><?php echo $vendor_website; ?>
                                </div>
                            <?php } ?>

                        </div>
                    </div>
                <?php } //End Else ?>
                <!-- End Vendor card -->

                <!-- project card -->
                <?php if ($project_id) { ?>
                    <div class="card">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-project-diagram mr-2"></i>Project</h5>
                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool ajax-modal" data-modal-url="modals/ticket/ticket_edit_project.php?id=<?= $ticket_id ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-3">
                            <div>
                                <i class="fa fa-fw fa-project-diagram text-secondary mr-2"></i><a href="project_details.php?project_id=<?php echo $project_id; ?>" target="_blank"><strong><?= $project_name ?><i class="fa fa-fw fa-external-link-alt ml-1"></i></strong>
                                </a>
                            </div>

                            <?php if ($project_manager) { ?>
                                <div class="mt-2">
                                    <i class="fa fa-fw fa-user-tie text-secondary mr-2"></i><?= $project_manager_name ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
                <!-- End project card -->

            </div> <!-- End col-3 -->

        </div> <!-- End row -->

    <?php
    }
}

?>
<script>
function initWsSig(wsId, fId) {
    var c = document.getElementById('sig_c_' + wsId + '_' + fId);
    if (!c) return;
    c.width = c.offsetWidth; c.height = 80;
    var ctx = c.getContext('2d'), drawing = false, lx, ly;
    function pos(e) { var r = c.getBoundingClientRect(), s = e.touches ? e.touches[0] : e; return {x:(s.clientX-r.left)*(c.width/r.width), y:(s.clientY-r.top)*(c.height/r.height)}; }
    c.addEventListener('mousedown', function(e){drawing=true; var p=pos(e); lx=p.x; ly=p.y;});
    c.addEventListener('mousemove', function(e){if(!drawing)return; var p=pos(e); ctx.beginPath(); ctx.moveTo(lx,ly); ctx.lineTo(p.x,p.y); ctx.strokeStyle='#000'; ctx.lineWidth=2; ctx.lineCap='round'; ctx.stroke(); lx=p.x; ly=p.y; saveSig(wsId,fId);});
    c.addEventListener('mouseup', function(){drawing=false;});
    c.addEventListener('touchstart', function(e){e.preventDefault(); drawing=true; var p=pos(e); lx=p.x; ly=p.y;}, {passive:false});
    c.addEventListener('touchmove', function(e){e.preventDefault(); if(!drawing)return; var p=pos(e); ctx.beginPath(); ctx.moveTo(lx,ly); ctx.lineTo(p.x,p.y); ctx.strokeStyle='#000'; ctx.lineWidth=2; ctx.lineCap='round'; ctx.stroke(); lx=p.x; ly=p.y; saveSig(wsId,fId);}, {passive:false});
    c.addEventListener('touchend', function(){drawing=false;});
}
function saveSig(wsId, fId) { var c=document.getElementById('sig_c_'+wsId+'_'+fId); if(c) document.getElementById('sig_d_'+wsId+'_'+fId).value=c.toDataURL(); }
function clearWsSig(wsId, fId) { var c=document.getElementById('sig_c_'+wsId+'_'+fId); if(c){c.getContext('2d').clearRect(0,0,c.width,c.height); document.getElementById('sig_d_'+wsId+'_'+fId).value='';} }
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('canvas[id^="sig_c_"]').forEach(function(c) {
        var parts = c.id.split('_'); initWsSig(parts[2], parts[3]);
    });
});
</script>
<?php
require_once "../includes/footer.php";

?>

<script src="/js/show_modals.js"></script>

<?php if (empty($ticket_closed_at)) { ?>
    <!-- create js variable related to ticket timer setting -->
    <script type="text/javascript">
        var ticketAutoStart = <?php echo json_encode($config_ticket_timer_autostart); ?>;
    </script>

    <!-- Ticket Time Tracking JS -->
    <script src="js/ticket_time_tracking.js"></script>

    <!-- Ticket collision detect JS (jQuery is called in footer, so collision detection script MUST be below it) -->
    <script src="js/ticket_collision_detection.js"></script>
<?php } ?>

<script src="/js/pretty_content.js"></script>

<script src="/plugins/SortableJS/Sortable.min.js"></script>
<script>
var _tasksTbody = document.querySelector('table#tasks tbody');
if (_tasksTbody) new Sortable(_tasksTbody, {
    handle: '.drag-handle',
    animation: 150,
    onEnd: function (evt) {
        const rows = document.querySelectorAll('table#tasks tbody tr');
        const positions = Array.from(rows).map((row, index) => ({
            id: row.dataset.taskId,
            order: index
        }));

        $.post('ajax.php', {
            update_ticket_tasks_order: true,
            csrf_token: '<?= $_SESSION['csrf_token'] ?>',
            ticket_id: <?php echo $ticket_id; ?>,
            positions: positions
        });
    }
});
</script>

<script>
// Ticket reply draft autosave
(function() {
    var draftKey = 'ticket_draft_<?= $ticket_id ?>';
    var $banner  = $('#ticket-reply-draft-banner');
    var saved    = localStorage.getItem(draftKey);

    // Show restore banner if draft exists
    if (saved && saved.trim() !== '' && saved.trim() !== '<p></p>') {
        $banner.css('display', 'flex');
        $('#draft-restore-btn').on('click', function() {
            tinymce.get('ticket-reply-editor').setContent(saved);
            $banner.hide();
        });
        $('#draft-discard-btn').on('click', function() {
            localStorage.removeItem(draftKey);
            $banner.hide();
        });
    }

    // Save every 3 seconds if content changed
    var lastSaved = '';
    setInterval(function() {
        var ed = tinymce.get('ticket-reply-editor');
        if (!ed) return;
        var content = ed.getContent();
        if (content === lastSaved) return;
        lastSaved = content;
        if (content.trim() !== '' && content.trim() !== '<p></p>') {
            localStorage.setItem(draftKey, content);
        } else {
            localStorage.removeItem(draftKey);
        }
    }, 3000);

    // Clear on submit
    $('form').on('submit', function() {
        localStorage.removeItem(draftKey);
    });
})();

// Insert canned response into the reply editor
$(document).on('click', '.insert-canned-response', function(e) {
    e.preventDefault();
    var ed = tinymce.get('ticket-reply-editor');
    if (!ed) return;
    var message = $(this).data('message');
    ed.execCommand('mceInsertContent', false, message);
});

// Inline quick-assign on ticket detail
$(document).on('change', '#quickAssignSelect', function() {
    var $sel = $(this);
    var ticketId = $sel.data('ticket-id');
    var csrf = $sel.data('csrf');
    var assignedTo = $sel.val();
    var $status = $('#quickAssignStatus');
    $status.html('<i class="fas fa-spinner fa-spin text-muted"></i>');
    $.post('post.php', {
        quick_assign_ticket: 1,
        ticket_id: ticketId,
        assigned_to: assignedTo,
        csrf_token: csrf
    }, function(res) {
        if (res.ok) {
            $status.html('<i class="fas fa-check text-success"></i>');
            setTimeout(function() { $status.html(''); }, 2000);
        } else {
            $status.html('<i class="fas fa-times text-danger"></i>');
        }
    }, 'json').fail(function() {
        $status.html('<i class="fas fa-times text-danger"></i>');
    });
});
</script>
