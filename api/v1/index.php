<?php
define('FROM_API', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Biometric');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$DOCUMENT_ROOT = realpath(__DIR__ . '/../../');
$_SERVER['DOCUMENT_ROOT'] = $DOCUMENT_ROOT;

require_once $DOCUMENT_ROOT . '/config.php';
require_once $DOCUMENT_ROOT . '/includes/db.php';
require_once $DOCUMENT_ROOT . '/functions.php';
require_once $DOCUMENT_ROOT . '/includes/load_company_settings.php';

function api_response(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function api_error(int $code, string $message): void {
    api_response($code, ['error' => $message]);
}

// Parse route
$uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base     = '/api/v1';
$path     = preg_replace('#^' . preg_quote($base, '#') . '#', '', $uri);
$segments = array_values(array_filter(explode('/', $path)));
$method   = $_SERVER['REQUEST_METHOD'];
$resource = $segments[0] ?? '';
$id       = isset($segments[1]) && is_numeric($segments[1]) ? intval($segments[1]) : null;
$sub      = $id !== null ? ($segments[2] ?? null) : ($segments[1] ?? null);

// Public endpoint: auth
if ($resource === 'auth') {
    require __DIR__ . '/auth.php';
    exit;
}

// All other endpoints require Bearer token
$api_token_row  = null;
$api_user_id    = null;

// Apache often strips Authorization header — try multiple sources
$authHeader = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? '';
if (empty($authHeader) && function_exists('getallheaders')) {
    $hdrs = getallheaders();
    $authHeader = $hdrs['Authorization'] ?? $hdrs['authorization'] ?? '';
}

if (preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $m)) {
    $raw_token  = $m[1];
    $token_hash = hash('sha256', $raw_token);
    $esc        = mysqli_real_escape_string($mysqli, $token_hash);
    $sql        = mysqli_query($mysqli,
        "SELECT t.*, u.user_id, u.user_name, u.user_email, u.user_type
         FROM api_tokens t
         JOIN users u ON t.token_user_id = u.user_id
         WHERE t.token_hash = '$esc'
         LIMIT 1"
    );
    $api_token_row = mysqli_fetch_assoc($sql);
    if ($api_token_row) {
        $api_user_id    = intval($api_token_row['user_id']);
        $session_user_id       = $api_user_id;
        $session_name          = $api_token_row['user_name'];
        $session_company_id    = 1;
        mysqli_query($mysqli, "UPDATE api_tokens SET token_last_used_at = NOW() WHERE token_hash = '$esc'");
    }
}

if (!$api_user_id) {
    api_error(401, 'Unauthorized');
}

// Route
switch ($resource) {
    case 'dashboard':     require __DIR__ . '/dashboard.php';     break;
    case 'tickets':
        if ($sub === 'charges' || $sub === 'worksheets' || $sub === 'outtake') {
            require __DIR__ . '/worksheets.php';
        } else {
            require __DIR__ . '/tickets.php';
        }
        break;
    case 'statuses':      require __DIR__ . '/tickets.php';      break;
    case 'clients':
        if ($sub !== null && !is_numeric($sub ?? '')) {
            require __DIR__ . '/client_tabs.php';
        } else {
            require __DIR__ . '/clients.php';
        }
        break;
    case 'contacts':      require __DIR__ . '/contacts.php';      break;
    case 'assets':        require __DIR__ . '/assets.php';        break;
    case 'credentials':   require __DIR__ . '/credentials.php';   break;
    case 'quotes':        require __DIR__ . '/quotes.php';        break;
    case 'invoices':      require __DIR__ . '/invoices.php';      break;
    case 'expenses':      require __DIR__ . '/expenses.php';      break;
    case 'worksheets':        require __DIR__ . '/worksheets.php'; break;
    case 'worksheet-responses': require __DIR__ . '/worksheets.php'; break;
    case 'worksheet-templates': require __DIR__ . '/worksheets.php'; break;
    case 'products':    require __DIR__ . '/products.php'; break;
    case 'me':                  require __DIR__ . '/me.php'; break;
    case 'appointments': require __DIR__ . '/appointments.php'; break;
    case 'notifications': require __DIR__ . '/notifications.php'; break;
    default:              api_error(404, 'Not found');
}
