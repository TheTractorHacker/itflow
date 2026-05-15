<?php

require_once "includes/inc_all_user.php";

// Handle disconnect
if (isset($_GET['disconnect_outlook'])) {
    validateCSRFToken($_GET['csrf_token']);
    mysqli_query($mysqli, "UPDATE users SET
        user_outlook_access_token  = NULL,
        user_outlook_refresh_token = NULL,
        user_outlook_token_expires = NULL
        WHERE user_id = $session_user_id");
    logAction("User", "Edit", "Disconnected Outlook Calendar");
    flash_alert("Outlook Calendar disconnected.", 'warning');
    header("Location: /agent/user/user_integrations.php");
    exit;
}

// Load current user's Outlook connection status
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_outlook_refresh_token, user_outlook_token_expires FROM users WHERE user_id = $session_user_id"));
$outlook_connected = !empty($row['user_outlook_refresh_token']);
$outlook_expires   = $row['user_outlook_token_expires'] ?? null;

$admin_configured = !empty($config_outlook_cal_client_id) && !empty($config_outlook_cal_tenant_id);

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fab fa-microsoft mr-2"></i>Outlook Calendar Sync</h3>
    </div>
    <div class="card-body">

        <?php if (!$admin_configured) { ?>
        <div class="alert alert-warning py-2">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Outlook Calendar Sync has not been configured yet. Ask your administrator to complete the Azure setup under <strong>Admin → Settings → Calendar Sync</strong>.
        </div>

        <?php } elseif ($outlook_connected) { ?>
        <div class="alert alert-success py-2 mb-3">
            <i class="fas fa-check-circle mr-2"></i>
            <strong>Connected.</strong> Scheduled tickets will automatically create and update events in your Outlook Calendar.
            <?php if ($outlook_expires) { ?>
            <br><small class="text-muted">Token active · refreshes automatically</small>
            <?php } ?>
        </div>

        <p>Your Outlook Calendar is connected. When you are assigned a ticket and a schedule is set, an event will be created in your calendar automatically. Changes and cancellations sync in real time.</p>

        <a href="/agent/user/user_integrations.php?disconnect_outlook=1&csrf_token=<?= $_SESSION['csrf_token'] ?>"
           class="btn btn-outline-danger confirm-link">
            <i class="fas fa-unlink mr-2"></i>Disconnect Outlook Calendar
        </a>

        <?php } else { ?>
        <p class="text-muted">Connect your Microsoft Outlook Calendar to automatically sync ticket appointments. When a ticket is scheduled and assigned to you, an event will appear in your calendar — and will update or cancel if the schedule changes.</p>

        <a href="/agent/outlook_calendar_connect.php" class="btn btn-primary">
            <i class="fab fa-microsoft mr-2"></i>Connect Outlook Calendar
        </a>
        <?php } ?>

    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . "/includes/footer.php"; ?>
