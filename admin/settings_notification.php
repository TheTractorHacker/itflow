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
        $firebase_project_id   = null;
        $firebase_client_email = null;
        if ($firebase_configured) {
            $sa_raw = @file_get_contents(__DIR__ . '/../config/firebase_service_account.json');
            if ($sa_raw) {
                $sa = json_decode($sa_raw, true) ?: [];
                $firebase_project_id   = $sa['project_id']   ?? null;
                $firebase_client_email = $sa['client_email']  ?? null;
            }
        }
        $sql_push_total = mysqli_query($mysqli, "SELECT COUNT(*) AS cnt FROM api_tokens WHERE token_fcm_token IS NOT NULL AND token_fcm_token != ''");
        $push_total_devices = intval(mysqli_fetch_assoc($sql_push_total)['cnt']);
        $sql_push_users = mysqli_query($mysqli, "SELECT COUNT(DISTINCT token_user_id) AS cnt FROM api_tokens WHERE token_fcm_token IS NOT NULL AND token_fcm_token != ''");
        $push_user_count = intval(mysqli_fetch_assoc($sql_push_users)['cnt']);
        $sql_push_breakdown = mysqli_query($mysqli, "
            SELECT u.user_name, COUNT(t.token_id) AS device_count
            FROM api_tokens t
            JOIN users u ON u.user_id = t.token_user_id
            WHERE t.token_fcm_token IS NOT NULL AND t.token_fcm_token != ''
            GROUP BY t.token_user_id
            ORDER BY u.user_name ASC
        ");
        ?>

        <!-- Mobile App Push Notifications -->
        <div class="notif-section mt-3">
            <div class="notif-section-header">
                <span class="section-icon text-white" style="background:#6f42c1;"><i class="fas fa-mobile-alt"></i></span>
                Mobile App Push Notifications
            </div>
            <div class="notif-section-body">

            <?php if ($firebase_configured) { ?>

                <!-- ── CONFIGURED STATE ── -->

                <!-- Status bar -->
                <div class="d-flex align-items-center justify-content-between flex-wrap mb-3 p-3 rounded"
                     style="background:rgba(40,167,69,.08);border:1px solid rgba(40,167,69,.25);gap:.75rem;">
                    <div>
                        <div class="font-weight-semibold text-success mb-1">
                            <i class="fas fa-check-circle mr-1"></i>Firebase Connected
                        </div>
                        <?php if ($firebase_project_id) { ?>
                        <div class="small text-muted">
                            <i class="fas fa-cube mr-1"></i>Project: <code><?= nullable_htmlentities($firebase_project_id) ?></code>
                        </div>
                        <?php } ?>
                        <?php if ($firebase_client_email) { ?>
                        <div class="small text-muted mt-1" style="word-break:break-all;">
                            <i class="fas fa-key mr-1"></i><?= nullable_htmlentities($firebase_client_email) ?>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="d-flex" style="gap:.4rem;flex-shrink:0;">
                        <?php if ($push_total_devices > 0) { ?>
                        <form method="post" action="post.php">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" name="test_push_notification" class="btn btn-sm btn-primary">
                                <i class="fas fa-paper-plane mr-1"></i>Send Test Push
                            </button>
                        </form>
                        <?php } ?>
                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                data-toggle="collapse" data-target="#firebaseJsonEditor">
                            <i class="fas fa-edit mr-1"></i>Update Key
                        </button>
                        <form method="post" action="post.php">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" name="remove_firebase_config" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Remove Firebase configuration? Push notifications will stop working.')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Update key collapse -->
                <div class="collapse mb-3" id="firebaseJsonEditor">
                    <div class="p-3 rounded" style="background:rgba(0,0,0,.03);border:1px solid var(--color-border,#dee2e6);">
                        <div class="small font-weight-bold mb-2"><i class="fas fa-file-code mr-1"></i>Paste new service account JSON</div>
                        <form method="post" action="post.php" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <textarea name="firebase_service_account_json" rows="8"
                                class="form-control form-control-sm mb-2"
                                style="font-family:monospace;font-size:.75rem;resize:vertical;"
                                placeholder='{ "type": "service_account", "project_id": "…", … }'></textarea>
                            <button type="submit" name="save_firebase_config" class="btn btn-sm btn-primary">
                                <i class="fas fa-save mr-1"></i>Update Configuration
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Device stats -->
                <div class="notif-row">
                    <div class="notif-row-meta">
                        <div class="notif-label">Registered Devices</div>
                        <div class="notif-desc">Staff members with the ITFlow mobile app logged in.</div>
                    </div>
                    <div class="notif-row-control">
                        <?php if ($push_total_devices > 0) { ?>
                        <div class="d-flex" style="gap:1.25rem;">
                            <div class="text-center">
                                <div style="font-size:1.5rem;font-weight:700;line-height:1;color:#6f42c1;"><?= $push_total_devices ?></div>
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;">Device<?= $push_total_devices !== 1 ? 's' : '' ?></div>
                            </div>
                            <div class="text-center">
                                <div style="font-size:1.5rem;font-weight:700;line-height:1;color:#6f42c1;"><?= $push_user_count ?></div>
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;">User<?= $push_user_count !== 1 ? 's' : '' ?></div>
                            </div>
                        </div>
                        <?php } else { ?>
                        <span class="text-muted small"><i class="fas fa-minus mr-1"></i>None yet</span>
                        <?php } ?>
                    </div>
                </div>

                <?php if ($push_total_devices > 0) { ?>
                <!-- Per-user breakdown -->
                <div class="mt-2 pt-2" style="border-top:1px solid var(--color-border,#eee);">
                    <div class="small text-muted font-weight-bold mb-2 text-uppercase" style="letter-spacing:.05em;font-size:.7rem;">
                        <i class="fas fa-users mr-1"></i>Staff with registered devices
                    </div>
                    <div class="d-flex flex-wrap" style="gap:.4rem;">
                        <?php while ($br = mysqli_fetch_assoc($sql_push_breakdown)) { ?>
                        <span class="badge badge-light border" style="font-size:.78rem;padding:.35em .65em;">
                            <i class="fas fa-user mr-1 text-muted" style="font-size:.7rem;"></i><?= nullable_htmlentities($br['user_name']) ?>
                            <span class="badge badge-secondary ml-1" style="font-size:.65rem;"><?= intval($br['device_count']) ?></span>
                        </span>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>

            <?php } else { ?>

                <!-- ── NOT CONFIGURED STATE ── -->

                <!-- Warning callout -->
                <div class="d-flex align-items-start p-3 rounded mb-3"
                     style="background:rgba(255,193,7,.08);border:1px solid rgba(255,193,7,.35);gap:.75rem;">
                    <i class="fas fa-exclamation-triangle text-warning mt-1" style="font-size:1.1rem;flex-shrink:0;"></i>
                    <div>
                        <div class="font-weight-semibold mb-1">Push notifications are not configured</div>
                        <div class="small text-muted">
                            Connect a Firebase project to send real-time push notifications to staff
                            on the ITFlow mobile app. Firebase Cloud Messaging (FCM) is free with no per-message cost.
                        </div>
                    </div>
                </div>

                <!-- Inline setup guide -->
                <div class="mb-3">
                    <div class="small font-weight-bold text-uppercase text-muted mb-2" style="letter-spacing:.05em;font-size:.7rem;">
                        <i class="fas fa-list-ol mr-1"></i>Setup Steps
                    </div>
                    <div class="push-steps">
                        <div class="push-step">
                            <div class="push-step-num">1</div>
                            <div class="push-step-body">
                                Go to the <a href="https://console.firebase.google.com/" target="_blank">Firebase Console</a>
                                and sign in with a Google account.
                            </div>
                        </div>
                        <div class="push-step">
                            <div class="push-step-num">2</div>
                            <div class="push-step-body">
                                Click <strong>Add project</strong>, give it a name (e.g. <em>ITFlow</em>), and complete the wizard.
                                Google Analytics is not required — you can disable it.
                            </div>
                        </div>
                        <div class="push-step">
                            <div class="push-step-num">3</div>
                            <div class="push-step-body">
                                Inside your project, click the <strong><i class="fab fa-android"></i> Android</strong> icon to register an app.
                                Set the Android package name to <code>com.foleyit.itflow</code> and skip the
                                <code>google-services.json</code> and SHA steps.
                            </div>
                        </div>
                        <div class="push-step">
                            <div class="push-step-num">4</div>
                            <div class="push-step-body">
                                Open <strong>Project Settings</strong> (gear icon in the left sidebar) and click the
                                <strong>Service accounts</strong> tab.
                            </div>
                        </div>
                        <div class="push-step">
                            <div class="push-step-num">5</div>
                            <div class="push-step-body">
                                Click <strong>Generate new private key</strong> and confirm. A <code>.json</code> file
                                will download — open it, copy all the text, and paste it below.
                            </div>
                        </div>
                        <div class="push-step" style="border-bottom:none;">
                            <div class="push-step-num">6</div>
                            <div class="push-step-body">
                                Staff log into the <strong>ITFlow mobile app</strong> and their devices register automatically.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- JSON paste form -->
                <div class="p-3 rounded" style="background:rgba(0,0,0,.03);border:1px solid var(--color-border,#dee2e6);">
                    <div class="small font-weight-bold mb-2"><i class="fas fa-file-code mr-1"></i>Paste service account JSON</div>
                    <form method="post" action="post.php" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <textarea name="firebase_service_account_json" rows="9"
                            class="form-control form-control-sm mb-2"
                            style="font-family:monospace;font-size:.75rem;resize:vertical;"
                            placeholder='{ "type": "service_account", "project_id": "…", … }'></textarea>
                        <button type="submit" name="save_firebase_config" class="btn btn-sm btn-primary">
                            <i class="fas fa-save mr-1"></i>Save Configuration
                        </button>
                    </form>
                </div>

            <?php } ?>

            </div>
        </div>

        <style>
        .push-steps { border:1px solid var(--color-border,#dee2e6); border-radius:.375rem; overflow:hidden; }
        .push-step { display:flex; align-items:flex-start; gap:.75rem; padding:.65rem .9rem; border-bottom:1px solid var(--color-border,#eee); }
        .push-step-num {
            flex-shrink:0; width:1.5rem; height:1.5rem; border-radius:50%;
            background:#6f42c1; color:#fff; font-size:.7rem; font-weight:700;
            display:flex; align-items:center; justify-content:center; margin-top:.05rem;
        }
        .push-step-body { font-size:.85rem; line-height:1.55; }
        .font-weight-semibold { font-weight:600; }
        </style>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
