<?php
require_once "includes/inc_all_user.php";

$pref = mysqli_fetch_assoc(mysqli_query($mysqli,
    "SELECT user_config_calendar_first_day, user_config_records_per_page FROM user_settings WHERE user_id = $session_user_id"
));
$calendar_first_day  = intval($pref['user_config_calendar_first_day']);
$records_per_page    = intval($pref['user_config_records_per_page'] ?: 10);
?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title"><i class="fas fa-fw fa-sliders-h mr-2"></i>Preferences</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <!-- Theme -->
            <div class="form-group">
                <label class="d-block mb-2">Theme</label>
                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                    <label class="btn btn-outline-secondary <?= $user_config_theme_dark === 0 ? 'active' : '' ?>">
                        <input type="radio" name="dark_mode" autocomplete="off"
                               <?= $user_config_theme_dark === 0 ? 'checked' : '' ?>>
                        <i class="fas fa-sun mr-1"></i> Light
                    </label>
                    <label class="btn btn-outline-secondary <?= $user_config_theme_dark === 1 ? 'active' : '' ?>">
                        <input type="radio" name="dark_mode" value="1" autocomplete="off"
                               <?= $user_config_theme_dark === 1 ? 'checked' : '' ?>>
                        <i class="fas fa-moon mr-1"></i> Dark
                    </label>
                </div>
            </div>

            <hr>

            <!-- Calendar -->
            <div class="form-group">
                <label>Calendar starts on</label>
                <div class="input-group" style="max-width:220px;">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                    </div>
                    <select class="form-control" name="calendar_first_day">
                        <option value="0" <?= $calendar_first_day == 0 ? 'selected' : '' ?>>Sunday</option>
                        <option value="1" <?= $calendar_first_day == 1 ? 'selected' : '' ?>>Monday</option>
                    </select>
                </div>
            </div>

            <!-- Records per page -->
            <div class="form-group">
                <label>Records per page</label>
                <div class="input-group" style="max-width:220px;">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                    </div>
                    <select class="form-control" name="records_per_page">
                        <?php foreach ([10, 25, 50, 100] as $n) { ?>
                        <option value="<?= $n ?>" <?= $records_per_page == $n ? 'selected' : '' ?>><?= $n ?></option>
                        <?php } ?>
                    </select>
                </div>
                <small class="text-muted">Number of rows shown in tables.</small>
            </div>

            <hr>

            <button type="submit" name="edit_your_user_preferences" class="btn btn-primary btn-sm">
                <i class="fas fa-check mr-1"></i>Save Preferences
            </button>
        </form>
    </div>
</div>

<?php require_once "../../includes/footer.php"; ?>
