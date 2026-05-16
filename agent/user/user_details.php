<?php
require_once "includes/inc_all_user.php";
?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title"><i class="fas fa-fw fa-user mr-2"></i>User Details</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="row">
                <!-- Avatar column -->
                <div class="col-auto text-center mr-4">
                    <?php if ($session_avatar) { ?>
                        <img src="../../uploads/users/<?= $session_user_id ?>/<?= nullable_htmlentities($session_avatar) ?>"
                             style="width:80px;height:80px;object-fit:cover;border-radius:50%;border:3px solid #dee2e6;">
                        <div class="mt-2">
                            <a href="post.php?clear_your_user_avatar&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                               class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-times mr-1"></i>Remove
                            </a>
                        </div>
                    <?php } else { ?>
                        <div style="width:80px;height:80px;border-radius:50%;background:#4a4a4a;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-user fa-2x text-secondary"></i>
                        </div>
                    <?php } ?>
                    <div class="mt-3">
                        <label class="btn btn-outline-secondary btn-sm mb-0" style="cursor:pointer;">
                            <i class="fas fa-upload mr-1"></i>Upload
                            <input type="file" accept="image/*" name="avatar" class="d-none"
                                   onchange="previewAvatar(this)">
                        </label>
                    </div>
                </div>

                <!-- Fields column -->
                <div class="col">
                    <div class="form-group">
                        <label>Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name"
                                   placeholder="Full Name"
                                   value="<?= stripslashes(nullable_htmlentities($session_name)) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                            </div>
                            <input type="email" class="form-control" name="email"
                                   placeholder="Email Address"
                                   value="<?= nullable_htmlentities($session_email) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                            </div>
                            <input type="text" class="form-control"
                                   value="<?= nullable_htmlentities($session_user_role_display) ?>" disabled>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email Signature</label>
                        <textarea class="form-control tinymceTicket" name="signature" rows="4"
                                  placeholder="Signature appended to ticket replies and emails"><?= getFieldById('user_settings', $session_user_id, 'user_config_signature', 'html') ?></textarea>
                    </div>

                    <button type="submit" name="edit_your_user_details" class="btn btn-primary">
                        <i class="fas fa-check mr-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const existing = document.querySelector('img[style*="border-radius:50%"]');
        const placeholder = document.querySelector('div[style*="border-radius:50%"]');
        if (existing) existing.src = e.target.result;
        else if (placeholder) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style = 'width:80px;height:80px;object-fit:cover;border-radius:50%;border:3px solid #dee2e6;';
            placeholder.replaceWith(img);
        }
    };
    reader.readAsDataURL(input.files[0]);
}
</script>

<?php require_once "../../includes/footer.php"; ?>
