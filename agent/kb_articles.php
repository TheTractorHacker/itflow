<?php

// Default Column Sortby Filter
$sort = "kb_article_title";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $kb_scope_query = "AND kb_article_client_id IN (0, $client_id)";
} else {
    require_once "includes/inc_all.php";

    if (isset($_GET['filter_client_id']) && $_GET['filter_client_id'] !== '') {
        $filter_client_id = intval($_GET['filter_client_id']);
        $kb_scope_query = "AND kb_article_client_id = $filter_client_id";
    } else {
        $filter_client_id = '';
        $kb_scope_query = '';
    }
}

enforceUserPermission('module_kb');

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS kb_articles.*, clients.client_name
     FROM kb_articles
     LEFT JOIN clients ON clients.client_id = kb_articles.kb_article_client_id
     WHERE kb_article_title LIKE '%$q%'
     AND kb_article_archived_at IS NULL
     $kb_scope_query
     ORDER BY $sort $order
     LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

if (!isset($client_id)) {
    $sql_client_select = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL $access_permission_query ORDER BY client_name ASC");
}

?>

<div class="alga-theme">

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-book mr-2"></i>Knowledge Base</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/kb_article/kb_article_add.php<?php if (isset($client_id)) { echo "?client_id=$client_id"; } ?>">
                <i class="fas fa-plus mr-2"></i>New Article
            </button>
        </div>
    </div>
    <div class="card-body">

        <form autocomplete="off" class="filter-bar">
            <?php if (isset($client_id)) { ?>
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <?php } ?>
            <div class="row align-items-center">
                <div class="col-auto mb-2">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Knowledge Base">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <?php if (!isset($client_id)) { ?>
                    <div class="col-auto mb-2">
                        <select class="form-control select2" name="filter_client_id" onchange="this.form.submit()" data-placeholder="All Articles" style="width: 220px">
                            <option value="">- All Articles -</option>
                            <option value="0" <?php if ($filter_client_id === 0) { echo "selected"; } ?>>Central (Company-wide)</option>
                            <?php
                            while ($row = mysqli_fetch_assoc($sql_client_select)) {
                                $select_client_id = intval($row['client_id']);
                                $select_client_name = nullable_htmlentities($row['client_name']);
                            ?>
                                <option value="<?php echo $select_client_id; ?>" <?php if ($filter_client_id === $select_client_id) { echo "selected"; } ?>><?php echo $select_client_name; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
            </div>
        </form>

        <hr>

        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=kb_article_title&order=<?php echo $disp; ?>">
                                Title <?php if ($sort == 'kb_article_title') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>Client</th>
                        <th class="text-center">Client Visible</th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=kb_article_updated_at&order=<?php echo $disp; ?>">
                                Updated <?php if ($sort == 'kb_article_updated_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $kb_article_id = intval($row['kb_article_id']);
                        $kb_article_title = nullable_htmlentities($row['kb_article_title']);
                        $kb_article_client_id = intval($row['kb_article_client_id']);
                        $kb_article_client_name = nullable_htmlentities($row['client_name']);
                        $kb_article_client_visible = intval($row['kb_article_client_visible']);
                        $kb_article_updated_at = $row['kb_article_updated_at'] ?? $row['kb_article_created_at'];

                        $kb_article_url = "kb_article.php?id=$kb_article_id";
                        if (isset($client_id)) {
                            $kb_article_url .= "&client_id=$client_id";
                        }
                    ?>
                        <tr>
                            <td>
                                <a class="text-dark" href="<?php echo $kb_article_url; ?>">
                                    <strong><?php echo $kb_article_title; ?></strong>
                                </a>
                            </td>
                            <td>
                                <?php if ($kb_article_client_id == 0) { ?>
                                    <span class="badge badge-info">Central</span>
                                <?php } else { ?>
                                    <span class="text-secondary"><?php echo $kb_article_client_name; ?></span>
                                <?php } ?>
                            </td>
                            <td class="text-center">
                                <?php if ($kb_article_client_visible == 1) { ?>
                                    <i class="fas fa-fw fa-check text-success" data-toggle="tooltip" title="Visible in client portal"></i>
                                <?php } else { ?>
                                    <i class="fas fa-fw fa-times text-muted" data-toggle="tooltip" title="Hidden from client portal"></i>
                                <?php } ?>
                            </td>
                            <td><small class="text-secondary"><?php echo nullable_htmlentities(date('Y-m-d', strtotime($kb_article_updated_at))); ?></small></td>
                            <td class="text-center">
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="<?php echo $kb_article_url; ?>">
                                            <i class="fas fa-fw fa-eye mr-2"></i>View
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/kb_article/kb_article_edit.php?id=<?php echo $kb_article_id; ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_kb_article=<?php echo $kb_article_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php require_once "../includes/filter_footer.php"; ?>

    </div>
</div>

</div>

<?php
require_once "../includes/footer.php";
