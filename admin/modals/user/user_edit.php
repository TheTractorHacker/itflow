<?php

require_once '../../../includes/modal_header.php';

$user_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM users
    LEFT JOIN user_settings ON users.user_id = user_settings.user_id
    WHERE users.user_id = $user_id LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);
$user_name = nullable_htmlentities($row['user_name']);
$user_email = nullable_htmlentities($row['user_email']);
$user_avatar = nullable_htmlentities($row['user_avatar']);
$user_token = nullable_htmlentities($row['user_token']);
$user_config_force_mfa = intval($row['user_config_force_mfa']);
$user_role_id = intval($row['user_role_id']);
$user_initials = nullable_htmlentities(initials($user_name));

// Get passkeys
$sql_passkeys = mysqli_query($mysqli, "SELECT * FROM user_passkeys WHERE passkey_user_id = $user_id ORDER BY passkey_created_at DESC");

// Get remember tokens
$remember_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM remember_tokens WHERE remember_token_user_id = $user_id"))[0]);

// Get User Client Access Permissions
$user_client_access_sql = mysqli_query($mysqli,"SELECT client_id FROM user_client_permissions WHERE user_id = $user_id");
$client_access_array = [];
while ($row = mysqli_fetch_assoc($user_client_access_sql)) {
    $client_access_array[] = intval($row['client_id']);
}

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-edit mr-2"></i>Editing user:
        <strong><?php echo $user_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-user-details<?php echo $user_id; ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-user-security<?php echo $user_id; ?>">Security</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-user-access<?php echo $user_id; ?>">Access</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-user-details<?php echo $user_id; ?>">

                <center class="mb-3">
                    <?php if (!empty($user_avatar)) { ?>
                        <img class="img-fluid" src="<?php echo "../uploads/users/$user_id/$user_avatar"; ?>">
                    <?php } else { ?>
                        <span class="fa-stack fa-4x">
                            <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                            <span class="fa fa-stack-1x text-white"><?php echo $user_initials; ?></span>
                        </span>
                    <?php } ?>
                </center>

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Full Name" maxlength="200"
                               value="<?php echo $user_name; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="email" placeholder="Email Address" maxlength="200"
                               value="<?php echo $user_email; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                        </div>
                        <input type="password" class="form-control" data-toggle="password" name="new_password" id="password"
                               placeholder="Leave Blank For No Password Change" autocomplete="new-password">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        </div>
                        <div class="input-group-append">
                            <span class="btn btn-default"><i class="fa fa-fw fa-question" onclick="generatePassword()"></i></span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Role <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                        </div>
                        <select class="form-control select2" name="role" required>
                            <?php
                            $sql_user_roles = mysqli_query($mysqli, "SELECT * FROM user_roles WHERE role_archived_at IS NULL");
                            while ($row = mysqli_fetch_assoc($sql_user_roles)) {
                                $role_id = intval($row['role_id']);
                                $role_name = nullable_htmlentities($row['role_name']);

                                ?>
                                <option <?php if ($role_id == $user_role_id) {echo "selected";} ?> value="<?php echo $role_id; ?>"><?php echo $role_name; ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Avatar</label>
                    <input type="file" class="form-control-file" accept="image/*" name="file">
                </div>

                <p class="text-muted small"><i class="fas fa-shield-alt mr-1"></i>Manage 2FA, passkeys, and sessions in the <a href="#" data-toggle="pill" data-target="#pills-user-security<?= $user_id ?>">Security tab</a>.</p>
            </div>

            <!-- Security Tab -->
            <div class="tab-pane fade" id="pills-user-security<?php echo $user_id; ?>">

                <!-- 2FA -->
                <h6 class="text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.05em">
                    <i class="fas fa-shield-alt mr-1"></i>Two-Factor Authentication
                </h6>
                <?php if (!empty($user_token)): ?>
                    <div class="d-flex align-items-center justify-content-between p-2 mb-3 border rounded">
                        <span><i class="fas fa-lock text-success mr-2"></i><strong>Enabled</strong> — TOTP authenticator app</span>
                        <a href="post.php?disable_2fa=<?= $user_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                           class="btn btn-sm btn-outline-danger confirm-link">
                            <i class="fas fa-unlock mr-1"></i>Disable
                        </a>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center p-2 mb-3 border rounded">
                        <i class="fas fa-unlock text-danger mr-2"></i><span class="text-muted">Not configured</span>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" type="checkbox" id="forceMFASec<?php echo $user_id; ?>" name="force_mfa" value="1" <?php if($user_config_force_mfa == 1){ echo "checked"; } ?>>
                        <label for="forceMFASec<?php echo $user_id; ?>" class="custom-control-label">Force MFA on next login</label>
                    </div>
                </div>

                <hr>

                <!-- Passkeys -->
                <h6 class="text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.05em">
                    <i class="fas fa-key mr-1"></i>Passkeys
                </h6>
                <?php
                $passkey_rows = [];
                while ($pk = mysqli_fetch_assoc($sql_passkeys)) { $passkey_rows[] = $pk; }
                if (empty($passkey_rows)): ?>
                    <p class="text-muted small mb-3">No passkeys registered.</p>
                <?php else: ?>
                    <ul class="list-group mb-3">
                    <?php foreach ($passkey_rows as $pk):
                        $pk_id   = intval($pk['passkey_id']);
                        $pk_name = nullable_htmlentities($pk['passkey_name']);
                        $pk_used = nullable_htmlentities($pk['passkey_last_used_at'] ?? 'Never');
                        $pk_created = nullable_htmlentities($pk['passkey_created_at']);
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <i class="fas fa-key text-secondary mr-2"></i>
                                <strong><?= $pk_name ?></strong>
                                <small class="text-muted ml-2">Added <?= $pk_created ?> · Last used <?= $pk_used ?></small>
                            </div>
                            <a href="post.php?delete_passkey=<?= $pk_id ?>&user_id=<?= $user_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                               class="btn btn-xs btn-outline-danger confirm-link" title="Delete passkey">
                                <i class="fas fa-trash"></i>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <hr>

                <!-- Sessions -->
                <h6 class="text-uppercase text-muted mb-2" style="font-size:.75rem;letter-spacing:.05em">
                    <i class="fas fa-desktop mr-1"></i>Remember-Me Sessions
                </h6>
                <div class="d-flex align-items-center justify-content-between p-2 border rounded">
                    <?php if ($remember_count > 0): ?>
                        <span><i class="fas fa-circle text-warning mr-2"></i><?= $remember_count ?> active session<?= $remember_count > 1 ? 's' : '' ?></span>
                        <a href="post.php?revoke_remember_me=<?= $user_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                           class="btn btn-sm btn-outline-warning confirm-link">
                            <i class="fas fa-ban mr-1"></i>Revoke All
                        </a>
                    <?php else: ?>
                        <span class="text-muted"><i class="fas fa-circle text-secondary mr-2"></i>No active sessions</span>
                    <?php endif; ?>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-user-access<?php echo $user_id; ?>">

                <div class="alert alert-info">
                    Check boxes to authorize user client access. No boxes grant full client access. Admin users are unaffected.
                </div>

                <ul class="list-group">
                    <li class="list-group-item bg-dark">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" onclick="this.closest('.tab-pane').querySelectorAll('.client-checkbox').forEach(checkbox => checkbox.checked = this.checked);">
                            <label class="form-check-label ml-3"><strong>Restrict Access to Clients</strong></label>
                        </div>
                    </li>

                    <?php

                    $sql_client_select = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_client_select)) {
                        $client_id_select = intval($row['client_id']);
                        $client_name_select = nullable_htmlentities($row['client_name']);

                    ?>

                    <li class="list-group-item">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input client-checkbox" name="clients[]" value="<?php echo $client_id_select; ?>" <?php if (in_array($client_id_select, $client_access_array)) { echo "checked"; } ?>>
                            <label class="form-check-label ml-2"><?php echo $client_name_select; ?></label>
                        </div>
                    </li>

                    <?php } ?>

                </ul>

            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_user" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<script>

function generatePassword() {
    // Send a GET request to ajax.php as ajax.php?get_readable_pass=true
    jQuery.get(
        "/agent/ajax.php", {
            get_readable_pass: 'true'
        },
        function(data) {
            //If we get a response from post.php, parse it as JSON
            const password = JSON.parse(data);
            document.getElementById("password").value = password;
        }
    );
}

</script>

<?php
require_once "../../../includes/modal_footer.php";
