<?php
require_once "includes/inc_all_admin.php";
require_once "../includes/comet.php";

$connected = $config_comet_enabled ? comet_test() : null;
?>

<div class="card card-dark mb-3" style="border-top:3px solid #f39c12;">
    <div class="card-header py-2 d-flex align-items-center">
        <h3 class="card-title mr-auto">
            <i class="fas fa-fw fa-cloud-upload-alt mr-2"></i>Comet Backup Integration
        </h3>
        <?php if ($config_comet_enabled): ?>
            <?php if ($connected): ?>
                <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Connected</span>
            <?php else: ?>
                <span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i>Cannot reach server</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
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

            <div class="form-group mb-0">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="comet_auto_ticket"
                           name="config_comet_auto_ticket" value="1"
                           <?= $config_comet_auto_ticket ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="comet_auto_ticket">
                        Auto-create tickets on backup failure (one ticket per device, auto-resolves on success)
                    </label>
                </div>
                <small class="text-muted ml-4">Requires cron to be enabled. Checks every cron run.</small>
            </div>

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
        <?php if (!$config_comet_enabled || !$connected): ?>
            <p class="text-muted text-center py-3 mb-0">
                <?= !$config_comet_enabled ? 'Enable and configure Comet above to manage mappings.' : 'Cannot reach Comet server. Check connection settings.' ?>
            </p>
        <?php else:
            $comet_users = array_keys(comet_get_users() ?: []);
            sort($comet_users);
            $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name");
            $sql_maps    = mysqli_query($mysqli, "SELECT map_client_id, map_comet_username FROM comet_client_map");
            $maps = [];
            while ($m = mysqli_fetch_assoc($sql_maps)) {
                $maps[intval($m['map_client_id'])] = $m['map_comet_username'];
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
                <?php while ($client = mysqli_fetch_assoc($sql_clients)):
                    $cid   = intval($client['client_id']);
                    $cname = nullable_htmlentities($client['client_name']);
                    $mapped = $maps[$cid] ?? '';
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

<?php require_once "../includes/footer.php"; ?>
