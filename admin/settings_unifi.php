<?php
require_once "includes/inc_all_admin.php";
enforceUserPermission('module_admin');

$sql_integrations = mysqli_query($mysqli, "SELECT * FROM unifi_integrations ORDER BY name ASC");

$sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
$all_clients = [];
while ($c = mysqli_fetch_assoc($sql_clients)) {
    $all_clients[] = ['id' => intval($c['client_id']), 'name' => $c['client_name']];
}
?>

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
                    <input type="checkbox" class="custom-control-input" id="unifi_enabled"
                           name="config_module_enable_unifi" value="1"
                           <?= $config_module_enable_unifi ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="unifi_enabled">Enable UniFi module</label>
                </div>
            </div>
            <button type="submit" name="save_unifi_module_settings" class="btn btn-primary btn-sm">
                <i class="fas fa-check mr-1"></i>Save Module Settings
            </button>
        </form>
    </div>
</div>

<!-- Integration List -->
<div class="card card-dark mb-3">
    <div class="card-header py-2 d-flex align-items-center">
        <h3 class="card-title mr-auto"><i class="fas fa-fw fa-plug mr-2"></i>UniFi Controllers</h3>
        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addIntegrationModal" onclick="resetModal()">
            <i class="fas fa-plus mr-1"></i>Add Controller
        </button>
    </div>
    <div class="card-body p-0">
        <?php if (mysqli_num_rows($sql_integrations) == 0): ?>
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
            mysqli_data_seek($sql_integrations, 0);
            while ($intg = mysqli_fetch_assoc($sql_integrations)):
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
                    <button class="btn btn-xs btn-info" onclick="testConnection(<?= $intg_id ?>)">
                        <i class="fas fa-plug mr-1"></i>Test
                    </button>
                    <button class="btn btn-xs btn-success" onclick="syncNow(<?= $intg_id ?>)">
                        <i class="fas fa-sync mr-1"></i>Sync Now
                    </button>
                    <button class="btn btn-xs btn-primary" onclick='openSiteMappings(<?= $intg_id ?>, <?= json_encode($intg['name']) ?>)'>
                        <i class="fas fa-sitemap mr-1"></i>Sites
                    </button>
                    <button class="btn btn-xs btn-secondary"
                            onclick='editIntegration(<?= json_encode([
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

<!-- Sync Log -->
<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title"><i class="fas fa-fw fa-history mr-2"></i>Recent Sync Log</h3>
    </div>
    <div class="card-body p-0">
        <?php
        $sql_log = mysqli_query($mysqli,
            "SELECT l.*, i.name as integration_name
             FROM unifi_sync_log l
             LEFT JOIN unifi_integrations i ON i.id = l.integration_id
             ORDER BY l.id DESC LIMIT 20"
        );
        if (mysqli_num_rows($sql_log) == 0): ?>
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
            <?php while ($lr = mysqli_fetch_assoc($sql_log)):
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

<!-- Add/Edit Integration Modal -->
<div class="modal fade" id="addIntegrationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title" id="integrationModalTitle">Add UniFi Controller</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="integration_id" id="edit_integration_id" value="">
                <div class="modal-body">

                    <div class="form-group">
                        <label class="text-light small">Name</label>
                        <input type="text" class="form-control form-control-sm" name="integration_name" id="integration_name" required
                               placeholder="e.g. Main Office UniFi">
                    </div>

                    <div class="form-group">
                        <label class="text-light small">Controller Host / IP</label>
                        <input type="text" class="form-control form-control-sm" name="integration_host" id="integration_host" required
                               placeholder="10.1.0.30">
                    </div>

                    <div class="form-group">
                        <label class="text-light small">Port</label>
                        <input type="number" class="form-control form-control-sm" name="integration_port" id="integration_port"
                               placeholder="443" min="1" max="65535" value="443">
                    </div>

                    <div class="form-group">
                        <label class="text-light small">API Key</label>
                        <input type="password" class="form-control form-control-sm" name="integration_api_key" id="integration_api_key"
                               autocomplete="new-password" placeholder="(leave blank to keep existing when editing)">
                        <small class="text-light">
                            Stored encrypted. Generate in UniFi OS &rarr; Settings &rarr; Control Plane &rarr; Integrations &rarr; API Key.
                        </small>
                    </div>

                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="integration_verify_ssl"
                               name="integration_verify_ssl" value="1">
                        <label class="custom-control-label" for="integration_verify_ssl">Verify SSL certificate</label>
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
                    <button type="submit" name="save_unifi_integration" class="btn btn-primary btn-sm">
                        <i class="fas fa-check mr-1"></i>Save Controller
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Site Mapping Modal -->
<div class="modal fade" id="siteMappingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title">Site &rarr; Client Mapping <span id="siteMappingIntgName" class="text-muted"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    By default, each UniFi site is matched to an ITFlow client by name (case-insensitive).
                    Use this to override that match, or skip syncing a site entirely.
                </p>
                <div id="siteMappingBody">
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-spinner fa-spin mr-1"></i>Loading sites...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveSiteMappingBtn" onclick="saveSiteMappings()" disabled>
                    <i class="fas fa-check mr-1"></i>Save Mapping
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= $_SESSION['csrf_token'] ?>';
const ALL_CLIENTS = <?= json_encode($all_clients) ?>;
let siteMappingIntegrationId = null;

function resetModal() {
    document.getElementById('integrationModalTitle').textContent = 'Add UniFi Controller';
    document.getElementById('edit_integration_id').value = '';
    document.getElementById('integration_name').value = '';
    document.getElementById('integration_host').value = '';
    document.getElementById('integration_port').value = '443';
    document.getElementById('integration_api_key').value = '';
    document.getElementById('integration_api_key').placeholder = '(required)';
    document.getElementById('integration_verify_ssl').checked = false;
    document.getElementById('integration_enabled').checked = true;
    document.getElementById('test_result').innerHTML = '';
}

function editIntegration(data) {
    document.getElementById('integrationModalTitle').textContent = 'Edit UniFi Controller';
    document.getElementById('edit_integration_id').value = data.id;
    document.getElementById('integration_name').value = data.name;
    document.getElementById('integration_host').value = data.host;
    document.getElementById('integration_port').value = data.port;
    document.getElementById('integration_api_key').value = '';
    document.getElementById('integration_api_key').placeholder = '(leave blank to keep existing)';
    document.getElementById('integration_verify_ssl').checked = data.verify_ssl == 1;
    document.getElementById('integration_enabled').checked = data.enabled == 1;
    document.getElementById('test_result').innerHTML = '';

    $('#addIntegrationModal').modal('show');
}

function testConnection(integrationId) {
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

function syncNow(integrationId) {
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

function openSiteMappings(integrationId, integrationName) {
    siteMappingIntegrationId = integrationId;
    document.getElementById('siteMappingIntgName').textContent = '- ' + integrationName;
    document.getElementById('saveSiteMappingBtn').disabled = true;
    document.getElementById('siteMappingBody').innerHTML =
        '<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-1"></i>Loading sites...</div>';

    $('#siteMappingModal').modal('show');

    fetch('/admin/post.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=' + CSRF + '&load_unifi_sites=1&integration_id=' + integrationId
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) {
            document.getElementById('siteMappingBody').innerHTML =
                '<div class="alert alert-danger mb-0">' + (d.error || 'Failed to load sites') + '</div>';
            return;
        }
        renderSiteMappings(d.sites);
        document.getElementById('saveSiteMappingBtn').disabled = false;
    })
    .catch(() => {
        document.getElementById('siteMappingBody').innerHTML =
            '<div class="alert alert-danger mb-0">Failed to load sites</div>';
    });
}

function renderSiteMappings(sites) {
    if (!sites.length) {
        document.getElementById('siteMappingBody').innerHTML =
            '<p class="text-muted text-center mb-0">No sites found on this controller.</p>';
        return;
    }

    let clientOptions = '<option value="">-- Select client --</option>';
    ALL_CLIENTS.forEach(c => {
        clientOptions += `<option value="${c.id}">${escapeHtml(c.name)}</option>`;
    });

    let html = '<table class="table table-sm table-hover mb-0"><thead class="text-muted small">' +
        '<tr><th>UniFi Site</th><th>Auto-Match</th><th>Mapping</th></tr></thead><tbody>';

    sites.forEach(site => {
        const siteId   = site.unifi_site_id;
        const current  = site.client_id; // string|null from JSON (could be "0", null, or numeric id)
        const autoName = site.auto_client_name;

        let selected = 'auto';
        if (current !== null) {
            selected = (current === '0' || current === 0) ? 'skip' : String(current);
        }

        let options = `<option value="auto" ${selected === 'auto' ? 'selected' : ''}>Auto (match by name)</option>`;
        options += `<option value="skip" ${selected === 'skip' ? 'selected' : ''}>Skip (don't sync)</option>`;
        ALL_CLIENTS.forEach(c => {
            options += `<option value="${c.id}" ${selected === String(c.id) ? 'selected' : ''}>${escapeHtml(c.name)}</option>`;
        });

        html += '<tr>' +
            `<td>${escapeHtml(site.unifi_site_name)}</td>` +
            `<td class="text-muted small">${autoName ? escapeHtml(autoName) : '<em>no match</em>'}</td>` +
            `<td><select class="form-control form-control-sm site-mapping-select" data-site-id="${escapeHtml(siteId)}">${options}</select></td>` +
            '</tr>';
    });

    html += '</tbody></table>';
    document.getElementById('siteMappingBody').innerHTML = html;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function saveSiteMappings() {
    const btn = document.getElementById('saveSiteMappingBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';

    const params = new URLSearchParams();
    params.append('csrf_token', CSRF);
    params.append('save_unifi_site_mapping', '1');
    params.append('integration_id', siteMappingIntegrationId);

    document.querySelectorAll('.site-mapping-select').forEach(sel => {
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
            $('#siteMappingModal').modal('hide');
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
