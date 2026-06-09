<?php
/*
 * LevelRmmClient — server-side service for Level.io RMM API calls.
 *
 * API key is decrypted once in the constructor and never exposed to the browser.
 *
 * Level.io API reference: https://levelapi.readme.io/reference/getting-started-with-your-api
 *
 * Authentication : X-API-Key header
 * Base URL       : https://app.level.io  (stored in rmm_integrations.api_url)
 * Org ID         : stored in rmm_integrations.web_url (repurposed for Level)
 */

class LevelRmmClient {

    private string $base_url;
    private string $org_id;
    private string $api_key;
    private int    $integration_id;

    public function __construct(int $integration_id) {
        global $mysqli;
        $id  = intval($integration_id);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,
            "SELECT * FROM rmm_integrations WHERE id=$id AND enabled=1 AND type='level'"
        ));
        if (!$row) {
            throw new RuntimeException("Level RMM integration $id not found or disabled");
        }
        $this->integration_id = $id;
        $this->base_url       = rtrim($row['api_url'], '/');
        $this->org_id         = trim($row['web_url'] ?? '');
        $this->api_key        = decryptSetting($row['api_key_enc'] ?? '');
        if (empty($this->api_key)) {
            throw new RuntimeException("Level RMM integration $id has no decryptable API key");
        }
    }

    private function get(string $endpoint): array {
        return $this->request('GET', $endpoint);
    }

    private function post(string $endpoint, array $body = []): array {
        return $this->request('POST', $endpoint, $body);
    }

    private function request(string $method, string $endpoint, array $body = []): array {
        $url = $this->base_url . $endpoint;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'X-API-Key: ' . $this->api_key,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException("Level API cURL error: $err");
        }
        if ($status === 401 || $status === 403) {
            throw new RuntimeException("Level API: HTTP $status — check API key");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Level API returned HTTP $status: " . substr($raw, 0, 200));
        }
        $decoded = json_decode($raw, true);
        if ($decoded === null && !empty($raw)) {
            throw new RuntimeException("Invalid JSON response from Level API");
        }
        return $decoded ?? [];
    }

    // -----------------------------------------------------------------------
    // Connection test
    // -----------------------------------------------------------------------

    public function testConnection(): bool {
        $result = $this->get('/v1/devices?per_page=1');
        return is_array($result);
    }

    // -----------------------------------------------------------------------
    // Agents / Devices
    // -----------------------------------------------------------------------

    public function getAgents(): array {
        $all      = [];
        $page     = 1;
        $per_page = 100;
        do {
            $result = $this->get("/v1/devices?per_page=$per_page&page=$page");
            $items  = $result['data'] ?? (isset($result[0]) ? $result : []);
            foreach ($items as $dev) {
                $all[] = $this->normalizeDevice($dev);
            }
            $total_pages = $result['meta']['last_page'] ?? 1;
            $page++;
        } while ($page <= $total_pages && count($items) >= $per_page);
        return $all;
    }

    public function getAgent(string $agent_id): array {
        $result = $this->get('/v1/devices/' . urlencode($agent_id));
        $dev    = $result['data'] ?? $result;
        return $this->normalizeDevice($dev);
    }

    public function getAgentSoftware(string $agent_id): array {
        try {
            $result = $this->get('/v1/devices/' . urlencode($agent_id) . '/software');
            return $result['data'] ?? $result ?? [];
        } catch (RuntimeException $e) {
            return [];
        }
    }

    public function getAgentServices(string $agent_id): array {
        try {
            $result = $this->get('/v1/devices/' . urlencode($agent_id) . '/services');
            return $result['data'] ?? $result ?? [];
        } catch (RuntimeException $e) {
            return [];
        }
    }

    public function getAgentWmi(string $agent_id): array {
        try {
            $agent = $this->getAgent($agent_id);
            return $agent['hardware'] ?? [];
        } catch (RuntimeException $e) {
            return [];
        }
    }

    // -----------------------------------------------------------------------
    // Alerts
    // -----------------------------------------------------------------------

    public function getAlerts(bool $resolved = false): array {
        try {
            $param  = $resolved ? '' : '?status=active';
            $result = $this->get('/v1/alerts' . $param);
            return $result['data'] ?? $result ?? [];
        } catch (RuntimeException $e) {
            return [];
        }
    }

    public function getAgentAlerts(string $agent_id): array {
        try {
            $result = $this->get('/v1/alerts?device_id=' . urlencode($agent_id));
            return $result['data'] ?? $result ?? [];
        } catch (RuntimeException $e) {
            return [];
        }
    }

    // -----------------------------------------------------------------------
    // Scripts
    // -----------------------------------------------------------------------

    public function getScripts(): array {
        try {
            $result = $this->get('/v1/scripts');
            return $result['data'] ?? $result ?? [];
        } catch (RuntimeException $e) {
            return [];
        }
    }

    public function runScript(string $agent_id, int $script_id, int $timeout = 120): array {
        return $this->post('/v1/devices/' . urlencode($agent_id) . '/scripts/' . $script_id . '/run', [
            'timeout' => $timeout,
        ]);
    }

    // -----------------------------------------------------------------------
    // URL helpers
    // -----------------------------------------------------------------------

    public function buildDeviceUrl(string $agent_id): string {
        return 'https://app.level.io/devices/' . urlencode($agent_id);
    }

    public function getIntegrationId(): int {
        return $this->integration_id;
    }

    // -----------------------------------------------------------------------
    // Field normalisation — map Level device fields to common schema
    // -----------------------------------------------------------------------

    private function normalizeDevice(array $dev): array {
        return [
            'id'               => $dev['id'] ?? $dev['device_id'] ?? '',
            'agent_id'         => $dev['id'] ?? $dev['device_id'] ?? '',
            'hostname'         => $dev['hostname'] ?? $dev['name'] ?? '',
            'description'      => $dev['description'] ?? '',
            'status'           => $this->mapStatus($dev['status'] ?? $dev['connection_status'] ?? 'unknown'),
            'last_seen'        => $dev['last_seen'] ?? $dev['last_contact'] ?? null,
            'logged_in_user'   => $dev['logged_in_user'] ?? $dev['current_user'] ?? '',
            'operating_system' => $dev['os_name'] ?? $dev['os'] ?? '',
            'os_version'       => $dev['os_version'] ?? '',
            'plat'             => $dev['platform'] ?? '',
            'manufacturer'     => $dev['manufacturer'] ?? '',
            'model'            => $dev['model'] ?? '',
            'cpu'              => $dev['cpu'] ?? $dev['processor'] ?? '',
            'ram'              => isset($dev['ram_total_gb']) ? $dev['ram_total_gb'].' GB'
                                : (isset($dev['memory_gb'])   ? $dev['memory_gb'].' GB' : ''),
            'serial_number'    => $dev['serial_number'] ?? $dev['serial'] ?? '',
            'local_ips'        => $dev['local_ips'] ?? $dev['ip_addresses'] ?? [],
            'tags'             => $dev['tags'] ?? $dev['labels'] ?? [],
            '_provider'        => 'level',
            '_raw'             => $dev,
        ];
    }

    private function mapStatus(string $status): string {
        $map = [
            'online'       => 'online',
            'connected'    => 'online',
            'active'       => 'online',
            'offline'      => 'offline',
            'disconnected' => 'offline',
            'inactive'     => 'offline',
        ];
        return $map[strtolower($status)] ?? 'unknown';
    }
}
