<?php
// Human-readable HTML reference for the ITFlow API v1.
// Served (public) by index.php's `case openapi/docs` block at GET /api/v1/docs.
//
// Fully self-contained: inline CSS only, NO JavaScript and NO external assets,
// so it renders safely even under a strict `default-src 'self'` CSP. It is a
// thin renderer over openapi.yaml (the single source of truth) — it scans the
// spec's `paths:` block for path / method / summary / tag rather than keeping a
// second endpoint list that could drift. PHP here has no yaml extension, hence
// the small hand-rolled line scanner below (the spec's own formatting is fixed
// and regular, so this stays simple).
defined('FROM_API') || die();

$spec_path = __DIR__ . '/openapi.yaml';
$raw = is_readable($spec_path) ? file($spec_path, FILE_IGNORE_NEW_LINES) : [];

$api_title   = 'ITFlow API v1';
$api_version = '';
$endpoints   = [];          // [ ['path'=>, 'method'=>, 'summary'=>, 'tag'=>], ... ]
$tag_order   = [];          // tag names in first-seen order

$in_paths = false;
$cur_path = null;
$cur_idx  = null;           // index into $endpoints for the current path+method
$http_methods = ['get', 'post', 'put', 'delete', 'patch'];

foreach ($raw as $line) {
    // Grab a couple of info fields (2-space indent, appear once).
    if ($api_version === '' && preg_match('/^  version:\s*(.+)$/', $line, $m)) {
        $api_version = trim($m[1]);
    }
    if (preg_match('/^  title:\s*(.+)$/', $line, $m)) {
        $api_title = trim($m[1]);
    }

    if (preg_match('/^paths:\s*$/', $line)) { $in_paths = true; continue; }
    if (!$in_paths) continue;
    // A new top-level key (column 0, non-space) ends the paths block.
    if (preg_match('/^\S/', $line)) break;

    // Path key: exactly two leading spaces then a "/...:".
    if (preg_match('#^  (/[^:]*):\s*$#', $line, $m)) {
        $cur_path = $m[1];
        $cur_idx  = null;
        continue;
    }
    // HTTP method key: four leading spaces.
    if ($cur_path !== null && preg_match('/^    ([a-z]+):\s*$/', $line, $m) && in_array($m[1], $http_methods, true)) {
        $endpoints[] = ['path' => $cur_path, 'method' => strtoupper($m[1]), 'summary' => '', 'tag' => 'Other'];
        $cur_idx = array_key_last($endpoints);
        continue;
    }
    // summary / tags: six leading spaces, belong to the current method.
    if ($cur_idx !== null && preg_match('/^      summary:\s*(.+)$/', $line, $m)) {
        $endpoints[$cur_idx]['summary'] = trim($m[1]);
        continue;
    }
    if ($cur_idx !== null && preg_match('/^      tags:\s*\[([^\]]*)\]/', $line, $m)) {
        $tag = trim(explode(',', $m[1])[0]);
        if ($tag !== '') {
            $endpoints[$cur_idx]['tag'] = $tag;
            if (!in_array($tag, $tag_order, true)) $tag_order[] = $tag;
        }
    }
}

// Group endpoints by tag, preserving first-seen tag order.
$by_tag = [];
foreach ($endpoints as $ep) {
    $by_tag[$ep['tag']][] = $ep;
}
if (!in_array('Other', $tag_order, true) && isset($by_tag['Other'])) $tag_order[] = 'Other';

$method_class = [
    'GET'    => 'get',
    'POST'   => 'post',
    'PUT'    => 'put',
    'DELETE' => 'delete',
    'PATCH'  => 'put',
];

$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($api_title) ?> — API Reference</title>
<style>
  :root {
    --bg: #f6f7f9; --card: #ffffff; --fg: #1c2430; --muted: #667085;
    --border: #e3e6ea; --accent: #2563eb; --code-bg: #eef1f5;
    --get: #0a7d3f; --get-bg: #e6f4ec;
    --post: #1b4fd8; --post-bg: #e6ecfd;
    --put: #a8600a; --put-bg: #fbf0df;
    --delete: #c1341a; --delete-bg: #fbe7e3;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #12161c; --card: #1a1f27; --fg: #e6e9ee; --muted: #9aa4b2;
      --border: #2a313b; --accent: #6ea0ff; --code-bg: #222834;
      --get: #57d98a; --get-bg: #16311f;
      --post: #8fb0ff; --post-bg: #1a2340;
      --put: #e0b25f; --put-bg: #352a13;
      --delete: #f08a75; --delete-bg: #3a1f19;
    }
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; background: var(--bg); color: var(--fg);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    line-height: 1.5; -webkit-font-smoothing: antialiased;
  }
  .wrap { max-width: 960px; margin: 0 auto; padding: 32px 20px 80px; }
  header.page { margin-bottom: 8px; }
  h1 { font-size: 1.7rem; margin: 0 0 4px; }
  .ver { color: var(--muted); font-size: .9rem; }
  .lead { color: var(--muted); margin: 14px 0 22px; }
  a { color: var(--accent); }
  .panel {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 10px; padding: 16px 18px; margin: 16px 0;
  }
  .panel h2 { font-size: 1rem; margin: 0 0 8px; }
  code, .mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: .86rem;
  }
  code { background: var(--code-bg); padding: 1px 6px; border-radius: 5px; }
  .toc { display: flex; flex-wrap: wrap; gap: 8px; margin: 18px 0 6px; }
  .toc a {
    text-decoration: none; font-size: .82rem; padding: 4px 10px;
    border: 1px solid var(--border); border-radius: 999px; background: var(--card);
    color: var(--fg);
  }
  section.tag { margin-top: 30px; }
  section.tag > h2 {
    font-size: 1.15rem; margin: 0 0 4px; padding-bottom: 6px;
    border-bottom: 2px solid var(--border);
  }
  .rows { border: 1px solid var(--border); border-radius: 10px; overflow: hidden; margin-top: 10px; }
  .ep {
    display: grid; grid-template-columns: 74px minmax(0, 1fr); gap: 12px;
    align-items: start; padding: 11px 14px; background: var(--card);
    border-top: 1px solid var(--border);
  }
  .ep:first-child { border-top: none; }
  .badge {
    display: inline-block; text-align: center; font-weight: 700;
    font-size: .68rem; letter-spacing: .04em; padding: 4px 0; border-radius: 6px;
    font-family: ui-monospace, monospace;
  }
  .badge.get { color: var(--get); background: var(--get-bg); }
  .badge.post { color: var(--post); background: var(--post-bg); }
  .badge.put { color: var(--put); background: var(--put-bg); }
  .badge.delete { color: var(--delete); background: var(--delete-bg); }
  .ep .path { font-weight: 600; word-break: break-all; }
  .ep .desc { color: var(--muted); font-size: .88rem; margin-top: 2px; }
  .overflow { overflow-x: auto; }
  footer { margin-top: 44px; color: var(--muted); font-size: .8rem; }
</style>
</head>
<body>
<div class="wrap">
  <header class="page">
    <h1><?= $e($api_title) ?></h1>
    <div class="ver">OpenAPI reference<?= $api_version !== '' ? ' &middot; v' . $e($api_version) : '' ?></div>
  </header>

  <p class="lead">
    Companion REST API for the ITFlow MSP mobile app and integrations. This page is
    generated from <a href="openapi">the OpenAPI spec</a> and lists every live endpoint.
    Base path: <code>/api/v1</code>.
  </p>

  <div class="panel">
    <h2>Authentication</h2>
    <p style="margin:.2rem 0 .6rem">Send one of:</p>
    <ul style="margin:.2rem 0 .4rem; padding-left:1.2rem">
      <li><code>Authorization: Bearer &lt;token&gt;</code> — a user token from <code>POST /api/v1/auth</code>.</li>
      <li><code>X-Api-Key: &lt;key&gt;</code> — a legacy instance API key (denied on <code>/credentials</code> and <code>/me</code>).</li>
    </ul>
    <p style="margin:.4rem 0 0; color:var(--muted); font-size:.86rem">
      <code>/auth</code>, <code>/openapi</code> and <code>/docs</code> are public. Errors return
      <code>{"error": "..."}</code>; list endpoints return <code>{"data": [...], "total": N}</code>;
      creates return <code>{"id": N}</code>; simple mutations return <code>{"ok": true}</code>.
      Per-caller rate limit is 300 requests/60s (429 with <code>Retry-After</code>).
    </p>
  </div>

  <div class="panel">
    <h2>Machine-readable spec</h2>
    <p style="margin:.2rem 0 0">
      <a href="openapi"><code>GET /api/v1/openapi</code></a> returns this API as OpenAPI 3.0 YAML —
      import it into Swagger UI, Postman, or Insomnia. The raw file is also at
      <a href="openapi.yaml"><code>/api/v1/openapi.yaml</code></a>.
    </p>
  </div>

  <?php if ($endpoints): ?>
  <nav class="toc">
    <?php foreach ($tag_order as $tag): if (empty($by_tag[$tag])) continue; ?>
      <a href="#tag-<?= $e(rawurlencode($tag)) ?>"><?= $e($tag) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php foreach ($tag_order as $tag): if (empty($by_tag[$tag])) continue; ?>
    <section class="tag" id="tag-<?= $e(rawurlencode($tag)) ?>">
      <h2><?= $e($tag) ?></h2>
      <div class="rows overflow">
        <?php foreach ($by_tag[$tag] as $ep):
          $cls = $method_class[$ep['method']] ?? 'get'; ?>
          <div class="ep">
            <span class="badge <?= $e($cls) ?>"><?= $e($ep['method']) ?></span>
            <div>
              <div class="path mono"><?= $e($ep['path']) ?></div>
              <?php if ($ep['summary'] !== ''): ?>
                <div class="desc"><?= $e($ep['summary']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <?php else: ?>
  <div class="panel"><h2>Spec unavailable</h2><p>Could not read openapi.yaml.</p></div>
  <?php endif; ?>

  <footer>
    <?= count($endpoints) ?> operations across <?= count(array_filter($tag_order, fn($t) => !empty($by_tag[$t]))) ?> groups.
    Generated from <code>openapi.yaml</code>.
  </footer>
</div>
</body>
</html>
<?php
exit;
