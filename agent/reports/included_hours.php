<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_sales');

$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
$year  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
if ($month < 1 || $month > 12) {
    $month = intval(date('n'));
}

$month_names = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];

$sql_clients = mysqli_query($mysqli,
    "SELECT client_id, client_name FROM clients
     WHERE client_support_hours_included IS NOT NULL AND client_archived_at IS NULL
     ORDER BY client_name ASC");

$rows = [];
while ($c = mysqli_fetch_assoc($sql_clients)) {
    $usage = getClientIncludedHoursUsage($mysqli, intval($c['client_id']), $month, $year);
    $rows[] = [
        'client_id'   => intval($c['client_id']),
        'client_name' => $c['client_name'],
        'usage'       => $usage,
    ];
}

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-clock me-2"></i>Included Support Hours</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none js-print-page"><i class="fas fa-fw fa-print me-2"></i>Print</button>
        </div>
    </div>
    <div class="card-body">
        <form class="mb-3 d-flex" style="gap:.5rem">
            <select class="form-control auto-submit-select" name="month" style="max-width:180px">
                <?php foreach ($month_names as $m => $mname) { ?>
                    <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= $mname ?></option>
                <?php } ?>
            </select>
            <select class="form-control auto-submit-select" name="year" style="max-width:120px">
                <?php for ($y = intval(date('Y')); $y >= intval(date('Y')) - 4; $y--) { ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php } ?>
            </select>
        </form>

        <div class="table-responsive-sm">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th class="text-end">Included</th>
                        <th class="text-end">Used</th>
                        <th class="text-end">Remaining</th>
                        <th class="text-end">%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)) { ?>
                        <tr><td colspan="5" class="text-center text-muted">No clients have an included-hours plan configured. Set one on a client's Edit form.</td></tr>
                    <?php } else { foreach ($rows as $r) {
                        $u = $r['usage'];
                        $over = $u['remaining'] !== null && $u['remaining'] < 0;
                    ?>
                        <tr>
                            <td><a href="../client_overview.php?client_id=<?= $r['client_id'] ?>"><?= nullable_htmlentities($r['client_name']) ?></a></td>
                            <td class="text-end"><?= number_format($u['included'], 2) ?> hrs</td>
                            <td class="text-end"><?= number_format($u['used'], 2) ?> hrs</td>
                            <td class="text-end <?= $over ? 'text-danger fw-bold' : '' ?>">
                                <?= $over ? '(' . number_format(abs($u['remaining']), 2) . ' over)' : number_format($u['remaining'], 2) . ' hrs' ?>
                            </td>
                            <td class="text-end">
                                <?php if ($u['pct'] !== null) { ?>
                                    <span class="badge <?= $u['pct'] >= 100 ? 'text-bg-danger' : ($u['pct'] >= 80 ? 'text-bg-warning' : 'text-bg-success') ?>"><?= $u['pct'] ?>%</span>
                                <?php } else { ?>
                                    &mdash;
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "../../includes/footer.php"; ?>
