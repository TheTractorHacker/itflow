<?php
require_once '../../../includes/modal_header.php';

$sql_cats = mysqli_query($mysqli,
    "SELECT category_id, category_name FROM categories
     WHERE category_type = 'Ticket' AND category_archived_at IS NULL
     ORDER BY category_order ASC, category_name ASC"
);
$sql_wt = mysqli_query($mysqli,
    "SELECT worksheet_template_id, worksheet_template_name FROM worksheet_templates
     WHERE worksheet_template_archived_at IS NULL
     ORDER BY worksheet_template_name ASC"
);
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-robot mr-2"></i>New Automation Rule</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>
<form action="/admin/post.php" method="POST" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="add_rule" value="1">
    <div class="modal-body">

        <div class="form-group">
            <label>Rule Name <strong class="text-danger">*</strong></label>
            <input type="text" name="rule_name" class="form-control" placeholder="e.g. Add on-site worksheet" maxlength="100" required autofocus>
        </div>

        <div class="form-group">
            <label>Condition — When this is true:</label>
            <div class="row">
                <div class="col-5">
                    <select name="rule_cond_field" class="form-control" id="ruleCondField">
                        <option value="age_hours">Ticket age (hours)</option>
                        <option value="idle_hours">Hours since last reply</option>
                        <option value="priority">Priority</option>
                        <option value="status_id">Status ID</option>
                        <option value="assigned_to">Assigned to (user ID)</option>
                        <option value="category">Ticket category</option>
                    </select>
                </div>
                <div class="col-3">
                    <select name="rule_cond_op" class="form-control">
                        <option value="greater_than">&gt; greater than</option>
                        <option value="less_than">&lt; less than</option>
                        <option value="equals">= equals</option>
                        <option value="not_equals">≠ not equals</option>
                    </select>
                </div>
                <div class="col-4">
                    <input type="text" name="rule_cond_value" id="condValueText" class="form-control" placeholder="Value" required>
                    <select name="rule_cond_value" id="condValueCat" class="form-control" style="display:none;" disabled>
                        <?php while ($c = mysqli_fetch_assoc($sql_cats)): ?>
                            <option value="<?= intval($c['category_id']) ?>"><?= nullable_htmlentities($c['category_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <small class="text-muted">
                e.g. <code>age_hours &gt; 4</code> &nbsp;|&nbsp; <code>priority = high</code> &nbsp;|&nbsp; <code>category = On-Site</code>
            </small>
        </div>

        <div class="form-group">
            <label>Action — Do this:</label>
            <select name="rule_action" class="form-control" id="ruleAction">
                <option value="set_priority">Set priority (low / medium / high / critical)</option>
                <option value="assign_to">Assign to user ID</option>
                <option value="set_status">Set status ID</option>
                <option value="add_note">Add internal note</option>
                <option value="notify_assignee">Notify assigned technician</option>
                <option value="close_ticket">Close ticket</option>
                <option value="add_worksheet">Add worksheet from template</option>
            </select>
        </div>

        <div class="form-group">
            <label>Action Value</label>
            <input type="text" name="rule_action_value" id="actionValueText" class="form-control"
                   placeholder="e.g. critical  |  3  |  Ticket is stale — please follow up">
            <select name="rule_action_value" id="actionValueWorksheet" class="form-control" style="display:none;" disabled>
                <?php while ($wt = mysqli_fetch_assoc($sql_wt)): ?>
                    <option value="<?= intval($wt['worksheet_template_id']) ?>"><?= nullable_htmlentities($wt['worksheet_template_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <small class="text-muted" id="actionValueHint">Leave blank for notify / close actions.</small>
        </div>

        <div class="form-group">
            <label>Order <small class="text-muted">(lower runs first)</small></label>
            <input type="number" name="rule_order" class="form-control" value="0" min="0">
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary"><i class="fas fa-check mr-2"></i>Save Rule</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>
<script>
(function () {
    var condField = document.getElementById('ruleCondField');
    var condText  = document.getElementById('condValueText');
    var condCat   = document.getElementById('condValueCat');
    var action    = document.getElementById('ruleAction');
    var actText   = document.getElementById('actionValueText');
    var actSheet  = document.getElementById('actionValueWorksheet');
    var actHint   = document.getElementById('actionValueHint');

    function updateCondValue() {
        var isCat = condField.value === 'category';
        condText.style.display = isCat ? 'none' : '';
        condText.disabled      = isCat;
        condText.required      = !isCat;
        condCat.style.display  = isCat ? '' : 'none';
        condCat.disabled       = !isCat;
    }

    function updateActionValue() {
        var isWS = action.value === 'add_worksheet';
        actText.style.display  = isWS ? 'none' : '';
        actText.disabled       = isWS;
        actSheet.style.display = isWS ? '' : 'none';
        actSheet.disabled      = !isWS;
        actHint.textContent    = isWS
            ? 'Select the worksheet template to attach to matching tickets.'
            : 'Leave blank for notify / close actions.';
    }

    condField.addEventListener('change', updateCondValue);
    action.addEventListener('change', updateActionValue);
})();
</script>
<?php require_once '../../../includes/modal_footer.php'; ?>
