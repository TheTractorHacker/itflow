<?php
require_once "includes/inc_all_admin.php";
enforceUserPermission('module_admin');

$sql_integrations = mysqli_query($mysqli, "SELECT * FROM rmm_integrations ORDER BY name ASC");
?>

<div class="card card-dark mb-3" style="border-top:3px solid #17a2b8;">
    <div class="card-header py-2 d-flex align-items-center">
        <h3 class="card-title mr-auto">
            <i class="fas fa-fw fa-desktop mr-2"></i>RMM Integration Settings
        </h3>
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
                    <input type="checkbox" class="custom-control-input" id="rmm_enabled"
                           name="config_module_enable_rmm" value="1"
                           <?= $config_module_enable_rmm ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="rmm_enabled">Enable RMM module (shows RMM features in asset and client pages)</label>
                </div>
            </div>
            <button type="submit" name="save_rmm_module_settings" class="btn btn-primary btn-sm">
                <i class="fas fa-check mr-1"></i>Save Module Settings
            </button>
        </form>
    </div>
</div>

<!-- Integration List -->
<div class="card card-dark mb-3">
    <div class="card-header py-2 d-flex align-items-center">
        <h3 class="card-title mr-auto"><i class="fas fa-fw fa-plug mr-2"></i>RMM Integrations</h3>
        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addIntegrationModal" onclick="resetModal()">
            <i class="fas fa-plus mr-1"></i>Add Integration
        </button>
    </div>
    <div class="card-body p-0">
        <?php if (mysqli_num_rows($sql_integrations) == 0): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-plug fa-3x mb-3"></i>
                <p class="mb-1">No integrations configured.</p>
                <p class="small">Add a Tactical RMM or Level RMM connection to get started.</p>
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
            mysqli_data_seek($sql_integrations, 0);
            while ($intg = mysqli_fetch_assoc($sql_integrations)):
                $intg_id   = intval($intg['id']);
                $intg_type = $intg['type'] ?? 'tactical_rmm';
                $last_sync_row = mysqli_fetch_assoc(mysqli_query($mysqli,
                    "SELECT MAX(finished_at) as ls, status FROM rmm_sync_log WHERE integration_id=$intg_id ORDER BY id DESC LIMIT 1"
                ));
                $provider_label = ['tactical_rmm' => 'Tactical RMM', 'level' => 'Level.io', 'action1' => 'Action1'][$intg_type] ?? $intg_type;
                $provider_color = ['tactical_rmm' => 'info', 'level' => 'primary', 'action1' => 'warning'][$intg_type] ?? 'secondary';
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
                    <button class="btn btn-xs btn-info" onclick="testConnection(<?= $intg_id ?>)">
                        <i class="fas fa-plug mr-1"></i>Test
                    </button>
                    <button class="btn btn-xs btn-secondary"
                            onclick='editIntegration(<?= json_encode([
                                "id"      => $intg_id,
                                "name"    => $intg['name'],
                                "type"    => $intg_type,
                                "api_url" => $intg['api_url'],
                                "web_url" => $intg['web_url'] ?? '',
                                "enabled" => intval($intg['enabled']),
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

<!-- Sync Log -->
<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title"><i class="fas fa-fw fa-history mr-2"></i>Recent Sync Log</h3>
    </div>
    <div class="card-body p-0">
        <?php
        $sql_log = mysqli_query($mysqli,
            "SELECT l.*, i.name as integration_name, i.type as integration_type
             FROM rmm_sync_log l
             LEFT JOIN rmm_integrations i ON i.id = l.integration_id
             ORDER BY l.id DESC LIMIT 20"
        );
        if (mysqli_num_rows($sql_log) == 0): ?>
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
            <?php while ($lr = mysqli_fetch_assoc($sql_log)):
                $badge = ['success'=>'badge-success','failed'=>'badge-danger','running'=>'badge-warning'];
            ?>
            <tr>
                <td class="pl-3">
                    <?= nullable_htmlentities($lr['integration_name']) ?>
                    <?php $tp = $lr['integration_type'] ?? ''; if ($tp): ?>
                    <span class="badge badge-secondary ml-1" style="font-size:10px"><?= ['level' => 'Level.io', 'action1' => 'Action1'][$tp] ?? 'Tactical' ?></span>
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

<!-- Add/Edit Integration Modal -->
<div class="modal fade" id="addIntegrationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title" id="integrationModalTitle">Add RMM Integration</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="integration_id" id="edit_integration_id" value="">
                <div class="modal-body">

                    <!-- Provider type selector -->
                    <div class="form-group">
                        <label class="text-muted small">Provider Type</label>
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <input type="radio" class="btn-check" name="integration_type" id="type_tactical"
                                   value="tactical_rmm" checked onchange="updateModalLabels('tactical_rmm')">
                            <label class="btn btn-outline-info flex-fill" for="type_tactical" id="lbl_tactical"
                                   style="border-radius:4px 0 0 0">
                                <i class="fas fa-server mr-1"></i>Tactical RMM
                            </label>
                            <input type="radio" class="btn-check" name="integration_type" id="type_level"
                                   value="level" onchange="updateModalLabels('level')">
                            <label class="btn btn-outline-primary flex-fill" for="type_level" id="lbl_level">
                                <i class="fas fa-layer-group mr-1"></i>Level.io
                            </label>
                            <input type="radio" class="btn-check" name="integration_type" id="type_action1"
                                   value="action1" onchange="updateModalLabels('action1')">
                            <label class="btn btn-outline-warning flex-fill" for="type_action1" id="lbl_action1"
                                   style="border-radius:0 4px 4px 0">
                                <i class="fas fa-shield-alt mr-1"></i>Action1
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="text-muted small">Integration Name</label>
                        <input type="text" class="form-control form-control-sm" name="integration_name" id="integration_name" required
                               placeholder="e.g. Primary Tactical RMM">
                    </div>

                    <div class="form-group">
                        <label class="text-muted small" id="label_api_url">API URL</label>
                        <input type="url" class="form-control form-control-sm" name="integration_api_url" id="integration_api_url"
                               placeholder="https://api.yourdomain.com" required>
                        <small class="text-muted" id="help_api_url">
                            Tactical RMM: enter API server base URL (no trailing slash). e.g. <code>https://api.yourdomain.com</code>
                        </small>
                    </div>

                    <div class="form-group" id="web_url_group">
                        <label class="text-muted small" id="label_web_url">Dashboard / Web URL</label>
                        <input type="url" class="form-control form-control-sm" name="integration_web_url" id="integration_web_url"
                               placeholder="https://rmm.yourdomain.com">
                        <small class="text-muted" id="help_web_url">
                            The browser-accessible dashboard URL (used for Connect button).
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="text-muted small" id="label_api_key">API Key</label>
                        <input type="password" class="form-control form-control-sm" name="integration_api_key" id="integration_api_key"
                               autocomplete="new-password" placeholder="(leave blank to keep existing when editing)">
                        <small class="text-muted" id="help_api_key">
                            Stored encrypted. Generate in Tactical RMM → Settings → Global Settings → API Keys.
                        </small>
                    </div>

                    <div class="form-group" id="client_secret_group" style="display:none">
                        <label class="text-muted small">Client Secret</label>
                        <input type="password" class="form-control form-control-sm" name="integration_client_secret" id="integration_client_secret"
                               autocomplete="new-password" placeholder="(leave blank to keep existing when editing)">
                        <small class="text-muted">
                            Stored encrypted. Generate in Action1 → Automation → API → Add API Credential.
                        </small>
                    </div>

                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="integration_enabled"
                               name="integration_enabled" value="1" checked>
                        <label class="custom-control-label" for="integration_enabled">Enabled</label>
                    </div>

                    <div id="test_result" class="mt-3"></div>
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

<script>
const CSRF = '<?= $_SESSION['csrf_token'] ?>';

const TYPE_HINTS = {
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
        placeholder_api_url: 'https://api.level.io',  // /v2 is appended automatically
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
};

function updateModalLabels(type) {
    const h = TYPE_HINTS[type] || TYPE_HINTS.tactical_rmm;
    document.getElementById('integration_name').placeholder    = h.placeholder_name;
    document.getElementById('integration_api_url').placeholder = h.placeholder_api_url;
    document.getElementById('help_api_url').innerHTML          = h.help_api_url;
    document.getElementById('label_web_url').textContent       = h.label_web_url;
    document.getElementById('integration_web_url').placeholder = h.placeholder_web_url;
    document.getElementById('help_web_url').innerHTML          = h.help_web_url;
    document.getElementById('label_api_key').textContent       = h.label_api_key || 'API Key';
    document.getElementById('help_api_key').innerHTML          = h.help_api_key;

    // Highlight the selected type button
    document.getElementById('lbl_tactical').className = type === 'tactical_rmm'
        ? 'btn btn-info flex-fill' : 'btn btn-outline-info flex-fill';
    document.getElementById('lbl_level').className = type === 'level'
        ? 'btn btn-primary flex-fill' : 'btn btn-outline-primary flex-fill';
    document.getElementById('lbl_action1').className = type === 'action1'
        ? 'btn btn-warning flex-fill' : 'btn btn-outline-warning flex-fill';

    // Fix border-radius back (gets clobbered by bootstrap button-group)
    document.getElementById('lbl_tactical').style.borderRadius = '4px 0 0 0';
    document.getElementById('lbl_level').style.borderRadius    = '0';
    document.getElementById('lbl_action1').style.borderRadius  = '0 4px 4px 0';

    // Client Secret field only applies to Action1 (OAuth2 client credentials)
    document.getElementById('client_secret_group').style.display = type === 'action1' ? '' : 'none';
}

function resetModal() {
    document.getElementById('integrationModalTitle').textContent = 'Add RMM Integration';
    document.getElementById('edit_integration_id').value = '';
    document.getElementById('integration_name').value    = '';
    document.getElementById('integration_api_url').value = '';
    document.getElementById('integration_web_url').value = '';
    document.getElementById('integration_api_key').value = '';
    document.getElementById('integration_client_secret').value = '';
    document.getElementById('integration_api_key').placeholder = '(leave blank to keep existing when editing)';
    document.getElementById('integration_enabled').checked = true;
    document.getElementById('type_tactical').checked = true;
    document.getElementById('test_result').innerHTML = '';
    updateModalLabels('tactical_rmm');
}

function editIntegration(data) {
    document.getElementById('integrationModalTitle').textContent = 'Edit Integration';
    document.getElementById('edit_integration_id').value    = data.id;
    document.getElementById('integration_name').value       = data.name;
    document.getElementById('integration_api_url').value    = data.api_url;
    document.getElementById('integration_web_url').value    = data.web_url || '';
    document.getElementById('integration_api_key').value    = '';
    document.getElementById('integration_client_secret').value = '';
    document.getElementById('integration_enabled').checked  = data.enabled == 1;
    document.getElementById('integration_api_key').placeholder = '(leave blank to keep existing)';
    document.getElementById('integration_client_secret').placeholder = '(leave blank to keep existing)';
    document.getElementById('test_result').innerHTML = '';

    // Set type radio
    const typeIds = {level: 'type_level', action1: 'type_action1', tactical_rmm: 'type_tactical'};
    const typeRadio = document.getElementById(typeIds[data.type] || 'type_tactical');
    if (typeRadio) typeRadio.checked = true;
    updateModalLabels(data.type || 'tactical_rmm');

    $('#addIntegrationModal').modal('show');
}

function testConnection(integrationId) {
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
</script>

<?php require_once "../includes/footer.php"; ?>
