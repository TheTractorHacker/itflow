<?php
require_once "includes/inc_all_admin.php";

$sql = mysqli_query($mysqli,
    "SELECT * FROM ticket_automation_rules ORDER BY rule_order ASC, rule_id ASC"
);
?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-robot mr-2"></i>Ticket Automation Rules</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/ticket_automation/add_rule.php">
                <i class="fas fa-plus mr-2"></i>New Rule
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="alert alert-info mx-3 mt-3">
            <i class="fas fa-info-circle mr-2"></i>
            Rules run automatically each time cron executes. Schedule cron at <code>cron/cron.php</code> every 15–60 minutes.
        </div>
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>Rule Name</th>
                    <th>Condition</th>
                    <th>Action</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($sql) === 0): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="fas fa-robot fa-2x mb-2 d-block"></i>
                        No automation rules yet. Click <strong>New Rule</strong> to add one.
                    </td>
                </tr>
            <?php endif; ?>
            <?php while ($rule = mysqli_fetch_assoc($sql)):
                $rule_id    = intval($rule['rule_id']);
                $name       = nullable_htmlentities($rule['rule_name']);
                $enabled    = intval($rule['rule_enabled']);
                $field      = $rule['rule_cond_field'];
                $op         = $rule['rule_cond_op'];
                $val        = nullable_htmlentities($rule['rule_cond_value']);
                $action     = $rule['rule_action'];
                $actionval  = nullable_htmlentities($rule['rule_action_value']);

                $field_labels = [
                    'age_hours'    => 'Ticket age (hours)',
                    'priority'     => 'Priority',
                    'status_id'    => 'Status ID',
                    'assigned_to'  => 'Assigned to (user ID)',
                    'idle_hours'   => 'Hours since last reply',
                ];
                $op_labels = [
                    'equals'       => '=',
                    'not_equals'   => '≠',
                    'greater_than' => '>',
                    'less_than'    => '<',
                    'contains'     => 'contains',
                ];
                $action_labels = [
                    'set_priority'     => 'Set priority',
                    'assign_to'        => 'Assign to user ID',
                    'set_status'       => 'Set status ID',
                    'add_note'         => 'Add internal note',
                    'notify_assignee'  => 'Notify assigned tech',
                    'close_ticket'     => 'Close ticket',
                ];
            ?>
                <tr class="<?php echo $enabled ? '' : 'text-muted'; ?>">
                    <td><?php echo $rule_id; ?></td>
                    <td>
                        <strong><?php echo $name; ?></strong>
                    </td>
                    <td>
                        <span class="badge badge-secondary"><?php echo $field_labels[$field] ?? $field; ?></span>
                        <span><?php echo $op_labels[$op] ?? $op; ?></span>
                        <code><?php echo $val; ?></code>
                    </td>
                    <td>
                        <span class="badge badge-primary"><?php echo $action_labels[$action] ?? $action; ?></span>
                        <?php if ($actionval): ?>
                            <code><?php echo $actionval; ?></code>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($enabled): ?>
                            <span class="badge badge-success">Enabled</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right">
                        <a href="post/ticket_automation.php?toggle=<?php echo $rule_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>"
                           class="btn btn-sm btn-<?php echo $enabled ? 'warning' : 'success'; ?>">
                            <?php echo $enabled ? 'Disable' : 'Enable'; ?>
                        </a>
                        <a href="post/ticket_automation.php?delete=<?php echo $rule_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Delete this rule?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
