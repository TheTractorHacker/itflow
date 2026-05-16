<?php
require_once "includes/inc_all_user.php";

// User remember me tokens
$sql_remember_tokens = mysqli_query($mysqli, "SELECT * FROM remember_tokens WHERE remember_token_user_id = $session_user_id");
$remember_token_count = mysqli_num_rows($sql_remember_tokens);

?>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-shield-alt mr-2"></i>Your Password</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label>Your New Password <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                    </div>
                    <input type="password" class="form-control" data-toggle="password" name="new_password" placeholder="Leave blank for no change" autocomplete="new-password" minlength="8" required>
                    <div class="input-group-append">
                        <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                    </div>
                </div>
            </div>

            <button type="submit" name="edit_your_user_password" class="btn btn-primary"><i class="fas fa-check mr-2"></i>Change</button>

        </form>

         <div class="float-right">
            <?php if (empty($session_token)) { ?>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#enableMFAModal">
                    <i class="fas fa-lock mr-2"></i>Enable MFA
                </button>

                <?php require_once "modals/user_mfa_modal.php"; ?>

            <?php } else { ?>
                <a href="post.php?disable_mfa&csrf_token=<?php echo $_SESSION['csrf_token'] ?>" class="btn btn-danger"><i class="fas fa-unlock mr-2"></i>Disable MFA</a>
            <?php } ?>
        </div>

    </div>
</div>

<?php if ($remember_token_count > 0) { ?>
    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-clock mr-2"></i>2FA Remember-Me Tokens</h3>
        </div>
        <div class="card-body">

            <ul>
                <?php while ($row = mysqli_fetch_assoc($sql_remember_tokens)) {
                    $token_id = intval($row['remember_token_id']);
                    $token_created = nullable_htmlentities($row['remember_token_created_at']);

                    echo "<li>ID: $token_id | Created: $token_created</li>";
                } ?>
            </ul>

            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <button type="submit" name="revoke_your_2fa_remember_tokens" class="btn btn-danger btn-block mt-3"><i class="fas fa-exclamation-triangle mr-2"></i>Revoke Remember-Me Tokens</button>

            </form>

        </div>
    </div>
<?php } ?>

<?php

// Show the error alert if it exists:
if (!empty($_SESSION['alert_type']) && $_SESSION['alert_type'] == 'error') {
    echo "<div class='alert alert-danger'>{$_SESSION['alert_message']}</div>";
    // Clear it so it doesn't persist on refresh
    unset($_SESSION['alert_type']);
    unset($_SESSION['alert_message']);
}

// If the user just failed a TOTP verification, auto-open the modal:
if (!empty($_SESSION['show_mfa_modal'])) {
    echo "
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // jQuery or vanilla JS to open the modal
            $('#enableMFAModal').modal('show');
        });
    </script>";
    unset($_SESSION['show_mfa_modal']);
}

?>
<div class="card card-dark mt-3">
    <div class="card-header py-3 d-flex align-items-center">
        <h3 class="card-title mr-auto"><i class="fas fa-fw fa-fingerprint mr-2"></i>Passkeys</h3>
        <button class="btn btn-primary btn-sm" id="addPasskeyBtn" onclick="passkeyRegister()">
            <i class="fas fa-plus mr-1"></i>Add Passkey
        </button>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-borderless table-striped mb-0" id="passkey-table">
            <thead class="text-dark">
                <tr><th>Name</th><th>Added</th><th>Last used</th><th></th></tr>
            </thead>
            <tbody>
            <?php
            $sql_pk = mysqli_query($mysqli, "SELECT * FROM user_passkeys WHERE passkey_user_id = $session_user_id ORDER BY passkey_created_at DESC");
            if (mysqli_num_rows($sql_pk) == 0) { ?>
                <tr id="passkey-empty-row"><td colspan="4" class="text-muted text-center py-3">No passkeys registered yet. Add one above.</td></tr>
            <?php } else {
                while ($pk = mysqli_fetch_assoc($sql_pk)) {
                    $pkid = intval($pk['passkey_id']);
                    $pkname = nullable_htmlentities($pk['passkey_name']);
                    $pkcreated = nullable_htmlentities($pk['passkey_created_at']);
                    $pklast = $pk['passkey_last_used_at'] ? timeAgo($pk['passkey_last_used_at']) : '<span class="text-muted">Never</span>';
                    ?>
                    <tr>
                        <td><i class="fas fa-fingerprint mr-2 text-primary"></i><?= $pkname ?></td>
                        <td class="text-secondary"><?= $pkcreated ?></td>
                        <td><?= $pklast ?></td>
                        <td class="text-right">
                            <button class="btn btn-sm btn-danger"
                                    onclick="deletePasskey(<?= $pkid ?>, '<?= htmlspecialchars($pkname, ENT_QUOTES) ?>', this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php }
            } ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Passkey Registration Modal -->
<div class="modal fade" id="passkeyAddModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-fingerprint mr-2"></i>Add Passkey</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="passkey-modal-idle">
                    <p class="text-muted mb-3">Give this passkey a name so you can identify which device it belongs to.</p>
                    <div class="form-group">
                        <label>Passkey name</label>
                        <input type="text" class="form-control" id="passkeyNameInput"
                               placeholder="e.g. MacBook Touch ID, iPhone Face ID"
                               maxlength="200">
                    </div>
                </div>
                <div id="passkey-modal-waiting" class="text-center py-3" style="display:none;">
                    <i class="fas fa-fingerprint fa-3x text-primary mb-3" style="animation:pulse 1.2s infinite;"></i>
                    <p class="mb-0"><strong>Waiting for your authenticator&hellip;</strong></p>
                    <p class="text-muted small">Touch ID, Face ID, or security key</p>
                </div>
                <div id="passkey-modal-error" class="alert alert-danger mt-2" style="display:none;"></div>
                <div id="passkey-modal-success" class="alert alert-success mt-2" style="display:none;">
                    <i class="fas fa-check-circle mr-2"></i>Passkey registered successfully!
                </div>
            </div>
            <div class="modal-footer" id="passkey-modal-footer">
                <button type="button" class="btn btn-primary" id="passkeyRegisterBtn" onclick="passkeyDoRegister()">
                    <i class="fas fa-fingerprint mr-1"></i>Register Passkey
                </button>
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
<style>
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.1)} }
</style>

<script>
function passkeyRegister() {
    // Reset modal state
    document.getElementById('passkey-modal-idle').style.display    = '';
    document.getElementById('passkey-modal-waiting').style.display = 'none';
    document.getElementById('passkey-modal-error').style.display   = 'none';
    document.getElementById('passkey-modal-success').style.display = 'none';
    document.getElementById('passkey-modal-footer').style.display  = '';
    document.getElementById('passkeyNameInput').value = '';
    $('#passkeyAddModal').modal('show');
}

async function passkeyDoRegister() {
    const nameInput = document.getElementById('passkeyNameInput');
    const passkeyName = nameInput.value.trim() || 'Passkey';
    const errBox  = document.getElementById('passkey-modal-error');
    const footer  = document.getElementById('passkey-modal-footer');

    errBox.style.display = 'none';
    document.getElementById('passkey-modal-idle').style.display    = 'none';
    document.getElementById('passkey-modal-waiting').style.display = '';
    footer.style.display = 'none';

    try {
        // 1. Get creation options
        const beginResp = await fetch('passkey_register_begin.php');
        const options   = await beginResp.json();
        if (options.error) throw new Error(options.error);

        // Decode base64url fields
        options.challenge = b64u_to_buf(options.challenge);
        options.user.id   = b64u_to_buf(options.user.id);
        if (options.excludeCredentials) {
            options.excludeCredentials = options.excludeCredentials.map(c => ({...c, id: b64u_to_buf(c.id)}));
        }

        // 2. Trigger authenticator
        const credential = await navigator.credentials.create({ publicKey: options });

        // 3. Send to server
        const body = {
            passkeyName,
            id:   credential.id,
            type: credential.type,
            response: {
                clientDataJSON:    buf_to_b64u(credential.response.clientDataJSON),
                attestationObject: buf_to_b64u(credential.response.attestationObject),
            }
        };
        const completeResp = await fetch('passkey_register_complete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body),
        });
        const result = await completeResp.json();

        if (result.ok) {
            document.getElementById('passkey-modal-waiting').style.display = 'none';
            document.getElementById('passkey-modal-success').style.display = '';
            setTimeout(() => { $('#passkeyAddModal').modal('hide'); window.location.reload(); }, 1200);
        } else {
            throw new Error(result.error || 'Server rejected the passkey');
        }
    } catch (err) {
        document.getElementById('passkey-modal-waiting').style.display = 'none';
        document.getElementById('passkey-modal-idle').style.display    = '';
        footer.style.display = '';
        if (err.name !== 'NotAllowedError' && err.name !== 'AbortError') {
            errBox.textContent = 'Error: ' + err.message;
            errBox.style.display = '';
        }
    }
}

async function deletePasskey(pkId, pkName, btn) {
    if (!confirm('Remove passkey "' + pkName + '"?')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    try {
        const resp = await fetch('passkey_delete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                passkey_id: pkId,
                csrf_token: document.querySelector('meta[name="csrf"]')?.content
                            || '<?php echo $_SESSION["csrf_token"]; ?>'
            })
        });
        const result = await resp.json();
        if (result.ok) {
            btn.closest('tr').remove();
            // Show empty-row if no passkeys left
            if (document.querySelectorAll('#passkey-table tbody tr').length === 0) {
                const tbody = document.querySelector('#passkey-table tbody');
                tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-3">No passkeys registered yet. Add one above.</td></tr>';
            }
        } else {
            alert('Delete failed: ' + (result.error || 'Unknown error'));
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash"></i>';
        }
    } catch (err) {
        alert('Error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash"></i>';
    }
}

// Base64url helpers
function b64u_to_buf(str) {
    const b64 = str.replace(/-/g,'+').replace(/_/g,'/') + '=='.slice(0, (4 - str.length % 4) % 4);
    return Uint8Array.from(atob(b64), c => c.charCodeAt(0)).buffer;
}
function buf_to_b64u(buf) {
    const bytes = new Uint8Array(buf);
    let s = ''; bytes.forEach(b => s += String.fromCharCode(b));
    return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=/g,'');
}
</script>

<?php
require_once "../../includes/footer.php";
