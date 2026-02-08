<?php

class HelloAssoClient {
    private $clientId;
    private $clientSecret;
    private $debug = false; // NOUVEAU
    private $logFile; // NOUVEAU

    // On ajoute le paramètre $debug au constructeur
    public function __construct($id, $secret, $debug = false) {
        $this->clientId = $id;
        $this->clientSecret = $secret;
        $this->debug = $debug;
        // Le fichier sera stocké dans un dossier "logs" à la racine
        $this->logFile = __DIR__ . '/../../logs/debug_helloasso.log';
    }

    // NOUVELLE FONCTION PRIVÉE : ÉCRITURE SUR DISQUE
    private function writeToDisk($type, $msg, $data = null) {
        if (!$this->debug) return;

        if (!is_dir(dirname($this->logFile))) mkdir(dirname($this->logFile), 0755, true);

        $entry = "========================================\n";
        $entry .= "[" . date('Y-m-d H:i:s') . "] [$type] $msg\n";
        if ($data !== null) {
            $entry .= is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $entry .= "\n";
        }
        $entry .= "========================================\n\n";

        file_put_contents($this->logFile, $entry, FILE_APPEND);
    }

    private function request($method, $url, $params = [], $token = null) {
        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60
        ];
        
        $headers = ['Accept: application/json'];
        
        if ($token) {
            $headers[] = "Authorization: Bearer " . $token;
        }

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            if (isset($params['grant_type'])) {
                $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
                $headers[] = "Content-Type: application/x-www-form-urlencoded";
            } else {
                $opts[CURLOPT_POSTFIELDS] = json_encode($params);
                $headers[] = "Content-Type: application/json";
            }
        }

        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        // --- LOG DE LA REQUÊTE ---
        $this->writeToDisk('REQUEST', "$method $url", $params);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($curlError) {
            // --- LOG ERREUR CURL ---
            $this->writeToDisk('CURL_ERROR', $curlError);
            return ['code' => 0, 'body' => null];
        }

        // --- LOG DE LA RÉPONSE ---
        $decoded = json_decode($response, true);
        $this->writeToDisk('RESPONSE', "Code: $httpCode", $decoded ?: $response);

        return ['code' => $httpCode, 'body' => $decoded];
    }

    public function getAccessToken() {
        if (empty($this->clientId)) return null;

        $res = $this->request('POST', "https://api.helloasso.com/oauth2/token", [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials'
        ]);

        if ($res['code'] === 200 && isset($res['body']['access_token'])) {
            return $res['body']['access_token'];
        }
        return null;
    }

    public function discoverCampaigns($orgSlug) {
        $token = $this->getAccessToken();
        if (!$token || !$orgSlug) return null;

        $res = $this->request('GET', "https://api.helloasso.com/v5/organizations/$orgSlug/forms", [], $token);
        
        if ($res['code'] !== 200) return null;

        $data = $res['body']['data'] ?? $res['body'] ?? [];

        return [
            'orgSlug' => $orgSlug,
            'forms' => array_map(function($f) {
                return [
                    'name' => $f['title'] ?? $f['formSlug'],
                    'slug' => $f['formSlug'],
                    'type' => $f['formType']
                ];
            }, $data)
        ];
    }

    public function fetchAllOrders($orgSlug, $formSlug, $formType = 'Event') {
        $token = $this->getAccessToken();
        if (!$token) return [];

        $orders = [];
        $continuationToken = null;
        $page = 0;

        do {
            $page++;
            $url = "https://api.helloasso.com/v5/organizations/$orgSlug/forms/$formType/$formSlug/orders?pageSize=50&withDetails=true";
            if ($continuationToken) $url .= "&continuationToken=" . urlencode($continuationToken);

            $res = $this->request('GET', $url, [], $token);
            
            if ($res['code'] === 200 && !empty($res['body']['data'])) {
                $orders = array_merge($orders, $res['body']['data']);
                $continuationToken = $res['body']['pagination']['continuationToken'] ?? null;
            } else {
                break;
            }
        } while ($continuationToken && $page < 50);

        return $orders;
    }
}