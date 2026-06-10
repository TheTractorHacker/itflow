<?php
require_once "includes/inc_all.php";
enforceUserPermission('module_rmm');

// Filter params
$filter_status    = sanitizeInput($_GET['status'] ?? '');
$filter_client_id = intval($_GET['client_id'] ?? 0);
$filter_intg_id   = intval($_GET['integration_id'] ?? $config_rmm_default_integration_id);

// Build WHERE clause
$where = "1=1";
if ($filter_status)    { $where .= " AND arl.rmm_status='" . mysqli_real_escape_string($mysqli, $filter_status) . "'"; }
if ($filter_client_id) { $where .= " AND a.asset_client_id=$filter_client_id"; }
if ($filter_intg_id)   { $where .= " AND arl.integration_id=$filter_intg_id"; }

$sql_links = mysqli_query($mysqli,
    "SELECT arl.*, a.asset_id, a.asset_name, a.asset_type, a.asset_client_id,
            c.client_name
     FROM asset_rmm_links arl
     JOIN assets a ON a.asset_id = arl.asset_id
     LEFT JOIN clients c ON c.client_id = a.asset_client_id
     WHERE $where
     ORDER BY arl.rmm_status ASC, arl.hostname ASC"
);

// Counts for stat cards
$cnt = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT
       SUM(rmm_status='online') as online,
       SUM(rmm_status='offline') as offline,
       SUM(rmm_status='unknown') as unknown,
       COUNT(*) as total
     FROM asset_rmm_links WHERE integration_id=" . ($filter_intg_id ?: 'integration_id')
));

$sql_integrations = mysqli_query($mysqli, "SELECT id, name FROM rmm_integrations WHERE enabled=1 ORDER BY name");
?>

<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0 mr-auto"><i class="fas fa-desktop mr-2"></i>RMM Assets</h4>
    <?php if (lookupUserPermission('module_rmm_sync') >= 1): ?>
    <button class="btn btn-success btn-sm mr-2" id="syncBtn" onclick="triggerSync()">
        <i class="fas fa-sync mr-1"></i>Sync from Tactical RMM
    </button>
    <?php endif; ?>
    <a href="/admin/settings_rmm.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-cog mr-1"></i>Settings
    </a>
</div>

<!-- Stat cards -->
<div class="row mb-3">
    <div class="col-md-3">
        <div class="small-box bg-success">
            <div class="inner"><h3><?= intval($cnt['online']) ?></h3><p>Online</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <a href="?status=online" class="small-box-footer">Filter <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-danger">
            <div class="inner"><h3><?= intval($cnt['offline']) ?></h3><p>Offline</p></div>
            <div class="icon"><i class="fas fa-times-circle"></i></div>
            <a href="?status=offline" class="small-box-footer">Filter <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-secondary">
            <div class="inner"><h3><?= intval($cnt['unknown']) ?></h3><p>Unknown</p></div>
            <div class="icon"><i class="fas fa-question-circle"></i></div>
            <a href="?status=unknown" class="small-box-footer">Filter <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-info">
            <div class="inner"><h3><?= intval($cnt['total']) ?></h3><p>Total Managed</p></div>
            <div class="icon"><i class="fas fa-desktop"></i></div>
            <a href="?" class="small-box-footer">Show All <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<!-- Sync status bar -->
<div id="syncStatus" class="alert alert-info d-none mb-3">
    <i class="fas fa-spinner fa-spin mr-2"></i><span id="syncStatusText">Syncing...</span>
</div>

<!-- Filter bar -->
<div class="card card-dark mb-2">
    <div class="card-body py-2">
        <form method="get" class="form-inline">
            <select name="integration_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="">All Integrations</option>
                <?php while ($i = mysqli_fetch_assoc($sql_integrations)): ?>
                <option value="<?= $i['id'] ?>" <?= $filter_intg_id == $i['id'] ? 'selected' : '' ?>>
                    <?= nullable_htmlentities($i['name']) ?>
                </option>
                <?php endwhile; ?>
            </select>
            <select name="status" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="online"  <?= $filter_status === 'online'  ? 'selected' : '' ?>>Online</option>
                <option value="offline" <?= $filter_status === 'offline' ? 'selected' : '' ?>>Offline</option>
                <option value="unknown" <?= $filter_status === 'unknown' ? 'selected' : '' ?>>Unknown</option>
            </select>
            <?php if ($filter_status || $filter_client_id): ?>
                <a href="?" class="btn btn-secondary btn-sm">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Asset table -->
<div class="card card-dark">
    <div class="card-body p-0">
        <?php if (mysqli_num_rows($sql_links) === 0): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-desktop fa-3x mb-3"></i>
                <p>No RMM assets found. <a href="#" onclick="triggerSync()">Sync from Tactical RMM</a> to import devices.</p>
            </div>
        <?php else: ?>
        <table class="table table-hover table-sm mb-0" id="rmm-assets-table">
            <thead class="text-muted small border-bottom" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;">
                <tr>
                    <th class="pl-3">Status</th>
                    <th>Hostname</th>
                    <th>Client</th>
                    <th>OS</th>
                    <th>Logged In User</th>
                    <th>Last Seen</th>
                    <th>Last Sync</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql_links)):
                $status    = $row['rmm_status'];
                $badge     = $status === 'online' ? 'badge-success' : ($status === 'offline' ? 'badge-danger' : 'badge-secondary');
                $icon      = $status === 'online' ? 'fa-circle text-success' : ($status === 'offline' ? 'fa-circle text-danger' : 'fa-question-circle text-muted');
            ?>
            <tr>
                <td class="pl-3">
                    <i class="fas <?= $icon ?>" data-toggle="tooltip" title="<?= ucfirst($status) ?>"></i>
                </td>
                <td>
                    <a href="/agent/asset_details.php?client_id=<?= intval($row['asset_client_id']) ?>&asset_id=<?= intval($row['asset_id']) ?>" class="font-weight-bold">
                        <?= nullable_htmlentities($row['hostname']) ?>
                    </a>
                </td>
                <td>
                    <?php if ($row['asset_client_id']): ?>
                        <a href="/agent/client_overview.php?client_id=<?= intval($row['asset_client_id']) ?>">
                            <?= nullable_htmlentities($row['client_name']) ?>
                        </a>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted small"><?= nullable_htmlentities($row['os_name']) ?></td>
                <td class="text-muted small"><?= nullable_htmlentities($row['logged_in_user']) ?: '—' ?></td>
                <td class="text-muted small">
                    <?= $row['last_seen'] ? nullable_htmlentities($row['last_seen']) : '—' ?>
                </td>
                <td class="text-muted small">
                    <?= $row['last_sync'] ? nullable_htmlentities($row['last_sync']) : '—' ?>
                </td>
                <td class="text-right pr-2">
                    <a href="/agent/asset_details.php?client_id=<?= intval($row['asset_client_id']) ?>&asset_id=<?= intval($row['asset_id']) ?>" class="btn btn-xs btn-info" data-toggle="tooltip" title="View Asset">
                        <i class="fas fa-tachometer-alt"></i>
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
function triggerSync() {
    const btn = document.getElementById('syncBtn');
    const bar = document.getElementById('syncStatus');
    const txt = document.getElementById('syncStatusText');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Syncing...'; }
    bar.classList.remove('d-none');
    txt.textContent = 'Syncing assets from Tactical RMM...';

    fetch('/agent/post/rmm_sync.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?= $_SESSION['csrf_token'] ?>&action=sync&integration_id=<?= $filter_intg_id ?>'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            txt.textContent = `Sync complete: ${d.created} created, ${d.updated} updated, ${d.matched} matched, ${d.skipped} skipped.`;
            bar.classList.replace('alert-info', 'alert-success');
            setTimeout(() => location.reload(), 2000);
        } else {
            txt.textContent = 'Sync failed: ' + (d.error || 'Unknown error');
            bar.classList.replace('alert-info', 'alert-danger');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync mr-1"></i>Sync from Tactical RMM'; }
        }
    })
    .catch(() => {
        txt.textContent = 'Network error during sync.';
        bar.classList.replace('alert-info', 'alert-danger');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync mr-1"></i>Sync from Tactical RMM'; }
    });
}
</script>

<?php require_once "../includes/footer.php"; ?>
