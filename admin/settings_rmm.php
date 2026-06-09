<?php
require_once "includes/inc_all_admin.php";
enforceUserPermission('module_admin');

$sql_integrations = mysqli_query($mysqli, "SELECT * FROM rmm_integrations ORDER BY name ASC");
?>

<div class="card card-dark mb-3" style="border-top:3px solid #17a2b8;">
    <div class="card-header py-2 d-flex align-items-center">
        <h3 class="card-title mr-auto">
            <i class="fas fa-fw fa-desktop mr-2"></i>RMM Integration (Tactical RMM)
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
            <div class="form-group">
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
        <h3 class="card-title mr-auto"><i class="fas fa-fw fa-plug mr-2"></i>Tactical RMM Integrations</h3>
        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addIntegrationModal">
            <i class="fas fa-plus mr-1"></i>Add Integration
        </button>
    </div>
    <div class="card-body p-0">
        <?php $count = mysqli_num_rows($sql_integrations); if ($count == 0): ?>
            <p class="text-muted text-center py-4 mb-0">No integrations configured. Add a Tactical RMM connection above.</p>
        <?php else: ?>
        <table class="table table-sm table-hover mb-0">
            <thead class="text-muted small border-bottom" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;">
                <tr>
                    <th class="pl-3">Name</th>
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
                $intg_name = nullable_htmlentities($intg['name']);
                $intg_url  = nullable_htmlentities($intg['api_url']);
                $intg_on   = intval($intg['enabled']);
                $last_sync_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT MAX(finished_at) as ls, status FROM rmm_sync_log WHERE integration_id=$intg_id ORDER BY id DESC LIMIT 1"));
            ?>
            <tr>
                <td class="pl-3 font-weight-bold"><?= $intg_name ?></td>
                <td class="text-muted small"><?= $intg_url ?></td>
                <td>
                    <?php if ($intg_on): ?>
                        <span class="badge badge-success">Enabled</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Disabled</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted small">
                    <?= $last_sync_row['ls'] ? nullable_htmlentities($last_sync_row['ls']) : 'Never' ?>
                </td>
                <td class="text-right pr-3">
                    <button class="btn btn-xs btn-info" onclick="testConnection(<?= $intg_id ?>)">
                        <i class="fas fa-plug mr-1"></i>Test
                    </button>
                    <a href="#" class="btn btn-xs btn-secondary"
                       onclick="editIntegration(<?= $intg_id ?>, '<?= addslashes($intg['name']) ?>', '<?= addslashes($intg['api_url']) ?>', <?= $intg_on ?>); return false;">
                        <i class="fas fa-edit"></i>
                    </a>
                    <form action="post.php" method="post" class="d-inline"
                          onsubmit="return confirm('Delete this integration?')">
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
        $sql_log = mysqli_query($mysqli, "SELECT l.*, i.name as integration_name FROM rmm_sync_log l
            LEFT JOIN rmm_integrations i ON i.id = l.integration_id
            ORDER BY l.id DESC LIMIT 20");
        if (mysqli_num_rows($sql_log) == 0):
        ?>
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
            <?php while ($lr = mysqli_fetch_assoc($sql_log)): ?>
            <tr>
                <td class="pl-3"><?= nullable_htmlentities($lr['integration_name']) ?></td>
                <td class="text-muted small"><?= nullable_htmlentities($lr['started_at']) ?></td>
                <td>
                    <?php
                    $badge = ['success'=>'badge-success','failed'=>'badge-danger','running'=>'badge-warning'];
                    $s = $lr['status'];
                    ?>
                    <span class="badge <?= $badge[$s] ?? 'badge-secondary' ?>"><?= htmlspecialchars($s) ?></span>
                </td>
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
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title" id="integrationModalTitle">Add Tactical RMM Integration</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="integration_id" id="edit_integration_id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="text-muted small">Name</label>
                        <input type="text" class="form-control form-control-sm" name="integration_name" id="integration_name"
                               placeholder="e.g. Primary Tactical RMM" required>
                    </div>
                    <div class="form-group">
                        <label class="text-muted small">Tactical RMM API URL</label>
                        <input type="url" class="form-control form-control-sm" name="integration_api_url" id="integration_api_url"
                               placeholder="https://api.yourdomain.com" required>
                        <small class="text-muted">Enter the API server base URL, <strong>not</strong> the dashboard URL (<code>rmm.yourdomain.com</code>). Older TRMM installs: <code>https://api.yourdomain.com</code>. Newer installs (v0.18+): <code>https://api.yourdomain.com/api/v3</code>. No trailing slash.</small>
                    </div>
                    <div class="form-group">
                        <label class="text-muted small">API Key</label>
                        <input type="password" class="form-control form-control-sm" name="integration_api_key" id="integration_api_key"
                               autocomplete="new-password" placeholder="(leave blank to keep existing when editing)">
                        <small class="text-muted">Stored encrypted. Generate in Tactical RMM → Settings → Global Settings → API Keys.</small>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="integration_enabled"
                                   name="integration_enabled" value="1" checked>
                            <label class="custom-control-label" for="integration_enabled">Enabled</label>
                        </div>
                    </div>
                    <div id="test_result" class="mt-2"></div>
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
function testConnection(integrationId) {
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Testing...';
    fetch('/agent/post/rmm_sync.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?= $_SESSION['csrf_token'] ?>&action=test&integration_id=' + integrationId
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
            btn.className = btn.className.replace('btn-success','btn-info').replace('btn-danger','btn-info');
            btn.disabled = false;
        }, 3000);
    })
    .catch(() => {
        btn.innerHTML = '<i class="fas fa-times mr-1"></i>Error';
        btn.disabled = false;
    });
}

function editIntegration(id, name, url, enabled) {
    document.getElementById('integrationModalTitle').textContent = 'Edit Integration';
    document.getElementById('edit_integration_id').value = id;
    document.getElementById('integration_name').value = name;
    document.getElementById('integration_api_url').value = url;
    document.getElementById('integration_enabled').checked = enabled == 1;
    document.getElementById('integration_api_key').placeholder = '(leave blank to keep existing)';
    $('#addIntegrationModal').modal('show');
}
</script>

<?php require_once "../includes/footer.php"; ?>
