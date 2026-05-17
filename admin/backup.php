<?php
require_once "includes/inc_all_admin.php";

$backup_dir  = $_SERVER['DOCUMENT_ROOT'] . '/backups';
$backup_glob = glob($backup_dir . '/itflow_*.zip') ?: [];
usort($backup_glob, fn($a,$b) => filemtime($b) - filemtime($a));

$last_auto   = null;
$last_manual = null;
foreach ($backup_glob as $f) {
    $base = basename($f);
    if ($last_auto   === null && str_contains($base, '_auto'))   $last_auto   = $f;
    if ($last_manual === null && str_contains($base, '_manual')) $last_manual = $f;
    if ($last_auto && $last_manual) break;
}
?>

<!-- Status row -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-primary"><i class="fas fa-database"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Last Manual Backup</span>
                <span class="info-box-number">
                    <?= $last_manual ? timeAgo(date('Y-m-d H:i:s', filemtime($last_manual))) : '<span class="text-muted small">Never</span>' ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-success"><i class="fas fa-clock"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Last Auto Backup</span>
                <span class="info-box-number">
                    <?= $last_auto ? timeAgo(date('Y-m-d H:i:s', filemtime($last_auto))) : '<span class="text-muted small">Never</span>' ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-info"><i class="fas fa-archive"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Stored Backups</span>
                <span class="info-box-number"><?= count($backup_glob) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Manual backup -->
<div class="card card-dark mb-3">
    <div class="card-header py-2 d-flex align-items-center">
        <h3 class="card-title mr-auto"><i class="fas fa-fw fa-download mr-2"></i>Manual Backup</h3>
        <div>
            <a class="btn btn-primary btn-sm" href="post.php?backup_download_fresh=1&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                <i class="fas fa-download mr-1"></i>Download Now
            </a>
            <a class="btn btn-secondary btn-sm ml-1" href="post.php?backup_save=1&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                <i class="fas fa-save mr-1"></i>Save to Server
            </a>
        </div>
    </div>
    <div class="card-body py-2">
        <p class="text-muted mb-0 small">
            <i class="fas fa-info-circle mr-1"></i>
            <strong>Download Now</strong> streams a fresh ZIP directly to your browser.
            <strong>Save to Server</strong> writes it to <code>backups/</code> and adds it to the history below.
            There is no built-in restore — see the <a href="https://docs.itflow.org/backups" target="_blank">restore docs</a>.
        </p>
    </div>
</div>

<!-- Auto-backup settings -->
<div class="card card-dark mb-3">
    <div class="card-header py-2">
        <h3 class="card-title"><i class="fas fa-fw fa-calendar-alt mr-2"></i>Scheduled Backups</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="row align-items-end">
                <div class="col-auto">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="backup_auto"
                               name="config_backup_auto_enabled" value="1"
                               <?= $config_backup_auto_enabled ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="backup_auto">Enable auto-backup via cron</label>
                    </div>
                </div>
                <div class="col-auto">
                    <label class="mb-0 mr-2">Frequency</label>
                    <select class="form-control form-control-sm d-inline-block w-auto" name="config_backup_frequency">
                        <option value="daily"  <?= $config_backup_frequency === 'daily'  ? 'selected' : '' ?>>Daily</option>
                        <option value="weekly" <?= $config_backup_frequency === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="mb-0 mr-2">Keep last</label>
                    <input type="number" class="form-control form-control-sm d-inline-block w-auto"
                           name="config_backup_retain_count" min="1" max="90"
                           value="<?= intval($config_backup_retain_count) ?>" style="width:70px!important;">
                    <span class="text-muted small ml-1">backups</span>
                </div>
                <div class="col-auto">
                    <button type="submit" name="save_backup_settings" class="btn btn-primary btn-sm">
                        <i class="fas fa-check mr-1"></i>Save
                    </button>
                </div>
            </div>
        </form>
        <?php if ($config_backup_auto_enabled): ?>
        <p class="text-success small mt-2 mb-0"><i class="fas fa-check-circle mr-1"></i>Auto-backup is active. Runs during nightly cron (requires cron to be enabled).</p>
        <?php else: ?>
        <p class="text-muted small mt-2 mb-0"><i class="fas fa-circle mr-1"></i>Auto-backup is disabled.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Backup history -->
<div class="card card-dark mb-3">
    <div class="card-header py-2">
        <h3 class="card-title"><i class="fas fa-fw fa-history mr-2"></i>Backup History</h3>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-borderless table-hover mb-0">
            <thead class="text-muted small border-bottom">
                <tr>
                    <th class="pl-3">File</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($backup_glob)): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No backups stored on server yet.</td></tr>
            <?php else: foreach ($backup_glob as $bfile):
                $bbase = basename($bfile);
                $bsize = filesize($bfile);
                $bdate = date('Y-m-d H:i:s', filemtime($bfile));
                $btype = str_contains($bbase, '_auto') ? 'Auto' : 'Manual';
                $badge = $btype === 'Auto' ? 'badge-success' : 'badge-primary';
                $bmb   = round($bsize / 1048576, 1);
            ?>
                <tr>
                    <td class="pl-3"><code class="small"><?= htmlspecialchars($bbase) ?></code></td>
                    <td><span class="badge <?= $badge ?>"><?= $btype ?></span></td>
                    <td class="text-muted small"><?= $bmb ?> MB</td>
                    <td class="text-muted small"><?= $bdate ?></td>
                    <td class="pr-3 text-right text-nowrap">
                        <a href="post.php?backup_serve=<?= urlencode($bbase) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-download"></i>
                        </a>
                        <a href="post.php?backup_delete=<?= urlencode($bbase) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                           class="btn btn-sm btn-outline-danger ml-1"
                           onclick="return confirm('Delete this backup?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Master key -->
<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title"><i class="fas fa-fw fa-key mr-2"></i>Backup Master Encryption Key</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small">The master encryption key is needed to decrypt credential data in a restored backup. Store it securely offline.</p>
        <form action="post.php" method="POST" class="form-inline">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="input-group" style="max-width:360px;">
                <input type="password" class="form-control" placeholder="Enter your account password" name="password" autocomplete="new-password" required>
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit" name="backup_master_key">
                        <i class="fas fa-key mr-1"></i>Reveal Key
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
