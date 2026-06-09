<?php
require_once "includes/inc_all.php";
enforceUserPermission('module_rmm');

$categories = ['Maintenance', 'Repair', 'Inventory', 'Security', 'Software Install'];

$sql_scripts = mysqli_query($mysqli,
    "SELECT s.*, u.user_firstname, u.user_lastname FROM rmm_scripts s
     LEFT JOIN users u ON u.user_id = s.created_by
     WHERE s.enabled=1 OR s.enabled=0
     ORDER BY s.category, s.name"
);

// Handle add/edit/delete via regular POST (for users without JS-only workflow)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['save_script'])) {
        enforceUserPermission('module_rmm_scripts');
        $sid         = intval($_POST['script_id'] ?? 0);
        $name        = sanitizeInput($_POST['name']);
        $category    = sanitizeInput($_POST['category']);
        $description = sanitizeInput($_POST['description']);
        $script_type = sanitizeInput($_POST['script_type']);
        $script_body = mysqli_real_escape_string($mysqli, $_POST['script_body'] ?? '');
        $tac_id      = intval($_POST['tactical_script_id'] ?? 0);
        $enabled     = isset($_POST['enabled']) ? 1 : 0;

        if ($sid > 0) {
            mysqli_query($mysqli, "UPDATE rmm_scripts SET name='$name', category='$category', description='$description', script_type='$script_type', script_body='$script_body', tactical_script_id=" . ($tac_id ?: 'NULL') . ", enabled=$enabled WHERE id=$sid");
            logAction('RMM', 'Script Edit', "$session_name edited script '$name'");
            flash_alert('Script updated');
        } else {
            mysqli_query($mysqli, "INSERT INTO rmm_scripts SET name='$name', category='$category', description='$description', script_type='$script_type', script_body='$script_body', tactical_script_id=" . ($tac_id ?: 'NULL') . ", enabled=$enabled, created_by=$session_user_id");
            logAction('RMM', 'Script Create', "$session_name created script '$name'");
            flash_alert('Script created');
        }
        redirect();
    }

    if (isset($_POST['delete_script'])) {
        enforceUserPermission('module_rmm_scripts');
        $sid = intval($_POST['script_id']);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT name FROM rmm_scripts WHERE id=$sid"));
        if ($row) {
            mysqli_query($mysqli, "DELETE FROM rmm_scripts WHERE id=$sid");
            logAction('RMM', 'Script Delete', "$session_name deleted script '{$row['name']}'");
            flash_alert('Script deleted');
        }
        redirect();
    }
}
?>

<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0 mr-auto"><i class="fas fa-code mr-2"></i>Script Library</h4>
    <?php if (lookupUserPermission('module_rmm_scripts') >= 1): ?>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#scriptModal" onclick="openNewScript()">
        <i class="fas fa-plus mr-1"></i>New Script
    </button>
    <?php endif; ?>
</div>

<div class="card card-dark">
    <div class="card-body p-0">
        <?php if (mysqli_num_rows($sql_scripts) === 0): ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-code fa-3x mb-3"></i>
            <p>No scripts yet. Add scripts here to run them from the asset dashboard.</p>
        </div>
        <?php else: ?>
        <table class="table table-sm table-hover mb-0">
            <thead class="text-muted small border-bottom" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;">
                <tr>
                    <th class="pl-3">Name</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Tactical ID</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php while ($scr = mysqli_fetch_assoc($sql_scripts)):
                $sid = intval($scr['id']);
            ?>
            <tr>
                <td class="pl-3 font-weight-bold"><?= nullable_htmlentities($scr['name']) ?></td>
                <td class="small"><span class="badge badge-secondary"><?= nullable_htmlentities($scr['category']) ?></span></td>
                <td class="small text-muted"><?= nullable_htmlentities($scr['script_type']) ?></td>
                <td class="small text-muted"><?= $scr['tactical_script_id'] ? intval($scr['tactical_script_id']) : '—' ?></td>
                <td class="small text-muted"><?= nullable_htmlentities($scr['user_firstname'] . ' ' . $scr['user_lastname']) ?></td>
                <td><?= $scr['enabled'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Disabled</span>' ?></td>
                <td class="text-right pr-2">
                    <?php if (lookupUserPermission('module_rmm_scripts') >= 1): ?>
                    <button class="btn btn-xs btn-secondary" onclick='editScript(<?= json_encode($scr) ?>)'>
                        <i class="fas fa-edit"></i>
                    </button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this script?')">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="script_id" value="<?= $sid ?>">
                        <button type="submit" name="delete_script" class="btn btn-xs btn-danger">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Runs -->
<div class="card card-dark mt-3">
    <div class="card-header py-2">
        <h6 class="mb-0"><i class="fas fa-history mr-2"></i>Recent Script Runs</h6>
    </div>
    <div class="card-body p-0">
    <?php
    $sql_runs = mysqli_query($mysqli,
        "SELECT sr.*, s.name as sname, a.asset_name, u.user_firstname, u.user_lastname
         FROM rmm_script_runs sr
         LEFT JOIN rmm_scripts s ON s.id = sr.script_id
         LEFT JOIN assets a ON a.asset_id = sr.asset_id
         LEFT JOIN users u ON u.user_id = sr.user_id
         ORDER BY sr.started_at DESC LIMIT 50"
    );
    if (mysqli_num_rows($sql_runs) === 0):
    ?>
        <p class="text-muted text-center py-3 mb-0">No script runs yet.</p>
    <?php else: ?>
    <table class="table table-sm table-hover mb-0">
        <thead class="text-muted small border-bottom" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;">
            <tr>
                <th class="pl-3">Script</th>
                <th>Asset</th>
                <th>Run By</th>
                <th>Status</th>
                <th>Started</th>
                <th>Output</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($run = mysqli_fetch_assoc($sql_runs)):
            $run_badge = ['completed'=>'success','failed'=>'danger','running'=>'warning','pending'=>'secondary'][$run['status']] ?? 'secondary';
        ?>
        <tr>
            <td class="pl-3 small font-weight-bold"><?= nullable_htmlentities($run['sname'] ?? 'Manual') ?></td>
            <td class="small"><a href="/agent/asset_details.php?asset_id=<?= intval($run['asset_id']) ?>"><?= nullable_htmlentities($run['asset_name']) ?></a></td>
            <td class="small text-muted"><?= nullable_htmlentities($run['user_firstname'] . ' ' . $run['user_lastname']) ?></td>
            <td><span class="badge badge-<?= $run_badge ?>"><?= $run['status'] ?></span></td>
            <td class="small text-muted"><?= nullable_htmlentities($run['started_at']) ?></td>
            <td class="small" style="max-width:300px">
                <?php if ($run['output']): ?>
                <code class="text-muted small" style="white-space:nowrap;overflow:hidden;display:block;max-width:300px;text-overflow:ellipsis">
                    <?= nullable_htmlentities(substr($run['output'], 0, 200)) ?>
                </code>
                <?php elseif ($run['error_message']): ?>
                <span class="text-danger small"><?= nullable_htmlentities(substr($run['error_message'], 0, 200)) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
</div>

<!-- Script Modal -->
<div class="modal fade" id="scriptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title" id="scriptModalTitle">New Script</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="script_id" id="edit_script_id" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="text-muted small">Script Name</label>
                                <input type="text" class="form-control form-control-sm" name="name" id="s_name" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="text-muted small">Category</label>
                                <select class="form-control form-control-sm" name="category" id="s_category">
                                    <?php foreach ($categories as $cat): ?>
                                    <option><?= $cat ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="text-muted small">Script Type</label>
                                <select class="form-control form-control-sm" name="script_type" id="s_type">
                                    <option value="powershell">PowerShell</option>
                                    <option value="cmd">CMD / Batch</option>
                                    <option value="python">Python</option>
                                    <option value="bash">Bash</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="text-muted small">Description</label>
                        <input type="text" class="form-control form-control-sm" name="description" id="s_description">
                    </div>
                    <div class="form-group">
                        <label class="text-muted small">Tactical RMM Script ID <small>(from Tactical → Scripts)</small></label>
                        <input type="number" class="form-control form-control-sm" name="tactical_script_id" id="s_tactical_id" placeholder="Leave blank if not yet synced">
                    </div>
                    <div class="form-group">
                        <label class="text-muted small">Script Body <small>(for reference / documentation)</small></label>
                        <textarea class="form-control form-control-sm font-monospace small" name="script_body" id="s_body" rows="8" style="font-size:12px"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="s_enabled" name="enabled" value="1" checked>
                            <label class="custom-control-label" for="s_enabled">Enabled</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_script" class="btn btn-primary btn-sm">
                        <i class="fas fa-check mr-1"></i>Save Script
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openNewScript() {
    document.getElementById('scriptModalTitle').textContent = 'New Script';
    document.getElementById('edit_script_id').value = '';
    document.getElementById('s_name').value = '';
    document.getElementById('s_description').value = '';
    document.getElementById('s_body').value = '';
    document.getElementById('s_tactical_id').value = '';
    document.getElementById('s_enabled').checked = true;
}
function editScript(scr) {
    document.getElementById('scriptModalTitle').textContent = 'Edit Script';
    document.getElementById('edit_script_id').value = scr.id;
    document.getElementById('s_name').value = scr.name;
    document.getElementById('s_description').value = scr.description || '';
    document.getElementById('s_body').value = scr.script_body || '';
    document.getElementById('s_tactical_id').value = scr.tactical_script_id || '';
    document.getElementById('s_enabled').checked = scr.enabled == 1;
    document.getElementById('s_category').value = scr.category;
    document.getElementById('s_type').value = scr.script_type;
    $('#scriptModal').modal('show');
}
</script>

<?php require_once "../includes/footer.php"; ?>
