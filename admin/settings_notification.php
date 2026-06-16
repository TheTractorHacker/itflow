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

        <?php
        $firebase_configured = file_exists(__DIR__ . '/../config/firebase_service_account.json');
        $sql_push_total = mysqli_query($mysqli, "SELECT COUNT(*) AS cnt FROM api_tokens WHERE token_fcm_token IS NOT NULL AND token_fcm_token != ''");
        $push_total_devices = intval(mysqli_fetch_assoc($sql_push_total)['cnt']);
        $sql_push_users = mysqli_query($mysqli, "SELECT COUNT(DISTINCT token_user_id) AS cnt FROM api_tokens WHERE token_fcm_token IS NOT NULL AND token_fcm_token != ''");
        $push_user_count = intval(mysqli_fetch_assoc($sql_push_users)['cnt']);
        ?>

        <!-- Mobile App Push Notifications -->
        <div class="notif-section mt-3">
            <div class="notif-section-header">
                <span class="section-icon text-white" style="background:#6f42c1;"><i class="fas fa-mobile-alt"></i></span>
                Mobile App Push Notifications
            </div>
            <div class="notif-section-body">

                <!-- Firebase service account config -->
                <div class="notif-row">
                    <div class="notif-row-meta">
                        <div class="notif-label">Firebase Service Account</div>
                        <div class="notif-desc">
                            Required for push notifications to the ITFlow mobile app.
                            In the <a href="https://console.firebase.google.com/" target="_blank">Firebase Console</a>,
                            go to Project Settings &rarr; Service Accounts &rarr; Generate new private key.
                        </div>

                        <!-- JSON paste form -->
                        <div class="collapse mt-3 <?= !$firebase_configured ? 'show' : '' ?>" id="firebaseJsonEditor">
                            <form method="post" action="post.php" autocomplete="off">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <textarea name="firebase_service_account_json" rows="9"
                                    class="form-control form-control-sm mb-2"
                                    style="font-family:monospace;font-size:.75rem;resize:vertical;"
                                    placeholder='Paste the full service account JSON here…'></textarea>
                                <button type="submit" name="save_firebase_config" class="btn btn-sm btn-primary">
                                    <i class="fas fa-save mr-1"></i><?= $firebase_configured ? 'Update' : 'Save' ?> Configuration
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="notif-row-control pt-1" style="display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;">
                        <?php if ($firebase_configured) { ?>
                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Configured</span>
                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                data-toggle="collapse" data-target="#firebaseJsonEditor">
                            <i class="fas fa-edit mr-1"></i>Update
                        </button>
                        <form method="post" action="post.php">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" name="remove_firebase_config"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Remove Firebase configuration? Push notifications will stop working.')">
                                <i class="fas fa-trash mr-1"></i>Remove
                            </button>
                        </form>
                        <?php } else { ?>
                        <span class="badge badge-warning px-2 py-1"><i class="fas fa-exclamation-triangle mr-1"></i>Not configured</span>
                        <?php } ?>
                    </div>
                </div>

                <!-- Registered device count -->
                <div class="notif-row">
                    <div class="notif-row-meta">
                        <div class="notif-label">Registered Devices</div>
                        <div class="notif-desc">Staff members with the ITFlow mobile app logged in and push notifications enabled.</div>
                    </div>
                    <div class="notif-row-control pt-1">
                        <?php if ($push_total_devices > 0) { ?>
                        <span class="text-success font-weight-bold"><?= $push_total_devices ?></span>
                        <span class="text-muted small">
                            &nbsp;device<?= $push_total_devices !== 1 ? 's' : '' ?>
                            across <?= $push_user_count ?> user<?= $push_user_count !== 1 ? 's' : '' ?>
                        </span>
                        <?php } else { ?>
                        <span class="text-muted"><i class="fas fa-minus mr-1"></i>None registered</span>
                        <?php } ?>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
