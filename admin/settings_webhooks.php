<?php
require_once "includes/inc_all_admin.php";

$ALL_EVENTS = ['ticket.created', 'ticket.replied', 'ticket.assigned', 'ticket.status_changed', 'ticket.resolved'];
?>

<div class="card card-dark">
    <div class="card-header py-3 d-flex align-items-center">
        <h3 class="card-title mr-auto"><i class="fas fa-fw fa-satellite-dish mr-2"></i>Webhooks</h3>
        <button class="btn btn-primary btn-sm ajax-modal" data-modal-url="modals/webhook/webhook_add.php">
            <i class="fas fa-plus mr-1"></i>Add Webhook
        </button>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-borderless table-hover mb-0">
            <thead class="text-dark">
                <tr>
                    <th>Name</th>
                    <th>URL</th>
                    <th>Events</th>
                    <th>Status</th>
                    <th>Recent Deliveries</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $sql_wh = mysqli_query($mysqli, "SELECT * FROM webhooks ORDER BY webhook_id ASC");
            if (mysqli_num_rows($sql_wh) == 0) { ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No webhooks configured yet.</td></tr>
            <?php } else {
                while ($wh = mysqli_fetch_assoc($sql_wh)) {
                    $wid     = intval($wh['webhook_id']);
                    $wname   = nullable_htmlentities($wh['webhook_name']);
                    $wurl    = nullable_htmlentities($wh['webhook_url']);
                    $wenabled = intval($wh['webhook_enabled']);
                    $wevents = array_filter(array_map('trim', explode(',', $wh['webhook_events'])));

                    // Recent delivery stats
                    $r = mysqli_fetch_assoc(mysqli_query($mysqli,
                        "SELECT
                            SUM(queue_status='delivered') AS delivered,
                            SUM(queue_status='failed') AS failed,
                            SUM(queue_status='pending') AS pending
                         FROM webhook_queue WHERE queue_webhook_id = $wid AND queue_created_at > NOW() - INTERVAL 7 DAY"));
                    $delivered = intval($r['delivered']);
                    $failed    = intval($r['failed']);
                    $pending   = intval($r['pending']);
                    ?>
                    <tr>
                        <td><strong><?= $wname ?></strong></td>
                        <td class="text-truncate" style="max-width:220px;" title="<?= $wurl ?>"><?= $wurl ?></td>
                        <td>
                            <?php foreach ($wevents as $ev) {
                                echo '<span class="badge badge-secondary mr-1">' . htmlspecialchars($ev) . '</span>';
                            } ?>
                        </td>
                        <td>
                            <?= $wenabled
                                ? '<span class="badge badge-success">Enabled</span>'
                                : '<span class="badge badge-secondary">Disabled</span>' ?>
                        </td>
                        <td>
                            <span class="badge badge-success" title="Delivered (7d)"><?= $delivered ?></span>
                            <?php if ($pending > 0) { ?><span class="badge badge-warning" title="Pending"><?= $pending ?></span><?php } ?>
                            <?php if ($failed > 0) { ?><span class="badge badge-danger" title="Failed"><?= $failed ?></span><?php } ?>
                        </td>
                        <td class="text-right">
                            <button class="btn btn-sm btn-light ajax-modal"
                                    data-modal-url="modals/webhook/webhook_edit.php?id=<?= $wid ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="post.php?delete_webhook=<?= $wid ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this webhook?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php }
            } ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($sql_wh) && mysqli_num_rows($sql_wh) > 0) { ?>
<div class="card card-dark mt-3">
    <div class="card-header py-2">
        <h3 class="card-title"><i class="fas fa-fw fa-list mr-2"></i>Delivery Log <small class="text-secondary ml-2">(last 100 entries)</small></h3>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped table-borderless mb-0">
            <thead class="text-dark">
                <tr><th>When</th><th>Webhook</th><th>Event</th><th>Status</th><th>HTTP</th><th>Attempts</th></tr>
            </thead>
            <tbody>
            <?php
            $sql_log = mysqli_query($mysqli,
                "SELECT wq.*, w.webhook_name FROM webhook_queue wq
                 JOIN webhooks w ON wq.queue_webhook_id = w.webhook_id
                 ORDER BY wq.queue_id DESC LIMIT 100");
            while ($lrow = mysqli_fetch_assoc($sql_log)) {
                $status_badge = match($lrow['queue_status']) {
                    'delivered' => '<span class="badge badge-success">delivered</span>',
                    'failed'    => '<span class="badge badge-danger">failed</span>',
                    default     => '<span class="badge badge-warning">pending</span>',
                };
                ?>
                <tr>
                    <td class="text-nowrap text-secondary" title="<?= nullable_htmlentities($lrow['queue_created_at']) ?>"><?= timeAgo($lrow['queue_created_at']) ?></td>
                    <td><?= nullable_htmlentities($lrow['webhook_name']) ?></td>
                    <td><code><?= nullable_htmlentities($lrow['queue_event']) ?></code></td>
                    <td><?= $status_badge ?></td>
                    <td><?= $lrow['queue_response_code'] ?: '—' ?></td>
                    <td><?= intval($lrow['queue_attempts']) ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>

<?php require_once "../includes/footer.php"; ?>
