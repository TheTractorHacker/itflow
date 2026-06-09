<?php
require_once "includes/inc_all.php";
enforceUserPermission('module_rmm');

// Stats
$stats = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT
       SUM(rmm_status='online')  as online,
       SUM(rmm_status='offline') as offline,
       SUM(rmm_status='unknown') as unknown,
       COUNT(*)                  as total
     FROM asset_rmm_links"
));

$alert_counts = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT
       SUM(status='new')          as new_cnt,
       SUM(status='acknowledged') as ack_cnt,
       SUM(status='new' AND severity IN ('critical','error')) as critical_cnt
     FROM rmm_alerts"
));

$open_tickets = intval(mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT COUNT(*) as c FROM tickets WHERE ticket_archived_at IS NULL AND ticket_resolved_at IS NULL"
))['c']);

$last_sync_row = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT finished_at, status, assets_created, assets_updated FROM rmm_sync_log
     WHERE status='success' ORDER BY id DESC LIMIT 1"
));

// Offline assets
$sql_offline = mysqli_query($mysqli,
    "SELECT arl.*, a.asset_name, a.asset_id, c.client_name, a.asset_client_id
     FROM asset_rmm_links arl
     JOIN assets a ON a.asset_id = arl.asset_id
     LEFT JOIN clients c ON c.client_id = a.asset_client_id
     WHERE arl.rmm_status='offline'
     ORDER BY arl.last_seen DESC
     LIMIT 20"
);

// Active alerts with asset link
$sql_new_alerts = mysqli_query($mysqli,
    "SELECT a.*, ast.asset_name, ast.asset_id as ast_id, c.client_name
     FROM rmm_alerts a
     LEFT JOIN assets ast ON ast.asset_id = a.asset_id
     LEFT JOIN clients c ON c.client_id = a.client_id
     WHERE a.status='new'
     ORDER BY FIELD(a.severity,'critical','error','warning','info'), a.created_at DESC
     LIMIT 15"
);

// Recent remote sessions
$sql_recent_sessions = mysqli_query($mysqli,
    "SELECT rs.*, a.asset_name, a.asset_id, u.user_firstname, u.user_lastname
     FROM rmm_remote_sessions rs
     JOIN assets a ON a.asset_id = rs.asset_id
     LEFT JOIN users u ON u.user_id = rs.user_id
     ORDER BY rs.created_at DESC
     LIMIT 8"
);

// Recent script runs
$sql_recent_runs = mysqli_query($mysqli,
    "SELECT sr.*, s.name as script_name, a.asset_name, a.asset_id, u.user_firstname, u.user_lastname
     FROM rmm_script_runs sr
     LEFT JOIN rmm_scripts s ON s.id = sr.script_id
     JOIN assets a ON a.asset_id = sr.asset_id
     LEFT JOIN users u ON u.user_id = sr.user_id
     ORDER BY sr.started_at DESC
     LIMIT 8"
);

// Client health
$sql_clients = mysqli_query($mysqli,
    "SELECT c.client_id, c.client_name,
       SUM(arl.rmm_status='online')  as online,
       SUM(arl.rmm_status='offline') as offline,
       COUNT(arl.id)                 as total,
       (SELECT COUNT(*) FROM rmm_alerts al WHERE al.client_id = c.client_id AND al.status='new') as alerts,
       (SELECT COUNT(*) FROM tickets t WHERE t.ticket_client_id = c.client_id AND t.ticket_archived_at IS NULL AND t.ticket_resolved_at IS NULL) as open_tickets
     FROM clients c
     JOIN assets a ON a.asset_client_id = c.client_id
     JOIN asset_rmm_links arl ON arl.asset_id = a.asset_id
     WHERE c.client_archived_at IS NULL
     GROUP BY c.client_id
     HAVING total > 0
     ORDER BY offline DESC, alerts DESC, c.client_name ASC"
);

// Integrations for sync selector
$sql_integrations = mysqli_query($mysqli, "SELECT id, name FROM rmm_integrations WHERE enabled=1 ORDER BY name");
$integrations = [];
while ($i = mysqli_fetch_assoc($sql_integrations)) { $integrations[] = $i; }
$default_intg = $config_rmm_default_integration_id ?: ($integrations[0]['id'] ?? 0);
?>

<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0 mr-auto"><i class="fas fa-tachometer-alt mr-2"></i>RMM Dashboard</h4>
    <?php if ($last_sync_row): ?>
    <small class="text-muted mr-3">
        <i class="fas fa-sync-alt mr-1 text-success"></i>Last sync: <?= nullable_htmlentities($last_sync_row['finished_at']) ?>
    </small>
    <?php endif; ?>
    <a href="/agent/rmm_assets.php" class="btn btn-info btn-sm mr-2">
        <i class="fas fa-desktop mr-1"></i>All Assets
    </a>
    <a href="/agent/rmm_alerts.php" class="btn btn-warning btn-sm mr-2">
        <i class="fas fa-bell mr-1"></i>Alerts<?php if ($alert_counts['new_cnt'] > 0): ?> <span class="badge badge-light"><?= intval($alert_counts['new_cnt']) ?></span><?php endif; ?>
    </a>
    <?php if (lookupUserPermission('module_rmm_sync') >= 1 && $default_intg): ?>
    <button class="btn btn-success btn-sm" onclick="quickSync()">
        <i class="fas fa-sync mr-1"></i>Sync Now
    </button>
    <?php endif; ?>
</div>

<!-- Stat cards -->
<div class="row mb-3">
    <div class="col-6 col-md-2">
        <a href="/agent/rmm_assets.php?status=online" class="text-decoration-none">
        <div class="info-box bg-success mb-0">
            <span class="info-box-icon"><i class="fas fa-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Online</span>
                <span class="info-box-number"><?= intval($stats['online']) ?></span>
            </div>
        </div></a>
    </div>
    <div class="col-6 col-md-2">
        <a href="/agent/rmm_assets.php?status=offline" class="text-decoration-none">
        <div class="info-box bg-danger mb-0">
            <span class="info-box-icon"><i class="fas fa-times-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Offline</span>
                <span class="info-box-number"><?= intval($stats['offline']) ?></span>
            </div>
        </div></a>
    </div>
    <div class="col-6 col-md-2">
        <a href="/agent/rmm_assets.php" class="text-decoration-none">
        <div class="info-box bg-secondary mb-0">
            <span class="info-box-icon"><i class="fas fa-desktop"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Managed</span>
                <span class="info-box-number"><?= intval($stats['total']) ?></span>
            </div>
        </div></a>
    </div>
    <div class="col-6 col-md-2">
        <a href="/agent/rmm_alerts.php?status=new" class="text-decoration-none">
        <div class="info-box <?= intval($alert_counts['critical_cnt']) > 0 ? 'bg-danger' : 'bg-warning' ?> mb-0">
            <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">New Alerts</span>
                <span class="info-box-number"><?= intval($alert_counts['new_cnt']) ?></span>
                <?php if ($alert_counts['critical_cnt'] > 0): ?>
                <span class="info-box-text" style="font-size:10px"><?= intval($alert_counts['critical_cnt']) ?> critical</span>
                <?php endif; ?>
            </div>
        </div></a>
    </div>
    <div class="col-6 col-md-2">
        <a href="/agent/tickets.php" class="text-decoration-none">
        <div class="info-box bg-primary mb-0">
            <span class="info-box-icon"><i class="fas fa-ticket-alt"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Open Tickets</span>
                <span class="info-box-number"><?= $open_tickets ?></span>
            </div>
        </div></a>
    </div>
    <div class="col-6 col-md-2">
        <a href="/agent/rmm_scripts.php" class="text-decoration-none">
        <div class="info-box bg-dark mb-0">
            <span class="info-box-icon"><i class="fas fa-code"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Script Runs</span>
                <?php
                $run_count = intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) as c FROM rmm_script_runs WHERE started_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"))['c']);
                ?>
                <span class="info-box-number"><?= $run_count ?></span>
                <span class="info-box-text" style="font-size:10px">last 24h</span>
            </div>
        </div></a>
    </div>
</div>

<div id="syncBar" class="alert alert-info d-none mb-3">
    <i class="fas fa-spinner fa-spin mr-2"></i><span id="syncBarText">Syncing...</span>
</div>

<div class="row">

    <!-- Left column -->
    <div class="col-md-6">

        <!-- Offline Assets -->
        <div class="card card-dark mb-3" style="border-top:3px solid #dc3545">
            <div class="card-header py-2 d-flex align-items-center">
                <h6 class="mb-0 mr-auto">
                    <i class="fas fa-times-circle mr-2 text-danger"></i>Offline Assets
                    <?php if (mysqli_num_rows($sql_offline) > 0): ?>
                    <span class="badge badge-danger ml-1"><?= mysqli_num_rows($sql_offline) ?></span>
                    <?php endif; ?>
                </h6>
                <a href="/agent/rmm_assets.php?status=offline" class="btn btn-xs btn-secondary">View All</a>
            </div>
            <div class="card-body p-0">
            <?php if (mysqli_num_rows($sql_offline) === 0): ?>
                <p class="text-muted text-center py-3 mb-0 small">
                    <i class="fas fa-check-circle text-success mr-1"></i>All assets online
                </p>
            <?php else: ?>
                <table class="table table-sm table-hover mb-0">
                    <thead class="text-muted border-bottom" style="font-size:10px;text-transform:uppercase;letter-spacing:.4px">
                        <tr>
                            <th class="pl-3">Asset</th>
                            <th>Client</th>
                            <th>Last Seen</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = mysqli_fetch_assoc($sql_offline)): ?>
                    <tr>
                        <td class="pl-3 small">
                            <i class="fas fa-circle text-danger mr-1" style="font-size:8px"></i>
                            <a href="/agent/asset_details.php?asset_id=<?= intval($row['asset_id']) ?>" class="font-weight-bold">
                                <?= nullable_htmlentities($row['hostname'] ?: $row['asset_name']) ?>
                            </a>
                            <?php if ($row['os_name']): ?>
                            <br><small class="text-muted ml-2"><?= nullable_htmlentities(substr($row['os_name'], 0, 30)) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= nullable_htmlentities($row['client_name']) ?></td>
                        <td class="small text-muted" style="white-space:nowrap">
                            <?= $row['last_seen'] ? nullable_htmlentities(date('M j g:ia', strtotime($row['last_seen']))) : '—' ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            </div>
        </div>

        <!-- New Alerts -->
        <div class="card card-dark mb-3" style="border-top:3px solid #ffc107">
            <div class="card-header py-2 d-flex align-items-center">
                <h6 class="mb-0 mr-auto">
                    <i class="fas fa-bell mr-2 text-warning"></i>New Alerts
                </h6>
                <?php if (lookupUserPermission('module_rmm_alerts_ack') >= 1 && intval($alert_counts['new_cnt']) > 0): ?>
                <button class="btn btn-xs btn-outline-warning mr-2" onclick="ackAllNew(this)">
                    <i class="fas fa-check-double mr-1"></i>Ack All
                </button>
                <?php endif; ?>
                <a href="/agent/rmm_alerts.php" class="btn btn-xs btn-secondary">All Alerts</a>
            </div>
            <div class="card-body p-0" id="alertList">
            <?php if (mysqli_num_rows($sql_new_alerts) === 0): ?>
                <p class="text-muted text-center py-3 mb-0 small">
                    <i class="fas fa-check-circle text-success mr-1"></i>No new alerts
                </p>
            <?php else: ?>
                <?php while ($alert = mysqli_fetch_assoc($sql_new_alerts)):
                    $sev_color = ['critical'=>'danger','error'=>'danger','warning'=>'warning','info'=>'info'][$alert['severity']] ?? 'secondary';
                    $aid = intval($alert['id']);
                ?>
                <div class="d-flex align-items-start border-bottom px-3 py-2 small" id="dash-alert-<?= $aid ?>">
                    <div class="mr-auto">
                        <div>
                            <span class="badge badge-<?= $sev_color ?> mr-1"><?= nullable_htmlentities($alert['severity']) ?></span>
                            <?php if ($alert['asset_id']): ?>
                            <a href="/agent/asset_details.php?asset_id=<?= intval($alert['asset_id']) ?>" class="text-white font-weight-bold">
                                <?= nullable_htmlentities($alert['asset_name']) ?>
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted mt-1" style="line-height:1.3">
                            <?= nullable_htmlentities(substr($alert['message'], 0, 100)) ?>
                        </div>
                        <div class="text-muted" style="font-size:10px">
                            <?= nullable_htmlentities($alert['client_name']) ?>
                            &bull; <?= nullable_htmlentities(date('M j g:ia', strtotime($alert['created_at']))) ?>
                        </div>
                    </div>
                    <div class="ml-2 flex-shrink-0">
                        <?php if (lookupUserPermission('module_rmm_alerts_ack') >= 1): ?>
                        <button class="btn btn-xs btn-outline-warning" onclick="dashAlertAck(<?= $aid ?>, this)" title="Acknowledge">
                            <i class="fas fa-eye"></i>
                        </button>
                        <?php endif; ?>
                        <?php if (lookupUserPermission('module_support') >= 2): ?>
                        <button class="btn btn-xs btn-outline-primary ml-1" onclick="dashAlertTicket(<?= $aid ?>, this)" title="Create Ticket">
                            <i class="fas fa-ticket-alt"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
            </div>
        </div>

    </div><!-- /left col -->

    <!-- Right column -->
    <div class="col-md-6">

        <!-- Client Health -->
        <div class="card card-dark mb-3">
            <div class="card-header py-2 d-flex align-items-center">
                <h6 class="mb-0 mr-auto"><i class="fas fa-users mr-2"></i>Client Health</h6>
                <a href="/agent/clients.php" class="btn btn-xs btn-secondary">All Clients</a>
            </div>
            <div class="card-body p-0">
            <?php if (mysqli_num_rows($sql_clients) === 0): ?>
                <p class="text-muted text-center py-3 mb-0 small">No clients with RMM assets.</p>
            <?php else: ?>
                <table class="table table-sm table-hover mb-0">
                    <thead class="text-muted border-bottom" style="font-size:10px;text-transform:uppercase;letter-spacing:.4px">
                        <tr>
                            <th class="pl-3">Client</th>
                            <th class="text-center" title="Online / Total">Assets</th>
                            <th class="text-center text-danger">Offline</th>
                            <th class="text-center text-warning">Alerts</th>
                            <th class="text-center text-primary">Tickets</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($cl = mysqli_fetch_assoc($sql_clients)):
                        $row_class = '';
                        if ($cl['offline'] > 0 && $cl['alerts'] > 0) $row_class = 'table-danger';
                        elseif ($cl['offline'] > 0 || $cl['alerts'] > 0) $row_class = 'table-warning';
                    ?>
                    <tr class="<?= $row_class ?>">
                        <td class="pl-3 small">
                            <a href="/agent/rmm_assets.php?client_id=<?= intval($cl['client_id']) ?>">
                                <i class="fas fa-desktop mr-1 text-muted"></i><?= nullable_htmlentities($cl['client_name']) ?>
                            </a>
                        </td>
                        <td class="text-center small">
                            <span class="text-success font-weight-bold"><?= intval($cl['online']) ?></span>
                            <span class="text-muted">/<?= intval($cl['total']) ?></span>
                        </td>
                        <td class="text-center small <?= $cl['offline'] > 0 ? 'text-danger font-weight-bold' : 'text-muted' ?>">
                            <?= intval($cl['offline']) ?>
                        </td>
                        <td class="text-center small">
                            <?php if ($cl['alerts'] > 0): ?>
                            <a href="/agent/rmm_alerts.php?client_id=<?= intval($cl['client_id']) ?>" class="badge badge-warning"><?= intval($cl['alerts']) ?></a>
                            <?php else: ?>
                            <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center small">
                            <?php if ($cl['open_tickets'] > 0): ?>
                            <a href="/agent/tickets.php?client_id=<?= intval($cl['client_id']) ?>" class="badge badge-primary"><?= intval($cl['open_tickets']) ?></a>
                            <?php else: ?>
                            <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            </div>
        </div>

        <!-- Recent Remote Sessions -->
        <div class="card card-dark mb-3">
            <div class="card-header py-2">
                <h6 class="mb-0"><i class="fas fa-desktop mr-2"></i>Recent Connections</h6>
            </div>
            <div class="card-body p-0">
            <?php if (mysqli_num_rows($sql_recent_sessions) === 0): ?>
                <p class="text-muted text-center py-3 mb-0 small">No remote sessions logged.</p>
            <?php else: ?>
                <?php while ($sess = mysqli_fetch_assoc($sql_recent_sessions)): ?>
                <div class="d-flex border-bottom px-3 py-2 small align-items-center">
                    <i class="fas fa-desktop mr-2 text-info"></i>
                    <a href="/agent/asset_details.php?asset_id=<?= intval($sess['asset_id']) ?>" class="mr-auto font-weight-bold">
                        <?= nullable_htmlentities($sess['asset_name']) ?>
                    </a>
                    <span class="text-muted"><?= nullable_htmlentities($sess['user_firstname'] . ' ' . $sess['user_lastname']) ?></span>
                    <span class="text-muted ml-2" style="font-size:10px"><?= nullable_htmlentities(date('M j g:ia', strtotime($sess['created_at']))) ?></span>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
            </div>
        </div>

        <!-- Recent Script Runs -->
        <div class="card card-dark mb-3">
            <div class="card-header py-2 d-flex align-items-center">
                <h6 class="mb-0 mr-auto"><i class="fas fa-code mr-2"></i>Recent Script Runs</h6>
                <a href="/agent/rmm_scripts.php" class="btn btn-xs btn-secondary">Library</a>
            </div>
            <div class="card-body p-0">
            <?php if (mysqli_num_rows($sql_recent_runs) === 0): ?>
                <p class="text-muted text-center py-3 mb-0 small">No script runs yet.</p>
            <?php else: ?>
                <?php while ($run = mysqli_fetch_assoc($sql_recent_runs)):
                    $run_badge = ['completed'=>'success','failed'=>'danger','running'=>'warning','pending'=>'secondary'][$run['status']] ?? 'secondary';
                ?>
                <div class="d-flex border-bottom px-3 py-2 small align-items-center">
                    <span class="badge badge-<?= $run_badge ?> mr-2"><?= $run['status'] ?></span>
                    <div class="mr-auto">
                        <div class="font-weight-bold"><?= nullable_htmlentities($run['script_name'] ?? 'Manual') ?></div>
                        <div class="text-muted" style="font-size:10px">
                            <a href="/agent/asset_details.php?asset_id=<?= intval($run['asset_id']) ?>"><?= nullable_htmlentities($run['asset_name']) ?></a>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-muted" style="font-size:10px"><?= nullable_htmlentities($run['user_firstname'] . ' ' . $run['user_lastname']) ?></div>
                        <a href="/agent/rmm_script_run.php?run_id=<?= intval($run['id']) ?>" class="btn btn-xs btn-outline-secondary mt-1">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
            </div>
        </div>

    </div><!-- /right col -->
</div>

<script>
const CSRF = '<?= $_SESSION['csrf_token'] ?>';

function quickSync() {
    const bar = document.getElementById('syncBar');
    const txt = document.getElementById('syncBarText');
    bar.classList.remove('d-none');
    bar.className = bar.className.replace(/alert-\w+/g, 'alert') + ' alert-info';
    txt.textContent = 'Syncing assets from Tactical RMM...';
    fetch('/agent/post/rmm_sync.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=' + CSRF + '&action=sync&integration_id=<?= intval($default_intg) ?>'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            txt.textContent = `Sync complete: ${d.created} created, ${d.updated} updated, ${d.matched} matched.`;
            bar.classList.replace('alert-info', 'alert-success');
            setTimeout(() => location.reload(), 2000);
        } else {
            txt.textContent = 'Sync failed: ' + (d.error || 'Unknown');
            bar.classList.replace('alert-info', 'alert-danger');
        }
    })
    .catch(() => {
        txt.textContent = 'Network error during sync.';
        bar.classList.replace('alert-info', 'alert-danger');
    });
}

function dashAlertAck(id, btn) {
    btn.disabled = true;
    fetch('/agent/post/rmm_alert.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=' + CSRF + '&action=acknowledge&alert_id=' + id
    }).then(r => r.json()).then(d => {
        if (d.success) {
            const row = document.getElementById('dash-alert-' + id);
            if (row) row.style.opacity = '0.35';
            btn.innerHTML = '<i class="fas fa-check"></i>';
        } else { alert(d.error || 'Failed'); btn.disabled = false; }
    });
}

function dashAlertTicket(id, btn) {
    if (!confirm('Create a support ticket from this alert?')) return;
    btn.disabled = true;
    fetch('/agent/post/rmm_alert.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=' + CSRF + '&action=create_ticket&alert_id=' + id
    }).then(r => r.json()).then(d => {
        if (d.success && d.redirect) { window.location.href = d.redirect; }
        else { alert(d.error || 'Failed'); btn.disabled = false; }
    });
}

function ackAllNew(btn) {
    if (!confirm('Acknowledge all new alerts?')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Acking...';
    document.querySelectorAll('[id^="dash-alert-"]').forEach(row => {
        const id = row.id.replace('dash-alert-', '');
        fetch('/agent/post/rmm_alert.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'csrf_token=' + CSRF + '&action=acknowledge&alert_id=' + id
        }).then(r => r.json()).then(d => {
            if (d.success) row.style.opacity = '0.3';
        });
    });
    setTimeout(() => { btn.innerHTML = '<i class="fas fa-check-double mr-1"></i>Done'; }, 1500);
}
</script>

<?php require_once "../includes/footer.php"; ?>
