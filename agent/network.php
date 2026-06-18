<?php
require_once "includes/inc_all.php";
enforceUserPermission('module_rmm');

$filter_client_id = intval($_GET['client_id'] ?? 0);
$filter_status    = sanitizeInput($_GET['status'] ?? '');
$filter_type      = sanitizeInput($_GET['device_type'] ?? '');
$filter_search    = trim((string) ($_GET['q'] ?? ''));

$allowed_types = ['Firewall/Router', 'Switch', 'Access Point'];
$type_esc = $filter_type && in_array($filter_type, $allowed_types)
    ? "'" . mysqli_real_escape_string($mysqli, $filter_type) . "'"
    : null;

$where = "a.asset_type IN ('Firewall/Router','Switch','Access Point') AND a.asset_archived_at IS NULL";
if ($filter_client_id) { $where .= " AND a.asset_client_id=" . intval($filter_client_id); }
if ($filter_status)    { $where .= " AND arl.rmm_status='" . mysqli_real_escape_string($mysqli, $filter_status) . "'"; }
if ($type_esc)         { $where .= " AND a.asset_type=$type_esc"; }
if ($filter_search !== '') {
    $sq = mysqli_real_escape_string($mysqli, $filter_search);
    $where .= " AND (a.asset_name LIKE '%$sq%' OR arl.hostname LIKE '%$sq%' OR arl.model LIKE '%$sq%'
                     OR c.client_name LIKE '%$sq%' OR ai.interface_ip LIKE '%$sq%')";
}

$sql_devices = mysqli_query($mysqli,
    "SELECT a.asset_id, a.asset_name, a.asset_type, a.asset_client_id,
            c.client_name,
            arl.rmm_status, arl.hostname, arl.model AS rmm_model, arl.os_version AS rmm_firmware,
            arl.last_seen, arl.integration_id,
            ri.name AS integration_name, ri.type AS integration_type,
            (SELECT COUNT(*) FROM rmm_alerts WHERE asset_id=a.asset_id AND status='new') AS alert_count,
            (SELECT ticket_id FROM rmm_alerts WHERE asset_id=a.asset_id AND status='new'
             AND ticket_id IS NOT NULL ORDER BY created_at DESC LIMIT 1) AS open_alert_ticket_id,
            ai.interface_ip
     FROM assets a
     LEFT JOIN clients c ON c.client_id = a.asset_client_id
     LEFT JOIN asset_rmm_links arl ON arl.asset_id = a.asset_id
     LEFT JOIN rmm_integrations ri ON ri.id = arl.integration_id
     LEFT JOIN asset_interfaces ai ON ai.interface_asset_id = a.asset_id AND ai.interface_primary = 1
     WHERE $where
     GROUP BY a.asset_id
     ORDER BY FIELD(a.asset_type,'Firewall/Router','Switch','Access Point'), arl.rmm_status ASC, a.asset_name ASC"
);

$cnt = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT
       SUM(arl.rmm_status='online')  AS online,
       SUM(arl.rmm_status='offline') AS offline,
       COUNT(*) AS total,
       SUM(a.asset_type='Firewall/Router') AS fw_count,
       SUM(a.asset_type='Switch')          AS sw_count,
       SUM(a.asset_type='Access Point')    AS ap_count,
       SUM((SELECT COUNT(*) FROM rmm_alerts WHERE asset_id=a.asset_id AND status='new') > 0) AS with_alerts
     FROM assets a
     LEFT JOIN asset_rmm_links arl ON arl.asset_id = a.asset_id
     WHERE a.asset_type IN ('Firewall/Router','Switch','Access Point') AND a.asset_archived_at IS NULL"
));

$sql_sophos = mysqli_query($mysqli, "SELECT id, name FROM rmm_integrations WHERE enabled=1 AND type='sophos_central' ORDER BY name");
$sophos_integrations = [];
while ($i = mysqli_fetch_assoc($sql_sophos)) { $sophos_integrations[] = $i; }
$default_sophos_id = !empty($sophos_integrations) ? $sophos_integrations[0]['id'] : 0;

$sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name");

$device_icons = [
    'Firewall/Router' => 'shield-alt',
    'Switch'          => 'network-wired',
    'Access Point'    => 'wifi',
];
$type_labels = [
    'Firewall/Router' => 'Firewall',
    'Switch'          => 'Switch',
    'Access Point'    => 'Access Point',
];
?>

<!-- Header -->
<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0 mr-auto"><i class="fas fa-network-wired mr-2"></i>Network</h4>
    <?php if (lookupUserPermission('module_rmm_sync') >= 1 && !empty($sophos_integrations)): ?>
    <div class="d-flex align-items-center">
        <?php if (count($sophos_integrations) > 1): ?>
        <select id="netSyncIntg" class="form-control form-control-sm mr-2" style="max-width:180px">
            <?php foreach ($sophos_integrations as $i): ?>
            <option value="<?= intval($i['id']) ?>"><?= nullable_htmlentities($i['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button class="btn btn-success btn-sm mr-2" id="netSyncBtn" onclick="triggerNetSync()">
            <i class="fas fa-sync mr-1"></i>Sync Firewalls
        </button>
    </div>
    <?php endif; ?>
    <a href="/admin/settings_integrations.php?tab=firewalls" class="btn btn-secondary btn-sm">
        <i class="fas fa-cog mr-1"></i>Settings
    </a>
</div>

<!-- Sync status bar -->
<div id="netSyncStatus" class="alert alert-info d-none mb-3">
    <i class="fas fa-spinner fa-spin mr-2"></i><span id="netSyncStatusText">Syncing...</span>
</div>

<!-- Stat cards -->
<div class="row mb-3">
    <div class="col-6 col-md-3">
        <div class="small-box bg-info shadow-sm">
            <div class="inner"><h3><?= intval($cnt['total']) ?></h3><p>Total Devices</p></div>
            <div class="icon"><i class="fas fa-network-wired"></i></div>
            <a href="?" class="small-box-footer">Show All <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="small-box bg-success shadow-sm">
            <div class="inner"><h3><?= intval($cnt['online']) ?></h3><p>Online</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <a href="?status=online" class="small-box-footer">Filter <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="small-box bg-danger shadow-sm">
            <div class="inner"><h3><?= intval($cnt['offline']) ?></h3><p>Offline</p></div>
            <div class="icon"><i class="fas fa-times-circle"></i></div>
            <a href="?status=offline" class="small-box-footer">Filter <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="small-box bg-warning shadow-sm">
            <div class="inner"><h3><?= intval($cnt['with_alerts']) ?></h3><p>With Alerts</p></div>
            <div class="icon"><i class="fas fa-bell"></i></div>
            <a href="/agent/alerts.php?source=rmm&status=new" class="small-box-footer">View Alerts <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<!-- Device type breakdown -->
<div class="row mb-3">
    <?php foreach ([
        ['label' => 'Firewalls',      'count' => $cnt['fw_count'], 'icon' => 'shield-alt',    'type' => 'Firewall/Router', 'color' => '#e74c3c'],
        ['label' => 'Switches',       'count' => $cnt['sw_count'], 'icon' => 'network-wired', 'type' => 'Switch',          'color' => '#3498db'],
        ['label' => 'Access Points',  'count' => $cnt['ap_count'], 'icon' => 'wifi',          'type' => 'Access Point',    'color' => '#27ae60'],
    ] as $dt): ?>
    <div class="col-md-4">
        <a href="?device_type=<?= urlencode($dt['type']) ?><?= $filter_client_id ? '&client_id='.$filter_client_id : '' ?>"
           class="info-box shadow-sm mb-0 text-decoration-none <?= $filter_type === $dt['type'] ? 'border border-primary' : '' ?>">
            <span class="info-box-icon" style="background-color:<?= $dt['color'] ?>;min-width:70px;">
                <i class="fas fa-<?= $dt['icon'] ?>"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text text-dark"><?= $dt['label'] ?></span>
                <span class="info-box-number text-dark"><?= intval($dt['count']) ?></span>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter bar -->
<div class="card card-dark mb-3">
    <div class="card-body py-2">
        <form method="get" class="d-flex flex-wrap align-items-center" style="gap:6px">
            <?php if ($filter_type): ?>
            <input type="hidden" name="device_type" value="<?= htmlspecialchars($filter_type) ?>">
            <?php endif; ?>
            <select name="client_id" class="form-control form-control-sm" style="max-width:200px" onchange="this.form.submit()">
                <option value="">All Clients</option>
                <?php while ($cl = mysqli_fetch_assoc($sql_clients)): ?>
                <option value="<?= $cl['client_id'] ?>" <?= $filter_client_id == $cl['client_id'] ? 'selected' : '' ?>>
                    <?= nullable_htmlentities($cl['client_name']) ?>
                </option>
                <?php endwhile; ?>
            </select>
            <select name="status" class="form-control form-control-sm" style="max-width:150px" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="online"  <?= $filter_status === 'online'  ? 'selected' : '' ?>>Online</option>
                <option value="offline" <?= $filter_status === 'offline' ? 'selected' : '' ?>>Offline</option>
            </select>
            <div class="input-group" style="max-width:240px">
                <input type="text" name="q" value="<?= htmlspecialchars($filter_search) ?>"
                       class="form-control form-control-sm" placeholder="Search device, client, IP…">
                <div class="input-group-append">
                    <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <?php if ($filter_client_id || $filter_status || $filter_search !== '' || $filter_type): ?>
            <a href="?" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times mr-1"></i>Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Device table -->
<div class="card card-dark">
    <div class="card-header py-2 d-flex align-items-center">
        <h3 class="card-title mb-0">
            <?php if ($filter_type): ?>
                <i class="fas fa-<?= htmlspecialchars($device_icons[$filter_type] ?? 'network-wired') ?> mr-2"></i>
                <?= htmlspecialchars($type_labels[$filter_type] ?? $filter_type) ?>s
            <?php else: ?>
                <i class="fas fa-network-wired mr-2"></i>All Network Devices
            <?php endif; ?>
            <?php if (mysqli_num_rows($sql_devices) > 0): ?>
            <span class="badge badge-secondary ml-2"><?= mysqli_num_rows($sql_devices) ?></span>
            <?php endif; ?>
        </h3>
        <?php if ($filter_type): ?>
        <a href="?" class="btn btn-sm btn-outline-secondary ml-auto">Show All Types</a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (mysqli_num_rows($sql_devices) === 0): ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-network-wired fa-3x mb-3"></i>
            <p class="mb-1">No network devices found<?= ($filter_client_id || $filter_status || $filter_type || $filter_search !== '') ? ' matching your filters' : '' ?>.</p>
            <?php if (!empty($sophos_integrations) && (!$filter_type || $filter_type === 'Firewall/Router')): ?>
            <p class="small"><a href="#" onclick="triggerNetSync();return false;">Sync from Sophos Central</a> to import firewalls.</p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;background:#f8f9fa;" class="text-muted border-bottom">
                <tr>
                    <th class="pl-3" style="width:40px"></th>
                    <th>Device</th>
                    <th>Client</th>
                    <th>IP Address</th>
                    <th>Model / Firmware</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Last Seen</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $current_type = null;
            while ($dev = mysqli_fetch_assoc($sql_devices)):
                $asset_id  = intval($dev['asset_id']);
                $dev_type  = $dev['asset_type'];
                $status    = $dev['rmm_status'] ?: ($dev['integration_id'] ? 'unknown' : null);
                $sc        = ['online' => 'success', 'offline' => 'danger', 'unknown' => 'secondary'][$status] ?? null;
                $si        = ['online' => 'check-circle', 'offline' => 'times-circle', 'unknown' => 'question-circle'][$status] ?? null;
                $icon      = $device_icons[$dev_type] ?? 'network-wired';
                $label     = $type_labels[$dev_type] ?? $dev_type;
                $display_name = nullable_htmlentities($dev['hostname'] ?: $dev['asset_name']);
                $ip        = nullable_htmlentities($dev['interface_ip'] ?? '');
                $model     = nullable_htmlentities($dev['rmm_model'] ?: '');
                $firmware  = nullable_htmlentities($dev['rmm_firmware'] ?: '');
                $ago       = $dev['last_seen'] ? timeAgo($dev['last_seen']) : '—';
                $alerts    = intval($dev['alert_count']);

                // Type separator row
                if (!$filter_type && $dev_type !== $current_type):
                    $current_type = $dev_type;
            ?>
            <tr style="background:#f4f6f9;">
                <td colspan="9" class="pl-3 py-1">
                    <small class="font-weight-bold text-uppercase text-muted" style="letter-spacing:.5px;">
                        <i class="fas fa-<?= $icon ?> mr-1"></i><?= htmlspecialchars($label) ?>s
                    </small>
                </td>
            </tr>
            <?php endif; ?>
                <tr>
                    <td class="pl-3 text-center align-middle">
                        <?php if ($sc): ?>
                        <i class="fas fa-<?= $si ?> text-<?= $sc ?> fa-lg" title="<?= ucfirst($status) ?>"></i>
                        <?php else: ?>
                        <i class="fas fa-<?= $icon ?> text-muted fa-lg"></i>
                        <?php endif; ?>
                    </td>
                    <td class="align-middle">
                        <a class="font-weight-bold text-dark" href="/agent/asset_details.php?asset_id=<?= $asset_id ?>">
                            <?= $display_name ?>
                        </a>
                        <?php if ($alerts > 0): ?>
                        <?php if ($dev['open_alert_ticket_id']): ?>
                        <a href="/agent/ticket.php?ticket_id=<?= intval($dev['open_alert_ticket_id']) ?>"
                           class="badge badge-danger ml-1" title="<?= $alerts ?> open alert(s) — click to view ticket">
                            <i class="fas fa-bell mr-1"></i><?= $alerts ?>
                        </a>
                        <?php else: ?>
                        <a href="/agent/alerts.php?source=rmm&status=new"
                           class="badge badge-danger ml-1" title="<?= $alerts ?> open alert(s)">
                            <i class="fas fa-bell mr-1"></i><?= $alerts ?>
                        </a>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="align-middle small">
                        <?php if ($dev['asset_client_id']): ?>
                        <a href="/agent/client_overview.php?client_id=<?= intval($dev['asset_client_id']) ?>">
                            <?= nullable_htmlentities($dev['client_name']) ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="align-middle small text-monospace">
                        <?= $ip ?: '<span class="text-muted">—</span>' ?>
                    </td>
                    <td class="align-middle small text-muted">
                        <?php if ($model || $firmware): ?>
                            <?php if ($model): ?><div><?= $model ?></div><?php endif; ?>
                            <?php if ($firmware): ?><div class="text-secondary" style="font-size:10px"><?= $firmware ?></div><?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="align-middle small text-muted">
                        <?= $dev['integration_name'] ? nullable_htmlentities($dev['integration_name']) : '<span class="text-muted">Manual</span>' ?>
                    </td>
                    <td class="align-middle">
                        <?php if ($sc): ?>
                        <span class="badge badge-<?= $sc ?>"><?= ucfirst($status) ?></span>
                        <?php else: ?>
                        <span class="badge badge-light text-muted">Manual</span>
                        <?php endif; ?>
                    </td>
                    <td class="align-middle small text-muted"><?= $ago ?></td>
                    <td class="align-middle text-right pr-3">
                        <a href="/agent/asset_details.php?asset_id=<?= $asset_id ?>"
                           class="btn btn-xs btn-outline-secondary" title="View asset">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function triggerNetSync() {
    const sel = document.getElementById('netSyncIntg');
    const integrationId = sel ? sel.value : <?= intval($default_sophos_id) ?>;
    if (!integrationId) { alert('No Sophos Central integration configured.'); return; }
    const btn = document.getElementById('netSyncBtn');
    const bar = document.getElementById('netSyncStatus');
    const txt = document.getElementById('netSyncStatusText');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Syncing...'; }
    bar.classList.remove('d-none');
    bar.className = bar.className.replace(/alert-(success|danger)/, 'alert-info');
    txt.textContent = 'Syncing firewalls from Sophos Central...';

    fetch('/agent/post/rmm_sync.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?= $_SESSION['csrf_token'] ?>&action=sync&integration_id=' + integrationId
    })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(d => {
        if (d.success) {
            txt.textContent = `Sync complete — ${d.created} created, ${d.updated} updated, ${d.matched} matched.`;
            bar.className = bar.className.replace('alert-info', 'alert-success');
            setTimeout(() => location.reload(), 2000);
        } else {
            txt.textContent = 'Sync failed: ' + (d.error || 'Unknown error');
            bar.className = bar.className.replace('alert-info', 'alert-danger');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync mr-1"></i>Sync Firewalls'; }
        }
    })
    .catch(err => {
        txt.textContent = 'Error: ' + err.message;
        bar.className = bar.className.replace('alert-info', 'alert-danger');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync mr-1"></i>Sync Firewalls'; }
    });
}
</script>

<?php require_once "../includes/footer.php"; ?>
