<?php

if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_url = '';
    $client_id = 0;
}

enforceUserPermission('module_support');

$outtake_id = intval($_GET['outtake_id']);
$ticket_id  = intval($_GET['ticket_id']);

$sql = mysqli_query($mysqli, "SELECT ot.*, t.ticket_prefix, t.ticket_number, t.ticket_subject, t.ticket_details, t.ticket_created_at, c.client_name, co.contact_name
    FROM ticket_outtake_forms ot
    JOIN tickets t ON ot.outtake_ticket_id = t.ticket_id
    LEFT JOIN clients c ON t.ticket_client_id = c.client_id
    LEFT JOIN contacts co ON t.ticket_contact_id = co.contact_id
    WHERE ot.outtake_id = $outtake_id LIMIT 1");

if (mysqli_num_rows($sql) == 0) {
    echo "<div class='alert alert-danger m-3'>Outtake form not found.</div>";
    require_once "../includes/footer.php";
    exit;
}

$row        = mysqli_fetch_assoc($sql);
$ticket_num = nullable_htmlentities($row['ticket_prefix']) . intval($row['ticket_number']);
$subject    = nullable_htmlentities($row['ticket_subject']);
$client_nm  = nullable_htmlentities($row['client_name']);
$contact_nm = nullable_htmlentities($row['contact_name']);
$tech_notes = nullable_htmlentities($row['outtake_tech_notes']);
$ot_token   = nullable_htmlentities($row['outtake_sign_token']);
$signed_at  = $row['outtake_signed_at'];
$signed_nm  = nullable_htmlentities($row['outtake_signed_name']);
$signature  = $row['outtake_signature'];
$ticket_id  = intval($row['outtake_ticket_id']);
$client_id  = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT ticket_client_id FROM tickets WHERE ticket_id = $ticket_id"))[0]);

// Ticket replies for "comments" section
$sql_replies = mysqli_query($mysqli, "SELECT tr.ticket_reply_details, tr.ticket_reply_type, tr.ticket_reply_created_at, u.user_name, co.contact_name
    FROM ticket_replies tr
    LEFT JOIN users u ON tr.ticket_reply_by = u.user_id
    LEFT JOIN contacts co ON tr.ticket_reply_by = co.contact_id
    WHERE tr.ticket_reply_ticket_id = $ticket_id AND tr.ticket_reply_archived_at IS NULL
    ORDER BY tr.ticket_reply_id ASC LIMIT 10");

$sign_url = "https://$config_base_url/guest/outtake_sign.php?token=$ot_token";

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-file-signature mr-2"></i>Outtake Form — <?= $ticket_num ?>: <?= $subject ?></h3>
        <div class="card-tools">
            <a href="ticket.php?ticket_id=<?= $ticket_id ?><?= $client_id ? '&client_id='.$client_id : '' ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i>Back to Ticket
            </a>
        </div>
    </div>
    <div class="card-body">

        <?php if ($signed_at) { ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle mr-2"></i>Signed by <strong><?= $signed_nm ?></strong> on <?= date('M j, Y g:i A', strtotime($signed_at)) ?>
            <?php if ($signature) { ?><br><img src="<?= $signature ?>" style="max-height:80px;border:1px solid #ccc;border-radius:4px;background:#fff;display:block;margin-top:8px;"><?php } ?>
        </div>
        <?php } else { ?>
        <div class="alert alert-warning d-flex align-items-center flex-wrap">
            <div class="flex-grow-1">
                <i class="fas fa-clock mr-2"></i><strong>Awaiting customer signature.</strong>
                <?php if ($ot_token) { ?>
                <div class="mt-1" style="font-size:12px;word-break:break-all;color:#856404;"><?= $sign_url ?></div>
                <?php } ?>
            </div>
            <?php if ($ot_token) { ?>
            <div class="ml-3 mt-2 mt-md-0 d-flex" style="gap:8px;">
                <button type="button" class="btn btn-success mr-2"
                    onclick="window.open('<?= $sign_url ?>', '_blank', 'noopener,noreferrer')" title="Open signing page now for customer to sign in-person">
                    <i class="fas fa-pen-nib mr-1"></i>Sign In-Person
                </button>
                <button type="button" class="btn btn-outline-secondary"
                    onclick="navigator.clipboard.writeText('<?= $sign_url ?>').then(function(){alert('Link copied!');})">
                    <i class="fas fa-copy mr-1"></i>Copy Link
                </button>
            </div>
            <?php } ?>
        </div>
        <?php } ?>

        <div class="row">
            <div class="col-md-8">
                <!-- Tech Notes -->
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="outtake_id" value="<?= $outtake_id ?>">
                    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                    <input type="hidden" name="client_id" value="<?= $client_id ?>">
                    <div class="form-group">
                        <label><strong>Tech Notes / Work Summary</strong> <small class="text-secondary">(visible on the outtake form)</small></label>
                        <textarea class="form-control" name="outtake_tech_notes" rows="5" placeholder="Describe the work completed, parts used, recommendations..."><?= $tech_notes ?></textarea>
                    </div>
                    <button type="submit" name="save_outtake_notes" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Notes</button>
                </form>

                <!-- Ticket Replies Preview -->
                <?php if (mysqli_num_rows($sql_replies) > 0) { ?>
                <div class="mt-4">
                    <strong class="text-secondary d-block mb-2">Ticket Comments (will appear on form)</strong>
                    <?php while ($reply = mysqli_fetch_assoc($sql_replies)) {
                        $by = $reply['ticket_reply_type'] == 'Agent' ? nullable_htmlentities($reply['user_name']) : nullable_htmlentities($reply['contact_name']);
                        $date = date('M j, Y', strtotime($reply['ticket_reply_created_at']));
                    ?>
                    <div class="border rounded p-2 mb-2 bg-light">
                        <small class="text-secondary"><?= $by ?> — <?= $date ?></small>
                        <div class="mt-1" style="font-size:13px;"><?= nullable_htmlentities(substr(strip_tags($reply['ticket_reply_details']), 0, 300)) ?>...</div>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
            <div class="col-md-4">
                <div class="card border">
                    <div class="card-body p-3">
                        <h6 class="border-bottom pb-2">Form Details</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-secondary">Ticket</td><td><?= $ticket_num ?></td></tr>
                            <tr><td class="text-secondary">Client</td><td><?= $client_nm ?></td></tr>
                            <?php if ($contact_nm) { ?><tr><td class="text-secondary">Contact</td><td><?= $contact_nm ?></td></tr><?php } ?>
                            <tr><td class="text-secondary">Subject</td><td><?= $subject ?></td></tr>
                            <tr><td class="text-secondary">Status</td><td><?= $signed_at ? '<span class="badge badge-success">Signed</span>' : '<span class="badge badge-warning text-dark">Unsigned</span>' ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
