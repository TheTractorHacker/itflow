<?php

require_once '../../../includes/modal_header.php';

$ticket_id = intval($_GET['ticket_id'] ?? 0);
$client_id = intval($_GET['client_id'] ?? 0);

$client_rate = 0.00;
if ($client_id) {
    $r = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_rate FROM clients WHERE client_id = $client_id LIMIT 1"));
    if ($r) $client_rate = floatval($r['client_rate']);
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-plus-circle mr-2"></i>Add Charge</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="charge_product_id" id="charge_product_id" value="0">

    <div class="modal-body">

        <div class="form-group">
            <label>Product / Service <small class="text-muted">(optional)</small></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
                </div>
                <select class="form-control select2" id="product_lookup" name="_product_lookup">
                    <option value="">— Custom / type below —</option>
                    <?php
                    $sql_products = mysqli_query($mysqli, "SELECT product_id, product_name, product_price, product_description FROM products WHERE product_archived_at IS NULL ORDER BY product_name ASC");
                    while ($p = mysqli_fetch_assoc($sql_products)) {
                        $pid   = intval($p['product_id']);
                        $pname = nullable_htmlentities($p['product_name']);
                        $pprice = floatval($p['product_price']);
                        $pdesc  = htmlspecialchars($p['product_description'] ?? '', ENT_QUOTES, 'UTF-8');
                        echo "<option value=\"$pid\" data-price=\"$pprice\" data-desc=\"$pdesc\">$pname</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Item Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                </div>
                <input type="text" class="form-control" name="charge_name" id="charge_name" placeholder="Labor / Parts / etc." required>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" name="charge_description" id="charge_description" rows="3" placeholder="Optional details..."></textarea>
        </div>

        <div class="form-row">
            <div class="col-sm-4">
                <div class="form-group">
                    <label>Qty <strong class="text-danger">*</strong></label>
                    <input type="number" class="form-control" name="charge_quantity" id="charge_quantity" value="1" min="0.01" step="0.01" required>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    <label>Unit Price <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                        <input type="number" class="form-control" name="charge_unit_price" id="charge_unit_price" value="<?= number_format($client_rate, 2, '.', '') ?>" min="0" step="0.01" required>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    <label>Total</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                        <input type="text" class="form-control" id="charge_total_display" readonly value="<?= number_format($client_rate, 2, '.', '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Tax</label>
            <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span></div>
                <select class="form-control select2" name="charge_tax_id">
                    <option value="0">None</option>
                    <?php
                    $sql_taxes = mysqli_query($mysqli, "SELECT * FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_name ASC");
                    while ($t = mysqli_fetch_assoc($sql_taxes)) {
                        echo '<option value="' . intval($t['tax_id']) . '">' . nullable_htmlentities($t['tax_name']) . ' ' . floatval($t['tax_percent']) . '%</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_ticket_charge" class="btn btn-primary"><i class="fas fa-check mr-2"></i>Add Charge</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<script>
$(function() {
    function recalcTotal() {
        var qty   = parseFloat($('#charge_quantity').val()) || 0;
        var price = parseFloat($('#charge_unit_price').val()) || 0;
        $('#charge_total_display').val((qty * price).toFixed(2));
    }
    $('#charge_quantity, #charge_unit_price').on('input', recalcTotal);

    $('#product_lookup').on('change', function() {
        var $opt = $(this).find(':selected');
        var pid   = parseInt($opt.val()) || 0;
        var price = parseFloat($opt.data('price')) || 0;
        var desc  = $opt.data('desc') || '';
        var name  = $opt.text().trim();

        $('#charge_product_id').val(pid);

        if (pid > 0) {
            $('#charge_name').val(name);
            $('#charge_description').val(desc);
            $('#charge_unit_price').val(price.toFixed(2));
            recalcTotal();
        }
    });

    recalcTotal();
});
</script>

<?php
require_once '../../../includes/modal_footer.php';
