<?php

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND asset_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
}

if (isset($_GET['asset_id'])) {
    $asset_id = intval($_GET['asset_id']);

    $sql = mysqli_query($mysqli, "SELECT * FROM assets
        LEFT JOIN clients ON client_id = asset_client_id
        LEFT JOIN contacts ON asset_contact_id = contact_id
        LEFT JOIN locations ON asset_location_id = location_id
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
        WHERE asset_id = $asset_id
        $client_query
        LIMIT 1
    ");

    if (mysqli_num_rows($sql) == 0) {
        echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

    } else {

        $row = mysqli_fetch_assoc($sql);
        $client_id = intval($row['client_id']);
        $client_name = nullable_htmlentities($row['client_name']);
        $asset_id = intval($row['asset_id']);
        $asset_type = nullable_htmlentities($row['asset_type']);
        $asset_name = nullable_htmlentities($row['asset_name']);
        $asset_tag = nullable_htmlentities($row['asset_tag']);
        $asset_description = nullable_htmlentities($row['asset_description']);
        $asset_make = nullable_htmlentities($row['asset_make']);
        $asset_model = nullable_htmlentities($row['asset_model']);
        $asset_serial = nullable_htmlentities($row['asset_serial']);
        $asset_os = nullable_htmlentities($row['asset_os']);
        $asset_uri = sanitize_url($row['asset_uri']);
        $asset_uri_2 = sanitize_url($row['asset_uri_2']);
        $asset_uri_client = sanitize_url($row['asset_uri_client']);
        $asset_status = nullable_htmlentities($row['asset_status']);
        $asset_purchase_reference = nullable_htmlentities($row['asset_purchase_reference']);
        $asset_purchase_date = nullable_htmlentities($row['asset_purchase_date']);
        $asset_warranty_expire = nullable_htmlentities($row['asset_warranty_expire']);
        $asset_install_date = nullable_htmlentities($row['asset_install_date']);
        $asset_photo = nullable_htmlentities($row['asset_photo']);
        $asset_physical_location = nullable_htmlentities($row['asset_physical_location']);
        $asset_notes = nullable_htmlentities($row['asset_notes']);
        $asset_favorite = intval($row['asset_favorite']);
        $asset_created_at = nullable_htmlentities($row['asset_created_at']);
        $asset_vendor_id = intval($row['asset_vendor_id']);
        $asset_location_id = intval($row['asset_location_id']);
        $asset_contact_id = intval($row['asset_contact_id']);

        $asset_ip = nullable_htmlentities($row['interface_ip']);
        $asset_ipv6 = nullable_htmlentities($row['interface_ipv6']);
        $asset_nat_ip = nullable_htmlentities($row['interface_nat_ip']);
        $asset_mac = nullable_htmlentities($row['interface_mac']);
        $asset_network_id = intval($row['interface_network_id']);

        $device_icon = getAssetIcon($asset_type);

        $contact_name = nullable_htmlentities($row['contact_name']);
        $contact_email = nullable_htmlentities($row['contact_email']);
        $contact_phone_country_code = nullable_htmlentities($row['contact_phone_country_code']);
        $contact_phone = nullable_htmlentities(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
        $contact_extension = nullable_htmlentities($row['contact_extension']);
        $contact_mobile_country_code = nullable_htmlentities($row['contact_mobile_country_code']);
        $contact_mobile = nullable_htmlentities(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
        $contact_archived_at = nullable_htmlentities($row['contact_archived_at']);
        if ($contact_archived_at) {
            $contact_name_display = "<span class='text-danger' title='Archived'><s>$contact_name</s></span>";
        } else {
            $contact_name_display = $contact_name;
        }
        $location_name = nullable_htmlentities($row['location_name']);
        if (empty($location_name)) {
            $location_name = "-";
        }
        $location_archived_at = nullable_htmlentities($row['location_archived_at']);
        if ($location_archived_at) {
            $location_name_display = "<span class='text-danger' title='Archived'><s>$location_name</s></span>";
        } else {
            $location_name_display = $location_name;
        }

        // Override Tab Title // No Sanitizing needed as this var will opnly be used in the tab title
        $page_title = $row['asset_name'];

        $sql_related_tickets = mysqli_query($mysqli, "
            SELECT tickets.*, users.*, ticket_statuses.*
            FROM tickets
            LEFT JOIN users ON ticket_assigned_to = user_id
            LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
            LEFT JOIN ticket_assets ON tickets.ticket_id = ticket_assets.ticket_id
            WHERE ticket_asset_id = $asset_id OR ticket_assets.asset_id = $asset_id
            GROUP BY tickets.ticket_id
            ORDER BY ticket_number DESC
        ");
        $ticket_count = mysqli_num_rows($sql_related_tickets);

        // Related Recurring Tickets Query
        $sql_related_recurring_tickets = mysqli_query($mysqli, "SELECT recurring_tickets.* FROM recurring_tickets
            LEFT JOIN recurring_ticket_assets ON recurring_tickets.recurring_ticket_id = recurring_ticket_assets.recurring_ticket_id
            WHERE recurring_ticket_asset_id = $asset_id OR recurring_ticket_assets.asset_id = $asset_id
            GROUP BY recurring_tickets.recurring_ticket_id
            ORDER BY recurring_ticket_next_run DESC"
        );
        $recurring_ticket_count = mysqli_num_rows($sql_related_recurring_tickets);

        // Related Documents
        $sql_related_documents = mysqli_query($mysqli, "SELECT * FROM asset_documents
            LEFT JOIN documents ON asset_documents.document_id = documents.document_id
            WHERE asset_documents.asset_id = $asset_id
            AND document_archived_at IS NULL
            ORDER BY document_name DESC"
        );
        $document_count = mysqli_num_rows($sql_related_documents);

        // Tags - many to many relationship
        $asset_tag_name_display_array = array();
        $asset_tag_id_array = array();
        $sql_asset_tags = mysqli_query($mysqli, "SELECT * FROM asset_tags LEFT JOIN tags ON asset_tag_tag_id = tag_id WHERE asset_tag_asset_id = $asset_id ORDER BY tag_name ASC");
        while ($row = mysqli_fetch_assoc($sql_asset_tags)) {

            $asset_tag_id = intval($row['tag_id']);
            $asset_tag_name = nullable_htmlentities($row['tag_name']);
            $asset_tag_color = nullable_htmlentities($row['tag_color']);
            if (empty($asset_tag_color)) {
                $asset_tag_color = "dark";
            }
            $asset_tag_icon = nullable_htmlentities($row['tag_icon']);
            if (empty($asset_tag_icon)) {
                $asset_tag_icon = "tag";
            }

            $asset_tag_id_array[] = $asset_tag_id;
            $asset_tag_name_display_array[] = "<a href='client_assets.php?client_id=$client_id&q=$asset_tag_name'><span class='badge text-light p-1 mr-1' style='background-color: $asset_tag_color;'><i class='fa fa-fw fa-$asset_tag_icon mr-2'></i>$asset_tag_name</span></a>";
        }
        $asset_tags_display = implode('', $asset_tag_name_display_array);

        // Network Interfaces
        $sql_related_interfaces = mysqli_query($mysqli, "
            SELECT
                ai.interface_id,
                ai.interface_name,
                ai.interface_description,
                ai.interface_type,
                ai.interface_mac,
                ai.interface_ip,
                ai.interface_nat_ip,
                ai.interface_ipv6,
                ai.interface_primary,
                ai.interface_notes,
                n.network_name,
                n.network_id,
                connected_interfaces.interface_id AS connected_interface_id,
                connected_interfaces.interface_name AS connected_interface_name,
                connected_assets.asset_name AS connected_asset_name,
                connected_assets.asset_id AS connected_asset_id,
                connected_assets.asset_type AS connected_asset_type
            FROM asset_interfaces AS ai
            LEFT JOIN networks AS n
              ON n.network_id = ai.interface_network_id
            LEFT JOIN asset_interface_links AS ail
              ON (ail.interface_a_id = ai.interface_id OR ail.interface_b_id = ai.interface_id)
            LEFT JOIN asset_interfaces AS connected_interfaces
              ON (
                  (ail.interface_a_id = ai.interface_id AND ail.interface_b_id = connected_interfaces.interface_id)
                  OR
                  (ail.interface_b_id = ai.interface_id AND ail.interface_a_id = connected_interfaces.interface_id)
              )
            LEFT JOIN assets AS connected_assets
              ON connected_assets.asset_id = connected_interfaces.interface_asset_id
            WHERE
                ai.interface_asset_id = $asset_id
                AND ai.interface_archived_at IS NULL
            ORDER BY ai.interface_name ASC
        ");

        $interface_count = mysqli_num_rows($sql_related_interfaces);

        // Related Files
        $sql_related_files = mysqli_query($mysqli, "SELECT * FROM asset_files
            LEFT JOIN files ON asset_files.file_id = files.file_id
            WHERE asset_files.asset_id = $asset_id
            AND file_archived_at IS NULL
            ORDER BY file_name DESC"
        );
        $files_count = mysqli_num_rows($sql_related_files);
        // View Mode -- 0 List, 1 Thumbnail
        if (!empty($_GET['view'])) {
            $view = intval($_GET['view']);
        } else {
            $view = 0;
        }
        if ($view == 1) {
            $query_images = "AND (file_ext LIKE 'JPG' OR file_ext LIKE 'jpg' OR file_ext LIKE 'JPEG' OR file_ext LIKE 'jpeg' OR file_ext LIKE 'png' OR file_ext LIKE 'PNG' OR file_ext LIKE 'webp' OR file_ext LIKE 'WEBP')";
        } else {
            $query_images = '';
        }

        // Related Documents
        $sql_related_documents = mysqli_query($mysqli, "SELECT * FROM asset_documents, documents
            LEFT JOIN users ON document_created_by = user_id
            WHERE asset_documents.asset_id = $asset_id
            AND asset_documents.document_id = documents.document_id
            AND document_archived_at IS NULL
            ORDER BY document_name ASC"
        );
        $document_count = mysqli_num_rows($sql_related_documents);


        // Related Credentials Query
        $sql_related_credentials = mysqli_query($mysqli, "
            SELECT
                credentials.credential_id AS credential_id,
                credentials.credential_name,
                credentials.credential_description,
                credentials.credential_uri,
                credentials.credential_username,
                credentials.credential_password,
                credentials.credential_otp_secret,
                credentials.credential_note,
                credentials.credential_favorite,
                credentials.credential_contact_id,
                credentials.credential_asset_id
            FROM credentials
            LEFT JOIN credential_tags ON credential_tags.credential_id = credentials.credential_id
            LEFT JOIN tags ON tags.tag_id = credential_tags.tag_id
            WHERE credential_asset_id = $asset_id
              AND credential_archived_at IS NULL
            GROUP BY credentials.credential_id
            ORDER BY credential_name DESC
        ");
        $credential_count = mysqli_num_rows($sql_related_credentials);

        // Related Software Query
        $sql_related_software = mysqli_query(
            $mysqli,
            "SELECT * FROM software_assets
            LEFT JOIN software ON software_assets.software_id = software.software_id
            WHERE software_assets.asset_id = $asset_id
            AND software_archived_at IS NULL
            ORDER BY software_name DESC"
        );

        $software_count = mysqli_num_rows($sql_related_software);

        // Linked Services
        $sql_linked_services = mysqli_query($mysqli, "SELECT * FROM service_assets, services
            WHERE service_assets.asset_id = $asset_id
            AND service_assets.service_id = services.service_id
            ORDER BY service_name ASC"
        );
        $service_count = mysqli_num_rows($sql_linked_services);

        $linked_services = array();

        // RMM Integration — load cached link data
        $rmm_link       = null;
        $rmm_badge      = 'badge-secondary';
        $rmm_border     = '#6c757d';
        $rmm_alerts_count = 0;
        if ($config_module_enable_rmm && lookupUserPermission('module_rmm') >= 1) {
            $rmm_link = mysqli_fetch_assoc(mysqli_query($mysqli,
                "SELECT arl.*, i.web_url
                 FROM asset_rmm_links arl
                 LEFT JOIN rmm_integrations i ON i.id = arl.integration_id
                 WHERE arl.asset_id = $asset_id LIMIT 1"
            ));
            if ($rmm_link) {
                if ($rmm_link['rmm_status'] === 'online')       { $rmm_badge = 'badge-success'; $rmm_border = '#28a745'; }
                elseif ($rmm_link['rmm_status'] === 'offline')  { $rmm_badge = 'badge-danger';  $rmm_border = '#dc3545'; }
                $rmm_alerts_count = intval((mysqli_fetch_assoc(mysqli_query($mysqli,
                    "SELECT COUNT(*) as c FROM rmm_alerts WHERE asset_id=$asset_id AND status='new'"
                )) ?: ['c'=>0])['c']);
            }
        }

        ?>

        <?php if ($rmm_link): ?>
        <div class="card card-dark mb-2" style="border-left:5px solid <?= $rmm_border ?>; border-radius:4px;">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center flex-wrap" style="gap:8px">
                    <div class="mr-auto">
                        <strong class="h5 mb-0"><?= nullable_htmlentities($rmm_link['hostname'] ?: $asset_name) ?></strong>
                        <span class="badge <?= $rmm_badge ?> ml-2"><?= ucfirst($rmm_link['rmm_status']) ?></span>
                        <?php if ($rmm_alerts_count > 0): ?>
                            <span class="badge badge-danger ml-1"><i class="fas fa-bell mr-1"></i><?= $rmm_alerts_count ?> Alert<?= $rmm_alerts_count != 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                        <div class="text-muted small mt-1">
                            <?php if ($rmm_link['os_name']): ?><i class="fab fa-windows mr-1"></i><?= nullable_htmlentities($rmm_link['os_name']) ?>&nbsp;<?php endif; ?>
                            <?php if ($rmm_link['logged_in_user']): ?>&bull;&nbsp;<i class="fas fa-user mx-1"></i><?= nullable_htmlentities($rmm_link['logged_in_user']) ?>&nbsp;<?php endif; ?>
                            <?php if ($rmm_link['last_seen']): ?>&bull;&nbsp;<i class="fas fa-clock mx-1"></i>Last seen <?= nullable_htmlentities($rmm_link['last_seen']) ?><?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex align-items-center" style="gap:6px">
                        <?php if (lookupUserPermission('module_rmm_remote_connect') >= 1 && !empty($rmm_link['tactical_agent_id'])): ?>
                        <div class="btn-group">
                            <button class="btn btn-success btn-sm" onclick="rmmConnect(<?= intval($rmm_link['id']) ?>, 'tactical')">
                                <i class="fas fa-desktop mr-1"></i>Connect
                            </button>
                            <?php if (!empty($rmm_link['mesh_node_id'])): ?>
                            <button type="button" class="btn btn-success btn-sm dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="#" onclick="rmmConnect(<?= intval($rmm_link['id']) ?>, 'mesh'); return false;">
                                    <i class="fas fa-tv mr-2"></i>MeshCentral Remote Desktop
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (lookupUserPermission('module_rmm_sync') >= 1): ?>
                        <div class="dropdown dropleft">
                            <button type="button" class="btn btn-outline-light btn-sm" data-toggle="dropdown" title="More RMM actions">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item text-danger" href="#" onclick="rmmUnlink(<?= intval($rmm_link['id']) ?>); return false;">
                                    <i class="fas fa-unlink mr-2"></i>Remove from RMM
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <style>
            #asset-details-content { font-size: 1.05rem; }
            #asset-details-content .small,
            #asset-details-content small { font-size: 92%; }
            #asset-details-content [style*="font-size:11px"] { font-size: 12px !important; }
            #asset-details-content [style*="font-size:13px"] { font-size: 14px !important; }
        </style>

        <div class="row" id="asset-details-content">

            <div class="col-md-3">

                <div class="card">
                    <div class="card-header">
                        <button type="button" class="btn btn-light float-right ajax-modal"
                            data-modal-url="modals/asset/asset_edit.php?id=<?= $asset_id ?>">
                            <i class="fas fa-fw fa-edit"></i>
                        </button>
                        <h4 class="text-bold"><i class="fa fa-fw text-secondary fa-<?= $device_icon; ?> mr-2"></i><?= $asset_name; ?>
                            <?php if ($asset_favorite) { ?><i class="fas fa-fw text-warning fa-star" title="Favorite"></i><?php } ?>
                        </h4>
                        <?php if ($asset_tag) { ?>
                            <div class="text-secondary small"><i class="fa fa-fw fa-barcode mr-1"></i>Asset Tag: <?= $asset_tag; ?></div>
                        <?php } ?>
                        <?php if ($asset_photo) { ?>
                            <img class="img-fluid img-circle p-3" alt="asset_photo" src="<?= "../uploads/clients/$client_id/$asset_photo"; ?>">
                        <?php } ?>
                        <?php if ($asset_description) { ?>
                            <div class="text-secondary"><?= $asset_description; ?></div>
                        <?php } ?>
                    </div>
                    <div class="card-body">
                        <div class="mb-1">
                            <?= $asset_tags_display ?>
                            <a href="#" class="btn btn-xs btn-outline-secondary ajax-modal" data-modal-url="modals/asset/asset_edit.php?id=<?= $asset_id ?>" data-modal-tab="pills-notes" title="Manage tags"><i class="fa fa-fw fa-tag mr-1"></i>+ Tag</a>
                        </div>
                        <?php if ($asset_type) { ?>
                            <div class="mt-1"><i class="fa fa-fw fa-tag text-secondary mr-2"></i><?= $asset_type; ?></div>
                        <?php }
                        if ($asset_make) { ?>
                            <div class="mt-2"><i class="fa fa-fw fa-circle text-secondary mr-2"></i><?= "$asset_make $asset_model"; ?></div>
                        <?php }
                        if ($asset_os) { ?>
                            <div class="mt-2"><i class="fab fa-fw fa-windows text-secondary mr-2"></i><?= "$asset_os"; ?></div>
                        <?php }
                        if ($asset_serial) { ?>
                            <div class="mt-2"><i class="fa fa-fw fa-barcode text-secondary mr-2"></i><?= $asset_serial; ?></div>
                        <?php }
                        if ($asset_purchase_date) { ?>
                            <div class="mt-2"><i class="fa fa-fw fa-shopping-cart text-secondary mr-2"></i><?= date('Y-m-d', strtotime($asset_purchase_date)); ?></div>
                        <?php }
                        if ($asset_install_date) { ?>
                            <div class="mt-2"><i class="fa fa-fw fa-calendar-check text-secondary mr-2"></i><?= date('Y-m-d', strtotime($asset_install_date)); ?></div>
                        <?php }
                        if ($asset_warranty_expire) { ?>
                            <div class="mt-2"><i class="fa fa-fw fa-exclamation-triangle text-secondary mr-2"></i><?= date('Y-m-d', strtotime($asset_warranty_expire)); ?></div>
                        <?php } ?>
                    </div>
                </div>

                <?php if ($asset_uri || $asset_uri_2 || $asset_uri_client): ?>
                <div class="card card-dark">
                    <div class="card-header">
                        <h5 class="card-title">Links</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($asset_uri) { ?>
                            <div><i class="fa fa-fw fa-link text-secondary mr-2"></i><a href="<?= $asset_uri; ?>" target="_blank" title="<?= $asset_uri; ?>"><?= truncate($asset_uri, 40); ?></a></div>
                        <?php }
                        if ($asset_uri_2) { ?>
                            <div class="mt-2"><i class="fa fa-fw fa-link text-secondary mr-2"></i><a href="<?= $asset_uri_2; ?>" target="_blank" title="<?= $asset_uri_2; ?>"><?= truncate($asset_uri_2, 40); ?></a></div>
                        <?php } ?>
                        <?php
                        if ($asset_uri_client) { ?>
                            <div class="mt-2"><i class="fa fa-fw fa-link text-secondary mr-2"></i>Client URI: <a href="<?= $asset_uri_client; ?>" target="_blank" title="<?= $asset_uri_client ?>"><?= truncate($asset_uri_client, 40); ?></a></div>
                        <?php } ?>
                    </div>
                </div>
                <?php endif; ?>


                <div class="card card-dark">
                    <div class="card-header">
                        <h5 class="card-title">Assignment</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($location_name) { ?>
                            <div><i class="fa fa-fw fa-map-marker-alt text-secondary mr-2"></i><?= $location_name_display; ?></div>
                        <?php }
                        if ($contact_name) { ?>
                            <div class="mt-2"><i class="fa fa-fw fa-user text-secondary mr-2"></i><?= $contact_name_display; ?></div>
                        <?php }
                        if ($contact_email) { ?>
                            <div class="mt-2"><i class="fa fa-fw fa-envelope text-secondary mr-2"></i><a href='mailto:<?= $contact_email; ?>'><?= $contact_email; ?></a><button class='btn btn-sm clipboardjs' data-clipboard-text='<?= $contact_email; ?>'><i class='far fa-copy text-secondary'></i></button></div>
                        <?php }
                        if ($contact_phone) { ?>
                            <div class="mt-2"><i class="fa fa-fw fa-phone text-secondary mr-2"></i><?= $contact_phone ?></div>
                        <?php }
                        if ($contact_extension) { ?>
                            <div class="mt-1"><i class="fa fa-fw text-secondary mr-2"></i><?= "ext. $contact_extension" ?></div>
                        <?php }
                        if ($contact_mobile) { ?>
                            <div class="mt-2"><i class="fa fa-fw fa-mobile-alt text-secondary mr-2"></i><?= $contact_mobile ?></div>
                        <?php } ?>

                    </div>
                </div>

                <div class="card card-dark mb-3">
                    <div class="card-header">
                        <h5 class="card-title">Additional Notes</h5>
                    </div>
                    <textarea class="form-control" rows=6 id="assetNotes" placeholder="Enter quick notes here" onblur="updateAssetNotes(<?= $asset_id ?>)"><?= $asset_notes ?></textarea>
                </div>

            </div>

            <div class="col-md-9">

                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="clients.php">Clients</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="client_overview.php?client_id=<?= $client_id; ?>"><?= $client_name; ?></a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="assets.php?client_id=<?= $client_id; ?>">Assets</a>
                    </li>
                    <li class="breadcrumb-item active"><?= $asset_name; ?></li>
                </ol>

                <div class="btn-group mb-3">
                    <div class="dropdown dropleft mr-2">
                        <button type="button" class="btn btn-primary" data-toggle="dropdown"><i class="fas fa-plus mr-2"></i>New</button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/ticket/ticket_add.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>" data-modal-size="lg">
                                <i class="fa fa-fw fa-life-ring mr-2"></i>New Ticket
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/recurring_ticket/recurring_ticket_add.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>" data-modal-size="lg">
                                <i class="fa fa-fw fa-recycle mr-2"></i>New Recurring Ticket
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/credential/credential_add.php?<?= $client_url ?>asset_id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-key mr-2"></i>New Credential
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/document/document_add.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>" data-modal-size="lg">
                                <i class="fa fa-fw fa-file-alt mr-2"></i>New Document
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/file/file_upload.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-upload mr-2"></i>Upload file(s)
                            </a>
                        </div>
                    </div>

                    <div class="dropdown dropleft">
                        <button type="button" class="btn btn-outline-primary" data-toggle="dropdown"><i class="fas fa-link mr-2"></i>Link</button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item text-dark ajax-modal" href="#"
                                data-modal-url="modals/asset/asset_link_software.php?id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-cube mr-2"></i>License
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-dark ajax-modal" href="#"
                                data-modal-url="modals/asset/asset_link_credential.php?id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-key mr-2"></i>Credential
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-dark ajax-modal" href="#"
                                data-modal-url="modals/asset/asset_link_service.php?id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-stream mr-2"></i>Service
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-dark ajax-modal" href="#"
                                data-modal-url="modals/asset/asset_link_document.php?id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-folder mr-2"></i>Document
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-dark ajax-modal" href="#"
                                data-modal-url="modals/asset/asset_link_file.php?id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-paperclip mr-2"></i>File
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($rmm_link): ?>
                <div class="card card-dark mb-3">
                    <div class="card-header p-0 border-bottom-0">
                        <ul class="nav nav-tabs px-3 pt-2" id="rmmDetailTabs">
                            <li class="nav-item">
                                <a class="nav-link active small" data-toggle="tab" href="#rdt-overview">
                                    <i class="fas fa-server mr-1"></i>RMM Overview
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link small" data-toggle="tab" href="#rdt-hardware"
                                   onclick="loadRmmLiveData('wmi',<?= intval($rmm_link['id']) ?>)">
                                    <i class="fas fa-microchip mr-1"></i>Hardware
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link small" data-toggle="tab" href="#rdt-software"
                                   onclick="loadRmmLiveData('software',<?= intval($rmm_link['id']) ?>)">
                                    <i class="fas fa-cube mr-1"></i>Software
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link small" data-toggle="tab" href="#rdt-services"
                                   onclick="loadRmmLiveData('services',<?= intval($rmm_link['id']) ?>)">
                                    <i class="fas fa-cogs mr-1"></i>Services
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link small" data-toggle="tab" href="#rdt-tickets">
                                    <i class="fas fa-life-ring mr-1"></i>Tickets<?php if ($ticket_count > 0): ?><span class="badge badge-secondary ml-1"><?= $ticket_count ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link small" data-toggle="tab" href="#rdt-alerts">
                                    <i class="fas fa-bell mr-1"></i>Alerts<?php if ($rmm_alerts_count > 0): ?><span class="badge badge-danger ml-1"><?= $rmm_alerts_count ?></span><?php endif; ?>
                                </a>
                            </li>
                            <?php if (lookupUserPermission('module_rmm_scripts') >= 1): ?>
                            <li class="nav-item">
                                <a class="nav-link small" data-toggle="tab" href="#rdt-scripts">
                                    <i class="fas fa-code mr-1"></i>Scripts
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane active p-3" id="rdt-overview">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless mb-0">
                                        <?php
                                        $rdt = function($lbl,$val){ if(trim(strip_tags($val??''))) echo "<tr><td class='text-muted pr-3' style='width:40%;white-space:nowrap'>$lbl</td><td>$val</td></tr>"; };
                                        if ($asset_tags_display) echo "<tr><td class='text-muted pr-3' style='width:40%;white-space:nowrap'>Tags</td><td>$asset_tags_display</td></tr>";
                                        $rdt('Hostname',   nullable_htmlentities($rmm_link['hostname']));
                                        $rdt('OS',         trim(nullable_htmlentities($rmm_link['os_name']).' '.nullable_htmlentities($rmm_link['os_version'])));
                                        $rdt('Manufacturer', nullable_htmlentities($rmm_link['manufacturer']));
                                        $rdt('Model',      nullable_htmlentities($rmm_link['model']));
                                        $rdt('CPU',        nullable_htmlentities($rmm_link['cpu']));
                                        if ($rmm_link['ram_gb']) $rdt('RAM', nullable_htmlentities($rmm_link['ram_gb']).' GB');
                                        ?>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless mb-0">
                                        <?php
                                        $rdt('Logged-in User', nullable_htmlentities($rmm_link['logged_in_user']));
                                        $rdt('Last Seen',  nullable_htmlentities($rmm_link['last_seen']));
                                        $rdt('Last Sync',  nullable_htmlentities($rmm_link['last_sync']));
                                        echo "<tr><td class='text-muted pr-3'>Status</td><td><span class='badge {$rmm_badge}'>".ucfirst($rmm_link['rmm_status'])."</span></td></tr>";
                                        $rdt('Agent ID', '<code class="small">'.nullable_htmlentities($rmm_link['tactical_agent_id']).'</code>');
                                        ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane p-3" id="rdt-hardware">
                            <div class="text-center p-4 text-muted rdt-loading"><i class="fas fa-spinner fa-spin mr-2"></i>Loading from Tactical RMM...</div>
                            <div class="rdt-data" style="display:none">
                                <div class="row mb-2">
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="info-box bg-light shadow-sm mb-0">
                                            <span class="info-box-icon"><i class="fas fa-desktop"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">Make / Model</span>
                                                <span class="info-box-number hw-make-model" style="font-size:13px"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="info-box bg-light shadow-sm mb-0">
                                            <span class="info-box-icon"><i class="fas fa-microchip"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">CPU</span>
                                                <span class="info-box-number hw-cpu" style="font-size:13px"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="info-box bg-light shadow-sm mb-0">
                                            <span class="info-box-icon"><i class="fas fa-memory"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">RAM</span>
                                                <span class="info-box-number hw-ram"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="info-box bg-light shadow-sm mb-0">
                                            <span class="info-box-icon"><i class="fas fa-tv"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">Graphics</span>
                                                <span class="info-box-number hw-graphics" style="font-size:13px"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <h6 class="text-muted small mb-2" style="text-transform:uppercase;letter-spacing:.4px"><i class="fas fa-network-wired mr-1"></i>Local IPs</h6>
                                        <p class="hw-local-ips small"></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted small mb-2" style="text-transform:uppercase;letter-spacing:.4px"><i class="fas fa-hdd mr-1"></i>Physical Disks</h6>
                                        <ul class="hw-physical-disks small mb-0 pl-3"></ul>
                                    </div>
                                </div>
                                <h6 class="text-muted small mb-2" style="text-transform:uppercase;letter-spacing:.4px">Disk Volumes</h6>
                                <div class="hw-volumes"></div>
                            </div>
                        </div>
                        <div class="tab-pane" id="rdt-software">
                            <div class="text-center p-4 text-muted rdt-loading"><i class="fas fa-spinner fa-spin mr-2"></i>Loading from Tactical RMM...</div>
                            <div class="rdt-data" style="display:none">
                                <div class="p-2 border-bottom">
                                    <input type="text" class="form-control form-control-sm rdt-search" placeholder="Search software..." style="max-width:300px" oninput="renderRmmTable('software',1)">
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="border-bottom text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px">
                                            <tr><th class="pl-3">Name</th><th>Version</th><th>Publisher</th></tr>
                                        </thead>
                                        <tbody class="rdt-body"></tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-2 border-top">
                                    <small class="text-muted rdt-pageinfo"></small>
                                    <div class="btn-group btn-group-sm rdt-pagination"></div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane" id="rdt-services">
                            <div class="text-center p-4 text-muted rdt-loading"><i class="fas fa-spinner fa-spin mr-2"></i>Loading from Tactical RMM...</div>
                            <div class="rdt-data" style="display:none">
                                <div class="p-2 border-bottom">
                                    <input type="text" class="form-control form-control-sm rdt-search" placeholder="Search services..." style="max-width:300px" oninput="renderRmmTable('services',1)">
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="border-bottom text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px">
                                            <tr><th class="pl-3">Service</th><th>Status</th><th>Start Type</th></tr>
                                        </thead>
                                        <tbody class="rdt-body"></tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-2 border-top">
                                    <small class="text-muted rdt-pageinfo"></small>
                                    <div class="btn-group btn-group-sm rdt-pagination"></div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane p-3" id="rdt-tickets">
                            <?php if ($ticket_count == 0): ?>
                            <p class="text-muted text-center mb-0">No tickets for this asset.</p>
                            <?php else: ?>
                            <table class="table table-sm table-hover mb-0">
                                <thead class="border-bottom text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px">
                                    <tr><th>Number</th><th>Subject</th><th>Priority</th><th>Status</th><th>Updated</th></tr>
                                </thead>
                                <tbody>
                                <?php while ($trow = mysqli_fetch_assoc($sql_related_tickets)):
                                    $tr_priority = nullable_htmlentities($trow['ticket_priority']);
                                    $tr_priority_badge = ['High'=>'badge-danger','Medium'=>'badge-warning','Low'=>'badge-info'][$tr_priority] ?? 'badge-secondary';
                                ?>
                                <tr>
                                    <td><a href="ticket.php?client_id=<?= $client_id; ?>&ticket_id=<?= intval($trow['ticket_id']); ?>"><span class="badge badge-pill badge-secondary p-2"><?= nullable_htmlentities($trow['ticket_prefix']) . intval($trow['ticket_number']); ?></span></a></td>
                                    <td><a href="ticket.php?client_id=<?= $client_id; ?>&ticket_id=<?= intval($trow['ticket_id']); ?>"><?= nullable_htmlentities($trow['ticket_subject']); ?></a></td>
                                    <td><?php if ($tr_priority): ?><span class="badge <?= $tr_priority_badge ?>"><?= $tr_priority ?></span><?php else: ?>-<?php endif; ?></td>
                                    <td><span class="badge badge-pill text-light p-2" style="background-color: <?= nullable_htmlentities($trow['ticket_status_color']); ?>"><?= nullable_htmlentities($trow['ticket_status_name']); ?></span></td>
                                    <td class="text-muted small"><?= nullable_htmlentities(substr($trow['ticket_updated_at'] ?: $trow['ticket_created_at'], 0, 16)); ?></td>
                                </tr>
                                <?php endwhile; mysqli_data_seek($sql_related_tickets, 0); ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane p-3" id="rdt-alerts">
                            <?php
                            $sql_rdt_alerts = mysqli_query($mysqli, "SELECT * FROM rmm_alerts WHERE asset_id=$asset_id ORDER BY created_at DESC LIMIT 25");
                            if (mysqli_num_rows($sql_rdt_alerts) == 0): ?>
                            <p class="text-muted text-center mb-0">No alerts for this device.</p>
                            <?php else: ?>
                            <table class="table table-sm table-hover mb-0">
                                <thead class="border-bottom text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px">
                                    <tr><th>Severity</th><th>Message</th><th>Status</th><th>Created</th><th></th></tr>
                                </thead>
                                <tbody>
                                <?php while ($ral = mysqli_fetch_assoc($sql_rdt_alerts)):
                                    $ral_badge = ['critical'=>'badge-danger','error'=>'badge-danger','warning'=>'badge-warning','info'=>'badge-info'][$ral['severity']] ?? 'badge-secondary';
                                    $ral_status_badge = ['new'=>'badge-danger','acknowledged'=>'badge-warning','resolved'=>'badge-success'][$ral['status']] ?? 'badge-secondary';
                                    $ral_id = intval($ral['id']);
                                ?>
                                <tr id="ral-row-<?= $ral_id ?>">
                                    <td><span class="badge <?= $ral_badge ?>"><?= nullable_htmlentities($ral['severity']) ?></span></td>
                                    <td class="small"><?= nullable_htmlentities($ral['message']) ?></td>
                                    <td><span class="badge <?= $ral_status_badge ?>"><?= nullable_htmlentities($ral['status']) ?></span></td>
                                    <td class="text-muted small"><?= nullable_htmlentities(substr($ral['created_at'], 0, 16)) ?></td>
                                    <td class="text-right pr-2">
                                        <?php if ($ral['status'] === 'new' && lookupUserPermission('module_rmm_alerts_ack') >= 1): ?>
                                        <button class="btn btn-xs btn-warning" onclick="assetAlertAction(<?= $ral_id ?>, 'acknowledge', this)">Ack</button>
                                        <?php endif; ?>
                                        <?php if ($ral['status'] !== 'resolved' && lookupUserPermission('module_rmm_alerts_ack') >= 1): ?>
                                        <button class="btn btn-xs btn-success" onclick="assetAlertAction(<?= $ral_id ?>, 'resolve', this)">Resolve</button>
                                        <?php endif; ?>
                                        <?php if (lookupUserPermission('module_support') >= 2): ?>
                                        <button class="btn btn-xs btn-primary" onclick="assetAlertAction(<?= $ral_id ?>, 'create_ticket', this)">
                                            <i class="fas fa-ticket-alt"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                        <?php if (lookupUserPermission('module_rmm_scripts') >= 1): ?>
                        <div class="tab-pane p-3" id="rdt-scripts">
                            <?php
                            $sql_rdt_scripts = mysqli_query($mysqli, "SELECT * FROM rmm_scripts WHERE enabled=1 ORDER BY category, name");
                            $sql_rdt_runs    = mysqli_query($mysqli,
                                "SELECT sr.*, s.name as sname FROM rmm_script_runs sr
                                 LEFT JOIN rmm_scripts s ON s.id = sr.script_id
                                 WHERE sr.asset_id=$asset_id ORDER BY sr.started_at DESC LIMIT 10"
                            );
                            ?>
                            <div class="row">
                                <div class="col-md-5 mb-3">
                                    <div class="card card-outline card-secondary mb-0 h-100">
                                        <div class="card-header py-2">
                                            <h6 class="card-title mb-0"><i class="fas fa-play-circle mr-1"></i>Run a Script</h6>
                                        </div>
                                        <div class="card-body">
                                            <?php if (mysqli_num_rows($sql_rdt_scripts) === 0): ?>
                                            <p class="text-muted small mb-0">No scripts configured. <a href="/agent/rmm_scripts.php">Add scripts</a> first.</p>
                                            <?php else: ?>
                                            <div class="form-group mb-2">
                                                <select id="rdt-script-select" class="form-control form-control-sm">
                                                    <option value="">— Select Script —</option>
                                                    <?php while ($scr = mysqli_fetch_assoc($sql_rdt_scripts)): ?>
                                                    <option value="<?= intval($scr['id']) ?>" data-name="<?= nullable_htmlentities($scr['name']) ?>">
                                                        [<?= nullable_htmlentities($scr['category']) ?>] <?= nullable_htmlentities($scr['name']) ?>
                                                    </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                            <button class="btn btn-sm btn-primary btn-block" onclick="runRmmScript(<?= intval($rmm_link['id']) ?>)">
                                                <i class="fas fa-play mr-1"></i>Run Script
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <h6 class="mb-2 text-muted small" style="text-transform:uppercase;letter-spacing:.4px">Recent Runs</h6>
                                    <?php if (mysqli_num_rows($sql_rdt_runs) === 0): ?>
                                    <p class="text-muted small">No scripts run yet on this asset.</p>
                                    <?php else: ?>
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="border-bottom text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px">
                                            <tr><th class="pl-2">Script</th><th>Status</th><th>Started</th><th></th></tr>
                                        </thead>
                                        <tbody>
                                        <?php while ($rr = mysqli_fetch_assoc($sql_rdt_runs)):
                                            $rr_badge = ['completed'=>'success','failed'=>'danger','running'=>'warning','pending'=>'secondary'][$rr['status']] ?? 'secondary';
                                        ?>
                                        <tr>
                                            <td class="pl-2 small"><?= nullable_htmlentities($rr['sname'] ?? 'Manual') ?></td>
                                            <td><span class="badge badge-<?= $rr_badge ?>"><?= ucfirst($rr['status']) ?></span></td>
                                            <td class="small text-muted"><?= nullable_htmlentities(substr($rr['started_at'], 0, 16)) ?></td>
                                            <td class="text-right pr-2"><a href="/agent/rmm_script_run.php?run_id=<?= intval($rr['id']) ?>" class="btn btn-xs btn-secondary"><i class="fas fa-eye"></i></a></td>
                                        </tr>
                                        <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card card-dark">
                    <div class="card-header py-2">
                        <h3 class="card-title mt-1"><i class="fa fa-fw fa-ethernet mr-2"></i>Interfaces</h3>
                        <div class="card-tools">
                            <div class="btn-group">
                                <button type="button" class="btn btn-tool ajax-modal" data-modal-url="modals/asset/asset_interface_add.php?&asset_id=<?= $asset_id ?>">
                                    <i class="fas fa-plus mr-2"></i>New Interface
                                </button>
                                <button type="button" class="btn btn-tool dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addMultipleAssetInterfacesModal">
                                        <i class="fa fa-fw fa-check-double mr-2"></i>Add Multiple
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#importAssetInterfaceModal">
                                        <i class="fa fa-fw fa-upload mr-2"></i>Import
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportAssetInterfaceModal">
                                        <i class="fa fa-fw fa-download mr-2"></i>Export
                                    </a>
                                </div>

                                <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                    <button class="btn btn-tool dropdown-toggle" type="button" data-toggle="dropdown">
                                        <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item text-dark ajax-modal" href="#"
                                            data-modal-url="modals/asset/asset_interface_bulk_edit_network.php?client_id=<?= $client_id ?>"
                                            data-bulk="true">
                                            <i class="fas fa-fw fa-network-wired mr-2"></i>Assign Network
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-dark ajax-modal" href="#"
                                            data-modal-url="modals/asset/asset_interface_bulk_edit_type.php?client_id=<?= $client_id ?>"
                                            data-bulk="true">
                                            <i class="fas fa-fw fa-ethernet mr-2"></i>Set Type
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item text-dark" type="submit" form="bulkActions" name="bulk_edit_asset_interface_ip_dhcp">
                                            <i class="fas fa-fw fa-list-ul mr-2"></i>Set to DHCP
                                        </button>
                                        <?php if (lookupUserPermission("module_support") === 3) { ?>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_asset_interfaces">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </button>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <form id="bulkActions" action="post.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="table-responsive-sm">
                            <table class="table table-striped table-borderless table-hover table-sm">
                                <thead class="<?php if ($interface_count == 0) { echo "d-none"; } ?>">
                                    <tr>
                                        <td class="bg-light checkbox-column">
                                            <div class="form-check">
                                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" onkeydown="checkAll(this)">
                                            </div>
                                        </td>
                                        <th>Name / Port</th>
                                        <th>Type</th>
                                        <th>Network</th>
                                        <th>IP</th>
                                        <th>MAC</th>
                                        <th>Connected To</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php while ($row = mysqli_fetch_assoc($sql_related_interfaces)) { ?>
                                    <?php
                                        $interface_id       = intval($row['interface_id']);
                                        $interface_name     = nullable_htmlentities($row['interface_name']);
                                        $interface_description = nullable_htmlentities($row['interface_description']);
                                        $interface_type     = nullable_htmlentities($row['interface_type']);
                                        $interface_mac      = nullable_htmlentities($row['interface_mac']);
                                        $interface_ip       = nullable_htmlentities($row['interface_ip']);
                                        $interface_nat_ip   = nullable_htmlentities($row['interface_nat_ip']);
                                        $interface_ipv6     = nullable_htmlentities($row['interface_ipv6']);
                                        $interface_primary  = intval($row['interface_primary']);
                                        $network_id         = intval($row['network_id']);
                                        $network_name       = nullable_htmlentities($row['network_name']);
                                        $interface_notes    = nullable_htmlentities($row['interface_notes']);

                                        // Prepare display text
                                        $interface_mac_display = $interface_mac ?: '-';
                                        $interface_ip_display  = $interface_ip ?: '-';
                                        $interface_type_display = $interface_type ?: '-';
                                        $network_name_display  = $network_name
                                            ? "<i class='fas fa-fw fa-network-wired mr-1'></i>$network_name"
                                            : '-';

                                        // Connected interface details
                                        $connected_asset_id = intval($row['connected_asset_id']);
                                        $connected_asset_name = nullable_htmlentities($row['connected_asset_name']);
                                        $connected_asset_type = nullable_htmlentities($row['connected_asset_type']);
                                        $connected_asset_icon = getAssetIcon($connected_asset_type);
                                        $connected_interface_name = nullable_htmlentities($row['connected_interface_name']);


                                        // Show either "-" or "AssetName - Port"
                                        if ($connected_asset_name) {
                                            $connected_to_display = "<a class='ajax-modal' href='#'
                                                data-modal-size='lg'
                                                data-modal-url='modals/asset/asset_details.php?id=$connected_asset_id'>
                                                <strong><i class='fa fa-fw text-dark fa-$connected_asset_icon mr-1'></i>$connected_asset_name</strong> - $connected_interface_name
                                            </a>";
                                        } else {
                                            $connected_to_display = "-";
                                        }
                                    ?>
                                    <tr>
                                        <td class="bg-light checkbox-column">
                                            <div class="form-check">
                                                <input class="form-check-input bulk-select" type="checkbox" name="interface_ids[]" value="<?= $interface_id ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <i class="fa fa-fw fa-ethernet text-secondary mr-1"></i>
                                            <a class="text-dark ajax-modal" href="#"
                                                data-modal-url="modals/asset/asset_interface_edit.php?id=<?= $interface_id ?>">
                                                <?= $interface_name ?> <?php if($interface_primary) { echo "<small class='text-primary'>(Primary)</small>"; } ?>
                                            </a>
                                        </td>
                                        <td><?= $interface_type_display; ?></td>
                                        <td><?= $network_name_display; ?></td>
                                        <td>
                                            <?= $interface_ip_display; ?>
                                            <div><small class="text-secondary"><?= $interface_ipv6 ?></small></div>
                                        </td>
                                        <td><?= $interface_mac_display; ?></td>
                                        <td><?= $connected_to_display; ?></td>
                                        <td>
                                            <div class="dropdown dropleft text-center">
                                                <button class="btn btn-tool btn-sm" type="button" data-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item ajax-modal" href="#"
                                                        data-modal-url="modals/asset/asset_interface_edit.php?id=<?= $interface_id ?>">
                                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                    </a>
                                                    <?php if ($session_user_role == 3 && $interface_primary == 0): ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-danger text-bold" href="post.php?delete_asset_interface=<?= $interface_id; ?>&csrf_token=<?= $_SESSION['csrf_token']; ?>">
                                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>

                <?php if (lookupUserPermission('module_credential')) { // Begin Credential Enforcement ?>

                <div class="card card-dark <?php if ($credential_count == 0) { echo "d-none"; } ?>">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa fa-fw fa-key mr-2"></i>Credentials</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive-sm-sm">
                            <table class="table table-striped table-borderless table-hover">
                                <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Username</th>
                                    <th>Password</th>
                                    <th>OTP</th>
                                    <th>URI</th>
                                    <th class="text-center">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                while ($row = mysqli_fetch_assoc($sql_related_credentials)) {
                                    $credential_id = intval($row['credential_id']);
                                    $credential_name = nullable_htmlentities($row['credential_name']);
                                    $credential_description = nullable_htmlentities($row['credential_description']);
                                    $credential_uri = nullable_htmlentities($row['credential_uri']);
                                    if (empty($credential_uri)) {
                                        $credential_uri_display = "-";
                                    } else {
                                        $credential_uri_display = "$credential_uri<button class='btn btn-sm clipboardjs' data-clipboard-text='$credential_uri'><i class='far fa-copy text-secondary'></i></button><a href='$credential_uri' target='_blank'><i class='fa fa-external-link-alt text-secondary'></i></a>";
                                    }
                                    $credential_username = nullable_htmlentities(decryptCredentialEntry($row['credential_username']));
                                    if (empty($credential_username)) {
                                        $credential_username_display = "-";
                                    } else {
                                        $credential_username_display = "$credential_username<button class='btn btn-sm clipboardjs' data-clipboard-text='$credential_username'><i class='far fa-copy text-secondary'></i></button>";
                                    }
                                    $credential_password = nullable_htmlentities(decryptCredentialEntry($row['credential_password']));
                                    $credential_otp_secret = nullable_htmlentities(decryptOtpSecret($row['credential_otp_secret'] ?? ''));
                                    $credential_id_with_secret = '"' . $row['credential_id'] . '","' . $row['credential_otp_secret'] . '"';
                                    if (empty($credential_otp_secret)) {
                                        $otp_display = "-";
                                    } else {
                                        $otp_display = "<span onmouseenter='showOTPViaCredentialID($credential_id)'><i class='far fa-clock'></i> <span id='otp_$credential_id'><i>Hover..</i></span></span>";
                                    }
                                    $credential_note = nullable_htmlentities($row['credential_note']);
                                    $credential_favorite = intval($row['credential_favorite']);
                                    $credential_contact_id = intval($row['credential_contact_id']);
                                    $credential_asset_id = intval($row['credential_asset_id']);

                                    // Tags
                                    $credential_tag_name_display_array = array();
                                    $credential_tag_id_array = array();
                                    $sql_credential_tags = mysqli_query($mysqli, "SELECT * FROM credential_tags LEFT JOIN tags ON credential_tags.tag_id = tags.tag_id WHERE credential_id = $credential_id ORDER BY tag_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql_credential_tags)) {

                                        $credential_tag_id = intval($row['tag_id']);
                                        $credential_tag_name = nullable_htmlentities($row['tag_name']);
                                        $credential_tag_color = nullable_htmlentities($row['tag_color']);
                                        if (empty($credential_tag_color)) {
                                            $credential_tag_color = "dark";
                                        }
                                        $credential_tag_icon = nullable_htmlentities($row['tag_icon']);
                                        if (empty($credential_tag_icon)) {
                                            $credential_tag_icon = "tag";
                                        }

                                        $credential_tag_id_array[] = $credential_tag_id;
                                        $credential_tag_name_display_array[] = "<a href='credentials.php?client_id=$client_id&tags[]=$credential_tag_id'><span class='badge text-light p-1 mr-1' style='background-color: $credential_tag_color;'><i class='fa fa-fw fa-$credential_tag_icon mr-2'></i>$credential_tag_name</span></a>";
                                    }
                                    $credential_tags_display = implode('', $credential_tag_name_display_array);

                                    ?>
                                    <tr>
                                        <td>
                                            <i class="fa fa-fw fa-key text-secondary"></i>
                                            <a class="text-dark ajax-modal" href="#"
                                                data-modal-url="modals/credential/credential_edit.php?id=<?= $credential_id ?>">
                                                <?= $credential_name ?>
                                            </a>
                                        </td>
                                        <td><?= $credential_description; ?></td>
                                        <td><?= $credential_username_display; ?></td>
                                        <td>
                                            <button class="btn p-0" type="button" data-toggle="popover" data-trigger="focus" data-placement="top" data-content="<?= $credential_password; ?>"><i class="fas fa-2x fa-ellipsis-h text-secondary"></i><i class="fas fa-2x fa-ellipsis-h text-secondary"></i></button><button class="btn btn-sm clipboardjs" data-clipboard-text="<?= $credential_password; ?>"><i class="far fa-copy text-secondary"></i></button>
                                        </td>
                                        <td><?= $otp_display; ?></td>
                                        <td><?= $credential_uri_display; ?></td>
                                        <td>
                                            <div class="dropdown dropleft text-center">
                                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item ajax-modal" href="#"
                                                        data-modal-url="modals/credential/credential_edit.php?id=<?= $credential_id ?>">
                                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                    </a>
                                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?= "$client_id, 'Credential', $credential_id"; ?>)">
                                                        <i class="fas fa-fw fa-share-alt mr-2"></i>Share
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="post.php?unlink_credential_from_asset&asset_id=<?= $asset_id; ?>&credential_id=<?= $credential_id; ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-unlink mr-2"></i>Unlink
                                                    </a>
                                                    <?php if ($session_user_role == 3) { ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-danger text-bold" href="post.php?delete_credential=<?= $credential_id; ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                        </a>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>

                                    <?php

                                }

                                ?>

                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>

                <?php } // End Credential Enforcement ?>

                <div class="card card-dark <?php if ($software_count == 0) { echo "d-none"; } ?>">
                    <div class="card-header py-2">
                        <h3 class="card-title mt-2"><i class="fa fa-fw fa-cube mr-2"></i>Licenses</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/asset/asset_link_software.php?id=<?= $asset_id ?>">
                                <i class="fas fa-link mr-2"></i>Link Software
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive-sm">
                            <table class="table table-striped table-borderless table-hover">
                                <thead class="text-dark">
                                <tr>
                                    <th>Software</th>
                                    <th>Type</th>
                                    <th>License Type</th>
                                    <th>Seats</th>
                                    <th class="text-center">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                while ($row = mysqli_fetch_assoc($sql_related_software)) {
                                    $software_id = intval($row['software_id']);
                                    $software_name = nullable_htmlentities($row['software_name']);
                                    $software_version = nullable_htmlentities($row['software_version']);
                                    $software_type = nullable_htmlentities($row['software_type']);
                                    $software_license_type = nullable_htmlentities($row['software_license_type']);
                                    $software_key = nullable_htmlentities($row['software_key']);
                                    $software_seats = nullable_htmlentities($row['software_seats']);
                                    $software_purchase = nullable_htmlentities($row['software_purchase']);
                                    $software_expire = nullable_htmlentities($row['software_expire']);
                                    $software_notes = nullable_htmlentities($row['software_notes']);

                                    $seat_count = 0;

                                    // Asset Licenses
                                    $asset_licenses_sql = mysqli_query($mysqli, "SELECT asset_id FROM software_assets WHERE software_id = $software_id");
                                    $asset_licenses_array = array();
                                    while ($row = mysqli_fetch_assoc($asset_licenses_sql)) {
                                        $asset_licenses_array[] = intval($row['asset_id']);
                                        $seat_count = $seat_count + 1;
                                    }
                                    $asset_licenses = implode(',', $asset_licenses_array);

                                    // Contact Licenses
                                    $contact_licenses_sql = mysqli_query($mysqli, "SELECT contact_id FROM software_contacts WHERE software_id = $software_id");
                                    $contact_licenses_array = array();
                                    while ($row = mysqli_fetch_assoc($contact_licenses_sql)) {
                                        $contact_licenses_array[] = intval($row['contact_id']);
                                        $seat_count = $seat_count + 1;
                                    }
                                    $contact_licenses = implode(',', $contact_licenses_array);

                                    $linked_software[] = $software_id;

                                    ?>
                                    <tr>
                                        <td>
                                            <a class="text-dark ajax-modal" href="#"
                                                data-modal-url="modals/software/software_edit.php?id=<?= $software_id ?>">
                                                <?= "$software_name<br><span class='text-secondary'>$software_version</span>"; ?>
                                            </a>
                                        </td>
                                        <td><?= $software_type; ?></td>
                                        <td><?= $software_license_type; ?></td>
                                        <td><?= "$seat_count / $software_seats"; ?></td>
                                        <td class="text-center">
                                            <a href="post.php?unlink_software_from_asset&asset_id=<?= $asset_id; ?>&software_id=<?= $software_id; ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-secondary btn-sm" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
                                        </td>
                                    </tr>

                                    <?php

                                }

                                ?>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card card-dark <?php if ($document_count == 0) { echo "d-none"; } ?>">
                    <div class="card-header py-2">
                        <h3 class="card-title mt-2"><i class="fa fa-fw fa-folder mr-2"></i>Documents</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/asset/asset_link_document.php?id=<?= $asset_id ?>">
                                <i class="fas fa-link mr-2"></i>Link Document
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive-sm">
                            <table class="table table-striped table-borderless table-hover">
                                <thead class="text-dark">
                                <tr>
                                    <th>Document Title</th>
                                    <th>By</th>
                                    <th>Created</th>
                                    <th>Updated</th>
                                    <th class="text-center">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                while ($row = mysqli_fetch_assoc($sql_related_documents)) {
                                    $document_id = intval($row['document_id']);
                                    $document_name = nullable_htmlentities($row['document_name']);
                                    $document_description = nullable_htmlentities($row['document_description']);
                                    $document_created_by = nullable_htmlentities($row['user_name']);
                                    $document_created_at = nullable_htmlentities($row['document_created_at']);
                                    $document_updated_at = nullable_htmlentities($row['document_updated_at']);

                                    $linked_documents[] = $document_id;

                                    ?>

                                    <tr>
                                        <td>
                                            <div><a href="document_details.php?client_id=<?= $client_id; ?>&document_id=<?= $document_id; ?>"><?= $document_name; ?></a></div>
                                            <div class="text-secondary"><?= $document_description; ?></div>
                                        </td>
                                        <td><?= $document_created_by ?></td>
                                        <td><?= $document_created_at ?></td>
                                        <td><?= $document_updated_at ?></td>
                                        <td class="text-center">
                                            <a href="#" class="btn btn-dark btn-sm ajax-modal"
                                                data-modal-size="lg"
                                                data-modal-url="modals/document/document_view.php?id=<?= $document_id ?>">
                                                <i class="fas fa-fw fa-eye"></i>
                                            </a>
                                            <a href="post.php?unlink_asset_from_document&asset_id=<?= $asset_id; ?>&document_id=<?= $document_id; ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-secondary btn-sm" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
                                        </td>
                                    </tr>

                                    <?php

                                }

                                ?>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card card-dark <?php if ($files_count == 0) { echo "d-none"; } ?>">
                    <div class="card-header py-2">
                        <h3 class="card-title mt-2"><i class="fa fa-fw fa-cube mr-2"></i>Files</h3>
                        <div class="card-tools">
                            <div class="btn-group">
                                <?php
                                if ($view == 0) {
                                ?>
                                <a href="?client_id=<?=$client_id?>&asset_id=<?=$asset_id?>&view=0" class="btn btn-primary"><i class="fas fa-list-ul"></i></a>
                                <a href="?client_id=<?=$client_id?>&asset_id=<?=$asset_id?>&view=1" class="btn btn-outline-secondary"><i class="fas fa-th-large"></i></a>
                                <?php
                                    } else {
                                ?>
                                <a href="?client_id=<?=$client_id?>&asset_id=<?=$asset_id?>&view=0" class="btn btn-outline-secondary"><i class="fas fa-list-ul"></i></a>
                                <a href="?client_id=<?=$client_id?>&asset_id=<?=$asset_id?>&view=1" class="btn btn-primary"><i class="fas fa-th-large"></i></a>
                                <?php
                                    }
                                ?>
                            </div>
                            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/asset/asset_link_file.php?id=<?= $asset_id ?>">
                                <i class="fas fa-link mr-2"></i>Link File
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive-sm">
                            <table class="table table-striped table-borderless table-hover">
                                <thead class="text-dark">
                                <tr>
                                    <th>Name</th>
                                    <th>Uploaded</th>
                                    <th class="text-center">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                while ($row = mysqli_fetch_assoc($sql_related_files)) {
                                    $file_id = intval($row['file_id']);
                                    $file_name = nullable_htmlentities($row['file_name']);
                                    $file_description = nullable_htmlentities($row['file_description']);
                                    $file_reference_name = nullable_htmlentities($row['file_reference_name']);
                                    $file_ext = nullable_htmlentities($row['file_ext']);
                                    if ($file_ext == 'pdf') {
                                        $file_icon = "file-pdf";
                                    } elseif ($file_ext == 'gz' || $file_ext == 'tar' || $file_ext == 'zip' || $file_ext == '7z' || $file_ext == 'rar') {
                                        $file_icon = "file-archive";
                                    } elseif ($file_ext == 'txt' || $file_ext == 'md') {
                                        $file_icon = "file-alt";
                                    } elseif ($file_ext == 'msg') {
                                        $file_icon = "envelope";
                                    } elseif ($file_ext == 'doc' || $file_ext == 'docx' || $file_ext == 'odt') {
                                        $file_icon = "file-word";
                                    } elseif ($file_ext == 'xls' || $file_ext == 'xlsx' || $file_ext == 'ods') {
                                        $file_icon = "file-excel";
                                    } elseif ($file_ext == 'pptx' || $file_ext == 'odp') {
                                        $file_icon = "file-powerpoint";
                                    } elseif ($file_ext == 'mp3' || $file_ext == 'wav' || $file_ext == 'ogg') {
                                        $file_icon = "file-audio";
                                    } elseif ($file_ext == 'mov' || $file_ext == 'mp4' || $file_ext == 'av1') {
                                        $file_icon = "file-video";
                                    } elseif ($file_ext == 'jpg' || $file_ext == 'jpeg' || $file_ext == 'png' || $file_ext == 'gif' || $file_ext == 'webp' || $file_ext == 'bmp' || $file_ext == 'tif') {
                                        $file_icon = "file-image";
                                    } else {
                                        $file_icon = "file";
                                    }
                                    $file_created_at = nullable_htmlentities($row['file_created_at']);

                                    $linked_files[] = $file_id;

                                    ?>
                                    <tr>
                                        <td><a class="text-dark" href="<?= "../uploads/clients/$client_id/$file_reference_name"; ?>" target="_blank" ><?= "$file_name<br><span class='text-secondary'>$file_description</span>"; ?></a></td>
                                        <td><?= $file_created_at; ?></td>
                                        <td class="text-center">
                                            <a href="post.php?unlink_asset_from_file&asset_id=<?= $asset_id; ?>&file_id=<?= $file_id; ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-secondary btn-sm" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
                                        </td>
                                    </tr>

                                    <?php

                                }

                                ?>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card card-dark <?php if ($recurring_ticket_count == 0) { echo "d-none"; } ?>">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa fa-fw fa-recycle mr-2"></i>Recurring Tickets</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive-sm">
                            <table class="table table-striped table-borderless table-hover">
                                <thead class="text-dark">
                                <tr>
                                    <th>Subject</th>
                                    <th>Priority</th>
                                    <th>Frequency</th>
                                    <th>Next Run</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                while ($row = mysqli_fetch_assoc($sql_related_recurring_tickets)) {
                                    $recurring_ticket_id = intval($row['recurring_ticket_id']);
                                    $recurring_ticket_subject = nullable_htmlentities($row['recurring_ticket_subject']);
                                    $recurring_ticket_priority = nullable_htmlentities($row['recurring_ticket_priority']);
                                    $recurring_ticket_frequency = nullable_htmlentities($row['recurring_ticket_frequency']);
                                    $recurring_ticket_next_run = nullable_htmlentities($row['recurring_ticket_next_run']);
                                ?>

                                    <tr>
                                        <td class="text-bold">
                                            <a class="ajax-modal" href="#"
                                                data-modal-size="lg"
                                                data-modal-url="modals/recurring_ticket/recurring_ticket_edit.php?id=<?= $recurring_ticket_id; ?>">
                                                <?= $recurring_ticket_subject ?>
                                            </a>
                                        </td>

                                        <td><?= $recurring_ticket_priority ?></td>

                                        <td><?= $recurring_ticket_frequency ?></td>

                                        <td><?= $recurring_ticket_next_run ?></td>

                                        <td>
                                            <div class="dropdown dropleft text-center">
                                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item ajax-modal" href="#"
                                                        data-modal-size="lg"
                                                        data-modal-url="modals/recurring_ticket/recurring_ticket_edit.php?id=<?= $recurring_ticket_id; ?>">
                                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="post.php?force_recurring_ticket=<?= $recurring_ticket_id; ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fa fa-fw fa-paper-plane text-secondary mr-2"></i>Force Reoccur
                                                    </a>
                                                    <?php
                                                    if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                    </a>
                                                </div>
                                                <?php } ?>
                                            </div>
                                        </td>
                                    </tr>

                                <?php } ?>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card card-dark <?php if ($ticket_count == 0) { echo "d-none"; } ?>">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa fa-fw fa-life-ring mr-2"></i>Tickets</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive-sm">
                            <table class="table table-striped table-borderless table-hover">
                                <thead class="text-dark">
                                <tr>
                                    <th>Number</th>
                                    <th>Subject</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Assigned</th>
                                    <th>Last Response</th>
                                    <th>Created</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                while ($row = mysqli_fetch_assoc($sql_related_tickets)) {
                                    $ticket_id = intval($row['ticket_id']);
                                    $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
                                    $ticket_number = intval($row['ticket_number']);
                                    $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                                    $ticket_priority = nullable_htmlentities($row['ticket_priority']);
                                    $ticket_status_id = intval($row['ticket_status_id']);
                                    $ticket_status_name = nullable_htmlentities($row['ticket_status_name']);
                                    $ticket_status_color = nullable_htmlentities($row['ticket_status_color']);
                                    $ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
                                    $ticket_updated_at = nullable_htmlentities($row['ticket_updated_at']);
                                    if (empty($ticket_updated_at)) {
                                        if ($ticket_status_name == "Closed") {
                                            $ticket_updated_at_display = "<p>Never</p>";
                                        } else {
                                            $ticket_updated_at_display = "<p class='text-danger'>Never</p>";
                                        }
                                    } else {
                                        $ticket_updated_at_display = $ticket_updated_at;
                                    }
                                    $ticket_closed_at = nullable_htmlentities($row['ticket_closed_at']);

                                    if ($ticket_priority == "High") {
                                        $ticket_priority_display = "<span class='p-2 badge badge-danger'>$ticket_priority</span>";
                                    } elseif ($ticket_priority == "Medium") {
                                        $ticket_priority_display = "<span class='p-2 badge badge-warning'>$ticket_priority</span>";
                                    } elseif ($ticket_priority == "Low") {
                                        $ticket_priority_display = "<span class='p-2 badge badge-info'>$ticket_priority</span>";
                                    } else {
                                        $ticket_priority_display = "-";
                                    }
                                    $ticket_assigned_to = intval($row['ticket_assigned_to']);
                                    if (empty($ticket_assigned_to)) {
                                        if ($ticket_status_id == 5) {
                                            $ticket_assigned_to_display = "<p>Not Assigned</p>";
                                        } else {
                                            $ticket_assigned_to_display = "<p class='text-danger'>Not Assigned</p>";
                                        }
                                    } else {
                                        $ticket_assigned_to_display = nullable_htmlentities($row['user_name']);
                                    }

                                    ?>

                                    <tr>
                                        <td><a href="ticket.php?client_id=<?= $client_id; ?>&ticket_id=<?= $ticket_id; ?>"><span class="badge badge-pill badge-secondary p-3"><?= "$ticket_prefix$ticket_number"; ?></span></a></td>
                                        <td><a href="ticket.php?client_id=<?= $client_id; ?>&ticket_id=<?= $ticket_id; ?>"><?= $ticket_subject; ?></a></td>
                                        <td><?= $ticket_priority_display; ?></td>
                                        <td>
                                            <span class='badge badge-pill text-light p-2' style="background-color: <?= $ticket_status_color; ?>"><?= $ticket_status_name; ?></span>
                                        </td>
                                        <td><?= $ticket_assigned_to_display; ?></td>
                                        <td><?= $ticket_updated_at_display; ?></td>
                                        <td><?= $ticket_created_at; ?></td>
                                    </tr>

                                    <?php

                                }

                                ?>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card card-dark <?php if ($service_count == 0) { echo "d-none"; } ?>">
                    <div class="card-header py-2">
                        <h3 class="card-title mt-2"><i class="fa fa-fw fa-stream mr-2"></i>Linked Services</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/asset/asset_link_service.php?id=<?= $asset_id ?>">
                                <i class="fas fa-link mr-2"></i>Link Service
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive-sm">
                            <table class="table table-striped table-borderless table-hover dataTables" style="width:100%">
                                <thead class="text-dark">
                                <tr>
                                    <th>Service</th>
                                    <th>Category</th>
                                    <th>Importance</th>
                                    <th class="text-center">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                while ($row = mysqli_fetch_assoc($sql_linked_services)) {
                                    $service_id = intval($row['service_id']);
                                    $service_name = nullable_htmlentities($row['service_name']);
                                    $service_description = nullable_htmlentities($row['service_description']);
                                    $service_category = nullable_htmlentities($row['service_category']);
                                    $service_importance = nullable_htmlentities($row['service_importance']);

                                    $linked_services[] = $service_id;

                                    ?>

                                    <tr>
                                        <td>
                                            <div><?= $service_name; ?></div>
                                            <div class="text-secondary"><?= $service_description; ?></div>
                                        </td>
                                        <td><?= $service_category; ?></td>
                                        <td><?= $service_importance; ?></td>
                                        <td class="text-center">
                                            <a href="post.php?unlink_service_from_asset&asset_id=<?= $asset_id; ?>&service_id=<?= $service_id; ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-secondary btn-sm" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
                                        </td>
                                    </tr>

                                    <?php

                                }

                                ?>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

        </div><!-- /#asset-details-content -->

        <?php

        require_once "modals/share_modal.php";

        }

        ?>

    <script>
        function updateAssetNotes(asset_id) {
            var notes = document.getElementById("assetNotes").value;

            // Send a POST request to ajax.php as ajax.php with data contact_set_notes=true, contact_id=NUM, notes=NOTES
            jQuery.post(
                "ajax.php",
                {
                    asset_set_notes: 'TRUE',
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                    asset_id: asset_id,
                    notes: notes
                }
            )
        }
    </script>

    <!-- JavaScript to Show/Hide Password Form Group -->
    <script>
        $(document).ready(function() {
            $('.authMethod').on('change', function() {
                var $form = $(this).closest('.authForm');
                if ($(this).val() === 'local') {
                    $form.find('.passwordGroup').show();
                } else {
                    $form.find('.passwordGroup').hide();
                }
            });
            $('.authMethod').trigger('change');
        });
    </script>

    <!-- Include script to get TOTP code via the credential ID -->
    <script src="js/credential_show_otp_via_id.js"></script>

    <script src="../js/bulk_actions.js"></script>

    <?php
    require_once "modals/asset/asset_interface_multiple_add.php";
    require_once "modals/asset/asset_interface_import.php";
    require_once "modals/asset/asset_interface_export.php";

}

if ($config_module_enable_rmm && isset($rmm_link) && $rmm_link):
?>
<script>
function rmmConnect(linkId, type) {
    fetch('/agent/post/rmm_remote.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?= $_SESSION["csrf_token"] ?>&link_id=' + linkId + '&type=' + type
    }).then(r => r.json()).then(d => {
        if (d.success && d.url) window.open(d.url, '_blank', 'noopener,noreferrer');
        else alert('Connect failed: ' + (d.error || 'Unknown error'));
    });
}
function rmmUnlink(linkId) {
    if (!confirm('Remove this asset from RMM monitoring? The asset will remain in ITFlow, but RMM data, alerts, and remote connect options will be removed.')) return;
    fetch('/agent/post/rmm_unlink.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?= $_SESSION["csrf_token"] ?>&link_id=' + linkId
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else alert('Failed: ' + (d.error || 'Unknown error'));
    });
}
const _rmmTabLoaded = {};
const _rmmTabData = {};
const RMM_PAGE_SIZE = 10;
function loadRmmLiveData(type, linkId) {
    if (_rmmTabLoaded[type]) return;
    _rmmTabLoaded[type] = true;
    const paneId  = type === 'wmi' ? 'rdt-hardware' : 'rdt-' + type;
    const pane    = document.getElementById(paneId);
    const loading = pane.querySelector('.rdt-loading');
    const dataDiv = pane.querySelector('.rdt-data');
    const tbody   = pane.querySelector('.rdt-body');
    fetch('/agent/post/rmm_live_data.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?= $_SESSION["csrf_token"] ?>&link_id=' + linkId + '&type=' + type
    }).then(r => r.json()).then(d => {
        loading.style.display = 'none';
        if (!d.success) {
            dataDiv.innerHTML = '<p class="text-danger p-3">' + esc(d.error || 'Load failed') + '</p>';
            dataDiv.style.display = 'block'; return;
        }
        const items = d.data || [];
        if (type === 'software' || type === 'services') {
            _rmmTabData[type] = items;
            renderRmmTable(type, 1);
        } else if (type === 'wmi') {
            const hw = d.data || {};
            pane.querySelector('.hw-make-model').textContent = hw.make_model || '-';
            pane.querySelector('.hw-cpu').textContent = Array.isArray(hw.cpu_model) ? hw.cpu_model.join(', ') : (hw.cpu_model || '-');
            pane.querySelector('.hw-ram').textContent = hw.total_ram ? hw.total_ram + ' GB' : '-';
            pane.querySelector('.hw-graphics').textContent = hw.graphics || '-';
            pane.querySelector('.hw-local-ips').textContent = hw.local_ips || '-';
            const pdList = pane.querySelector('.hw-physical-disks');
            pdList.innerHTML = (hw.physical_disks || []).length
                ? hw.physical_disks.map(pd => '<li>' + esc(pd) + '</li>').join('')
                : '<li class="text-muted">No data</li>';
            const volumes = pane.querySelector('.hw-volumes');
            volumes.innerHTML = (hw.disks || []).length ? hw.disks.map(disk => {
                const pct = Number(disk.percent ?? 0);
                const barColor = pct >= 90 ? 'bg-danger' : pct >= 75 ? 'bg-warning' : 'bg-success';
                return '<div class="mb-2">' +
                    '<div class="d-flex justify-content-between small mb-1">' +
                    '<span><i class="fas fa-hdd mr-1 text-secondary"></i>' + esc(disk.device || '') + ' <span class="text-muted">(' + esc(disk.fstype || '') + ')</span></span>' +
                    '<span class="text-muted">' + esc(disk.used || '') + ' used / ' + esc(disk.free || '') + ' free / ' + esc(disk.total || '') + ' total</span>' +
                    '</div>' +
                    '<div class="progress" style="height:6px">' +
                    '<div class="progress-bar ' + barColor + '" style="width:' + pct + '%"></div>' +
                    '</div></div>';
            }).join('') : '<p class="text-muted text-center mb-0">No disk data</p>';
        }
        dataDiv.style.display = 'block';
    }).catch(() => { loading.innerHTML = '<p class="text-danger p-3">Failed to connect to Tactical RMM</p>'; });
}
function renderRmmTable(type, page) {
    const pane   = document.getElementById('rdt-' + type);
    const tbody  = pane.querySelector('.rdt-body');
    const search = (pane.querySelector('.rdt-search').value || '').trim().toLowerCase();
    let items = _rmmTabData[type] || [];

    if (search) {
        items = items.filter(item => {
            const fields = type === 'software'
                ? [item.name, item.software, item.version, item.publisher]
                : [item.name, item.display_name, item.status, item.start_type];
            return fields.some(f => String(f || '').toLowerCase().includes(search));
        });
    }

    const total = items.length;
    const totalPages = Math.max(1, Math.ceil(total / RMM_PAGE_SIZE));
    page = Math.min(Math.max(1, page), totalPages);
    const pageItems = items.slice((page - 1) * RMM_PAGE_SIZE, page * RMM_PAGE_SIZE);

    if (type === 'software') {
        tbody.innerHTML = pageItems.length ? pageItems.map(s =>
            '<tr><td class="pl-3">' + esc(s.name || s.software || '') + '</td>' +
            '<td class="text-muted small">' + esc(s.version || '') + '</td>' +
            '<td class="text-muted small">' + esc(s.publisher || '') + '</td></tr>'
        ).join('') : '<tr><td colspan="3" class="text-muted p-3 text-center">No software found</td></tr>';
    } else if (type === 'services') {
        tbody.innerHTML = pageItems.length ? pageItems.map(s =>
            '<tr><td class="pl-3">' + esc(s.name || s.display_name || '') + '</td>' +
            '<td><span class="badge ' + (String(s.status).toLowerCase()==='running'?'badge-success':'badge-secondary') + '">' + esc(s.status || '') + '</span></td>' +
            '<td class="text-muted small">' + esc(s.start_type || '') + '</td></tr>'
        ).join('') : '<tr><td colspan="3" class="text-muted p-3 text-center">No services found</td></tr>';
    }

    const pageinfo = pane.querySelector('.rdt-pageinfo');
    pageinfo.textContent = total
        ? `Showing ${(page - 1) * RMM_PAGE_SIZE + 1}-${Math.min(page * RMM_PAGE_SIZE, total)} of ${total}`
        : 'No results';

    const pagination = pane.querySelector('.rdt-pagination');
    pagination.innerHTML = totalPages > 1
        ? '<button class="btn btn-outline-secondary"' + (page <= 1 ? ' disabled' : '') + ' onclick="renderRmmTable(\'' + type + '\',' + (page - 1) + ')"><i class="fas fa-chevron-left"></i></button>' +
          '<button class="btn btn-outline-secondary disabled">' + page + ' / ' + totalPages + '</button>' +
          '<button class="btn btn-outline-secondary"' + (page >= totalPages ? ' disabled' : '') + ' onclick="renderRmmTable(\'' + type + '\',' + (page + 1) + ')"><i class="fas fa-chevron-right"></i></button>'
        : '';
}
function esc(str) { return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function assetAlertAction(alertId, action, btn) {
    if (action === 'create_ticket' && !confirm('Create a ticket from this alert?')) return;
    fetch('/agent/post/rmm_alert.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?= $_SESSION["csrf_token"] ?>&action=' + action + '&alert_id=' + alertId
    }).then(r => r.json()).then(d => {
        if (d.success) {
            if (d.redirect) { window.location.href = d.redirect; }
            else {
                const row = document.getElementById('ral-row-' + alertId);
                if (row) row.style.opacity = '0.4';
                if (btn) btn.disabled = true;
            }
        } else { alert('Failed: ' + (d.error || 'Unknown error')); }
    });
}
function runRmmScript(linkId) {
    const sel = document.getElementById('rdt-script-select');
    const sid = sel ? sel.value : '';
    if (!sid) { alert('Please select a script.'); return; }
    const name = sel.options[sel.selectedIndex].dataset.name;
    if (!confirm('Run script "' + name + '" on this device?')) return;
    fetch('/agent/post/rmm_script_run.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?= $_SESSION["csrf_token"] ?>&script_id=' + sid + '&link_id=' + linkId
    }).then(r => r.json()).then(d => {
        if (d.success) {
            if (d.warning) alert(d.warning);
            window.location.href = '/agent/rmm_script_run.php?run_id=' + d.run_id;
        } else {
            alert('Script failed: ' + (d.error || 'Unknown error'));
        }
    });
}
</script>
<?php
endif;

require_once "../includes/footer.php";
