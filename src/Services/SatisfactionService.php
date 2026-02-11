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
                read_at DATETIME,
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

            // Migration: Add read_at column if missing
            try {
                $this->db->query("SELECT read_at FROM survey_tokens LIMIT 1");
            } catch (Exception $e) {
                $this->db->exec("ALTER TABLE survey_tokens ADD COLUMN read_at DATETIME");
            }

            // Migration: Add status column if missing
            try {
                $this->db->query("SELECT status FROM survey_tokens LIMIT 1");
            } catch (Exception $e) {
                $this->db->exec("ALTER TABLE survey_tokens ADD COLUMN status TEXT DEFAULT 'sent'");
            }
        } catch (PDOException $e) {
            error_log("SatisfactionService Connection Error: " . $e->getMessage());
        }
    }

    public function getQuestions($campaignSlug, $formType = null) {
        $stmt = $this->db->prepare("SELECT questions_json FROM campaign_questions WHERE campaign_slug = ?");
        $stmt->execute([$campaignSlug]);
        $row = $stmt->fetch();

        if ($row) {
            return json_decode($row['questions_json'], true);
        }

        // Default questions adapted by formType
        if ($formType === 'Shop' || $formType === 'Checkout' || $formType === 'PaymentForm' || $formType === 'Product') {
            return [
                ['label' => 'Êtes-vous satisfait de votre interaction avec l\'association ?'],
                ['label' => 'Le processus d\'achat vous a-t-il semblé simple ?'],
                ['label' => 'L\'objet ou le produit correspondait-il à vos attentes ?'],
                ['label' => 'Seriez-vous prêt à commander à nouveau ou à nous recommander ?'],
                ['label' => 'Comment évaluez-vous la qualité du retrait / livraison ?']
            ];
        } else if ($formType === 'Donation') {
            return [
                ['label' => 'Êtes-vous satisfait de votre interaction avec l\'association ?'],
                ['label' => 'Le processus de don vous a-t-il semblé simple ?'],
                ['label' => 'Avez-vous le sentiment que votre don est utile ?'],
                ['label' => 'Seriez-vous prêt à nous soutenir à nouveau ?'],
                ['label' => 'La transparence sur l\'usage des fonds vous semble-t-elle correcte ?']
            ];
        } else if ($formType === 'Membership') {
            return [
                ['label' => 'Êtes-vous satisfait de votre interaction avec l\'association ?'],
                ['label' => 'Le processus d\'adhésion vous a-t-il semblé simple ?'],
                ['label' => 'Les avantages de l\'adhésion répondent-ils à vos attentes ?'],
                ['label' => 'Recommanderiez-vous notre association à votre entourage ?'],
                ['label' => 'Le montant de la cotisation vous semble-t-il justifié ?']
            ];
        }

        // Default / Event
        return [
            ['label' => 'Êtes-vous satisfait de votre interaction avec l\'association ?'],
            ['label' => 'Le processus d\'inscription vous a-t-il semblé simple ?'],
            ['label' => 'L\'événement correspondait-il à vos attentes ?'],
            ['label' => 'Seriez-vous prêt à renouveler l\'expérience ou à nous recommander ?'],
            ['label' => 'Comment évaluez-vous la qualité de l\'accueil / organisation ?']
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

    public function markAsRead($token) {
        $stmt = $this->db->prepare("UPDATE survey_tokens SET read_at = CURRENT_TIMESTAMP WHERE token = ? AND read_at IS NULL");
        return $stmt->execute([$token]);
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

    public function deleteParticipation($token) {
        $stmt = $this->db->prepare("DELETE FROM survey_responses WHERE token = ?");
        $stmt->execute([$token]);
        $stmt = $this->db->prepare("DELETE FROM survey_tokens WHERE token = ?");
        return $stmt->execute([$token]);
    }

    public function getTokensByCampaign($campaignSlug) {
        $stmt = $this->db->prepare("SELECT * FROM survey_tokens WHERE campaign_slug = ?");
        $stmt->execute([$campaignSlug]);
        return $stmt->fetchAll();
    }

    public function getResponsesByCampaign($campaignSlug = null) {
        $sql = "SELECT r.*, t.payer_name, t.item_name, t.campaign_slug, t.email, t.read_at, t.sent_at, t.order_id
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

    public function getStats($campaignSlug = null) {
        $sql = "SELECT
            COUNT(DISTINCT t.token) as total_sent,
            COUNT(t.read_at) as total_read,
            COUNT(DISTINCT r.token) as total_responses,
            AVG((q1 + q2 + q3 + q4 + q5 - 5) / 20.0 * 100.0) as avg_csat
            FROM survey_tokens t
            LEFT JOIN survey_responses r ON t.token = r.token";

        if ($campaignSlug) {
            $sql .= " WHERE t.campaign_slug = :slug";
        }

        $stmt = $this->db->prepare($sql);
        if ($campaignSlug) {
            $stmt->bindValue(':slug', $campaignSlug);
        }
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getStatsBySource($campaignSlug = null) {
        $responses = $this->getResponsesByCampaign($campaignSlug);
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
