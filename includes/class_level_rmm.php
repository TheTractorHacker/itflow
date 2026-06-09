<?php
/*
 * LevelRmmClient — server-side service for Level.io RMM API calls.
 *
 * Level.io API:
 *   Base URL : https://api.level.io/v2   (api_url = https://api.level.io)
 *   Auth     : Authorization: {api_key}  (no Bearer prefix)
 *   Devices  : GET /v2/devices
 *   Alerts   : GET /v2/alerts
 *   Scripts  : GET /v2/scripts
 *
 * Device status is a boolean field "online" (true/false), not a string.
 * Client mapping uses device field "group_name" → ITFlow client name.
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
        $this->base_url       = rtrim($row['api_url'], '/') . '/v2';
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
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $this->api_key,   // no Bearer prefix
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw           = curl_exec($ch);
        $status        = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $err           = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException("Level API cURL error: $err");
        }
        if (strpos($raw, '<!DOCTYPE') !== false || strpos($raw, '<html') !== false) {
            throw new RuntimeException(
                "Level API URL is pointing to the web UI. Set API URL to: https://api.level.io"
            );
        }
        if ($status === 401 || $status === 403) {
            throw new RuntimeException("Level API: HTTP $status Unauthorized — check API key");
        }
        if ($status < 200 || $status >= 300) {
            $decoded = json_decode($raw, true);
            $msg     = $decoded['error'] ?? $decoded['message'] ?? substr($raw, 0, 200);
            throw new RuntimeException("Level API HTTP $status: $msg");
        }
        $decoded = json_decode($raw, true);
        if ($decoded === null && !empty($raw)) {
            throw new RuntimeException("Invalid JSON from Level API: " . substr($raw, 0, 200));
        }
        return $decoded ?? [];
    }

    // -----------------------------------------------------------------------
    // Connection test
    // -----------------------------------------------------------------------

    public function testConnection(): bool {
        $result = $this->get('/devices?per_page=1');
        return isset($result['data']) || is_array($result);
    }

    // -----------------------------------------------------------------------
    // Agents / Devices
    // -----------------------------------------------------------------------

    public function getAgents(): array {
        $all      = [];
        $page     = 1;
        $per_page = 100;
        do {
            $result = $this->get("/devices?per_page=$per_page&page=$page");
            $items  = $result['data'] ?? [];
            foreach ($items as $dev) {
                $all[] = $this->normalizeDevice($dev);
            }
            $meta  = $result['meta'] ?? [];
            $more  = !empty($meta['next_page']) || !empty($meta['next_cursor']);
            $page++;
        } while ($more && count($items) >= $per_page);
        return $all;
    }

    public function getAgent(string $agent_id): array {
        $result = $this->get('/devices/' . urlencode($agent_id));
        $dev    = $result['data'] ?? $result;
        return $this->normalizeDevice($dev);
    }

    public function getAgentSoftware(string $agent_id): array {
        try {
            $result = $this->get('/devices/' . urlencode($agent_id) . '/software');
            return $result['data'] ?? [];
        } catch (RuntimeException $e) { return []; }
    }

    public function getAgentServices(string $agent_id): array {
        try {
            $result = $this->get('/devices/' . urlencode($agent_id) . '/services');
            return $result['data'] ?? [];
        } catch (RuntimeException $e) { return []; }
    }

    public function getAgentWmi(string $agent_id): array {
        try {
            return $this->getAgent($agent_id)['hardware'] ?? [];
        } catch (RuntimeException $e) { return []; }
    }

    // -----------------------------------------------------------------------
    // Alerts
    // -----------------------------------------------------------------------

    public function getAlerts(bool $resolved = false): array {
        try {
            $param  = $resolved ? '' : '?status=active';
            $result = $this->get('/alerts' . $param);
            return $result['data'] ?? [];
        } catch (RuntimeException $e) { return []; }
    }

    public function getAgentAlerts(string $agent_id): array {
        try {
            $result = $this->get('/alerts?device_id=' . urlencode($agent_id));
            return $result['data'] ?? [];
        } catch (RuntimeException $e) { return []; }
    }

    // -----------------------------------------------------------------------
    // Scripts
    // -----------------------------------------------------------------------

    public function getScripts(): array {
        try {
            $result = $this->get('/scripts');
            return $result['data'] ?? [];
        } catch (RuntimeException $e) { return []; }
    }

    public function runScript(string $agent_id, int $script_id, int $timeout = 120): array {
        return $this->post('/devices/' . urlencode($agent_id) . '/scripts/' . $script_id . '/run', [
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
    // Field normalisation
    // Level device fields: id, hostname, nickname, role, group_name, group_id,
    //   tags (array), online (bool), platform, notes, maintenance_mode
    // -----------------------------------------------------------------------

    private function normalizeDevice(array $dev): array {
        // "online" is a boolean in Level v2 API
        $online = $dev['online'] ?? false;

        // Use nickname as display name when set, otherwise hostname
        $hostname    = $dev['hostname'] ?? '';
        $display     = ($dev['nickname'] ?? '') ?: $hostname;

        // Map Level role to a readable OS/type hint
        $platform    = $dev['platform'] ?? '';

        return [
            'id'               => $dev['id'] ?? '',
            'agent_id'         => $dev['id'] ?? '',
            'hostname'         => $hostname,
            'description'      => $dev['notes'] ?? '',
            'status'           => $online ? 'online' : 'offline',
            'last_seen'        => $dev['last_seen'] ?? null,
            'logged_in_user'   => '',
            'operating_system' => $platform,
            'os_version'       => '',
            'plat'             => $platform,
            'manufacturer'     => '',
            'model'            => $dev['role'] ?? '',
            'cpu'              => '',
            'ram'              => '',
            'serial_number'    => '',
            'local_ips'        => [],
            'tags'             => $dev['tags'] ?? [],
            'group_name'       => $dev['group_name'] ?? '',
            'display_name'     => $display,
            '_provider'        => 'level',
            '_raw'             => $dev,
        ];
    }
}
