<?php
require_once "includes/inc_all.php";
enforceUserPermission('module_rmm');

// Firewalls are just RMM-managed assets typed 'Firewall/Router' — this page
// is a focused view over that subset (currently populated by the Sophos
// Central integration), the same way agent/backups.php is a focused view
// over Comet rather than a separate data model.
$sql_fw_integrations = mysqli_query($mysqli, "SELECT id, name FROM rmm_integrations WHERE enabled=1 AND type='sophos_central' ORDER BY name");
$fw_integrations = [];
while ($i = mysqli_fetch_assoc($sql_fw_integrations)) { $fw_integrations[] = $i; }

if (empty($fw_integrations)) {
    ?>
    <div class="card card-dark">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-fire-alt fa-3x mb-3 d-block"></i>
            No Sophos Central integration is configured.<br>
            Ask an admin to add one under <a href="/admin/settings_integrations.php?tab=rmm">Settings &rarr; Integrations &rarr; RMM</a>.
        </div>
    </div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

$filter_client_id = intval($_GET['client_id'] ?? 0);
$filter_status     = sanitizeInput($_GET['status'] ?? '');
$filter_search     = trim((string) ($_GET['q'] ?? ''));
$sync_integration_id = intval($_GET['sync_integration_id'] ?? $fw_integrations[0]['id']);

$where = "a.asset_type = 'Firewall/Router'";
if ($filter_client_id) { $where .= " AND a.asset_client_id=$filter_client_id"; }
if ($filter_status)    { $where .= " AND arl.rmm_status='" . mysqli_real_escape_string($mysqli, $filter_status) . "'"; }
if ($filter_search !== '') {
    $sq = mysqli_real_escape_string($mysqli, $filter_search);
    $where .= " AND (arl.hostname LIKE '%$sq%' OR arl.model LIKE '%$sq%' OR c.client_name LIKE '%$sq%')";
}

$sql_firewalls = mysqli_query($mysqli,
    "SELECT arl.*, a.asset_id, a.asset_name, a.asset_client_id, c.client_name,
            (SELECT alert_id FROM rmm_alerts WHERE asset_id=a.asset_id AND status='new' ORDER BY created_at DESC LIMIT 1) AS open_alert_id,
            (SELECT ticket_id FROM rmm_alerts WHERE asset_id=a.asset_id AND status='new' AND ticket_id IS NOT NULL ORDER BY created_at DESC LIMIT 1) AS open_alert_ticket_id
     FROM asset_rmm_links arl
     JOIN assets a ON a.asset_id = arl.asset_id
     LEFT JOIN clients c ON c.client_id = a.asset_client_id
     WHERE $where
     ORDER BY arl.rmm_status ASC, arl.hostname ASC"
);

$cnt = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT
       SUM(arl.rmm_status='online')  AS online,
       SUM(arl.rmm_status='offline') AS offline,
       SUM(arl.rmm_status='unknown' OR arl.rmm_status IS NULL) AS unknown,
       COUNT(*) AS total
     FROM asset_rmm_links arl
     JOIN assets a ON a.asset_id = arl.asset_id
     WHERE a.asset_type = 'Firewall/Router'"
));

$sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name");
?>

<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0 mr-auto"><i class="fas fa-fire-alt mr-2"></i>Firewalls</h4>
    <?php if (lookupUserPermission('module_rmm_sync') >= 1): ?>
    <?php if (count($fw_integrations) > 1) { ?>
    <select id="fwSyncIntg" class="form-control form-control-sm mr-2" style="max-width:200px;display:inline-block;width:auto;">
        <?php foreach ($fw_integrations as $i) { ?>
        <option value="<?= intval($i['id']) ?>" <?= $sync_integration_id == $i['id'] ? 'selected' : '' ?>><?= nullable_htmlentities($i['name']) ?></option>
        <?php } ?>
    </select>
    <?php } ?>
    <button class="btn btn-success btn-sm" id="fwSyncBtn" onclick="triggerFwSync()">
        <i class="fas fa-sync mr-1"></i>Sync Firewalls
    </button>
    <?php endif; ?>
</div>

<!-- Sync status bar -->
<div id="fwSyncStatus" class="alert alert-info d-none mb-3">
    <i class="fas fa-spinner fa-spin mr-2"></i><span id="fwSyncStatusText">Syncing...</span>
</div>

<!-- Summary row -->
<div class="row mb-3">
    <?php foreach ([
        ['label'=>'Total Firewalls', 'val'=>intval($cnt['total']),   'icon'=>'fire-alt',        'color'=>'primary'],
        ['label'=>'Online',          'val'=>intval($cnt['online']),  'icon'=>'check-circle',    'color'=>'success'],
        ['label'=>'Offline',         'val'=>intval($cnt['offline']), 'icon'=>'times-circle',    'color'=>'danger'],
        ['label'=>'Unknown',         'val'=>intval($cnt['unknown']), 'icon'=>'question-circle', 'color'=>'secondary'],
    ] as $s): ?>
    <div class="col-md-3">
        <div class="info-box shadow-sm mb-0">
            <span class="info-box-icon bg-<?= $s['color'] ?>" style="font-size:1.4rem;">
                <i class="fas fa-<?= $s['icon'] ?>"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text"><?= $s['label'] ?></span>
                <span class="info-box-number"><?= $s['val'] ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter bar -->
<div class="card card-dark mb-2">
    <div class="card-body py-2">
        <form method="get" class="d-flex flex-wrap align-items-center" style="gap:6px">
            <?php if (mysqli_num_rows($sql_clients) > 0) { ?>
            <select name="client_id" class="form-control form-control-sm mr-2" style="max-width:200px" onchange="this.form.submit()">
                <option value="">All Clients</option>
                <?php while ($cl = mysqli_fetch_assoc($sql_clients)) { ?>
                <option value="<?= $cl['client_id'] ?>" <?= $filter_client_id == $cl['client_id'] ? 'selected' : '' ?>>
                    <?= nullable_htmlentities($cl['client_name']) ?>
                </option>
                <?php } ?>
            </select>
            <?php } ?>
            <select name="status" class="form-control form-control-sm mr-2" style="max-width:160px" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="online"  <?= $filter_status === 'online'  ? 'selected' : '' ?>>Online</option>
                <option value="offline" <?= $filter_status === 'offline' ? 'selected' : '' ?>>Offline</option>
                <option value="unknown" <?= $filter_status === 'unknown' ? 'selected' : '' ?>>Unknown</option>
            </select>
            <input type="text" name="q" value="<?= htmlspecialchars($filter_search) ?>" class="form-control form-control-sm mr-2" placeholder="Search device, model, client…" style="max-width:220px">
            <button type="submit" class="btn btn-sm btn-secondary mr-2"><i class="fas fa-search"></i></button>
            <?php if ($filter_client_id || $filter_status || $filter_search !== '') { ?>
            <a href="?" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times mr-1"></i>Clear</a>
            <?php } ?>
        </form>
    </div>
</div>

<!-- Firewall table -->
<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title"><i class="fas fa-fw fa-fire-alt mr-2"></i>Firewall Status</h3>
    </div>
    <div class="card-body p-0">
        <?php if (mysqli_num_rows($sql_firewalls) === 0): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-fire-alt fa-3x mb-3"></i>
                <p>No firewalls match your filters. <a href="#" onclick="triggerFwSync()">Sync</a> to import devices.</p>
            </div>
        <?php else: ?>
        <table class="table table-sm table-borderless table-hover mb-0">
            <thead style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;" class="text-muted border-bottom">
                <tr>
                    <th class="pl-3" style="width:36px;"></th>
                    <th>Device</th>
                    <th>Client</th>
                    <th>Model</th>
                    <th>Firmware</th>
                    <th>Status</th>
                    <th>Last Seen</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php while ($fw = mysqli_fetch_assoc($sql_firewalls)):
                $status = $fw['rmm_status'] ?: 'unknown';
                $sc = ['online' => 'success', 'offline' => 'danger', 'unknown' => 'secondary'][$status] ?? 'secondary';
                $si = ['online' => 'check-circle', 'offline' => 'times-circle', 'unknown' => 'question-circle'][$status] ?? 'question-circle';
                $ago = $fw['last_seen'] ? timeAgo($fw['last_seen']) : '—';
            ?>
                <tr>
                    <td class="pl-3 text-center"><i class="fas fa-<?= $si ?> text-<?= $sc ?>"></i></td>
                    <td class="font-weight-bold small">
                        <a href="/agent/asset_details.php?asset_id=<?= intval($fw['asset_id']) ?>"><?= nullable_htmlentities($fw['hostname'] ?: $fw['asset_name']) ?></a>
                        <?php if ($fw['open_alert_id']) { ?>
                        <?php if ($fw['open_alert_ticket_id']) { ?>
                        <a href="/agent/ticket.php?ticket_id=<?= intval($fw['open_alert_ticket_id']) ?>" class="badge badge-danger ml-1" title="Open alert — click to view ticket">
                            <i class="fas fa-bell"></i>
                        </a>
                        <?php } else { ?>
                        <a href="/agent/alerts.php?source=rmm&status=new" class="badge badge-danger ml-1" title="Open alert">
                            <i class="fas fa-bell"></i>
                        </a>
                        <?php } ?>
                        <?php } ?>
                    </td>
                    <td class="small">
                        <?php if ($fw['asset_client_id']) { ?>
                        <a href="/agent/client_overview.php?client_id=<?= intval($fw['asset_client_id']) ?>"><?= nullable_htmlentities($fw['client_name']) ?></a>
                        <?php } else { ?><span class="text-muted">—</span><?php } ?>
                    </td>
                    <td class="text-muted small"><?= nullable_htmlentities($fw['model']) ?: '—' ?></td>
                    <td class="text-muted small"><?= nullable_htmlentities($fw['os_version']) ?: '—' ?></td>
                    <td><span class="badge badge-<?= $sc ?>"><?= ucfirst($status) ?></span></td>
                    <td class="text-muted small"><?= $ago ?></td>
                    <td class="text-right pr-3">
                        <a href="/agent/asset_details.php?asset_id=<?= intval($fw['asset_id']) ?>" class="btn btn-xs btn-outline-secondary">
                            <i class="fas fa-external-link-alt mr-1"></i>View
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
function triggerFwSync() {
    const sel = document.getElementById('fwSyncIntg');
    const integrationId = sel ? sel.value : <?= intval($sync_integration_id) ?>;
    const btn = document.getElementById('fwSyncBtn');
    const bar = document.getElementById('fwSyncStatus');
    const txt = document.getElementById('fwSyncStatusText');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Syncing...'; }
    bar.classList.remove('d-none');
    txt.textContent = 'Syncing firewalls...';

    fetch('/agent/post/rmm_sync.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?= $_SESSION['csrf_token'] ?>&action=sync&integration_id=' + integrationId
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
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync mr-1"></i>Sync Firewalls'; }
        }
    })
    .catch(() => {
        txt.textContent = 'Network error during sync.';
        bar.classList.replace('alert-info', 'alert-danger');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync mr-1"></i>Sync Firewalls'; }
    });
}
</script>

<?php require_once "../includes/footer.php"; ?>
