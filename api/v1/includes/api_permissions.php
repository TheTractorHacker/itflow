<?php
defined('FROM_API') || die();

/**
 * Mirrors the web app's enforceUserPermission() for the token-based API context, where
 * $_SESSION isn't populated. Ends the request with a 403 if the signed-in API user's role
 * lacks at least $min_level access to $module_name. Admin roles always pass.
 */
function api_require_module_permission(mysqli $mysqli, int $api_user_id, string $module_name, int $min_level = 1): void {
    $role = mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT u.user_role_id, r.role_is_admin
         FROM users u LEFT JOIN user_roles r ON r.role_id = u.user_role_id
         WHERE u.user_id = $api_user_id LIMIT 1"
    ));
    if ($role && $role['role_is_admin']) return;

    $module_esc = mysqli_real_escape_string($mysqli, $module_name);
    $has_perm = $role ? mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT urp.user_role_permission_level
         FROM user_role_permissions urp
         JOIN modules m ON m.module_id = urp.module_id
         WHERE urp.user_role_id = {$role['user_role_id']}
           AND m.module_name = '$module_esc'
           AND urp.user_role_permission_level >= $min_level
         LIMIT 1"
    )) : null;

    if (!$has_perm) {
        api_error(403, 'Insufficient permissions');
    }
}
