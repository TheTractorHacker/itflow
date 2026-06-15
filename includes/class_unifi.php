<?php
/*
 * UnifiClient — server-side service for UniFi OS (UOS) Network Controller API calls.
 *
 * UniFi OS 5.x API:
 *   Base URL : https://{host}:{port}
 *   Auth     : X-API-KEY: {api_key}
 *   Sites    : GET /proxy/network/api/self/sites
 *   Devices  : GET /proxy/network/api/s/{site_id}/stat/device
 *   WLANs    : GET /proxy/network/api/s/{site_id}/rest/wlanconf
 *   Networks : GET /proxy/network/api/s/{site_id}/rest/networkconf
 */

class UnifiClient {

    private string $base_url;
    private string $api_key;
    private bool   $verify_ssl;
    private int    $integration_id;

    public function __construct(int $integration_id) {
        global $mysqli;
        $id  = intval($integration_id);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,
            "SELECT * FROM unifi_integrations WHERE id=$id AND enabled=1"
        ));
        if (!$row) {
            throw new RuntimeException("UniFi integration $id not found or disabled");
        }
        $this->integration_id = $id;
        $this->base_url       = "https://" . rtrim($row['host'], '/') . ":" . intval($row['port']);
        $this->verify_ssl     = (bool) $row['verify_ssl'];
        $this->api_key        = decryptSetting($row['api_key_enc'] ?? '');
        if (empty($this->api_key)) {
            throw new RuntimeException("UniFi integration $id has no decryptable API key");
        }
    }

    private function get(string $endpoint): array {
        $url = $this->base_url . $endpoint;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => $this->verify_ssl,
            CURLOPT_SSL_VERIFYHOST => $this->verify_ssl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_HTTPHEADER     => [
                'X-API-KEY: ' . $this->api_key,
                'Accept: application/json',
            ],
        ]);
        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException("UniFi API cURL error: $err");
        }
        if ($status === 401 || $status === 403) {
            throw new RuntimeException("UniFi API: HTTP $status Unauthorized — check API key");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("UniFi API HTTP $status: " . substr((string) $raw, 0, 200));
        }
        $decoded = json_decode((string) $raw, true);
        if ($decoded === null && !empty($raw)) {
            throw new RuntimeException("Invalid JSON from UniFi API: " . substr((string) $raw, 0, 200));
        }
        return $decoded['data'] ?? [];
    }

    // -----------------------------------------------------------------------
    // Connection test
    // -----------------------------------------------------------------------

    public function testConnection(): bool {
        $this->getSites();
        return true;
    }

    // -----------------------------------------------------------------------
    // Endpoints
    // -----------------------------------------------------------------------

    public function getSites(): array {
        return $this->get('/proxy/network/api/self/sites');
    }

    public function getDevices(string $site_id): array {
        return $this->get("/proxy/network/api/s/$site_id/stat/device");
    }

    public function getWlans(string $site_id): array {
        return $this->get("/proxy/network/api/s/$site_id/rest/wlanconf");
    }

    public function getNetworkConf(string $site_id): array {
        return $this->get("/proxy/network/api/s/$site_id/rest/networkconf");
    }
}
