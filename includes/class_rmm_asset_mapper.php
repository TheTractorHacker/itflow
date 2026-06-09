<?php
/*
 * RmmAssetMapper — matches Tactical RMM agents to ITFlow assets and syncs data.
 *
 * Match priority:
 *   1. tactical_agent_id already in asset_rmm_links (already linked)
 *   2. asset_serial match
 *   3. MAC address match via asset_interfaces
 *   4. Case-insensitive hostname match on asset_name (only if unique)
 */

class RmmAssetMapper {

    private $mysqli;
    private int $integration_id;
    private int $triggered_by;

    public function __construct($mysqli, int $integration_id, int $triggered_by = 0) {
        $this->mysqli         = $mysqli;
        $this->integration_id = $integration_id;
        $this->triggered_by   = $triggered_by;
    }

    public function syncAgents(array $agents): array {
        $stats = ['created' => 0, 'updated' => 0, 'matched' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($agents as $agent) {
            try {
                $result = $this->syncAgent($agent);
                $stats[$result]++;
            } catch (Exception $e) {
                $stats['errors'][] = ($agent['hostname'] ?? 'unknown') . ': ' . $e->getMessage();
                $stats['skipped']++;
            }
        }
        return $stats;
    }

    private function syncAgent(array $agent): string {
        $m            = $this->mysqli;
        $intg_id      = $this->integration_id;
        $agent_id     = sanitizeInput($agent['agent_id'] ?? $agent['id'] ?? '');
        $hostname     = sanitizeInput($agent['hostname'] ?? '');
        $serial       = sanitizeInput($agent['serial_number'] ?? '');
        $os_name      = sanitizeInput($agent['operating_system'] ?? '');
        $os_version   = sanitizeInput($agent['os_version'] ?? $agent['os_build_number'] ?? '');
        $manufacturer = sanitizeInput($agent['manufacturer'] ?? $agent['make_model'] ?? '');
        $model        = '';
        $cpu          = sanitizeInput($agent['cpu'] ?? $agent['cpu_model'] ?? '');
        $ram_gb       = sanitizeInput($agent['ram'] ?? $agent['total_ram'] ?? '');
        $mesh_node_id = sanitizeInput($agent['mesh_node_id'] ?? '');
        $_s = $agent['status'] ?? 'offline';
        $status = in_array($_s, ['online','offline','unknown']) ? $_s : ($_s === 'online' ? 'online' : 'offline');
        $last_seen    = sanitizeInput($agent['last_seen'] ?? '');
        $logged_user  = sanitizeInput($agent['logged_in_user'] ?? $agent['logged_in_username'] ?? '');
        $client_id    = 0; // We don't auto-assign clients; that's a manual step
        $raw_json     = mysqli_real_escape_string($m, json_encode($agent));

        if (empty($agent_id) || empty($hostname)) {
            return 'skipped';
        }

        // Normalize last_seen to MySQL datetime
        $last_seen_sql = '';
        if (!empty($last_seen)) {
            $ts = strtotime($last_seen);
            $last_seen_sql = $ts ? date('Y-m-d H:i:s', $ts) : '';
        }
        $last_seen_val = $last_seen_sql ? "'$last_seen_sql'" : 'NULL';

        // ----- Step 1: Check existing link -----
        $existing = mysqli_fetch_assoc(mysqli_query($m,
            "SELECT id, asset_id FROM asset_rmm_links
             WHERE integration_id=$intg_id AND tactical_agent_id='$agent_id'"
        ));

        if ($existing) {
            // Already linked — just update the cached data
            $this->updateLink($existing['id'], $status, $last_seen_val, $os_name, $os_version,
                $manufacturer, $model, $cpu, $ram_gb, $logged_user, $mesh_node_id, $raw_json);
            return 'updated';
        }

        // ----- Step 2: Try to match an existing ITFlow asset -----
        $asset_id = 0;

        // 2a: serial number
        if (!$asset_id && !empty($serial)) {
            $row = mysqli_fetch_assoc(mysqli_query($m,
                "SELECT asset_id FROM assets WHERE asset_serial='$serial' AND asset_archived_at IS NULL LIMIT 1"
            ));
            if ($row) { $asset_id = intval($row['asset_id']); }
        }

        // 2b: MAC address
        if (!$asset_id) {
            $macs = $this->extractMacs($agent);
            foreach ($macs as $mac) {
                if (empty($mac)) continue;
                $mac_esc = mysqli_real_escape_string($m, strtolower($mac));
                $row = mysqli_fetch_assoc(mysqli_query($m,
                    "SELECT a.asset_id FROM assets a
                     JOIN asset_interfaces ai ON ai.interface_asset_id = a.asset_id
                     WHERE LOWER(ai.interface_mac)='$mac_esc' AND a.asset_archived_at IS NULL LIMIT 1"
                ));
                if ($row) { $asset_id = intval($row['asset_id']); break; }
            }
        }

        // 2c: hostname match (unique only)
        if (!$asset_id && !empty($hostname)) {
            $h = mysqli_real_escape_string($m, $hostname);
            $count_row = mysqli_fetch_assoc(mysqli_query($m,
                "SELECT COUNT(*) as cnt FROM assets WHERE LOWER(asset_name)=LOWER('$h') AND asset_archived_at IS NULL"
            ));
            if (intval($count_row['cnt']) === 1) {
                $row = mysqli_fetch_assoc(mysqli_query($m,
                    "SELECT asset_id FROM assets WHERE LOWER(asset_name)=LOWER('$h') AND asset_archived_at IS NULL LIMIT 1"
                ));
                if ($row) { $asset_id = intval($row['asset_id']); }
            }
        }

        // ----- Step 3: Create new ITFlow asset if still no match -----
        if (!$asset_id) {
            $asset_type = $this->guessAssetType($os_name);
            $h = mysqli_real_escape_string($m, $hostname);
            $s = mysqli_real_escape_string($m, $serial);
            $o = mysqli_real_escape_string($m, "$os_name $os_version");
            $mk = mysqli_real_escape_string($m, $manufacturer);
            mysqli_query($m,
                "INSERT INTO assets SET
                 asset_type='$asset_type',
                 asset_name='$h',
                 asset_serial='$s',
                 asset_os='$o',
                 asset_make='$mk',
                 asset_status='Active',
                 asset_created_at=NOW()"
            );
            $asset_id = intval(mysqli_insert_id($m));
            $created = 'created';
        } else {
            $created = 'matched';
        }

        // ----- Step 4: Insert the link row -----
        mysqli_query($m,
            "INSERT INTO asset_rmm_links SET
             asset_id=$asset_id,
             integration_id=$intg_id,
             tactical_agent_id='$agent_id',
             hostname='$hostname',
             mesh_node_id='$mesh_node_id',
             rmm_status='$status',
             last_seen=$last_seen_val,
             os_name='$os_name',
             os_version='$os_version',
             manufacturer='$manufacturer',
             model='$model',
             cpu='$cpu',
             ram_gb='$ram_gb',
             logged_in_user='$logged_user',
             last_sync=NOW(),
             raw_data_json='$raw_json'"
        );

        return $created;
    }

    private function updateLink(int $link_id, string $status, string $last_seen_val,
        string $os_name, string $os_version, string $manufacturer, string $model,
        string $cpu, string $ram_gb, string $logged_user, string $mesh_node_id, string $raw_json): void
    {
        $m = $this->mysqli;
        mysqli_query($m,
            "UPDATE asset_rmm_links SET
             rmm_status='$status',
             last_seen=$last_seen_val,
             os_name='$os_name',
             os_version='$os_version',
             manufacturer='$manufacturer',
             model='$model',
             cpu='$cpu',
             ram_gb='$ram_gb',
             logged_in_user='$logged_user',
             mesh_node_id='$mesh_node_id',
             last_sync=NOW(),
             raw_data_json='$raw_json'
             WHERE id=$link_id"
        );
    }

    private function extractMacs(array $agent): array {
        $macs = [];
        // Tactical stores network info in various places depending on version
        if (!empty($agent['local_ips'])) {
            // older format: comma list of IPs, no MAC — skip
        }
        if (!empty($agent['wmi_detail']['network_config'])) {
            foreach ($agent['wmi_detail']['network_config'] as $nic) {
                if (!empty($nic['MACAddress'])) {
                    $macs[] = strtolower(str_replace('-', ':', $nic['MACAddress']));
                }
            }
        }
        return $macs;
    }

    private function guessAssetType(string $os_name): string {
        $os = strtolower($os_name);
        if (str_contains($os, 'server')) return 'Server';
        if (str_contains($os, 'linux'))  return 'Server';
        return 'Desktop';
    }

    public function startSyncLog(): int {
        global $mysqli;
        mysqli_query($mysqli, "INSERT INTO rmm_sync_log SET integration_id={$this->integration_id}, triggered_by={$this->triggered_by}");
        return intval(mysqli_insert_id($mysqli));
    }

    public function finishSyncLog(int $log_id, array $stats): void {
        global $mysqli;
        $status   = empty($stats['errors']) ? 'success' : 'failed';
        $created  = intval($stats['created']);
        $updated  = intval($stats['updated']);
        $matched  = intval($stats['matched']);
        $skipped  = intval($stats['skipped']);
        $errors   = mysqli_real_escape_string($mysqli, implode('; ', $stats['errors']));
        mysqli_query($mysqli, "UPDATE rmm_sync_log SET
            finished_at=NOW(), status='$status',
            assets_created=$created, assets_updated=$updated,
            assets_matched=$matched, assets_skipped=$skipped,
            errors='$errors'
            WHERE id=$log_id");
    }
}
