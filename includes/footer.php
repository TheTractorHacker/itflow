<?php
require_once "inc_confirm_modal.php";
?>

<?php
if (basename(dirname($_SERVER['REQUEST_URI'])) === 'admin') { ?>
    <p class="text-right font-weight-light">ITFlow <?php echo APP_VERSION ?> &nbsp; · &nbsp; <a target="_blank" href="https://docs.itflow.org">Docs</a> &nbsp; · &nbsp; <a target="_blank" href="https://forum.itflow.org">Forum</a> &nbsp; · &nbsp; <a target="_blank" href="https://services.itflow.org">Services</a></p>
    <br>
<?php } ?>
<?php
if (basename(dirname($_SERVER['REQUEST_URI'])) === 'guest') { ?>
<p class="text-center">
    <?php
        echo nullable_htmlentities($session_company_name);
        if (!$config_whitelabel_enabled) {
            echo '<br><small class="text-muted">Powered by ITFlow</small>';
        }
    ?>
</p>
<?php } ?>

</div><!-- /.container-fluid -->
</div> <!-- /.content -->
</div> <!-- /.content-wrapper -->
</div> <!-- ./wrapper -->

<!-- Set the browser window title to the clients name -->
<script>document.title = <?php echo json_encode("$tab_title - $page_title"); ?>;</script>

<!-- REQUIRED SCRIPTS -->

<!-- Bootstrap 4 -->
<script src="/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Custom js-->
<script src="/plugins/moment/moment.min.js"></script>
<script src="/plugins/chart.js/chart.umd.min.js"></script>
<script src="/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<script src="/plugins/daterangepicker/daterangepicker.js"></script>
<script src="/plugins/select2/js/select2.min.js"></script>
<script src="/plugins/inputmask/jquery.inputmask.min.js"></script>
<script src="/plugins/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script src="/plugins/marked/marked.min.js"></script>
<script src="/plugins/turndown/turndown.js"></script>
<script src="/plugins/turndown/turndown-plugin-gfm.js"></script>
<script src="/plugins/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>
<script src="/plugins/clipboardjs/clipboard.min.js"></script>
<script src="/js/keepalive.js"></script>
<script src="/plugins/DataTables/datatables.min.js"></script>
<script src="/plugins/intl-tel-input/js/intlTelInput.min.js"></script>

<!-- AdminLTE App -->
<script src="/plugins/adminlte/js/adminlte.min.js"></script>
<?php
// Cache-bust first-party JS on every edit (falls back to the request time if the
// file is somehow missing) so a stale Cloudflare/browser cache can't keep serving
// an old copy after a deploy - static assets otherwise have no way to know they changed.
foreach (['app.js', 'ajax_modal.js', 'confirm_modal.js', 'date_filter.js'] as $__asset) {
    $__asset_path = __DIR__ . '/../js/' . $__asset;
    $__asset_version = file_exists($__asset_path) ? filemtime($__asset_path) : time();
    echo '<script src="/js/' . $__asset . '?v=' . $__asset_version . '"></script>' . "\n";
}
?>

</body>
</html>

<?php

// Calculate Execution time Uncomment for test

//$time_end = microtime(true);
//$execution_time = ($time_end - $time_start);
//echo '<h2>Total Execution Time: '.number_format((float) $execution_time, 10) .' seconds</h2>';
