<?php
require_once "includes/inc_all_admin.php";
 ?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-cube mr-2"></i>Modules</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="config_module_enable_itdoc" <?php if ($config_module_enable_itdoc == 1) { echo "checked"; } ?> value="1" id="customSwitch1">
                    <label class="custom-control-label" for="customSwitch1">Show IT Documentation</label>
                </div>
            </div>

            <div class="form-group">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="config_module_enable_ticketing" <?php if ($config_module_enable_ticketing == 1) { echo "checked"; } ?> value="1" id="customSwitch2">
                    <label class="custom-control-label" for="customSwitch2">Show Ticketing</label>
                </div>
            </div>

            <div class="form-group">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="config_module_enable_accounting" <?php if ($config_module_enable_accounting == 1) { echo "checked"; } ?> value="1" id="customSwitch3">
                    <label class="custom-control-label" for="customSwitch3">Show Invoicing / Accounting</label>
                </div>
            </div>

            <div class="form-group">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="config_module_enable_ticket_charges" <?php if ($config_module_enable_ticket_charges == 1) { echo "checked"; } ?> value="1" id="customSwitch3b">
                    <label class="custom-control-label" for="customSwitch3b">Show Ticket Charges (Billing)</label>
                </div>
                <small class="form-text text-muted">Lets techs add billable charges/labor to tickets, independent of Invoicing / Accounting. Useful if you handle invoicing elsewhere (e.g. QuickBooks) but still want to track billable time per ticket.</small>
            </div>

            <div class="form-group">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="config_module_enable_kb" <?php if ($config_module_enable_kb == 1) { echo "checked"; } ?> value="1" id="customSwitch3c">
                    <label class="custom-control-label" for="customSwitch3c">Show Knowledge Base</label>
                </div>
                <small class="form-text text-muted">Adds a Knowledge Base section for agents and clients - per-client articles plus a Central (company-wide) library.</small>
            </div>

            <div class="form-group">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="config_module_enable_live_chat" <?php if ($config_module_enable_live_chat == 1) { echo "checked"; } ?> value="1" id="customSwitch3d">
                    <label class="custom-control-label" for="customSwitch3d">Show Live Chat on Tickets</label>
                </div>
                <small class="form-text text-muted">Adds a real-time chat panel to ticket views for agents and clients, alongside the normal email-style replies.</small>
            </div>

            <div class="form-group">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="config_client_portal_enable" <?php if ($config_client_portal_enable == 1) { echo "checked"; } ?> value="1" id="customSwitch4">
                    <label class="custom-control-label" for="customSwitch4">Enable Client Portal</label>
                </div>
            </div>

            <hr>

            <div class="form-group">
                <div class="custom-control custom-switch">
                    <input type="checkbox" disabled class="custom-control-input" name="config_whitelabel_enabled" <?php if ($config_whitelabel_enabled == 1) { echo "checked"; } ?> value="1" id="customSwitch5">
                    <label class="custom-control-label" for="customSwitch5">White-label <small class="text-secondary">(Hides 'Powered by ITFlow' banner)</small></label>
                </div>
            </div>

            <div class="form-group">
                <label>White-label key</label>
                <textarea class="form-control" name="config_whitelabel_key" rows="2" placeholder="Enter a key to enable white-labelling the client portal"><?php echo nullable_htmlentities($config_whitelabel_key); ?></textarea>
            </div>

            <?php if ($config_whitelabel_enabled == 1 && validateWhitelabelKey($config_whitelabel_key)) {
                $key_info = validateWhitelabelKey($config_whitelabel_key);
                $key_desc = $key_info["description"];
                $key_org = $key_info["organisation"];
                $key_expires = $key_info["expires"];
                ?>
                <div class="form-group">
                    <p>White-labelling is active - thank you for your support! :)</p>
                    <ul>
                        <li>Key: <?php echo $key_desc ?></li>
                        <li>Org: <?php echo $key_org ?></li>
                        <li>Expires: <?php echo $key_expires; if ($key_expires < date('Y-m-d H:i:s')) { echo " (expiring) "; } ?></li>
                    </ul>

                </div>
            <?php } ?>

            <hr>

            <button type="submit" name="edit_module_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

        </form>
    </div>
</div>

<?php
require_once "../includes/footer.php";

