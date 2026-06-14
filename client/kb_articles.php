<?php
/*
 * Client Portal
 * Knowledge Base - Central (company-wide) + client-specific articles
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

if ($config_module_enable_kb != 1) {
    header("Location: index.php");
    exit();
}

$kb_articles_sql = mysqli_query(
    $mysqli,
    "SELECT kb_article_id, kb_article_title, kb_article_client_id, kb_article_updated_at, kb_article_created_at
     FROM kb_articles
     WHERE kb_article_client_visible = 1
     AND kb_article_client_id IN (0, $session_client_id)
     AND kb_article_archived_at IS NULL
     ORDER BY kb_article_client_id ASC, kb_article_title ASC"
);

?>

<div class="row">
    <div class="col">
        <h3><i class="fas fa-book mr-2"></i>Knowledge Base</h3>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-12">
        <div class="card card-outline card-primary">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Title</th>
                                <th>Updated</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (mysqli_num_rows($kb_articles_sql) == 0) { ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No articles found.</td>
                                </tr>
                            <?php }
                            while ($row = mysqli_fetch_assoc($kb_articles_sql)) {
                                $kb_article_id = intval($row['kb_article_id']);
                                $kb_article_title = nullable_htmlentities($row['kb_article_title']);
                                $kb_article_client_id = intval($row['kb_article_client_id']);
                                $kb_article_updated_at = $row['kb_article_updated_at'] ?? $row['kb_article_created_at'];
                            ?>
                                <tr>
                                    <td>
                                        <a href="kb_article.php?id=<?php echo $kb_article_id; ?>">
                                            <i class="fas fa-file-alt mr-2"></i><?php echo $kb_article_title; ?>
                                        </a>
                                        <?php if ($kb_article_client_id == 0) { ?>
                                            <span class="badge badge-info ml-2">Central</span>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($kb_article_updated_at)); ?></td>
                                    <td class="text-center">
                                        <a href="kb_article.php?id=<?php echo $kb_article_id; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once "includes/footer.php";
