<?php
/*
 * getRmmClient(integration_id) — returns the correct RMM client instance.
 *
 * Currently supports:
 *   tactical_rmm  → TacticalRmmClient
 *   level         → LevelRmmClient
 *
 * Callers require this file; they don't need to know which class to use.
 */

function getRmmClient(int $integration_id): object {
    global $mysqli;
    $id  = intval($integration_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT type FROM rmm_integrations WHERE id=$id LIMIT 1"
    ));
    if (!$row) {
        throw new RuntimeException("RMM integration $id not found");
    }
    switch ($row['type']) {
        case 'level':
            require_once __DIR__ . '/class_level_rmm.php';
            return new LevelRmmClient($id);
        case 'tactical_rmm':
        default:
            require_once __DIR__ . '/class_tactical_rmm.php';
            return new TacticalRmmClient($id);
    }
}
