<?php
/*
 * RmmAssetMapper — matches RMM agents (Tactical or Level) to ITFlow assets.
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
    private $rmmClient;

    public function __construct($mysqli, int $integration_id, int $triggered_by = 0, $rmmClient = null) {
        $this->mysqli         = $mysqli;
        $this->integration_id = $integration_id;
        $this->triggered_by   = $triggered_by;
        $this->rmmClient      = $rmmClient;
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
        $m       = $this->mysqli;
        $intg_id = $this->integration_id;

        // Coerce any field that might be an array (e.g. local_ips, tags) to a plain string
        $str = function ($v): string {
            if (is_array($v))  return implode(', ', array_filter(array_map('strval', $v)));
            if (is_bool($v))   return $v ? '1' : '0';
            return (string) ($v ?? '');
        };

        $agent_id     = sanitizeInput($str($agent['agent_id'] ?? $agent['id'] ?? ''));
        $hostname     = sanitizeInput($str($agent['hostname'] ?? ''));
        $serial       = sanitizeInput($str($agent['serial_number'] ?? ''));
        $os_name      = sanitizeInput($str($agent['operating_system'] ?? ''));
        $os_version   = sanitizeInput($str($agent['os_version'] ?? $agent['os_build_number'] ?? ''));
        $manufacturer = sanitizeInput($str($agent['manufacturer'] ?? $agent['make_model'] ?? ''));
        $model        = sanitizeInput($str($agent['model'] ?? ''));
        $cpu          = sanitizeInput($str($agent['cpu'] ?? $agent['cpu_model'] ?? ''));
        $ram_gb       = sanitizeInput($str($agent['ram'] ?? $agent['total_ram'] ?? ''));
        $mesh_node_id = sanitizeInput($str($agent['mesh_node_id'] ?? ''));
        $last_seen    = sanitizeInput($str($agent['last_seen'] ?? ''));
        $logged_user  = sanitizeInput($str($agent['logged_in_user'] ?? $agent['logged_in_username'] ?? ''));

        $_s     = $agent['status'] ?? 'offline';
        $status = in_array($_s, ['online', 'offline', 'unknown']) ? $_s : 'offline';

        $client_id = 0;
        $raw_json  = mysqli_real_escape_string($m, json_encode($agent));

        if (empty($agent_id) || empty($hostname)) {
            return 'skipped';
        }

        // Normalize last_seen to MySQL datetime
        $last_seen_val = 'NULL';
        if (!empty($last_seen)) {
            $ts = strtotime($last_seen);
            if ($ts) { $last_seen_val = "'" . date('Y-m-d H:i:s', $ts) . "'"; }
        }

        // ----- Step 1: Check existing link -----
        $existing = mysqli_fetch_assoc(mysqli_query($m,
            "SELECT id, asset_id, rmm_status FROM asset_rmm_links
             WHERE integration_id=$intg_id AND tactical_agent_id='$agent_id'"
        ));

        if ($existing) {
            $this->updateLink($existing['id'], $existing['rmm_status'], $status, $last_seen_val, $os_name, $os_version,
                $manufacturer, $model, $cpu, $ram_gb, $logged_user, $mesh_node_id, $raw_json);
            $this->syncInterfaces(intval($existing['asset_id']), $this->resolveWmiDetail($agent, $agent_id));
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
            foreach ($this->extractMacs($agent) as $mac) {
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
            $h    = mysqli_real_escape_string($m, $hostname);
            $cnt  = intval(mysqli_fetch_assoc(mysqli_query($m,
                "SELECT COUNT(*) as c FROM assets WHERE LOWER(asset_name)=LOWER('$h') AND asset_archived_at IS NULL"
            ))['c']);
            if ($cnt === 1) {
                $row = mysqli_fetch_assoc(mysqli_query($m,
                    "SELECT asset_id FROM assets WHERE LOWER(asset_name)=LOWER('$h') AND asset_archived_at IS NULL LIMIT 1"
                ));
                if ($row) { $asset_id = intval($row['asset_id']); }
            }
        }

        // ----- Step 3: Create new ITFlow asset if no match -----
        if (!$asset_id) {
            $asset_type = $this->guessAssetType($os_name);
            $h  = mysqli_real_escape_string($m, $hostname);
            $s  = mysqli_real_escape_string($m, $serial);
            $o  = mysqli_real_escape_string($m, trim("$os_name $os_version"));
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
            $outcome  = 'created';
        } else {
            $outcome = 'matched';
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

        $this->syncInterfaces($asset_id, $this->resolveWmiDetail($agent, $agent_id));

        return $outcome;
    }

    private function updateLink(int $link_id, ?string $old_status, string $status, string $last_seen_val,
        string $os_name, string $os_version, string $manufacturer, string $model,
        string $cpu, string $ram_gb, string $logged_user, string $mesh_node_id, string $raw_json): void
    {
        $m = $this->mysqli;
        $status_changed_sql = ($old_status !== null && $old_status !== $status)
            ? "rmm_status_changed_at=NOW(),"
            : "";
        mysqli_query($m,
            "UPDATE asset_rmm_links SET
             rmm_status='$status',
             $status_changed_sql
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

    private function resolveWmiDetail(array $agent, string $agent_id): array {
        if (!empty($agent['wmi_detail'])) {
            return $agent['wmi_detail'];
        }
        // The /agents/ list endpoint doesn't include wmi_detail — fetch agent detail
        if ($this->rmmClient) {
            try {
                $detail = $this->rmmClient->getAgentWmi($agent_id);
                return $detail['wmi_detail'] ?? [];
            } catch (\Throwable $e) {
                return [];
            }
        }
        return [];
    }

    private function syncInterfaces(int $asset_id, array $wmiDetail): void {
        $m = $this->mysqli;
        $netConfig = $wmiDetail['network_config'] ?? [];

        foreach ($netConfig as $entry) {
            // WMI returns each adapter wrapped in its own single-element array
            $nic = (is_array($entry) && isset($entry[0]) && is_array($entry[0])) ? $entry[0] : $entry;
            if (!is_array($nic)) continue;

            $ips = $nic['IPAddress'] ?? null;
            if (empty($ips)) continue; // skip adapters with no configured IP

            $caption = (string) ($nic['Description'] ?? $nic['Caption'] ?? '');
            $mac     = strtolower(str_replace('-', ':', (string) ($nic['MACAddress'] ?? '')));

            if (str_contains(strtolower($caption), 'bluetooth')) continue;

            if (preg_match('/wi-?fi|wireless/i', $caption)) {
                $type = 'WiFi';
            } elseif (preg_match('/vmware|hyper-v|virtual|tap-windows|tunnel|wintun|miniport|kernel debug|ras async|loopback/i', $caption)) {
                $type = 'Virtual';
            } else {
                $type = 'Ethernet';
            }

            $ipv4 = ''; $ipv6 = '';
            foreach ((array) $ips as $ip) {
                if (str_contains((string) $ip, ':')) {
                    if (!$ipv6) $ipv6 = $ip;
                } else {
                    if (!$ipv4) $ipv4 = $ip;
                }
            }

            $name = preg_replace('/^\[\d+\]\s*/', '', $caption);
            $name = mysqli_real_escape_string($m, substr($name, 0, 200));
            $type_esc = mysqli_real_escape_string($m, $type);
            $mac_esc  = mysqli_real_escape_string($m, $mac);
            $ipv4_esc = mysqli_real_escape_string($m, $ipv4);
            $ipv6_esc = mysqli_real_escape_string($m, $ipv6);

            $existing = null;
            if ($mac) {
                $existing = mysqli_fetch_assoc(mysqli_query($m,
                    "SELECT interface_id FROM asset_interfaces
                     WHERE interface_asset_id=$asset_id AND LOWER(interface_mac)='$mac_esc' LIMIT 1"
                ));
            }
            if (!$existing) {
                $existing = mysqli_fetch_assoc(mysqli_query($m,
                    "SELECT interface_id FROM asset_interfaces
                     WHERE interface_asset_id=$asset_id AND interface_name='$name' AND interface_archived_at IS NULL LIMIT 1"
                ));
            }

            if ($existing) {
                mysqli_query($m,
                    "UPDATE asset_interfaces SET
                     interface_type='$type_esc',
                     interface_mac='$mac_esc',
                     interface_ip='$ipv4_esc',
                     interface_ipv6='$ipv6_esc'
                     WHERE interface_id={$existing['interface_id']}"
                );
            } else {
                mysqli_query($m,
                    "INSERT INTO asset_interfaces SET
                     interface_asset_id=$asset_id,
                     interface_name='$name',
                     interface_type='$type_esc',
                     interface_mac='$mac_esc',
                     interface_ip='$ipv4_esc',
                     interface_ipv6='$ipv6_esc',
                     interface_primary=0"
                );
            }
        }
    }

    private function extractMacs(array $agent): array {
        $macs = [];
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
        mysqli_query($mysqli,
            "INSERT INTO rmm_sync_log SET integration_id={$this->integration_id}, triggered_by={$this->triggered_by}"
        );
        return intval(mysqli_insert_id($mysqli));
    }

    public function finishSyncLog(int $log_id, array $stats): void {
        global $mysqli;
        $status  = empty($stats['errors']) ? 'success' : 'failed';
        $errors  = mysqli_real_escape_string($mysqli, implode('; ', $stats['errors']));
        mysqli_query($mysqli,
            "UPDATE rmm_sync_log SET
             finished_at=NOW(), status='$status',
             assets_created={$stats['created']}, assets_updated={$stats['updated']},
             assets_matched={$stats['matched']}, assets_skipped={$stats['skipped']},
             errors='$errors'
             WHERE id=$log_id"
        );
    }
}
