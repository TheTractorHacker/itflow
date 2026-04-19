<?php
require_once '../../../includes/modal_header.php';
$ticket_id = intval($_GET['ticket_id']);
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-clipboard-list mr-2"></i>Add Worksheet to Ticket</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
    <div class="modal-body">
        <div class="form-group">
            <label>Select Worksheet Template <strong class="text-danger">*</strong></label>
            <select class="form-control select2" name="worksheet_template_id" required>
                <option value="">- Select Template -</option>
                <?php
                $sql_templates = mysqli_query($mysqli, "SELECT * FROM worksheet_templates WHERE worksheet_template_archived_at IS NULL ORDER BY worksheet_template_name");
                while ($t = mysqli_fetch_assoc($sql_templates)) {
                    $tid = intval($t['worksheet_template_id']);
                    $tname = nullable_htmlentities($t['worksheet_template_name']);
                    $tdesc = nullable_htmlentities($t['worksheet_template_description']);
                    echo "<option value=\"$tid\">$tname" . ($tdesc ? " — $tdesc" : "") . "</option>";
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="isOuttake" name="is_outtake" value="1">
                <label class="custom-control-label" for="isOuttake"><strong>Is Required</strong> <small class="text-secondary">(ticket cannot close until finalized)</small></label>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_ticket_worksheet" class="btn btn-primary"><i class="fa fa-check mr-2"></i>Add Worksheet</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>
<?php require_once '../../../includes/modal_footer.php'; ?>
