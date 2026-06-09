<?php
/*
 * TacticalRmmClient — server-side service for Tactical RMM API calls.
 * API key is decrypted once in the constructor and never exposed to the browser.
 */

class TacticalRmmClient {

    private string $base_url;
    private string $web_url;
    private string $api_key;
    private int $integration_id;

    public function __construct(int $integration_id) {
        global $mysqli;
        $id  = intval($integration_id);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM rmm_integrations WHERE id=$id AND enabled=1"));
        if (!$row) {
            throw new RuntimeException("RMM integration $id not found or disabled");
        }
        $this->integration_id = $id;
        $this->base_url = rtrim($row['api_url'], '/');
        $this->web_url  = rtrim($row['web_url'] ?? $row['api_url'], '/');
        $this->api_key  = decryptSetting($row['api_key_enc'] ?? '');
        if (empty($this->api_key)) {
            throw new RuntimeException("RMM integration $id has no decryptable API key");
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
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'X-API-KEY: ' . $this->api_key,
                'Content-Type: application/json',
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
            throw new RuntimeException("cURL error: $err");
        }
        if ($status === 401) {
            throw new RuntimeException("Tactical RMM API: 401 Unauthorized — check API key");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Tactical RMM API returned HTTP $status");
        }
        $decoded = json_decode($raw, true);
        if ($decoded === null && !empty($raw)) {
            throw new RuntimeException("Invalid JSON response from Tactical RMM");
        }
        return $decoded ?? [];
    }

    public function testConnection(): bool {
        // Let exceptions propagate — callers handle error reporting
        $result = $this->get('/agents/');
        return is_array($result);
    }

    public function getAgents(): array {
        return $this->get('/agents/');
    }

    public function getAgent(string $agent_id): array {
        return $this->get('/agents/' . urlencode($agent_id) . '/');
    }

    public function getAgentWmi(string $agent_id): array {
        // Tactical RMM has no GET /agents/{id}/wmi/ endpoint — hardware
        // details are embedded in the agent detail response.
        try {
            $agent = $this->getAgent($agent_id);
        } catch (RuntimeException $e) {
            return [];
        }
        return [
            'make_model'     => $agent['make_model'] ?? '',
            'cpu_model'      => $agent['cpu_model'] ?? [],
            'total_ram'      => $agent['total_ram'] ?? null,
            'disks'          => $agent['disks'] ?? [],
            'physical_disks' => $agent['physical_disks'] ?? [],
            'graphics'       => $agent['graphics'] ?? '',
            'local_ips'      => $agent['local_ips'] ?? '',
            'wmi_detail'     => $agent['wmi_detail'] ?? [],
        ];
    }

    public function getAgentSoftware(string $agent_id): array {
        try {
            $result = $this->get('/software/' . urlencode($agent_id) . '/');
        } catch (RuntimeException $e) {
            return [];
        }
        return $result['software'] ?? [];
    }

    public function getAgentServices(string $agent_id): array {
        try {
            return $this->get('/services/' . urlencode($agent_id) . '/');
        } catch (RuntimeException $e) {
            return [];
        }
    }

    public function getAlerts(bool $resolved = false): array {
        $param = $resolved ? '' : '?resolved=false';
        try {
            return $this->get('/alerts/' . $param);
        } catch (RuntimeException $e) {
            return [];
        }
    }

    public function getAgentAlerts(string $agent_id): array {
        try {
            return $this->get('/alerts/?agent=' . urlencode($agent_id));
        } catch (RuntimeException $e) {
            return [];
        }
    }

    public function getScripts(): array {
        try {
            return $this->get('/scripts/');
        } catch (RuntimeException $e) {
            return [];
        }
    }

    public function runScript(string $agent_id, int $tactical_script_id, int $timeout = 120): array {
        return $this->post('/agents/' . urlencode($agent_id) . '/runscript/', [
            'script'  => $tactical_script_id,
            'timeout' => $timeout,
            'run_as_user' => false,
        ]);
    }


    // -----------------------------------------------------------------------
    // Check management
    // -----------------------------------------------------------------------

    public function getAgentChecks(string $agent_id): array {
        try {
            return $this->get('/agents/' . urlencode($agent_id) . '/checks/');
        } catch (RuntimeException $e) {
            return [];
        }
    }

    public function createCheck(string $agent_id, array $payload): array {
        return $this->post('/agents/' . urlencode($agent_id) . '/checks/', $payload);
    }

    public function deleteCheck(int $check_id): bool {
        $url = $this->base_url . '/checks/' . $check_id . '/';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => ['X-API-KEY: ' . $this->api_key, 'Content-Type: application/json'],
        ]);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $status >= 200 && $status < 300;
    }

    public function buildDeviceUrl(string $agent_id): string {
        // Tactical RMM "Take Control" route — opens a MeshCentral remote
        // desktop session for the agent directly.
        $base = $this->web_url ?: $this->base_url;
        return $base . '/takecontrol/' . urlencode($agent_id);
    }

    public function buildMeshUrl(string $mesh_node_id): string {
        // Extracts host from base_url for MeshCentral (Tactical embeds Mesh at same host by default)
        $parsed = parse_url($this->base_url);
        $host   = $parsed['scheme'] . '://' . $parsed['host'];
        return $host . '/mesh/action.ashx?nodeid=' . urlencode($mesh_node_id) . '&arg=remotedesktop';
    }

    public function getIntegrationId(): int {
        return $this->integration_id;
    }
}
