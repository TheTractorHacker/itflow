<?php

require_once "includes/inc_all_admin.php";

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-bell mr-2"></i>Notification Settings</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <!-- Cron -->
            <div class="form-group">
                <label class="d-block font-weight-bold mb-1">Cron Job</label>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="config_enable_cron" <?php if ($config_enable_cron) echo "checked"; ?> value="1" id="enableCronSwitch">
                    <label class="custom-control-label" for="enableCronSwitch">Enable Cron
                        <small class="text-muted ml-1">(Several cron scripts must also be added to cron with correct schedules — <a href="https://docs.itflow.org/cron" target="_blank">docs</a>)</small>
                    </label>
                </div>
            </div>

            <hr>

            <!-- New Ticket Notification -->
            <div class="form-group">
                <label class="font-weight-bold"><i class="fas fa-fw fa-ticket-alt text-primary mr-1"></i>New Ticket Email Alert</label>
                <small class="d-block text-muted mb-2">Send an email to the address below when a new ticket is created. Leave blank to disable.</small>
                <div class="input-group" style="max-width:380px;">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
                    <input type="email" class="form-control" name="config_ticket_new_ticket_notification_email"
                           value="<?= nullable_htmlentities($config_ticket_new_ticket_notification_email) ?>"
                           placeholder="alerts@example.com">
                </div>
            </div>

            <!-- Client general notifications -->
            <div class="form-group">
                <label class="font-weight-bold"><i class="fas fa-fw fa-users text-info mr-1"></i>Client Portal Notifications</label>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="config_ticket_client_general_notifications"
                           <?php if ($config_ticket_client_general_notifications) echo "checked"; ?> value="1" id="clientNotifSwitch">
                    <label class="custom-control-label" for="clientNotifSwitch">Email clients when tickets are opened or closed</label>
                </div>
            </div>

            <hr>

            <!-- Invoice reminders -->
            <div class="form-group">
                <label class="font-weight-bold"><i class="fas fa-fw fa-file-invoice-dollar text-success mr-1"></i>Invoice Reminders</label>
                <div class="custom-control custom-switch mb-2">
                    <input type="checkbox" class="custom-control-input" name="config_send_invoice_reminders"
                           <?php if ($config_send_invoice_reminders) echo "checked"; ?> value="1" id="sendRemindersSwitch">
                    <label class="custom-control-label" for="sendRemindersSwitch">Send automatic overdue invoice reminders to clients (every 30 days)</label>
                </div>
                <div class="custom-control custom-switch mb-2">
                    <input type="checkbox" class="custom-control-input" name="config_invoice_overdue_reminders"
                           <?php if (!empty($config_invoice_overdue_reminders)) echo "checked"; ?> value="1" id="overdueSwitch">
                    <label class="custom-control-label" for="overdueSwitch">Send internal overdue invoice alerts to staff</label>
                </div>
            </div>

            <!-- Invoice paid notification -->
            <div class="form-group">
                <label class="font-weight-bold"><i class="fas fa-fw fa-dollar-sign text-success mr-1"></i>Invoice Paid Notification</label>
                <small class="d-block text-muted mb-2">Notify this email address when a client pays an invoice. Leave blank to disable.</small>
                <div class="input-group" style="max-width:380px;">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
                    <input type="email" class="form-control" name="config_invoice_paid_notification_email"
                           value="<?= nullable_htmlentities($config_invoice_paid_notification_email) ?>"
                           placeholder="billing@example.com">
                </div>
            </div>

            <!-- Recurring invoice auto-send -->
            <div class="form-group">
                <label class="font-weight-bold"><i class="fas fa-fw fa-redo-alt text-warning mr-1"></i>Recurring Invoices</label>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="config_recurring_auto_send_invoice"
                           <?php if ($config_recurring_auto_send_invoice) echo "checked"; ?> value="1" id="sendRecurringSwitch">
                    <label class="custom-control-label" for="sendRecurringSwitch">Email clients when recurring invoices are generated</label>
                </div>
            </div>

            <!-- Quote accepted -->
            <div class="form-group">
                <label class="font-weight-bold"><i class="fas fa-fw fa-file-contract text-info mr-1"></i>Quote Accepted Notification</label>
                <small class="d-block text-muted mb-2">Notify this email address when a client accepts a quote. Leave blank to disable.</small>
                <div class="input-group" style="max-width:380px;">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
                    <input type="email" class="form-control" name="config_quote_notification_email"
                           value="<?= nullable_htmlentities($config_quote_notification_email) ?>"
                           placeholder="sales@example.com">
                </div>
            </div>

            <hr>

            <!-- Domain / cert / asset expiry -->
            <div class="form-group">
                <label class="font-weight-bold"><i class="fas fa-fw fa-globe text-warning mr-1"></i>Expiration Alerts</label>
                <small class="d-block text-muted mb-2">Send in-app alerts at 1, 7, and 45 days before expiry.</small>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="config_enable_alert_domain_expire"
                           <?php if ($config_enable_alert_domain_expire) echo "checked"; ?> value="1" id="domainExpireSwitch">
                    <label class="custom-control-label" for="domainExpireSwitch">Domain and certificate expiration alerts</label>
                </div>
            </div>

            <hr>

            <button type="submit" name="edit_notification_settings" class="btn btn-primary">
                <i class="fa fa-check mr-2"></i>Save Settings
            </button>

        </form>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
