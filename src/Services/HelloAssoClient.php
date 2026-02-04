<?php

class HelloAssoClient {
    private $clientId;
    private $clientSecret;
    private $logs = [];

    public function __construct($id, $secret) {
        $this->clientId = $id;
        $this->clientSecret = $secret;
    }

    private function log($type, $msg, $data = null) {
        $entry = "[" . date('H:i:s') . "] [$type] $msg";
        if ($data !== null) {
            $cleanData = $data;
            if (is_string($data) && (str_starts_with(trim($data), '{') || str_starts_with(trim($data), '['))) {
                $decoded = json_decode($data, true);
                if (json_last_error() === JSON_ERROR_NONE) $cleanData = $decoded;
            }
            
            if (is_array($cleanData)) {
                $keysToHide = ['access_token', 'refresh_token', 'client_secret', 'client_id'];
                array_walk_recursive($cleanData, function(&$v, $k) use ($keysToHide) {
                    if (in_array($k, $keysToHide)) $v = '***MASQUÉ***';
                });
                $entry .= " >> JSON: " . json_encode($cleanData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } else {
                $entry .= " >> RAW: " . $cleanData;
            }
        }
        $this->logs[] = $entry;
    }

    public function getLogs() { return $this->logs; }

    private function request($method, $url, $params = [], $token = null) {
        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60
        ];
        
        $headers = ['Accept: application/json'];
        
        // --- CORRECTIF ICI : On envoie le token COMPLET ---
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

        $this->log('API_REQ', "$method $url", $method === 'POST' ? $params : null);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->log('API_ERR', "Erreur cURL: $curlError");
            return ['code' => 0, 'body' => null];
        }

        $this->log('API_RES', "Code HTTP: $httpCode", $response);

        return ['code' => $httpCode, 'body' => json_decode($response, true)];
    }

    public function getAccessToken() {
        if (empty($this->clientId)) {
            $this->log('ERROR', "Client ID manquant");
            return null;
        }

        $res = $this->request('POST', "https://api.helloasso.com/oauth2/token", [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials'
        ]);

        if ($res['code'] === 200 && isset($res['body']['access_token'])) {
            return $res['body']['access_token'];
        }
        
        $this->log('ERROR', "Echec Token", $res['body']);
        return null;
    }

    public function testConnection($orgSlug = null) {
        $token = $this->getAccessToken();
        if (!$token) return ['success' => false, 'message' => "Echec Token"];

        if (empty($orgSlug)) {
            $this->log('ERROR', "Arrêt : Aucun slug fourni.");
            return ['success' => false, 'message' => "Slug manquant."];
        }

        $this->log('INFO', "Test ciblé sur le slug : $orgSlug");
        
        // Test sur les formulaires (Route autorisée pour les assos)
        $url = "https://api.helloasso.com/v5/organizations/$orgSlug/forms";
        $res = $this->request('GET', $url, [], $token);
        
        if ($res['code'] === 200) {
            // Succès !
            $count = isset($res['body']['data']) ? count($res['body']['data']) : 0;
            return ['success' => true, 'name' => "$orgSlug ($count formulaires)", 'slug' => $orgSlug];
        }
        
        if ($res['code'] === 403) {
            return ['success' => false, 'message' => "Accès refusé (403). Vérifiez le slug: '$orgSlug'"];
        }
        
        if ($res['code'] === 404) {
            return ['success' => false, 'message' => "Association introuvable (404). Vérifiez le slug."];
        }

        return ['success' => false, 'message' => "Erreur " . $res['code']];
    }

    public function discoverCampaigns($orgSlug) {
        $token = $this->getAccessToken();
        if (!$token || !$orgSlug) {
            return null;
        }

        $res = $this->request('GET', "https://api.helloasso.com/v5/organizations/$orgSlug/forms", [], $token);
        
        if ($res['code'] !== 200) {
            $this->log('ERROR', "Erreur récupération formulaires", $res['body']);
            return null;
        }

        $data = $res['body']['data'] ?? $res['body'] ?? [];
        $this->log('INFO', count($data) . " formulaires trouvés.");

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