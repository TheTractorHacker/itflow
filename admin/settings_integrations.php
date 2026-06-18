<?php
require_once "includes/inc_all_admin.php";
enforceUserPermission('module_admin');
require_once "../includes/comet.php";

$active_tab = in_array($_GET['tab'] ?? '', ['rmm', 'backups', 'unifi']) ? $_GET['tab'] : 'rmm';

// ───────────────────────── RMM ─────────────────────────
$sql_rmm_integrations = mysqli_query($mysqli, "SELECT * FROM rmm_integrations ORDER BY name ASC");
$sql_rmm_clients      = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");

// ───────────────────────── Backups (Comet) ─────────────────────────
$comet_connected = $config_comet_enabled ? comet_test() : null;
$comet_error     = ($config_comet_enabled && !$comet_connected) ? comet_get_last_error() : null;

// ───────────────────────── UniFi ─────────────────────────
$sql_unifi_integrations = mysqli_query($mysqli, "SELECT * FROM unifi_integrations ORDER BY name ASC");
$sql_unifi_clients      = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
$all_unifi_clients      = [];
while ($c = mysqli_fetch_assoc($sql_unifi_clients)) {
    $all_unifi_clients[] = ['id' => intval($c['client_id']), 'name' => $c['client_name']];
}
?>

<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0"><i class="fas fa-plug mr-2"></i>Integrations</h4>
</div>

<div class="card card-dark">
    <div class="card-header p-0">
        <ul class="nav nav-tabs card-header-tabs" id="integrationsTabs">
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'rmm' ? 'active' : '' ?>" data-toggle="tab" href="#tab-rmm" data-tabkey="rmm">
                    <i class="fas fa-desktop mr-1"></i>RMM
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'backups' ? 'active' : '' ?>" data-toggle="tab" href="#tab-backups" data-tabkey="backups">
                    <i class="fas fa-cloud-upload-alt mr-1"></i>Backups
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'unifi' ? 'active' : '' ?>" data-toggle="tab" href="#tab-unifi" data-tabkey="unifi">
                    <i class="fas fa-wifi mr-1"></i>UniFi
                </a>
            </li>
        </ul>
    </div>
</div>

<div class="tab-content mt-3">

<!-- =======================================================================
     RMM TAB
     ======================================================================= -->
<div class="tab-pane <?= $active_tab === 'rmm' ? 'show active' : '' ?>" id="tab-rmm">

    <div class="card card-dark mb-3" style="border-top:3px solid #17a2b8;">
        <div class="card-header py-2 d-flex align-items-center">
            <h3 class="card-title mr-auto"><i class="fas fa-fw fa-desktop mr-2"></i>RMM Integration Settings</h3>
            <?php if ($config_module_enable_rmm): ?>
                <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Module Enabled</span>
            <?php else: ?>
                <span class="badge badge-secondary"><i class="fas fa-times-circle mr-1"></i>Module Disabled</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="form-group mb-2">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="rmm_module_enabled"
                               name="config_module_enable_rmm" value="1"
                               <?= $config_module_enable_rmm ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="rmm_module_enabled">Enable RMM module (shows RMM features in asset and client pages)</label>
                    </div>
                </div>
                <button type="submit" name="save_rmm_module_settings" class="btn btn-primary btn-sm">
                    <i class="fas fa-check mr-1"></i>Save Module Settings
                </button>
            </form>
        </div>
    </div>

    <?php if ($config_module_enable_rmm): ?>
    <div class="card card-dark mb-3">
        <div class="card-header py-2">
            <h3 class="card-title"><i class="fas fa-fw fa-ticket-alt mr-2"></i>Automatic Ticket Creation</h3>
        </div>
        <div class="card-body">
            <p class="text-muted small">When a new RMM alert comes in with one of the selected severities, a ticket will be created automatically.</p>
            <form action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <?php
                $auto_ticket_severities = array_filter(explode(',', $config_rmm_auto_ticket_severities));
                foreach (['critical' => 'Critical', 'error' => 'Error', 'warning' => 'Warning', 'info' => 'Info'] as $sev => $label):
                ?>
                <div class="custom-control custom-checkbox custom-control-inline">
                    <input type="checkbox" class="custom-control-input" id="rmm_auto_ticket_<?= $sev ?>"
                           name="auto_ticket_severities[]" value="<?= $sev ?>"
                           <?= in_array($sev, $auto_ticket_severities) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="rmm_auto_ticket_<?= $sev ?>"><?= $label ?></label>
                </div>
                <?php endforeach; ?>
                <div class="mt-3">
                    <button type="submit" name="save_rmm_auto_ticket_settings" class="btn btn-primary btn-sm">
                        <i class="fas fa-check mr-1"></i>Save Automation Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card card-dark mb-3">
        <div class="card-header py-2 d-flex align-items-center">
            <h3 class="card-title mr-auto"><i class="fas fa-fw fa-plug mr-2"></i>RMM Integrations</h3>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#rmm_addIntegrationModal" onclick="rmmResetModal()">
                <i class="fas fa-plus mr-1"></i>Add Integration
            </button>
        </div>
        <div class="card-body p-0">
            <?php if (mysqli_num_rows($sql_rmm_integrations) == 0): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-plug fa-3x mb-3"></i>
                    <p class="mb-1">No integrations configured.</p>
                    <p class="small">Add a Tactical RMM, Level.io, Action1, or Sophos Central connection to get started.</p>
                </div>
            <?php else: ?>
            <table class="table table-sm table-hover mb-0">
                <thead class="text-muted small border-bottom" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;">
                    <tr>
                        <th class="pl-3">Name</th>
                        <th>Provider</th>
                        <th>API URL</th>
                        <th>Status</th>
                        <th>Last Sync</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                mysqli_data_seek($sql_rmm_integrations, 0);
                while ($intg = mysqli_fetch_assoc($sql_rmm_integrations)):
                    $intg_id   = intval($intg['id']);
                    $intg_type = $intg['type'] ?? 'tactical_rmm';
                    $last_sync_row = mysqli_fetch_assoc(mysqli_query($mysqli,
                        "SELECT MAX(finished_at) as ls, status FROM rmm_sync_log WHERE integration_id=$intg_id ORDER BY id DESC LIMIT 1"
                    ));
                    $provider_label = ['tactical_rmm' => 'Tactical RMM', 'level' => 'Level.io', 'action1' => 'Action1', 'sophos_central' => 'Sophos Central'][$intg_type] ?? $intg_type;
                    $provider_color = ['tactical_rmm' => 'info', 'level' => 'primary', 'action1' => 'warning', 'sophos_central' => 'success'][$intg_type] ?? 'secondary';
                ?>
                <tr>
                    <td class="pl-3 font-weight-bold"><?= nullable_htmlentities($intg['name']) ?></td>
                    <td><span class="badge badge-<?= $provider_color ?>"><?= $provider_label ?></span></td>
                    <td class="text-muted small"><?= nullable_htmlentities($intg['api_url']) ?></td>
                    <td>
                        <?= $intg['enabled']
                            ? '<span class="badge badge-success">Enabled</span>'
                            : '<span class="badge badge-secondary">Disabled</span>' ?>
                    </td>
                    <td class="text-muted small">
                        <?= $last_sync_row['ls'] ? nullable_htmlentities($last_sync_row['ls']) : 'Never' ?>
                    </td>
                    <td class="text-right pr-3" style="white-space:nowrap">
                        <button class="btn btn-xs btn-info" onclick="rmmTestConnection(<?= $intg_id ?>)">
                            <i class="fas fa-plug mr-1"></i>Test
                        </button>
                        <button class="btn btn-xs btn-success" onclick="rmmSyncNow(<?= $intg_id ?>)">
                            <i class="fas fa-sync mr-1"></i>Sync Now
                        </button>
                        <button class="btn btn-xs btn-secondary"
                                onclick='rmmEditIntegration(<?= json_encode([
                                    "id"                => $intg_id,
                                    "name"               => $intg['name'],
                                    "type"                => $intg_type,
                                    "api_url"             => $intg['api_url'],
                                    "web_url"             => $intg['web_url'] ?? '',
                                    "default_client_id"   => intval($intg['default_client_id'] ?? 0),
                                    "enabled"             => intval($intg['enabled']),
                                ]) ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="post.php" method="post" class="d-inline"
                              onsubmit="return confirm('Delete this integration? All linked asset data will be orphaned.')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="integration_id" value="<?= $intg_id ?>">
                            <button type="submit" name="delete_rmm_integration" class="btn btn-xs btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title"><i class="fas fa-fw fa-history mr-2"></i>Recent Sync Log</h3>
        </div>
        <div class="card-body p-0">
            <?php
            $sql_rmm_log = mysqli_query($mysqli,
                "SELECT l.*, i.name as integration_name, i.type as integration_type
                 FROM rmm_sync_log l
                 LEFT JOIN rmm_integrations i ON i.id = l.integration_id
                 ORDER BY l.id DESC LIMIT 20"
            );
            if (mysqli_num_rows($sql_rmm_log) == 0): ?>
                <p class="text-muted text-center py-3 mb-0">No sync history yet.</p>
            <?php else: ?>
            <table class="table table-sm table-hover mb-0">
                <thead class="text-muted small border-bottom" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;">
                    <tr>
                        <th class="pl-3">Integration</th>
                        <th>Started</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>Matched</th>
                        <th>Skipped</th>
                        <th>Errors</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($lr = mysqli_fetch_assoc($sql_rmm_log)):
                    $badge = ['success'=>'badge-success','failed'=>'badge-danger','running'=>'badge-warning'];
                ?>
                <tr>
                    <td class="pl-3">
                        <?= nullable_htmlentities($lr['integration_name']) ?>
                        <?php $tp = $lr['integration_type'] ?? ''; if ($tp): ?>
                        <span class="badge badge-secondary ml-1" style="font-size:10px"><?= ['level' => 'Level.io', 'action1' => 'Action1', 'sophos_central' => 'Sophos Central'][$tp] ?? 'Tactical' ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= nullable_htmlentities($lr['started_at']) ?></td>
                    <td><span class="badge <?= $badge[$lr['status']] ?? 'badge-secondary' ?>"><?= htmlspecialchars($lr['status']) ?></span></td>
                    <td><?= intval($lr['assets_created']) ?></td>
                    <td><?= intval($lr['assets_updated']) ?></td>
                    <td><?= intval($lr['assets_matched']) ?></td>
                    <td><?= intval($lr['assets_skipped']) ?></td>
                    <td class="text-muted small" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= nullable_htmlentities($lr['errors']) ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit RMM Integration Modal -->
    <div class="modal fade" id="rmm_addIntegrationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title" id="rmm_integrationModalTitle">Add RMM Integration</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form action="post.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="integration_id" id="rmm_edit_integration_id" value="">
                    <div class="modal-body">

                        <div class="form-group">
                            <label class="text-light small">Provider Type</label>
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <input type="radio" class="btn-check" name="integration_type" id="rmm_type_tactical"
                                       value="tactical_rmm" checked onchange="rmmUpdateModalLabels('tactical_rmm')">
                                <label class="btn btn-outline-info flex-fill" for="rmm_type_tactical" id="rmm_lbl_tactical"
                                       style="border-radius:4px 0 0 0">
                                    <i class="fas fa-server mr-1"></i>Tactical RMM
                                </label>
                                <input type="radio" class="btn-check" name="integration_type" id="rmm_type_level"
                                       value="level" onchange="rmmUpdateModalLabels('level')">
                                <label class="btn btn-outline-primary flex-fill" for="rmm_type_level" id="rmm_lbl_level">
                                    <i class="fas fa-layer-group mr-1"></i>Level.io
                                </label>
                                <input type="radio" class="btn-check" name="integration_type" id="rmm_type_action1"
                                       value="action1" onchange="rmmUpdateModalLabels('action1')">
                                <label class="btn btn-outline-warning flex-fill" for="rmm_type_action1" id="rmm_lbl_action1">
                                    <i class="fas fa-shield-alt mr-1"></i>Action1
                                </label>
                                <input type="radio" class="btn-check" name="integration_type" id="rmm_type_sophos"
                                       value="sophos_central" onchange="rmmUpdateModalLabels('sophos_central')">
                                <label class="btn btn-outline-success flex-fill" for="rmm_type_sophos" id="rmm_lbl_sophos"
                                       style="border-radius:0 4px 4px 0">
                                    <i class="fas fa-fire-alt mr-1"></i>Sophos Central
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="text-light small">Integration Name</label>
                            <input type="text" class="form-control form-control-sm" name="integration_name" id="rmm_integration_name" required
                                   placeholder="e.g. Primary Tactical RMM">
                        </div>

                        <div class="form-group">
                            <label class="text-light small" id="rmm_label_api_url">API URL</label>
                            <input type="url" class="form-control form-control-sm" name="integration_api_url" id="rmm_integration_api_url"
                                   placeholder="https://api.yourdomain.com" required>
                            <small class="text-light" id="rmm_help_api_url">
                                Tactical RMM: enter API server base URL (no trailing slash). e.g. <code>https://api.yourdomain.com</code>
                            </small>
                        </div>

                        <div class="form-group" id="rmm_web_url_group">
                            <label class="text-light small" id="rmm_label_web_url">Dashboard / Web URL</label>
                            <input type="url" class="form-control form-control-sm" name="integration_web_url" id="rmm_integration_web_url"
                                   placeholder="https://rmm.yourdomain.com">
                            <small class="text-light" id="rmm_help_web_url">
                                The browser-accessible dashboard URL (used for Connect button).
                            </small>
                        </div>

                        <div class="form-group">
                            <label class="text-light small" id="rmm_label_api_key">API Key</label>
                            <input type="password" class="form-control form-control-sm" name="integration_api_key" id="rmm_integration_api_key"
                                   autocomplete="new-password" placeholder="(leave blank to keep existing when editing)">
                            <small class="text-light" id="rmm_help_api_key">
                                Stored encrypted. Generate in Tactical RMM → Settings → Global Settings → API Keys.
                            </small>
                        </div>

                        <div class="form-group" id="rmm_client_secret_group" style="display:none">
                            <label class="text-light small">Client Secret</label>
                            <input type="password" class="form-control form-control-sm" name="integration_client_secret" id="rmm_integration_client_secret"
                                   autocomplete="new-password" placeholder="(leave blank to keep existing when editing)">
                            <small class="text-light">Stored encrypted.</small>
                        </div>

                        <div class="form-group">
                            <label class="text-light small">Default Client</label>
                            <select class="form-control form-control-sm" name="integration_default_client_id" id="rmm_integration_default_client_id">
                                <option value="0">— None —</option>
                                <?php
                                mysqli_data_seek($sql_rmm_clients, 0);
                                while ($c = mysqli_fetch_assoc($sql_rmm_clients)): ?>
                                <option value="<?= intval($c['client_id']) ?>"><?= nullable_htmlentities($c['client_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-light">
                                Used when a device has no client/group name to match (e.g. a single-tenant Sophos Central
                                account). Newly-discovered devices are assigned here instead of being skipped.
                            </small>
                        </div>

                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="rmm_integration_enabled"
                                   name="integration_enabled" value="1" checked>
                            <label class="custom-control-label" for="rmm_integration_enabled">Enabled</label>
                        </div>

                        <div id="rmm_test_result" class="mt-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_rmm_integration" class="btn btn-primary btn-sm">
                            <i class="fas fa-check mr-1"></i>Save Integration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<!-- =======================================================================
     BACKUPS (COMET) TAB
     ======================================================================= -->
<div class="tab-pane <?= $active_tab === 'backups' ? 'show active' : '' ?>" id="tab-backups">

    <div class="card card-dark mb-3" style="border-top:3px solid #f39c12;">
        <div class="card-header py-2 d-flex align-items-center">
            <h3 class="card-title mr-auto">
                <i class="fas fa-fw fa-cloud-upload-alt mr-2"></i>Comet Backup Integration
            </h3>
            <?php if ($config_comet_enabled): ?>
                <?php if ($comet_connected): ?>
                    <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Connected</span>
                <?php else: ?>
                    <span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i>Cannot reach server</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php if ($comet_error) { ?>
        <div class="px-3 pt-2">
            <div class="small text-danger"><i class="fas fa-exclamation-triangle mr-1"></i><?= nullable_htmlentities($comet_error) ?></div>
        </div>
        <?php } ?>
        <div class="card-body">
            <form action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="comet_enabled"
                               name="config_comet_enabled" value="1"
                               <?= $config_comet_enabled ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="comet_enabled">Enable Comet Backup integration</label>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="text-muted small mb-1">Server URL</label>
                            <input type="text" class="form-control form-control-sm"
                                   name="config_comet_server_url"
                                   value="<?= nullable_htmlentities($config_comet_server_url) ?>"
                                   placeholder="http://10.1.0.35:8060">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="text-muted small mb-1">Admin Username</label>
                            <input type="text" class="form-control form-control-sm"
                                   name="config_comet_admin_user" autocomplete="off"
                                   value="<?= nullable_htmlentities($config_comet_admin_user) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="text-muted small mb-1">Admin Password</label>
                            <input type="password" class="form-control form-control-sm"
                                   name="config_comet_admin_pass" autocomplete="new-password"
                                   placeholder="<?= $config_comet_admin_pass ? '(saved — leave blank to keep)' : '' ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="text-muted small mb-1">
                                TOTP Secret <small>(for 2FA admin accounts — base32 secret from your authenticator)</small>
                            </label>
                            <input type="password" class="form-control form-control-sm font-monospace"
                                   name="config_comet_totp_secret" autocomplete="new-password"
                                   placeholder="<?= $config_comet_totp_secret ? '(saved)' : 'JBSWY3DPEHPK3PXP...' ?>">
                            <small class="text-muted">Leave blank to keep existing. Stored to generate TOTP codes automatically — a session key is cached so codes are only generated when needed.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="text-muted small mb-1">Webhook Secret</label>
                            <input type="text" class="form-control form-control-sm font-monospace"
                                   name="config_comet_webhook_secret" autocomplete="off"
                                   value="<?= nullable_htmlentities($config_comet_webhook_secret) ?>"
                                   placeholder="random-secret-string">
                            <small class="text-muted">Comet sends this in the <code>X-Comet-Secret</code> header. Leave blank to skip verification.</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="comet_auto_ticket"
                               name="config_comet_auto_ticket" value="1"
                               <?= $config_comet_auto_ticket ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="comet_auto_ticket">
                            Auto-create tickets on backup failure (one ticket per device, auto-resolves on success)
                        </label>
                    </div>
                </div>

                <?php if (!empty($config_base_url)): ?>
                <div class="alert alert-secondary py-2 mb-3">
                    <strong><i class="fas fa-link mr-1"></i>Webhook URL</strong> — add this in Comet Server → Admin → Server Settings → Webhooks:<br>
                    <code>https://<?= nullable_htmlentities($config_base_url) ?>/comet_webhook.php</code><br>
                    <small class="text-muted">Event: <strong>Job Completed (4201)</strong> &middot; Custom Header: <strong>X-Comet-Secret</strong></small>
                </div>
                <?php endif; ?>

                <hr>
                <button type="submit" name="save_comet_settings" class="btn btn-primary btn-sm">
                    <i class="fas fa-check mr-1"></i>Save &amp; Test Connection
                </button>
                <?php if ($config_comet_enabled): ?>
                <a href="comet_status.php" class="btn btn-secondary btn-sm ml-2">
                    <i class="fas fa-th-list mr-1"></i>View Backup Status
                </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Client mapping -->
    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title"><i class="fas fa-fw fa-link mr-2"></i>Client → Comet User Mapping</h3>
        </div>
        <div class="card-body p-0">
            <?php if (!$config_comet_enabled || !$comet_connected): ?>
                <p class="text-muted text-center py-3 mb-0">
                    <?= !$config_comet_enabled ? 'Enable and configure Comet above to manage mappings.' : 'Cannot reach Comet server. Check connection settings.' ?>
                </p>
            <?php else:
                $comet_users = array_keys(comet_get_users() ?: []);
                sort($comet_users);
                $sql_comet_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name");
                $sql_comet_maps    = mysqli_query($mysqli, "SELECT map_client_id, map_comet_username FROM comet_client_map");
                $comet_maps = [];
                while ($m = mysqli_fetch_assoc($sql_comet_maps)) {
                    $comet_maps[intval($m['map_client_id'])] = $m['map_comet_username'];
                }
            ?>
            <form action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <table class="table table-sm table-borderless table-hover mb-0">
                    <thead class="text-muted small border-bottom" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;">
                        <tr>
                            <th class="pl-3">ITFlow Client</th>
                            <th>Comet Username</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($client = mysqli_fetch_assoc($sql_comet_clients)):
                        $cid   = intval($client['client_id']);
                        $cname = nullable_htmlentities($client['client_name']);
                        $mapped = $comet_maps[$cid] ?? '';
                    ?>
                        <tr>
                            <td class="pl-3"><?= $cname ?></td>
                            <td style="width:55%;">
                                <select class="form-control form-control-sm" name="comet_map[<?= $cid ?>]">
                                    <option value="">— Not mapped —</option>
                                    <?php foreach ($comet_users as $cu): ?>
                                        <option value="<?= htmlspecialchars($cu) ?>" <?= $mapped === $cu ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cu) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <div class="card-footer py-2">
                    <button type="submit" name="save_comet_maps" class="btn btn-primary btn-sm">
                        <i class="fas fa-check mr-1"></i>Save Mappings
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- =======================================================================
     UNIFI TAB
     ======================================================================= -->
<div class="tab-pane <?= $active_tab === 'unifi' ? 'show active' : '' ?>" id="tab-unifi">

    <div class="card card-dark mb-3" style="border-top:3px solid #17a2b8;">
        <div class="card-header py-2 d-flex align-items-center">
            <h3 class="card-title mr-auto">
                <i class="fas fa-fw fa-wifi mr-2"></i>UniFi Integration Settings
            </h3>
            <?php if ($config_module_enable_unifi): ?>
                <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Module Enabled</span>
            <?php else: ?>
                <span class="badge badge-secondary"><i class="fas fa-times-circle mr-1"></i>Module Disabled</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Syncs UniFi access points/switches to Assets, Wi-Fi SSIDs to Credentials, and networks (VLANs/subnets)
                to Networks. UniFi sites are matched to ITFlow clients by name (case-insensitive).
            </p>
            <form action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="form-group mb-2">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="unifi_module_enabled"
                               name="config_module_enable_unifi" value="1"
                               <?= $config_module_enable_unifi ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="unifi_module_enabled">Enable UniFi module</label>
                    </div>
                </div>
                <button type="submit" name="save_unifi_module_settings" class="btn btn-primary btn-sm">
                    <i class="fas fa-check mr-1"></i>Save Module Settings
                </button>
            </form>
        </div>
    </div>

    <div class="card card-dark mb-3">
        <div class="card-header py-2 d-flex align-items-center">
            <h3 class="card-title mr-auto"><i class="fas fa-fw fa-plug mr-2"></i>UniFi Controllers</h3>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#unifi_addIntegrationModal" onclick="unifiResetModal()">
                <i class="fas fa-plus mr-1"></i>Add Controller
            </button>
        </div>
        <div class="card-body p-0">
            <?php if (mysqli_num_rows($sql_unifi_integrations) == 0): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-wifi fa-3x mb-3"></i>
                    <p class="mb-1">No UniFi controllers configured.</p>
                    <p class="small">Add a UniFi OS controller connection to get started.</p>
                </div>
            <?php else: ?>
            <table class="table table-sm table-hover mb-0">
                <thead class="text-muted small border-bottom" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;">
                    <tr>
                        <th class="pl-3">Name</th>
                        <th>Host</th>
                        <th>Port</th>
                        <th>Status</th>
                        <th>Last Sync</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                mysqli_data_seek($sql_unifi_integrations, 0);
                while ($intg = mysqli_fetch_assoc($sql_unifi_integrations)):
                    $intg_id = intval($intg['id']);
                    $last_sync_row = mysqli_fetch_assoc(mysqli_query($mysqli,
                        "SELECT MAX(finished_at) as ls, status FROM unifi_sync_log WHERE integration_id=$intg_id ORDER BY id DESC LIMIT 1"
                    ));
                ?>
                <tr>
                    <td class="pl-3 font-weight-bold"><?= nullable_htmlentities($intg['name']) ?></td>
                    <td class="text-muted small"><?= nullable_htmlentities($intg['host']) ?></td>
                    <td class="text-muted small"><?= intval($intg['port']) ?></td>
                    <td>
                        <?= $intg['enabled']
                            ? '<span class="badge badge-success">Enabled</span>'
                            : '<span class="badge badge-secondary">Disabled</span>' ?>
                    </td>
                    <td class="text-muted small">
                        <?= $last_sync_row['ls'] ? nullable_htmlentities($last_sync_row['ls']) : 'Never' ?>
                    </td>
                    <td class="text-right pr-3" style="white-space:nowrap">
                        <button class="btn btn-xs btn-info" onclick="unifiTestConnection(<?= $intg_id ?>)">
                            <i class="fas fa-plug mr-1"></i>Test
                        </button>
                        <button class="btn btn-xs btn-success" onclick="unifiSyncNow(<?= $intg_id ?>)">
                            <i class="fas fa-sync mr-1"></i>Sync Now
                        </button>
                        <button class="btn btn-xs btn-primary" onclick='unifiOpenSiteMappings(<?= $intg_id ?>, <?= json_encode($intg['name']) ?>)'>
                            <i class="fas fa-sitemap mr-1"></i>Sites
                        </button>
                        <button class="btn btn-xs btn-secondary"
                                onclick='unifiEditIntegration(<?= json_encode([
                                    "id"         => $intg_id,
                                    "name"       => $intg['name'],
                                    "host"       => $intg['host'],
                                    "port"       => intval($intg['port']),
                                    "verify_ssl" => intval($intg['verify_ssl']),
                                    "enabled"    => intval($intg['enabled']),
                                ]) ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="post.php" method="post" class="d-inline"
                              onsubmit="return confirm('Delete this UniFi controller?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="integration_id" value="<?= $intg_id ?>">
                            <button type="submit" name="delete_unifi_integration" class="btn btn-xs btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title"><i class="fas fa-fw fa-history mr-2"></i>Recent Sync Log</h3>
        </div>
        <div class="card-body p-0">
            <?php
            $sql_unifi_log = mysqli_query($mysqli,
                "SELECT l.*, i.name as integration_name
                 FROM unifi_sync_log l
                 LEFT JOIN unifi_integrations i ON i.id = l.integration_id
                 ORDER BY l.id DESC LIMIT 20"
            );
            if (mysqli_num_rows($sql_unifi_log) == 0): ?>
                <p class="text-muted text-center py-3 mb-0">No sync history yet.</p>
            <?php else: ?>
            <table class="table table-sm table-hover mb-0">
                <thead class="text-muted small border-bottom" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;">
                    <tr>
                        <th class="pl-3">Controller</th>
                        <th>Started</th>
                        <th>Status</th>
                        <th>Devices</th>
                        <th>Wi-Fi</th>
                        <th>Networks</th>
                        <th>Errors</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($lr = mysqli_fetch_assoc($sql_unifi_log)):
                    $badge = ['success'=>'badge-success','failed'=>'badge-danger','running'=>'badge-warning'];
                ?>
                <tr>
                    <td class="pl-3"><?= nullable_htmlentities($lr['integration_name']) ?></td>
                    <td class="text-muted small"><?= nullable_htmlentities($lr['started_at']) ?></td>
                    <td><span class="badge <?= $badge[$lr['status']] ?? 'badge-secondary' ?>"><?= htmlspecialchars($lr['status']) ?></span></td>
                    <td class="text-muted small">
                        +<?= intval($lr['devices_created']) ?> / ~<?= intval($lr['devices_updated']) ?> / -<?= intval($lr['devices_skipped']) ?>
                    </td>
                    <td class="text-muted small">
                        +<?= intval($lr['wifi_created']) ?> / ~<?= intval($lr['wifi_updated']) ?> / -<?= intval($lr['wifi_skipped']) ?>
                    </td>
                    <td class="text-muted small">
                        +<?= intval($lr['networks_created']) ?> / ~<?= intval($lr['networks_updated']) ?> / -<?= intval($lr['networks_skipped']) ?>
                    </td>
                    <td class="text-muted small" style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= nullable_htmlentities($lr['errors']) ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit UniFi Controller Modal -->
    <div class="modal fade" id="unifi_addIntegrationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title" id="unifi_integrationModalTitle">Add UniFi Controller</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form action="post.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="integration_id" id="unifi_edit_integration_id" value="">
                    <div class="modal-body">

                        <div class="form-group">
                            <label class="text-light small">Name</label>
                            <input type="text" class="form-control form-control-sm" name="integration_name" id="unifi_integration_name" required
                                   placeholder="e.g. Main Office UniFi">
                        </div>

                        <div class="form-group">
                            <label class="text-light small">Controller Host / IP</label>
                            <input type="text" class="form-control form-control-sm" name="integration_host" id="unifi_integration_host" required
                                   placeholder="10.1.0.30">
                        </div>

                        <div class="form-group">
                            <label class="text-light small">Port</label>
                            <input type="number" class="form-control form-control-sm" name="integration_port" id="unifi_integration_port"
                                   placeholder="443" min="1" max="65535" value="443">
                        </div>

                        <div class="form-group">
                            <label class="text-light small">API Key</label>
                            <input type="password" class="form-control form-control-sm" name="integration_api_key" id="unifi_integration_api_key"
                                   autocomplete="new-password" placeholder="(leave blank to keep existing when editing)">
                            <small class="text-light">
                                Stored encrypted. Generate in UniFi OS &rarr; Settings &rarr; Control Plane &rarr; Integrations &rarr; API Key.
                            </small>
                        </div>

                        <div class="custom-control custom-switch mb-2">
                            <input type="checkbox" class="custom-control-input" id="unifi_integration_verify_ssl"
                                   name="integration_verify_ssl" value="1">
                            <label class="custom-control-label" for="unifi_integration_verify_ssl">Verify SSL certificate</label>
                        </div>

                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="unifi_integration_enabled"
                                   name="integration_enabled" value="1" checked>
                            <label class="custom-control-label" for="unifi_integration_enabled">Enabled</label>
                        </div>

                        <div id="unifi_test_result" class="mt-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_unifi_integration" class="btn btn-primary btn-sm">
                            <i class="fas fa-check mr-1"></i>Save Controller
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Site Mapping Modal -->
    <div class="modal fade" id="unifi_siteMappingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title">Site &rarr; Client Mapping <span id="unifi_siteMappingIntgName" class="text-muted"></span></h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        By default, each UniFi site is matched to an ITFlow client by name (case-insensitive).
                        Use this to override that match, or skip syncing a site entirely.
                    </p>
                    <div id="unifi_siteMappingBody">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-spinner fa-spin mr-1"></i>Loading sites...
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="unifi_saveSiteMappingBtn" onclick="unifiSaveSiteMappings()" disabled>
                        <i class="fas fa-check mr-1"></i>Save Mapping
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

</div><!-- /.tab-content -->

<script>
const CSRF = '<?= $_SESSION['csrf_token'] ?>';

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Keep the active tab in the URL so reloads/bookmarks land back on it
$('#integrationsTabs a').on('shown.bs.tab', function (e) {
    const tab = e.target.getAttribute('data-tabkey');
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    history.replaceState(null, '', url);
});

/* =========================================================================
   RMM
   ========================================================================= */

const RMM_TYPE_HINTS = {
    tactical_rmm: {
        placeholder_name:    'e.g. Primary Tactical RMM',
        placeholder_api_url: 'https://api.yourdomain.com',
        help_api_url:        'API server base URL. Older installs: <code>https://api.yourdomain.com</code>. Newer (v0.18+): <code>https://api.yourdomain.com/api/v3</code>',
        label_web_url:       'Dashboard URL',
        placeholder_web_url: 'https://rmm.yourdomain.com',
        help_web_url:        'Browser dashboard URL (used for Connect button). e.g. <code>https://rmm.yourdomain.com</code>',
        help_api_key:        'Generate in Tactical RMM → Settings → Global Settings → API Keys.',
    },
    level: {
        placeholder_name:    'e.g. Level.io RMM',
        placeholder_api_url: 'https://api.level.io',
        help_api_url:        'Level.io API server. Enter <code>https://api.level.io</code> — the <code>/v2</code> prefix is added automatically. Do NOT enter app.level.io.',
        label_web_url:       'Organization ID (optional)',
        placeholder_web_url: 'your-org-id',
        help_web_url:        'Your Level.io organization slug (leave blank if unsure — device URLs will still work).',
        help_api_key:        'Generate in Level.io → Settings → API Keys.',
    },
    action1: {
        placeholder_name:    'e.g. Action1 RMM',
        placeholder_api_url: 'https://app.action1.com/api/3.0',
        help_api_url:        'Action1 API base URL. e.g. <code>https://app.action1.com/api/3.0</code>',
        label_web_url:       'Dashboard URL',
        placeholder_web_url: 'https://app.action1.com',
        help_web_url:        'Browser dashboard URL (used for Connect button). e.g. <code>https://app.action1.com</code>',
        label_api_key:       'Client ID',
        help_api_key:        'Generate in Action1 → Automation → API → Add API Credential.',
    },
    sophos_central: {
        placeholder_name:    'e.g. Sophos Central Firewalls',
        placeholder_api_url: 'https://api.central.sophos.com',
        help_api_url:        'Fixed Sophos Central API entry point — leave as-is.',
        label_web_url:       'Dashboard URL (optional)',
        placeholder_web_url: 'https://cloud.sophos.com',
        help_web_url:        'Not required — only used as a fallback link.',
        label_api_key:       'Client ID',
        help_api_key:        'Generate in Sophos Central → Global Settings → API Credentials. Single-tenant credentials only (not Partner/Organization).',
    },
};

function rmmUpdateModalLabels(type) {
    const h = RMM_TYPE_HINTS[type] || RMM_TYPE_HINTS.tactical_rmm;
    document.getElementById('rmm_integration_name').placeholder    = h.placeholder_name;
    document.getElementById('rmm_integration_api_url').placeholder = h.placeholder_api_url;
    document.getElementById('rmm_help_api_url').innerHTML          = h.help_api_url;
    document.getElementById('rmm_label_web_url').textContent       = h.label_web_url;
    document.getElementById('rmm_integration_web_url').placeholder = h.placeholder_web_url;
    document.getElementById('rmm_help_web_url').innerHTML          = h.help_web_url;
    document.getElementById('rmm_label_api_key').textContent       = h.label_api_key || 'API Key';
    document.getElementById('rmm_help_api_key').innerHTML          = h.help_api_key;

    if (type === 'sophos_central' && !document.getElementById('rmm_integration_api_url').value) {
        document.getElementById('rmm_integration_api_url').value = 'https://api.central.sophos.com';
    }

    document.getElementById('rmm_lbl_tactical').className = type === 'tactical_rmm'
        ? 'btn btn-info flex-fill' : 'btn btn-outline-info flex-fill';
    document.getElementById('rmm_lbl_level').className = type === 'level'
        ? 'btn btn-primary flex-fill' : 'btn btn-outline-primary flex-fill';
    document.getElementById('rmm_lbl_action1').className = type === 'action1'
        ? 'btn btn-warning flex-fill' : 'btn btn-outline-warning flex-fill';
    document.getElementById('rmm_lbl_sophos').className = type === 'sophos_central'
        ? 'btn btn-success flex-fill' : 'btn btn-outline-success flex-fill';

    document.getElementById('rmm_lbl_tactical').style.borderRadius = '4px 0 0 0';
    document.getElementById('rmm_lbl_level').style.borderRadius    = '0';
    document.getElementById('rmm_lbl_action1').style.borderRadius = '0';
    document.getElementById('rmm_lbl_sophos').style.borderRadius  = '0 4px 4px 0';

    document.getElementById('rmm_client_secret_group').style.display =
        (type === 'action1' || type === 'sophos_central') ? '' : 'none';
}

function rmmResetModal() {
    document.getElementById('rmm_integrationModalTitle').textContent = 'Add RMM Integration';
    document.getElementById('rmm_edit_integration_id').value = '';
    document.getElementById('rmm_integration_name').value    = '';
    document.getElementById('rmm_integration_api_url').value = '';
    document.getElementById('rmm_integration_web_url').value = '';
    document.getElementById('rmm_integration_api_key').value = '';
    document.getElementById('rmm_integration_client_secret').value = '';
    document.getElementById('rmm_integration_api_key').placeholder = '(leave blank to keep existing when editing)';
    document.getElementById('rmm_integration_default_client_id').value = '0';
    document.getElementById('rmm_integration_enabled').checked = true;
    document.getElementById('rmm_type_tactical').checked = true;
    document.getElementById('rmm_test_result').innerHTML = '';
    rmmUpdateModalLabels('tactical_rmm');
}

function rmmEditIntegration(data) {
    document.getElementById('rmm_integrationModalTitle').textContent = 'Edit Integration';
    document.getElementById('rmm_edit_integration_id').value    = data.id;
    document.getElementById('rmm_integration_name').value       = data.name;
    document.getElementById('rmm_integration_api_url').value    = data.api_url;
    document.getElementById('rmm_integration_web_url').value    = data.web_url || '';
    document.getElementById('rmm_integration_api_key').value    = '';
    document.getElementById('rmm_integration_client_secret').value = '';
    document.getElementById('rmm_integration_default_client_id').value = data.default_client_id || '0';
    document.getElementById('rmm_integration_enabled').checked  = data.enabled == 1;
    document.getElementById('rmm_integration_api_key').placeholder = '(leave blank to keep existing)';
    document.getElementById('rmm_integration_client_secret').placeholder = '(leave blank to keep existing)';
    document.getElementById('rmm_test_result').innerHTML = '';

    const typeIds = {level: 'rmm_type_level', action1: 'rmm_type_action1', tactical_rmm: 'rmm_type_tactical', sophos_central: 'rmm_type_sophos'};
    const typeRadio = document.getElementById(typeIds[data.type] || 'rmm_type_tactical');
    if (typeRadio) typeRadio.checked = true;
    rmmUpdateModalLabels(data.type || 'tactical_rmm');

    $('#rmm_addIntegrationModal').modal('show');
}

function rmmTestConnection(integrationId) {
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Testing...';
    fetch('/admin/post.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=' + CSRF + '&test_rmm_connection=1&integration_id=' + integrationId
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        if (d.success) {
            btn.innerHTML = '<i class="fas fa-check mr-1"></i>Connected';
            btn.classList.replace('btn-info', 'btn-success');
        } else {
            btn.innerHTML = '<i class="fas fa-times mr-1"></i>Failed';
            btn.classList.replace('btn-info', 'btn-danger');
            alert('Connection failed: ' + (d.error || 'Unknown error'));
        }
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-plug mr-1"></i>Test';
            btn.className = btn.className.replace(/btn-(success|danger)/g, 'btn-info');
            btn.disabled = false;
        }, 3000);
    })
    .catch(() => {
        btn.innerHTML = '<i class="fas fa-times mr-1"></i>Error';
        btn.disabled = false;
    });
}

function rmmSyncNow(integrationId) {
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Syncing...';
    fetch('/admin/post.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=' + CSRF + '&sync_rmm_now=1&integration_id=' + integrationId
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync mr-1"></i>Sync Now';
        if (d.success) {
            location.reload();
        } else {
            alert('Sync failed: ' + (d.error || 'Unknown error'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync mr-1"></i>Sync Now';
    });
}

/* =========================================================================
   UNIFI
   ========================================================================= */

const UNIFI_ALL_CLIENTS = <?= json_encode($all_unifi_clients) ?>;
let unifiSiteMappingIntegrationId = null;

function unifiResetModal() {
    document.getElementById('unifi_integrationModalTitle').textContent = 'Add UniFi Controller';
    document.getElementById('unifi_edit_integration_id').value = '';
    document.getElementById('unifi_integration_name').value = '';
    document.getElementById('unifi_integration_host').value = '';
    document.getElementById('unifi_integration_port').value = '443';
    document.getElementById('unifi_integration_api_key').value = '';
    document.getElementById('unifi_integration_api_key').placeholder = '(required)';
    document.getElementById('unifi_integration_verify_ssl').checked = false;
    document.getElementById('unifi_integration_enabled').checked = true;
    document.getElementById('unifi_test_result').innerHTML = '';
}

function unifiEditIntegration(data) {
    document.getElementById('unifi_integrationModalTitle').textContent = 'Edit UniFi Controller';
    document.getElementById('unifi_edit_integration_id').value = data.id;
    document.getElementById('unifi_integration_name').value = data.name;
    document.getElementById('unifi_integration_host').value = data.host;
    document.getElementById('unifi_integration_port').value = data.port;
    document.getElementById('unifi_integration_api_key').value = '';
    document.getElementById('unifi_integration_api_key').placeholder = '(leave blank to keep existing)';
    document.getElementById('unifi_integration_verify_ssl').checked = data.verify_ssl == 1;
    document.getElementById('unifi_integration_enabled').checked = data.enabled == 1;
    document.getElementById('unifi_test_result').innerHTML = '';

    $('#unifi_addIntegrationModal').modal('show');
}

function unifiTestConnection(integrationId) {
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Testing...';
    fetch('/admin/post.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=' + CSRF + '&test_unifi_connection=1&integration_id=' + integrationId
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        if (d.success) {
            btn.innerHTML = '<i class="fas fa-check mr-1"></i>Connected';
            btn.classList.replace('btn-info', 'btn-success');
        } else {
            btn.innerHTML = '<i class="fas fa-times mr-1"></i>Failed';
            btn.classList.replace('btn-info', 'btn-danger');
            alert('Connection failed: ' + (d.error || 'Unknown error'));
        }
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-plug mr-1"></i>Test';
            btn.className = btn.className.replace(/btn-(success|danger)/g, 'btn-info');
            btn.disabled = false;
        }, 3000);
    })
    .catch(() => {
        btn.innerHTML = '<i class="fas fa-times mr-1"></i>Error';
        btn.disabled = false;
    });
}

function unifiSyncNow(integrationId) {
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Syncing...';
    fetch('/admin/post.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=' + CSRF + '&sync_unifi_now=1&integration_id=' + integrationId
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync mr-1"></i>Sync Now';
        if (d.success) {
            location.reload();
        } else {
            alert('Sync failed: ' + (d.error || 'Unknown error'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync mr-1"></i>Sync Now';
    });
}

function unifiOpenSiteMappings(integrationId, integrationName) {
    unifiSiteMappingIntegrationId = integrationId;
    document.getElementById('unifi_siteMappingIntgName').textContent = '- ' + integrationName;
    document.getElementById('unifi_saveSiteMappingBtn').disabled = true;
    document.getElementById('unifi_siteMappingBody').innerHTML =
        '<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-1"></i>Loading sites...</div>';

    $('#unifi_siteMappingModal').modal('show');

    fetch('/admin/post.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=' + CSRF + '&load_unifi_sites=1&integration_id=' + integrationId
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) {
            document.getElementById('unifi_siteMappingBody').innerHTML =
                '<div class="alert alert-danger mb-0">' + (d.error || 'Failed to load sites') + '</div>';
            return;
        }
        unifiRenderSiteMappings(d.sites);
        document.getElementById('unifi_saveSiteMappingBtn').disabled = false;
    })
    .catch(() => {
        document.getElementById('unifi_siteMappingBody').innerHTML =
            '<div class="alert alert-danger mb-0">Failed to load sites</div>';
    });
}

function unifiRenderSiteMappings(sites) {
    if (!sites.length) {
        document.getElementById('unifi_siteMappingBody').innerHTML =
            '<p class="text-muted text-center mb-0">No sites found on this controller.</p>';
        return;
    }

    let clientOptions = '<option value="">-- Select client --</option>';
    UNIFI_ALL_CLIENTS.forEach(c => {
        clientOptions += `<option value="${c.id}">${escapeHtml(c.name)}</option>`;
    });

    let html = '<table class="table table-sm table-hover mb-0"><thead class="text-muted small">' +
        '<tr><th>UniFi Site</th><th>Auto-Match</th><th>Mapping</th></tr></thead><tbody>';

    sites.forEach(site => {
        const siteId   = site.unifi_site_id;
        const current  = site.client_id;
        const autoName = site.auto_client_name;

        let selected = 'auto';
        if (current !== null) {
            selected = (current === '0' || current === 0) ? 'skip' : String(current);
        }

        let options = `<option value="auto" ${selected === 'auto' ? 'selected' : ''}>Auto (match by name)</option>`;
        options += `<option value="skip" ${selected === 'skip' ? 'selected' : ''}>Skip (don't sync)</option>`;
        UNIFI_ALL_CLIENTS.forEach(c => {
            options += `<option value="${c.id}" ${selected === String(c.id) ? 'selected' : ''}>${escapeHtml(c.name)}</option>`;
        });

        html += '<tr>' +
            `<td>${escapeHtml(site.unifi_site_name)}</td>` +
            `<td class="text-muted small">${autoName ? escapeHtml(autoName) : '<em>no match</em>'}</td>` +
            `<td><select class="form-control form-control-sm unifi-site-mapping-select" data-site-id="${escapeHtml(siteId)}">${options}</select></td>` +
            '</tr>';
    });

    html += '</tbody></table>';
    document.getElementById('unifi_siteMappingBody').innerHTML = html;
}

function unifiSaveSiteMappings() {
    const btn = document.getElementById('unifi_saveSiteMappingBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';

    const params = new URLSearchParams();
    params.append('csrf_token', CSRF);
    params.append('save_unifi_site_mapping', '1');
    params.append('integration_id', unifiSiteMappingIntegrationId);

    document.querySelectorAll('.unifi-site-mapping-select').forEach(sel => {
        params.append('mapping[' + sel.dataset.siteId + ']', sel.value);
    });

    fetch('/admin/post.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-1"></i>Save Mapping';
        if (d.success) {
            $('#unifi_siteMappingModal').modal('hide');
        } else {
            alert('Failed to save mapping: ' + (d.error || 'Unknown error'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-1"></i>Save Mapping';
    });
}
</script>

<?php require_once "../includes/footer.php"; ?>
