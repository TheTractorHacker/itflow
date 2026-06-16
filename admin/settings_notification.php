<?php

require_once "includes/inc_all_admin.php";

?>

<style>
.notif-section {
    border: 1px solid var(--color-border, #dee2e6);
    border-radius: .5rem;
    margin-bottom: 1.25rem;
    background: var(--color-surface, #fff);
    overflow: hidden;
}
.notif-section-header {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .75rem 1.25rem;
    background: rgba(0,0,0,.03);
    border-bottom: 1px solid var(--color-border, #dee2e6);
    font-weight: 600;
    font-size: .95rem;
}
.notif-section-header .section-icon {
    width: 1.9rem;
    height: 1.9rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: .85rem;
}
.notif-section-body {
    padding: 1rem 1.25rem;
}
.notif-row {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: .65rem 0;
    border-bottom: 1px solid var(--color-border, #eee);
}
.notif-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.notif-row-meta {
    flex: 1;
}
.notif-row-meta .notif-label {
    font-weight: 500;
    font-size: .9rem;
    margin-bottom: .15rem;
}
.notif-row-meta .notif-desc {
    font-size: .8rem;
    color: var(--color-text-muted, #6c757d);
}
.notif-row-control {
    flex-shrink: 0;
    padding-top: .15rem;
}
.notif-email-input {
    max-width: 340px;
    margin-top: .5rem;
}
</style>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-bell mr-2"></i>Notification Settings</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <!-- Cron / Scheduler -->
            <div class="notif-section">
                <div class="notif-section-header">
                    <span class="section-icon bg-secondary text-white"><i class="fas fa-clock"></i></span>
                    Scheduler
                </div>
                <div class="notif-section-body">
                    <div class="notif-row">
                        <div class="notif-row-meta">
                            <div class="notif-label">Enable Cron Job</div>
                            <div class="notif-desc">Required for email reminders, expiration alerts, and other scheduled tasks. Several cron entries must also be configured on your server — <a href="https://docs.itflow.org/cron" target="_blank">see docs</a>.</div>
                        </div>
                        <div class="notif-row-control">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" name="config_enable_cron" <?php if ($config_enable_cron) echo "checked"; ?> value="1" id="enableCronSwitch">
                                <label class="custom-control-label" for="enableCronSwitch"></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ticket Notifications -->
            <div class="notif-section">
                <div class="notif-section-header">
                    <span class="section-icon bg-primary text-white"><i class="fas fa-ticket-alt"></i></span>
                    Ticket Notifications
                </div>
                <div class="notif-section-body">
                    <div class="notif-row">
                        <div class="notif-row-meta">
                            <div class="notif-label">New Ticket Alert Email</div>
                            <div class="notif-desc">Send an email whenever a new ticket is created. Leave blank to disable.</div>
                            <div class="notif-email-input">
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
                                    <input type="email" class="form-control" name="config_ticket_new_ticket_notification_email"
                                           value="<?= nullable_htmlentities($config_ticket_new_ticket_notification_email) ?>"
                                           placeholder="alerts@example.com">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="notif-row">
                        <div class="notif-row-meta">
                            <div class="notif-label">Client Portal Notifications</div>
                            <div class="notif-desc">Email clients automatically when their tickets are opened or closed.</div>
                        </div>
                        <div class="notif-row-control">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" name="config_ticket_client_general_notifications"
                                       <?php if ($config_ticket_client_general_notifications) echo "checked"; ?> value="1" id="clientNotifSwitch">
                                <label class="custom-control-label" for="clientNotifSwitch"></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice Notifications -->
            <div class="notif-section">
                <div class="notif-section-header">
                    <span class="section-icon bg-success text-white"><i class="fas fa-file-invoice-dollar"></i></span>
                    Invoice Notifications
                </div>
                <div class="notif-section-body">
                    <div class="notif-row">
                        <div class="notif-row-meta">
                            <div class="notif-label">Client Overdue Reminders</div>
                            <div class="notif-desc">Automatically email clients about overdue invoices every 30 days.</div>
                        </div>
                        <div class="notif-row-control">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" name="config_send_invoice_reminders"
                                       <?php if ($config_send_invoice_reminders) echo "checked"; ?> value="1" id="sendRemindersSwitch">
                                <label class="custom-control-label" for="sendRemindersSwitch"></label>
                            </div>
                        </div>
                    </div>
                    <div class="notif-row">
                        <div class="notif-row-meta">
                            <div class="notif-label">Internal Overdue Alerts</div>
                            <div class="notif-desc">Notify staff when invoices become overdue.</div>
                        </div>
                        <div class="notif-row-control">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" name="config_invoice_overdue_reminders"
                                       <?php if (!empty($config_invoice_overdue_reminders)) echo "checked"; ?> value="1" id="overdueSwitch">
                                <label class="custom-control-label" for="overdueSwitch"></label>
                            </div>
                        </div>
                    </div>
                    <div class="notif-row">
                        <div class="notif-row-meta">
                            <div class="notif-label">Invoice Paid Notification</div>
                            <div class="notif-desc">Send an email when a client pays an invoice. Leave blank to disable.</div>
                            <div class="notif-email-input">
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
                                    <input type="email" class="form-control" name="config_invoice_paid_notification_email"
                                           value="<?= nullable_htmlentities($config_invoice_paid_notification_email) ?>"
                                           placeholder="billing@example.com">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="notif-row">
                        <div class="notif-row-meta">
                            <div class="notif-label">Recurring Invoice Emails</div>
                            <div class="notif-desc">Email clients when their recurring invoices are automatically generated.</div>
                        </div>
                        <div class="notif-row-control">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" name="config_recurring_auto_send_invoice"
                                       <?php if ($config_recurring_auto_send_invoice) echo "checked"; ?> value="1" id="sendRecurringSwitch">
                                <label class="custom-control-label" for="sendRecurringSwitch"></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quote Notifications -->
            <div class="notif-section">
                <div class="notif-section-header">
                    <span class="section-icon bg-info text-white"><i class="fas fa-file-contract"></i></span>
                    Quote Notifications
                </div>
                <div class="notif-section-body">
                    <div class="notif-row">
                        <div class="notif-row-meta">
                            <div class="notif-label">Quote Accepted Notification</div>
                            <div class="notif-desc">Send an email when a client accepts a quote. Leave blank to disable.</div>
                            <div class="notif-email-input">
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
                                    <input type="email" class="form-control" name="config_quote_notification_email"
                                           value="<?= nullable_htmlentities($config_quote_notification_email) ?>"
                                           placeholder="sales@example.com">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expiration Alerts -->
            <div class="notif-section">
                <div class="notif-section-header">
                    <span class="section-icon bg-warning text-white"><i class="fas fa-globe"></i></span>
                    Expiration Alerts
                </div>
                <div class="notif-section-body">
                    <div class="notif-row">
                        <div class="notif-row-meta">
                            <div class="notif-label">Domain &amp; Certificate Expiry</div>
                            <div class="notif-desc">Show in-app alerts at 1, 7, and 45 days before domain and SSL certificate expiration.</div>
                        </div>
                        <div class="notif-row-control">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" name="config_enable_alert_domain_expire"
                                       <?php if ($config_enable_alert_domain_expire) echo "checked"; ?> value="1" id="domainExpireSwitch">
                                <label class="custom-control-label" for="domainExpireSwitch"></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" name="edit_notification_settings" class="btn btn-primary">
                <i class="fa fa-check mr-2"></i>Save Settings
            </button>

        </form>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
