<?php

class SatisfactionService {
    private $db;
    private $dbPath;

    public function __construct() {
        $this->dbPath = __DIR__ . '/../../config/satisfaction.db';
        $this->connect();
    }

    private function connect() {
        try {
            $this->db = new PDO('sqlite:' . $this->dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $this->db->exec("CREATE TABLE IF NOT EXISTS campaign_questions (
                campaign_slug TEXT PRIMARY KEY,
                questions_json TEXT
            )");

            $this->db->exec("CREATE TABLE IF NOT EXISTS survey_tokens (
                token TEXT PRIMARY KEY,
                campaign_slug TEXT,
                order_id TEXT,
                email TEXT,
                payer_name TEXT,
                item_name TEXT,
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                status TEXT DEFAULT 'sent'
            )");

            $this->db->exec("CREATE TABLE IF NOT EXISTS survey_responses (
                token TEXT PRIMARY KEY,
                q1 INTEGER,
                q2 INTEGER,
                q3 INTEGER,
                q4 INTEGER,
                q5 INTEGER,
                comment TEXT,
                submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (PDOException $e) {
            error_log("SatisfactionService Connection Error: " . $e->getMessage());
        }
    }

    public function getQuestions($campaignSlug) {
        $stmt = $this->db->prepare("SELECT questions_json FROM campaign_questions WHERE campaign_slug = ?");
        $stmt->execute([$campaignSlug]);
        $row = $stmt->fetch();

        if ($row) {
            return json_decode($row['questions_json'], true);
        }

        // Default questions
        return [
            ['label' => 'Êtes-vous satisfait de votre interaction avec l\'association ?'],
            ['label' => 'Le processus (inscription ou achat) vous a-t-il semblé simple ?'],
            ['label' => 'Le service ou l\'objet correspondait-il à vos attentes ?'],
            ['label' => 'Seriez-vous prêt à renouveler l\'expérience ou à nous recommander ?'],
            ['label' => 'Comment évaluez-vous la réactivité de notre équipe ?']
        ];
    }

    public function saveQuestions($campaignSlug, $questions) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO campaign_questions (campaign_slug, questions_json) VALUES (?, ?)");
        return $stmt->execute([$campaignSlug, json_encode($questions)]);
    }

    public function generateToken($campaignSlug, $orderId, $email, $payerName, $itemName) {
        $token = bin2hex(random_bytes(16));
        $stmt = $this->db->prepare("INSERT INTO survey_tokens (token, campaign_slug, order_id, email, payer_name, item_name) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$token, $campaignSlug, $orderId, $email, $payerName, $itemName]);
        return $token;
    }

    public function isAlreadySent($campaignSlug, $orderId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM survey_tokens WHERE campaign_slug = ? AND order_id = ?");
        $stmt->execute([$campaignSlug, $orderId]);
        return $stmt->fetchColumn() > 0;
    }

    public function hasEverReceived($email) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM survey_tokens WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }

    public function getTokenInfo($token) {
        $stmt = $this->db->prepare("SELECT * FROM survey_tokens WHERE token = ?");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    public function saveResponse($token, $ratings, $comment) {
        $stmt = $this->db->prepare("INSERT INTO survey_responses (token, q1, q2, q3, q4, q5, comment) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $token,
            $ratings[0] ?? null,
            $ratings[1] ?? null,
            $ratings[2] ?? null,
            $ratings[3] ?? null,
            $ratings[4] ?? null,
            $comment
        ]);
    }

    public function getResponsesByCampaign($campaignSlug = null) {
        $sql = "SELECT r.*, t.payer_name, t.item_name, t.campaign_slug, t.email
                FROM survey_responses r
                JOIN survey_tokens t ON r.token = t.token";

        if ($campaignSlug) {
            $sql .= " WHERE t.campaign_slug = :slug";
        }
        $sql .= " ORDER BY r.submitted_at DESC";

        $stmt = $this->db->prepare($sql);
        if ($campaignSlug) {
            $stmt->bindValue(':slug', $campaignSlug);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getStats() {
        $stmt = $this->db->query("SELECT
            COUNT(r.token) as total_responses,
            AVG((q1 + q2 + q3 + q4 + q5 - 5) / 20.0 * 100.0) as avg_csat
            FROM survey_responses r");
        return $stmt->fetch();
    }

    public function getStatsBySource() {
        // We might need to join with campaigns to get formType, or store it in tokens
        // For now let's assume we can get it from campaign files
        $responses = $this->getResponsesByCampaign();
        $bySource = [];

        // Load campaigns to know form types
        $campaigns = [];
        $files = glob(__DIR__ . '/../../config/campaigns/*.json');
        foreach($files as $f) {
            $c = json_decode(file_get_contents($f), true);
            $campaigns[$c['slug']] = $c['formType'] ?? 'Unknown';
        }

        foreach ($responses as $r) {
            $type = $campaigns[$r['campaign_slug']] ?? 'Other';
            if (!isset($bySource[$type])) {
                $bySource[$type] = ['count' => 0, 'sum_csat' => 0];
            }
            $csat = ($r['q1'] + $r['q2'] + $r['q3'] + $r['q4'] + $r['q5'] - 5) / 20.0 * 100.0;
            $bySource[$type]['count']++;
            $bySource[$type]['sum_csat'] += $csat;
        }

        $result = [];
        foreach ($bySource as $type => $data) {
            $result[$type] = [
                'count' => $data['count'],
                'avg' => $data['count'] > 0 ? $data['sum_csat'] / $data['count'] : 0
            ];
        }
        return $result;
    }
}
