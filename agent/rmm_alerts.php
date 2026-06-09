<?php
require_once "includes/inc_all.php";
enforceUserPermission('module_rmm_alerts');

$filter_status   = sanitizeInput($_GET['status'] ?? 'new');
$filter_asset_id = intval($_GET['asset_id'] ?? 0);
$filter_client   = intval($_GET['client_id'] ?? 0);

$where = "1=1";
if ($filter_status && $filter_status !== 'all') {
    $where .= " AND a.status='" . mysqli_real_escape_string($mysqli, $filter_status) . "'";
}
if ($filter_asset_id) { $where .= " AND a.asset_id=$filter_asset_id"; }
if ($filter_client)   { $where .= " AND a.client_id=$filter_client"; }

$sql_alerts = mysqli_query($mysqli,
    "SELECT a.*, ast.asset_name, c.client_name,
            u.user_firstname, u.user_lastname
     FROM rmm_alerts a
     LEFT JOIN assets ast ON ast.asset_id = a.asset_id
     LEFT JOIN clients c ON c.client_id = a.client_id
     LEFT JOIN users u ON u.user_id = a.acknowledged_by
     WHERE $where
     ORDER BY a.created_at DESC
     LIMIT 200"
);

// Counts
$cnt = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT SUM(status='new') as new_cnt, SUM(status='acknowledged') as ack_cnt, SUM(status='resolved') as res_cnt FROM rmm_alerts"
));
?>

<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0 mr-auto"><i class="fas fa-bell mr-2"></i>RMM Alerts</h4>
    <a href="/agent/rmm_assets.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-desktop mr-1"></i>Back to Assets
    </a>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="small-box bg-danger">
            <div class="inner"><h3><?= intval($cnt['new_cnt']) ?></h3><p>New Alerts</p></div>
            <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
            <a href="?status=new" class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box bg-warning">
            <div class="inner"><h3><?= intval($cnt['ack_cnt']) ?></h3><p>Acknowledged</p></div>
            <div class="icon"><i class="fas fa-eye"></i></div>
            <a href="?status=acknowledged" class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box bg-success">
            <div class="inner"><h3><?= intval($cnt['res_cnt']) ?></h3><p>Resolved</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <a href="?status=resolved" class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<!-- Filter bar -->
<div class="card card-dark mb-2">
    <div class="card-body py-2">
        <form method="get" class="form-inline">
            <select name="status" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="all"          <?= $filter_status === 'all'          ? 'selected' : '' ?>>All Statuses</option>
                <option value="new"          <?= $filter_status === 'new'          ? 'selected' : '' ?>>New</option>
                <option value="acknowledged" <?= $filter_status === 'acknowledged' ? 'selected' : '' ?>>Acknowledged</option>
                <option value="resolved"     <?= $filter_status === 'resolved'     ? 'selected' : '' ?>>Resolved</option>
            </select>
            <?php if ($filter_status !== 'new'): ?>
            <a href="?" class="btn btn-secondary btn-sm ml-2">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card card-dark">
    <div class="card-body p-0">
        <?php if (mysqli_num_rows($sql_alerts) === 0): ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
            <p>No alerts in this view.</p>
        </div>
        <?php else: ?>
        <table class="table table-sm table-hover mb-0">
            <thead class="text-muted small border-bottom" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;">
                <tr>
                    <th class="pl-3">Severity</th>
                    <th>Message</th>
                    <th>Asset</th>
                    <th>Client</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php while ($alert = mysqli_fetch_assoc($sql_alerts)):
                $sev_color = ['critical'=>'danger','error'=>'danger','warning'=>'warning','info'=>'info'][$alert['severity']] ?? 'secondary';
                $status_color = ['new'=>'danger','acknowledged'=>'warning','resolved'=>'success'][$alert['status']] ?? 'secondary';
                $aid = intval($alert['id']);
            ?>
            <tr id="alert-row-<?= $aid ?>">
                <td class="pl-3"><span class="badge badge-<?= $sev_color ?>"><?= nullable_htmlentities($alert['severity']) ?></span></td>
                <td class="small" style="max-width:300px"><?= nullable_htmlentities($alert['message']) ?></td>
                <td class="small">
                    <?php if ($alert['asset_id']): ?>
                    <?php
                    $rmm_link_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM asset_rmm_links WHERE asset_id=" . intval($alert['asset_id']) . " LIMIT 1"));
                    ?>
                    <a href="<?= $rmm_link_row ? '/agent/rmm_asset.php?id=' . $rmm_link_row['id'] : '/agent/asset_details.php?asset_id=' . $alert['asset_id'] ?>">
                        <?= nullable_htmlentities($alert['asset_name']) ?>
                    </a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="small">
                    <?php if ($alert['client_id']): ?>
                    <a href="/agent/client_details.php?client_id=<?= intval($alert['client_id']) ?>">
                        <?= nullable_htmlentities($alert['client_name']) ?>
                    </a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td><span class="badge badge-<?= $status_color ?>"><?= $alert['status'] ?></span></td>
                <td class="text-muted small"><?= nullable_htmlentities($alert['created_at']) ?></td>
                <td class="text-right pr-2">
                    <?php if ($alert['status'] === 'new' && lookupUserPermission('module_rmm_alerts_ack') >= 1): ?>
                    <button class="btn btn-xs btn-warning" onclick="alertAction(<?= $aid ?>, 'acknowledge')">Ack</button>
                    <?php endif; ?>
                    <?php if ($alert['status'] !== 'resolved' && lookupUserPermission('module_rmm_alerts_ack') >= 1): ?>
                    <button class="btn btn-xs btn-success" onclick="alertAction(<?= $aid ?>, 'resolve')">Resolve</button>
                    <?php endif; ?>
                    <?php if (lookupUserPermission('module_support') >= 2): ?>
                    <button class="btn btn-xs btn-primary" onclick="alertAction(<?= $aid ?>, 'create_ticket')">
                        <i class="fas fa-ticket-alt"></i> Ticket
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
const CSRF = '<?= $_SESSION['csrf_token'] ?>';
function alertAction(alertId, action) {
    if (action === 'create_ticket' && !confirm('Create a ticket from this alert?')) return;
    fetch('/agent/post/rmm_alert.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `csrf_token=${CSRF}&action=${action}&alert_id=${alertId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            if (d.redirect) { window.location.href = d.redirect; }
            else {
                const row = document.getElementById('alert-row-' + alertId);
                if (row) row.remove();
            }
        } else {
            alert('Failed: ' + (d.error || 'Unknown error'));
        }
    });
}
</script>

<?php require_once "../includes/footer.php"; ?>
