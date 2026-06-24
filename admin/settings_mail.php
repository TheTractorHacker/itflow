<?php
require_once "includes/inc_all_admin.php";
 ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-envelope mr-2"></i>SMTP Mail Settings <small>(For Sending Email)</small></h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <!-- SMTP Provider -->
                <div class="form-group">
                    <label>SMTP Provider</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-cloud"></i></span>
                        </div>
                        <select class="form-control" name="config_smtp_provider" id="config_smtp_provider">
                            <option value="" <?php if(empty($config_smtp_provider)) { echo 'selected'; } ?>>None (Disabled)</option>
                            <option value="standard_smtp" <?php if($config_smtp_provider === 'standard_smtp') { echo 'selected'; } ?>>Standard SMTP (Username/Password)</option>
                            <option value="google_oauth" <?php if($config_smtp_provider === 'google_oauth') { echo 'selected'; } ?>>Google Workspace (OAuth)</option>
                            <option value="microsoft_oauth" <?php if($config_smtp_provider === 'microsoft_oauth') { echo 'selected'; } ?>>Microsoft 365 (OAuth)</option>
                        </select>
                    </div>
                    <small class="text-secondary d-block mt-1" id="smtp_provider_hint">
                        Choose your SMTP provider. OAuth options ignore the SMTP password here.
                    </small>
                </div>


                <!-- Standard SMTP fields (show only for standard_smtp) -->
                <div id="smtp_standard_fields">
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                            </div>
                            <input type="text" class="form-control" name="config_smtp_host" placeholder="Mail Server Address" value="<?php echo nullable_htmlentities($config_smtp_host); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>SMTP Port</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span>
                            </div>
                            <input type="number" min="0" class="form-control" name="config_smtp_port" placeholder="Mail Server Port Number" value="<?php echo intval($config_smtp_port); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Encryption</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            </div>
                            <select class="form-control" name="config_smtp_encryption">
                                <option value=''>None</option>
                                <option <?php if ($config_smtp_encryption == 'tls') { echo "selected"; } ?> value="tls">TLS</option>
                                <option <?php if ($config_smtp_encryption == 'ssl') { echo "selected"; } ?> value="ssl">SSL</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>SMTP Username</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <input type="text" class="form-control" name="config_smtp_username" placeholder="Username (Leave blank if no auth is required)" value="<?php echo nullable_htmlentities($config_smtp_username); ?>">
                        </div>
                    </div>

                    <div class="form-group" id="smtp_password_group">
                        <div class="form-group">
                            <label>SMTP Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                                </div>
                                <input type="password" class="form-control" data-toggle="password" name="config_smtp_password" placeholder="Password (Leave blank if no auth is required)" value="<?php echo nullable_htmlentities($config_smtp_password); ?>" autocomplete="new-password">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <hr>

                <button type="submit" name="edit_mail_smtp_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-envelope mr-2"></i>IMAP Mail Settings <small>(For Monitoring Ticket Inbox)</small></h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label>IMAP Provider</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-cloud"></i></span>
                        </div>
                        <select class="form-control" name="config_imap_provider" id="config_imap_provider">
                            <option value="" <?php if(empty($config_imap_provider)) { echo 'selected'; } ?>>None (Disabled)</option>
                            <option value="standard_imap" <?php if($config_imap_provider === 'standard_imap') { echo 'selected'; } ?>>Standard IMAP (Username/Password)</option>
                            <option value="google_oauth" <?php if($config_imap_provider === 'google_oauth') { echo 'selected'; } ?>>Google Workspace (OAuth)</option>
                            <option value="microsoft_oauth" <?php if($config_imap_provider === 'microsoft_oauth') { echo 'selected'; } ?>>Microsoft 365 (OAuth)</option>
                        </select>
                    </div>
                    <small class="text-secondary d-block mt-1" id="imap_provider_hint">
                    Select your mailbox provider. OAuth options ignore the IMAP password here.
                    </small>
                </div>
                <div id="standard_fields" style="display:none;">
                    <div class="form-group">
                        <label>IMAP Host</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                            </div>
                            <input type="text" class="form-control" name="config_imap_host" placeholder="Incoming Mail Server Address (for email to ticket parsing)" value="<?php echo nullable_htmlentities($config_imap_host); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>IMAP Port</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span>
                            </div>
                            <input type="number" min="0" class="form-control" name="config_imap_port" placeholder="Incoming Mail Server Port Number (993)" value="<?php echo intval($config_imap_port); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>IMAP Encryption</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            </div>
                            <select class="form-control" name="config_imap_encryption">
                                <option value=''>None</option>
                                <option <?php if ($config_imap_encryption == 'tls') { echo "selected"; } ?> value="tls">TLS</option>
                                <option <?php if ($config_imap_encryption == 'ssl') { echo "selected"; } ?> value="ssl">SSL</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class='form-group'>
                    <label>IMAP Username</label>
                    <div class='input-group'>
                        <div class='input-group-prepend'>
                            <span class='input-group-text'><i class='fa fa-fw fa-user'></i></span>
                        </div>
                        <input type='text' class='form-control' name='config_imap_username' placeholder='Username (email address)' value="<?php echo nullable_htmlentities($config_imap_username); ?>" required>
                    </div>
                </div>

                <div class='form-group' id="imap_password_group">
                    <label>IMAP Password</label>
                    <div class='input-group'>
                        <div class='input-group-prepend'>
                            <span class='input-group-text'><i class='fa fa-fw fa-key'></i></span>
                        </div>
                        <input type='password' class='form-control' data-toggle='password' name='config_imap_password' placeholder='Password (not used for OAuth)' value="<?php echo nullable_htmlentities($config_imap_password); ?>" autocomplete='new-password'>
                        <div class='input-group-append'>
                            <span class='input-group-text'><i class='fa fa-fw fa-eye'></i></span>
                        </div>
                    </div>
                </div>

                <!-- OAuth shared fields (show for google_oauth / microsoft_oauth) -->
                <div id="oauth_fields" style="display:none;">
                    <hr>
                    <h5 class="mb-2">OAuth Settings (shared for IMAP & SMTP)</h5>
                    <p class="text-secondary" id="oauth_hint">
                        Configure OAuth credentials for the selected provider.
                    </p>

                    <div class="form-group">
                        <label>OAuth Client ID</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span></div>
                            <input type="text" class="form-control" name="config_mail_oauth_client_id"
                                   value="<?php echo nullable_htmlentities($config_mail_oauth_client_id ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>OAuth Client Secret</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-key"></i></span></div>
                            <input type="password" class="form-control" data-toggle="password" name="config_mail_oauth_client_secret"
                                   value="<?php echo nullable_htmlentities($config_mail_oauth_client_secret ?? ''); ?>" autocomplete="new-password">
                            <div class="input-group-append"><span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span></div>
                        </div>
                    </div>

                    <div class="form-group" id="tenant_row" style="display:none;">
                        <label>Tenant ID (Microsoft 365 only)</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-building"></i></span></div>
                            <input type="text" class="form-control" name="config_mail_oauth_tenant_id"
                                   value="<?php echo nullable_htmlentities($config_mail_oauth_tenant_id ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Refresh Token</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-sync-alt"></i></span></div>
                            <textarea class="form-control" name="config_mail_oauth_refresh_token" rows="2"
                                      placeholder="Paste refresh token"><?php echo nullable_htmlentities($config_mail_oauth_refresh_token ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Access Token (optional – will refresh if expired)</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-shield-alt"></i></span></div>
                            <textarea class="form-control" name="config_mail_oauth_access_token" rows="2"
                                      placeholder="Can be left blank; system refreshes using the refresh token"><?php echo nullable_htmlentities($config_mail_oauth_access_token ?? ''); ?></textarea>
                        </div>
                        <small class="text-secondary">
                            Expires at: <?php echo !empty($config_mail_oauth_access_token_expires_at) ? htmlspecialchars($config_mail_oauth_access_token_expires_at) : 'n/a'; ?>
                        </small>
                    </div>
                </div>

                <?php
                if (defined('BASE_URL') && !empty(BASE_URL)) {
                    $mail_oauth_callback_uri = rtrim((string) BASE_URL, '/') . '/admin/oauth_microsoft_mail_callback.php';
                } else {
                    $mail_oauth_callback_uri = 'https://' . rtrim((string) $config_base_url, '/') . '/admin/oauth_microsoft_mail_callback.php';
                }
                ?>

                <div class="form-group">
                    <label>Microsoft OAuth Connect (Web)</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                        </div>
                        <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars($mail_oauth_callback_uri); ?>">
                        <div class="input-group-append">
                            <button type="submit" name="oauth_connect_microsoft_mail" class="btn btn-outline-primary">
                                <i class="fab fa-fw fa-microsoft mr-2"></i>Connect Microsoft 365
                            </button>
                        </div>
                    </div>
                    <small class="text-secondary">
                        Add this callback URI in Entra App Registration, then click Connect to authorize and store refresh token automatically.
                        <a href="#" data-toggle="modal" data-target="#ms365OAuthGuideModal" class="ml-2"><i class="fas fa-question-circle mr-1"></i>Setup Guide</a>
                    </small>
                </div>

                <hr>

                <button type="submit" name="edit_mail_imap_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-paper-plane mr-2"></i>Mail From Configuration</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <p>Each of the "From Email" Addresses need to be able to send email on behalf of the SMTP user configured above
                <h5>System Default</h5>
                <p class="text-secondary">(used for system tasks such as sending share links)</p>
                <div class="form-group">
                    <label>From Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_mail_from_email" placeholder="Email Address (ex noreply@yourcompany.com)" value="<?php echo nullable_htmlentities($config_mail_from_email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>From Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_mail_from_name" placeholder="Name (ex YourCompany)" value="<?php echo nullable_htmlentities($config_mail_from_name); ?>">
                    </div>
                </div>

                <h5>Invoices</h5>
                <p class="text-secondary">(used for when invoice emails are sent)</p>

                <div class="form-group">
                    <label>From Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_invoice_from_email" placeholder="Email (ex billing@yourcompany.com)" value="<?php echo nullable_htmlentities($config_invoice_from_email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>From Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_invoice_from_name" placeholder="Name (ex CompanyName Billing)" value="<?php echo nullable_htmlentities($config_invoice_from_name); ?>">
                    </div>
                </div>

                <h5>Quotes</h5>
                <p class="text-secondary">(used for when quote emails are sent)</p>

                <div class="form-group">
                    <label>From Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_quote_from_email" placeholder="Email (ex sales@yourcompany.com)" value="<?php echo nullable_htmlentities($config_quote_from_email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>From Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_quote_from_name" placeholder="Name (ex YourCompany Sales)" value="<?php echo nullable_htmlentities($config_quote_from_name); ?>">
                    </div>
                </div>

                <h5>Tickets</h5>
                <p class="text-secondary">(used for when tickets are created and emailed to a client)</p>

                <div class="form-group">
                    <label>From Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_ticket_from_email" placeholder="Email (ex support@yourcompany.com)" value="<?php echo nullable_htmlentities($config_ticket_from_email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>From Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_ticket_from_name" placeholder="Name (ex YourCompany Support)" value="<?php echo nullable_htmlentities($config_ticket_from_name); ?>">
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_mail_from_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

    <?php
    $smtp_standard_ready = !empty($config_smtp_host)
        && !empty($config_smtp_port)
        && !empty($config_mail_from_email)
        && !empty($config_mail_from_name);

    $smtp_oauth_ready = ($config_smtp_provider === 'google_oauth' || $config_smtp_provider === 'microsoft_oauth')
        && !empty($config_mail_from_email)
        && !empty($config_mail_from_name)
        && !empty($config_mail_oauth_client_id)
        && !empty($config_mail_oauth_client_secret)
        && !empty($config_mail_oauth_refresh_token)
        && ($config_smtp_provider !== 'microsoft_oauth' || !empty($config_mail_oauth_tenant_id));
    ?>

    <?php if ($smtp_standard_ready || $smtp_oauth_ready) { ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-paper-plane mr-2"></i>Test Email Sending</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="input-group">
                    <select class="form-control select2" name="test_email" required>
                        <option value="">- Select an Email Address to send from -</option>
                        <?php
                        if ($config_mail_from_email) {
                        ?>
                        <option value="1"><?php echo nullable_htmlentities($config_mail_from_name); ?> (<?php echo nullable_htmlentities($config_mail_from_email); ?>)</option>
                        <?php } ?>

                        <?php
                        if ($config_invoice_from_email) {
                        ?>
                        <option value="2"><?php echo nullable_htmlentities($config_invoice_from_name); ?> (<?php echo nullable_htmlentities($config_invoice_from_email); ?>)</option>
                        <?php } ?>

                        <?php
                        if ($config_quote_from_email) {
                        ?>
                        <option value="3"><?php echo nullable_htmlentities($config_quote_from_name); ?> (<?php echo nullable_htmlentities($config_quote_from_email); ?>)</option>
                        <?php } ?>

                        <?php
                        if ($config_ticket_from_email) {
                        ?>
                        <option value="4"><?php echo nullable_htmlentities($config_ticket_from_name); ?> (<?php echo nullable_htmlentities($config_ticket_from_email); ?>)</option>
                        <?php } ?>


                    </select>
                    <input type="email" class="form-control " name="email_to" placeholder="Email address to send to">
                    <div class="input-group-append">
                        <button type="submit" name="test_email_smtp" class="btn btn-success"><i class="fas fa-fw fa-paper-plane mr-2"></i>Send</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php } ?>

    <?php
    $imap_standard_ready = !empty($config_imap_username)
        && !empty($config_imap_password)
        && !empty($config_imap_host)
        && !empty($config_imap_port);

    $imap_oauth_ready = ($config_imap_provider === 'google_oauth' || $config_imap_provider === 'microsoft_oauth')
        && !empty($config_imap_username)
        && !empty($config_mail_oauth_client_id)
        && !empty($config_mail_oauth_client_secret)
        && !empty($config_mail_oauth_refresh_token)
        && ($config_imap_provider !== 'microsoft_oauth' || !empty($config_mail_oauth_tenant_id));
    ?>

    <?php if ($imap_standard_ready || $imap_oauth_ready) { ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-plug mr-2"></i>Test IMAP Connection</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="input-group-append">
                    <button type="submit" name="test_email_imap" class="btn btn-success"><i class="fas fa-fw fa-inbox mr-2"></i>Test</button>
                </div>
            </form>
        </div>
    </div>

    <?php } ?>

    <?php
    $oauth_provider_for_test = '';
    if ($config_imap_provider === 'google_oauth' || $config_imap_provider === 'microsoft_oauth') {
        $oauth_provider_for_test = $config_imap_provider;
    } elseif ($config_smtp_provider === 'google_oauth' || $config_smtp_provider === 'microsoft_oauth') {
        $oauth_provider_for_test = $config_smtp_provider;
    }

    $oauth_has_required_fields = !empty($oauth_provider_for_test)
        && !empty($config_mail_oauth_client_id)
        && !empty($config_mail_oauth_client_secret)
        && !empty($config_mail_oauth_refresh_token)
        && ($oauth_provider_for_test !== 'microsoft_oauth' || !empty($config_mail_oauth_tenant_id));
    ?>

    <?php if ($oauth_has_required_fields) { ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-key mr-2"></i>Test OAuth Token Refresh</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="oauth_provider" value="<?php echo htmlspecialchars($oauth_provider_for_test); ?>">

                <p class="text-secondary mb-3">
                    This validates your refresh token and stores a new access token for
                    <?php echo $oauth_provider_for_test === 'microsoft_oauth' ? 'Microsoft 365' : 'Google Workspace'; ?>.
                </p>

                <button type="submit" name="test_oauth_token_refresh" class="btn btn-success">
                    <i class="fas fa-fw fa-sync-alt mr-2"></i>Test OAuth Token Refresh
                </button>
            </form>
        </div>
    </div>

    <?php } ?>

<!-- Microsoft 365 OAuth Setup Guide Modal -->
<div class="modal fade" id="ms365OAuthGuideModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:#1f2d40;color:#c2cfd6;">
            <div class="modal-header" style="border-color:#2d3f55;">
                <h5 class="modal-title text-white"><i class="fab fa-microsoft mr-2"></i>Microsoft 365 OAuth Setup Guide</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">

                <p class="text-muted mb-3">Follow these steps to connect ITFlow to Microsoft 365 for IMAP email monitoring and SMTP sending using OAuth.</p>

                <div id="ms365GuideAccordion">

                    <!-- Step 1 -->
                    <div class="mb-1 rounded overflow-hidden" style="border:1px solid #2d3f55;">
                        <div style="background:#2d3f55;padding:.6rem 1rem;">
                            <h6 class="mb-0">
                                <a href="#" data-toggle="collapse" data-target="#ms365Step1" aria-expanded="true" aria-controls="ms365Step1" class="text-white d-flex align-items-center" style="text-decoration:none;">
                                    <span class="badge badge-primary mr-2 px-2 py-1">1</span>
                                    <span>Register an App in Entra ID</span>
                                    <i class="fas fa-chevron-down ml-auto"></i>
                                </a>
                            </h6>
                        </div>
                        <div id="ms365Step1" class="collapse show" data-parent="#ms365GuideAccordion">
                            <div style="padding:1rem 1.25rem;">
                                <ol class="mb-0 pl-3 text-light">
                                    <li>Go to <strong>portal.azure.com</strong> &rarr; <strong>Microsoft Entra ID</strong> &rarr; <strong>App registrations</strong> &rarr; <strong>New registration</strong></li>
                                    <li class="mt-2">Give it a name (e.g. <code>ITFlow Mail</code>)</li>
                                    <li class="mt-2">Supported account types: <strong>Accounts in this organizational directory only (Single tenant)</strong></li>
                                    <li class="mt-2">
                                        Redirect URI &mdash; set type to <strong>Web</strong> and paste this URI:
                                        <div class="input-group mt-1">
                                            <input type="text" class="form-control form-control-sm border-0 text-white" readonly
                                                   style="background:#111d2b;"
                                                   value="<?php echo htmlspecialchars($mail_oauth_callback_uri); ?>"
                                                   id="ms365GuideCallbackUri">
                                            <div class="input-group-append">
                                                <button class="btn btn-sm btn-outline-secondary text-light" type="button"
                                                        onclick="navigator.clipboard.writeText(document.getElementById('ms365GuideCallbackUri').value); this.innerHTML='<i class=\'fas fa-check text-success\'></i>'">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="mt-2">Click <strong>Register</strong></li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="mb-1 rounded overflow-hidden" style="border:1px solid #2d3f55;">
                        <div style="background:#2d3f55;padding:.6rem 1rem;">
                            <h6 class="mb-0">
                                <a href="#" data-toggle="collapse" data-target="#ms365Step2" aria-expanded="false" aria-controls="ms365Step2" class="text-white collapsed d-flex align-items-center" style="text-decoration:none;">
                                    <span class="badge badge-primary mr-2 px-2 py-1">2</span>
                                    <span>Add API Permissions</span>
                                    <i class="fas fa-chevron-down ml-auto"></i>
                                </a>
                            </h6>
                        </div>
                        <div id="ms365Step2" class="collapse" data-parent="#ms365GuideAccordion">
                            <div style="padding:1rem 1.25rem;">
                                <ol class="mb-0 pl-3 text-light">
                                    <li>In your new app, go to <strong>API permissions</strong> &rarr; <strong>Add a permission</strong></li>
                                    <li class="mt-2">
                                        Click the <strong>"APIs my organization uses"</strong> tab &mdash; <em>not</em> "Microsoft APIs"
                                        <div class="alert alert-warning mt-2 mb-0 py-2">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            <strong>Do not use Microsoft Graph.</strong> The <code>Mail.Read</code> / <code>Mail.Send</code> permissions listed there will not work for IMAP OAuth. You need the Exchange Online API specifically.
                                        </div>
                                    </li>
                                    <li class="mt-2">Search for <strong>Office 365 Exchange Online</strong> and select it</li>
                                    <li class="mt-2">Choose <strong>Delegated permissions</strong> (your application accesses the API as the signed-in user)</li>
                                    <li class="mt-2">
                                        In the <strong>filter/search box</strong>, type <code>IMAP</code> &mdash; tick <strong>IMAP.AccessAsUser.All</strong>
                                        <div class="alert alert-warning mt-2 mb-0 py-2">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            <strong>Search returns "No results"?</strong> IMAP OAuth is not enabled in your tenant. You need to enable it first:
                                            <ol class="mt-2 mb-1 pl-4">
                                                <li>Open a new tab &rarr; go to <strong>admin.exchange.microsoft.com</strong></li>
                                                <li>Go to <strong>Settings &rarr; Mail flow</strong> (or search <em>Modern authentication</em>)</li>
                                                <li>Alternatively, run this in <strong>Exchange Online PowerShell</strong>:<br>
                                                    <code>Set-OrganizationConfig -OAuth2ClientProfileEnabled $true</code>
                                                </li>
                                                <li>Wait a few minutes, then <strong>refresh</strong> this Azure page and search again</li>
                                            </ol>
                                        </div>
                                    </li>
                                    <li class="mt-2">
                                        Clear the filter, type <code>SMTP</code> &mdash; tick <strong>SMTP.Send</strong>
                                    </li>
                                    <li class="mt-2">Click <strong>Add permissions</strong> at the bottom</li>
                                    <li class="mt-2">Back on the permissions list, click <strong>Grant admin consent for [your org]</strong> and confirm</li>
                                </ol>
                                <div class="alert alert-info mt-3 mb-0 py-2">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    These must be <strong>Delegated</strong> permissions, not Application. The OAuth flow requires a real user to sign in interactively.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="mb-1 rounded overflow-hidden" style="border:1px solid #2d3f55;">
                        <div style="background:#2d3f55;padding:.6rem 1rem;">
                            <h6 class="mb-0">
                                <a href="#" data-toggle="collapse" data-target="#ms365Step3" aria-expanded="false" aria-controls="ms365Step3" class="text-white collapsed d-flex align-items-center" style="text-decoration:none;">
                                    <span class="badge badge-primary mr-2 px-2 py-1">3</span>
                                    <span>Create a Client Secret</span>
                                    <i class="fas fa-chevron-down ml-auto"></i>
                                </a>
                            </h6>
                        </div>
                        <div id="ms365Step3" class="collapse" data-parent="#ms365GuideAccordion">
                            <div style="padding:1rem 1.25rem;">
                                <ol class="mb-0 pl-3 text-light">
                                    <li>Under your app &rarr; <strong>Certificates &amp; secrets</strong> &rarr; <strong>New client secret</strong></li>
                                    <li class="mt-2">Set an expiry and note the date &mdash; you must rotate the secret before it expires or mail will stop working</li>
                                    <li class="mt-2">Copy the <strong>Value</strong> immediately &mdash; it is hidden after you navigate away</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="mb-1 rounded overflow-hidden" style="border:1px solid #2d3f55;">
                        <div style="background:#2d3f55;padding:.6rem 1rem;">
                            <h6 class="mb-0">
                                <a href="#" data-toggle="collapse" data-target="#ms365Step4" aria-expanded="false" aria-controls="ms365Step4" class="text-white collapsed d-flex align-items-center" style="text-decoration:none;">
                                    <span class="badge badge-primary mr-2 px-2 py-1">4</span>
                                    <span>Fill In ITFlow Settings &amp; Connect</span>
                                    <i class="fas fa-chevron-down ml-auto"></i>
                                </a>
                            </h6>
                        </div>
                        <div id="ms365Step4" class="collapse" data-parent="#ms365GuideAccordion">
                            <div style="padding:1rem 1.25rem;">
                                <p class="mb-2 text-light">From your app's <strong>Overview</strong> page, copy the following into the IMAP settings form:</p>
                                <table class="table table-sm mb-3" style="color:#e9ecef;border-color:#3d5166;">
                                    <thead>
                                        <tr style="background:#111d2b;color:#fff;border-color:#3d5166;">
                                            <th style="border-color:#3d5166;">ITFlow Field</th>
                                            <th style="border-color:#3d5166;">Where to find it in Azure</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="background:#162032;border-color:#3d5166;"><td style="border-color:#3d5166;">IMAP Provider</td><td style="border-color:#3d5166;">Set to <strong class="text-white">Microsoft 365 (OAuth)</strong></td></tr>
                                        <tr style="background:#1a2a40;border-color:#3d5166;"><td style="border-color:#3d5166;">IMAP Username</td><td style="border-color:#3d5166;">The mailbox email to monitor (e.g. <code>support@company.com</code>)</td></tr>
                                        <tr style="background:#162032;border-color:#3d5166;"><td style="border-color:#3d5166;">OAuth Client ID</td><td style="border-color:#3d5166;">App Overview &rarr; <strong class="text-white">Application (client) ID</strong></td></tr>
                                        <tr style="background:#1a2a40;border-color:#3d5166;"><td style="border-color:#3d5166;">OAuth Client Secret</td><td style="border-color:#3d5166;">The value you copied in Step 3</td></tr>
                                        <tr style="background:#162032;border-color:#3d5166;"><td style="border-color:#3d5166;">Tenant ID</td><td style="border-color:#3d5166;">App Overview &rarr; <strong class="text-white">Directory (tenant) ID</strong></td></tr>
                                    </tbody>
                                </table>
                                <p class="mb-1 text-light">Then:</p>
                                <ol class="mb-0 pl-3 text-light">
                                    <li>Click <strong>Save</strong> on the IMAP settings form</li>
                                    <li class="mt-2">Click <strong>Connect Microsoft 365</strong> &mdash; a Microsoft login window will open</li>
                                    <li class="mt-2">Sign in with the mailbox account (or the licensed user who has access to a shared mailbox)</li>
                                    <li class="mt-2">Grant consent &mdash; you'll be redirected back with a success message and tokens stored automatically</li>
                                </ol>
                                <div class="alert alert-danger mt-3 mb-0 py-2">
                                    <i class="fas fa-times-circle mr-1"></i>
                                    <strong>Error: AADSTS700016 &mdash; Application not found in directory?</strong>
                                    <ul class="mt-2 mb-0 pl-4">
                                        <li>The <strong>Client ID</strong> doesn't match any app in your tenant. Go back to Azure &rarr; App registrations &rarr; your app &rarr; <strong>Overview</strong>.</li>
                                        <li>Make sure you copied the <strong>Application (client) ID</strong> &mdash; <em>not</em> the Object ID (it's just below it and easy to grab by mistake).</li>
                                        <li>Confirm the app was registered in the <strong>same tenant</strong> as the account you're signing in with. If you have multiple tenants, check the directory switcher in the top-right of the Azure portal.</li>
                                        <li>If you registered the app in a personal Microsoft account tenant by mistake, delete it and re-register under your organization directory.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5 — Shared Mailbox -->
                    <div class="mb-1 rounded overflow-hidden" style="border:1px solid #2d3f55;">
                        <div style="background:#2d3f55;padding:.6rem 1rem;">
                            <h6 class="mb-0">
                                <a href="#" data-toggle="collapse" data-target="#ms365Step5" aria-expanded="false" aria-controls="ms365Step5" class="text-white collapsed d-flex align-items-center" style="text-decoration:none;">
                                    <span class="badge badge-info mr-2 px-2 py-1"><i class="fas fa-users"></i></span>
                                    <span>Using a Shared Mailbox</span>
                                    <i class="fas fa-chevron-down ml-auto"></i>
                                </a>
                            </h6>
                        </div>
                        <div id="ms365Step5" class="collapse" data-parent="#ms365GuideAccordion">
                            <div style="padding:1rem 1.25rem;">
                                <p class="text-light">Shared mailboxes are supported. The OAuth token is obtained from a licensed user who has access to the shared mailbox.</p>
                                <ol class="pl-3 mb-3 text-light">
                                    <li>In <strong>Exchange Admin Center</strong>, grant the licensed user <strong>Full Access</strong> to the shared mailbox</li>
                                    <li class="mt-2">Set <strong>IMAP Username</strong> to the <em>shared mailbox</em> address (e.g. <code>support@company.com</code>)</li>
                                    <li class="mt-2">When clicking <strong>Connect Microsoft 365</strong>, sign in with the <em>licensed user account</em>, not the shared mailbox itself</li>
                                    <li class="mt-2">ITFlow will access the shared mailbox using that user's token</li>
                                </ol>
                                <div class="alert alert-info py-2 mb-0">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    To <strong>send from</strong> the shared mailbox address, also grant the licensed user <strong>Send As</strong> permission in Exchange Admin Center.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 6 — Token Maintenance -->
                    <div class="mb-1 rounded overflow-hidden" style="border:1px solid #2d3f55;">
                        <div style="background:#2d3f55;padding:.6rem 1rem;">
                            <h6 class="mb-0">
                                <a href="#" data-toggle="collapse" data-target="#ms365Step6" aria-expanded="false" aria-controls="ms365Step6" class="text-white collapsed d-flex align-items-center" style="text-decoration:none;">
                                    <span class="badge badge-secondary mr-2 px-2 py-1"><i class="fas fa-sync-alt"></i></span>
                                    <span>Token Maintenance</span>
                                    <i class="fas fa-chevron-down ml-auto"></i>
                                </a>
                            </h6>
                        </div>
                        <div id="ms365Step6" class="collapse" data-parent="#ms365GuideAccordion">
                            <div style="padding:1rem 1.25rem;">
                                <ul class="pl-3 mb-0 text-light">
                                    <li>The refresh token stays valid as long as ITFlow polls regularly. If idle for <strong>90 days</strong>, Microsoft invalidates it and you must click <strong>Connect Microsoft 365</strong> again.</li>
                                    <li class="mt-2">Your <strong>Client Secret</strong> has its own expiry date (set in Step 3). Rotate it in Azure before it expires and update the value here, then click <strong>Connect Microsoft 365</strong> again.</li>
                                    <li class="mt-2">Use <strong>Test OAuth Token Refresh</strong> (shown on this page when credentials are configured) to verify your tokens are working at any time.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div><!-- /accordion -->
            </div>
            <div class="modal-footer py-2" style="border-color:#2d3f55;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<!-- /Microsoft 365 OAuth Setup Guide Modal -->

<script>
(function(){
  function setDisabled(container, disabled){
    if(!container) return;
    container.querySelectorAll('input, select, textarea').forEach(el => el.disabled = !!disabled);
  }

  function wireProvider(selectId, standardWrapId, passwordGroupId, oauthWrapId, tenantRowId, hintId, oauthHintId){
    const sel   = document.getElementById(selectId);
    const std   = document.getElementById(standardWrapId);
    const pwd   = document.getElementById(passwordGroupId);
    const oauth = document.getElementById(oauthWrapId);
    const ten   = document.getElementById(tenantRowId);
    const hint  = document.getElementById(hintId);
    const ohint = document.getElementById(oauthHintId);

    function toggle(){
      const v = (sel && sel.value) || '';
      const isNone  = (v === 'none' || v === '');
      const isStd   = v === 'standard_smtp' || v === 'standard_imap';
      const isG     = v === 'google_oauth';
      const isM     = v === 'microsoft_oauth';
      const isOAuth = isG || isM;

      if (std)   std.style.display   = isStd ? '' : 'none';
      if (pwd)   pwd.style.display   = isStd ? '' : 'none';
      if (oauth) oauth.style.display = isOAuth ? '' : 'none';
      if (ten)   ten.style.display   = isM ? '' : 'none';

      setDisabled(std,   !isStd);
      setDisabled(pwd,   !isStd);
      setDisabled(oauth, !isOAuth);

      if (hint) {
        hint.textContent = isNone
          ? 'Disabled.'
          : isStd
            ? 'Standard: provide host, port, encryption, username & password.'
            : isG
              ? 'Google OAuth: set Client ID/Secret; paste a refresh token; username should be the mailbox email.'
              : 'Microsoft 365 OAuth: set Client ID/Secret/Tenant; paste a refresh token; username should be the mailbox email.';
      }
      if (ohint) {
        ohint.textContent = isG
          ? 'Google Workspace OAuth: Client ID/Secret from Google Cloud; Refresh token via consent.'
          : isM
            ? 'Microsoft 365 OAuth: Client ID/Secret/Tenant from Entra ID; Refresh token via consent.'
            : 'Configure OAuth credentials for the selected provider.';
      }
    }

    if (sel) { sel.addEventListener('change', toggle); toggle(); }
  }

  // IMAP (you already have these IDs in your page)
  wireProvider('config_imap_provider', 'standard_fields', 'imap_password_group',
               'oauth_fields', 'tenant_row', 'imap_provider_hint', 'oauth_hint');

  // SMTP (the IDs we just added)
  wireProvider('config_smtp_provider', 'smtp_standard_fields', 'smtp_password_group',
               'smtp_oauth_fields', 'smtp_tenant_row', 'smtp_provider_hint', 'smtp_oauth_hint');
})();
</script>

<?php require_once "../includes/footer.php";
