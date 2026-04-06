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
                custom_answer TEXT,
                submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->db->exec("CREATE TABLE IF NOT EXISTS campaign_analysis (
                campaign_slug TEXT PRIMARY KEY,
                analysis_text TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $this->db->exec("CREATE TABLE IF NOT EXISTS survey_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token TEXT,
                status TEXT,
                error_message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

            // Migration: Add custom_answer column to survey_responses if missing
            try {
                $this->db->query("SELECT custom_answer FROM survey_responses LIMIT 1");
            } catch (Exception $e) {
                $this->db->exec("ALTER TABLE survey_responses ADD COLUMN custom_answer TEXT");
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
        $defaultTextQuestion = ['label' => 'Auriez-vous une idée pour nos prochaines actions ou évènements ?', 'type' => 'text'];

        if ($formType === 'Shop' || $formType === 'Checkout' || $formType === 'PaymentForm' || $formType === 'Product') {
            return [
                ['label' => 'Êtes-vous satisfait de votre interaction avec l\'association ?'],
                ['label' => 'Le processus d\'achat vous a-t-il semblé simple ?'],
                ['label' => 'L\'objet ou le produit correspondait-il à vos attentes ?'],
                ['label' => 'Seriez-vous prêt à commander à nouveau ou à nous recommander ?'],
                ['label' => 'Comment évaluez-vous la qualité du retrait / livraison ?'],
                $defaultTextQuestion
            ];
        } else if ($formType === 'Donation') {
            return [
                ['label' => 'Êtes-vous satisfait de votre interaction avec l\'association ?'],
                ['label' => 'Le processus de don vous a-t-il semblé simple ?'],
                ['label' => 'Avez-vous le sentiment que votre don est utile ?'],
                ['label' => 'Seriez-vous prêt à nous soutenir à nouveau ?'],
                ['label' => 'La transparence sur l\'usage des fonds vous semble-t-elle correcte ?'],
                $defaultTextQuestion
            ];
        } else if ($formType === 'Membership') {
            return [
                ['label' => 'Êtes-vous satisfait de votre interaction avec l\'association ?'],
                ['label' => 'Le processus d\'adhésion vous a-t-il semblé simple ?'],
                ['label' => 'Les avantages de l\'adhésion répondent-ils à vos attentes ?'],
                ['label' => 'Recommanderiez-vous notre association à votre entourage ?'],
                ['label' => 'Le montant de la cotisation vous semble-t-il justifié ?'],
                $defaultTextQuestion
            ];
        }

        // Default / Event
        return [
            ['label' => 'Êtes-vous satisfait de votre interaction avec l\'association ?'],
            ['label' => 'Le processus d\'inscription vous a-t-il semblé simple ?'],
            ['label' => 'L\'événement correspondait-il à vos attentes ?'],
            ['label' => 'Seriez-vous prêt à renouveler l\'expérience ou à nous recommander ?'],
            ['label' => 'Comment évaluez-vous la qualité de l\'accueil / organisation ?'],
            $defaultTextQuestion
        ];
    }

    public function saveQuestions($campaignSlug, $questions) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO campaign_questions (campaign_slug, questions_json) VALUES (?, ?)");
        return $stmt->execute([$campaignSlug, json_encode($questions)]);
    }

    public function generateToken($campaignSlug, $orderId, $email, $payerName, $itemName, $markAsSent = false) {
        $token = bin2hex(random_bytes(16));
        $sentAt = $markAsSent ? date('Y-m-d H:i:s') : null;
        $status = $markAsSent ? 'sent' : 'pending';
        $stmt = $this->db->prepare("INSERT INTO survey_tokens (token, campaign_slug, order_id, email, payer_name, item_name, sent_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$token, $campaignSlug, $orderId, $email, $payerName, $itemName, $sentAt, $status]);
        return $token;
    }

    public function getTokenByOrder($campaignSlug, $orderId) {
        $stmt = $this->db->prepare("SELECT * FROM survey_tokens WHERE campaign_slug = ? AND order_id = ?");
        $stmt->execute([$campaignSlug, $orderId]);
        return $stmt->fetch();
    }

    public function getTokenByEmail($campaignSlug, $email) {
        $stmt = $this->db->prepare("SELECT * FROM survey_tokens WHERE campaign_slug = ? AND email = ? ORDER BY (sent_at IS NOT NULL) DESC, rowid DESC LIMIT 1");
        $stmt->execute([$campaignSlug, $email]);
        return $stmt->fetch();
    }

    public function updateSentDate($token) {
        $stmt = $this->db->prepare("UPDATE survey_tokens SET sent_at = CURRENT_TIMESTAMP, status = 'sent' WHERE token = ?");
        return $stmt->execute([$token]);
    }

    public function isAlreadySent($campaignSlug, $orderId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM survey_tokens WHERE campaign_slug = ? AND order_id = ? AND sent_at IS NOT NULL");
        $stmt->execute([$campaignSlug, $orderId]);
        return $stmt->fetchColumn() > 0;
    }

    public function isAlreadySentToEmail($campaignSlug, $email) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM survey_tokens WHERE campaign_slug = ? AND email = ? AND sent_at IS NOT NULL");
        $stmt->execute([$campaignSlug, $email]);
        return $stmt->fetchColumn() > 0;
    }

    public function hasEverReceived($email) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM survey_tokens WHERE email = ? AND sent_at IS NOT NULL");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }

    public function getTokenInfo($token) {
        $stmt = $this->db->prepare("SELECT * FROM survey_tokens WHERE token = ?");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    public function markAsRead($token) {
        $stmt = $this->db->prepare("UPDATE survey_tokens SET read_at = CURRENT_TIMESTAMP WHERE token = ? AND sent_at IS NOT NULL AND read_at IS NULL");
        return $stmt->execute([$token]);
    }

    public function saveResponse($token, $ratings, $comment, $customAnswer = null) {
        $stmt = $this->db->prepare("INSERT INTO survey_responses (token, q1, q2, q3, q4, q5, comment, custom_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $token,
            $ratings[0] ?? null,
            $ratings[1] ?? null,
            $ratings[2] ?? null,
            $ratings[3] ?? null,
            $ratings[4] ?? null,
            $comment,
            $customAnswer
        ]);
    }

    public function deleteParticipation($token) {
        $stmt = $this->db->prepare("DELETE FROM survey_responses WHERE token = ?");
        $stmt->execute([$token]);
        $stmt = $this->db->prepare("DELETE FROM survey_tokens WHERE token = ?");
        return $stmt->execute([$token]);
    }

    public function getTokensByCampaign($campaignSlug) {
        $stmt = $this->db->prepare("SELECT * FROM survey_tokens WHERE campaign_slug = ? ORDER BY email ASC, (sent_at IS NOT NULL) DESC, rowid DESC");
        $stmt->execute([$campaignSlug]);
        return $stmt->fetchAll();
    }

    public function getRespondedEmailsByCampaign($campaignSlug) {
        $stmt = $this->db->prepare("
            SELECT DISTINCT LOWER(TRIM(t.email)) AS email
            FROM survey_responses r
            JOIN survey_tokens t ON t.token = r.token
            WHERE t.campaign_slug = ?
              AND t.email IS NOT NULL
              AND TRIM(t.email) <> ''
        ");
        $stmt->execute([$campaignSlug]);

        $emails = [];
        foreach ($stmt->fetchAll() as $row) {
            $email = $row['email'] ?? '';
            if ($email !== '') {
                $emails[$email] = true;
            }
        }

        return $emails;
    }

    public function buildRecipientsByEmail($campaignSlug, $campaignTitle, array $orders, array $checkins = [], $excludeSent = false, $excludeEver = false, $excludeResponded = false) {
        $recipientsByEmail = [];
        $respondedEmails = $excludeResponded ? $this->getRespondedEmailsByCampaign($campaignSlug) : [];

        foreach ($orders as $order) {
            $hasValidItem = false;
            foreach ($order['items'] ?? [] as $item) {
                if (in_array(($item['state'] ?? ''), ['Paid', 'Processed'], true)) {
                    $hasValidItem = true;
                    break;
                }
            }

            if (!$hasValidItem) {
                continue;
            }

            $email = trim(strtolower($order['payer']['email'] ?? ''));
            if (!$email) {
                continue;
            }

            if ($excludeEver && $this->hasEverReceived($email)) {
                continue;
            }

            if ($excludeSent && $this->isAlreadySentToEmail($campaignSlug, $email)) {
                continue;
            }

            if ($excludeResponded && isset($respondedEmails[$email])) {
                continue;
            }

            $orderIsPresent = false;
            foreach ($order['items'] ?? [] as $item) {
                $checkId = ($order['id'] ?? '') . '-' . ($item['id'] ?? '');
                if (!empty($checkins[$checkId]) || !empty($checkins[$order['id'] ?? ''])) {
                    $orderIsPresent = true;
                    break;
                }
            }

            if (!isset($recipientsByEmail[$email])) {
                $recipientsByEmail[$email] = [
                    'orderId' => $order['id'] ?? null,
                    'email' => $email,
                    'firstName' => trim($order['payer']['firstName'] ?? ''),
                    'lastName' => trim($order['payer']['lastName'] ?? ''),
                    'itemName' => $campaignTitle,
                    'date' => $order['date'] ?? null,
                    'isPresent' => $orderIsPresent
                ];
                continue;
            }

            if ($orderIsPresent) {
                $recipientsByEmail[$email]['isPresent'] = true;
            }

            if (($order['date'] ?? '') > ($recipientsByEmail[$email]['date'] ?? '')) {
                $recipientsByEmail[$email]['date'] = $order['date'] ?? null;
                $recipientsByEmail[$email]['orderId'] = $order['id'] ?? null;
            }
        }

        return $recipientsByEmail;
    }

    public function getResponsesByCampaign($campaignSlug = null) {
        $sql = "SELECT r.*, t.payer_name, t.item_name, t.campaign_slug, t.email, t.read_at, t.sent_at, t.order_id, r.custom_answer
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
            COUNT(DISTINCT CASE WHEN t.sent_at IS NOT NULL THEN t.token END) as total_sent,
            COUNT(CASE WHEN t.sent_at IS NOT NULL AND t.read_at IS NOT NULL THEN 1 END) as total_read,
            COUNT(DISTINCT r.token) as total_responses,
            AVG(r.q1) as avg_q1,
            AVG(r.q2) as avg_q2,
            AVG(r.q3) as avg_q3,
            AVG(r.q4) as avg_q4,
            AVG(r.q5) as avg_q5,
            AVG(
                CASE WHEN ((q1 IS NOT NULL) + (q2 IS NOT NULL) + (q3 IS NOT NULL) + (q4 IS NOT NULL) + (q5 IS NOT NULL)) > 0
                THEN (CAST(COALESCE(q1,0) + COALESCE(q2,0) + COALESCE(q3,0) + COALESCE(q4,0) + COALESCE(q5,0) AS FLOAT) - ((q1 IS NOT NULL) + (q2 IS NOT NULL) + (q3 IS NOT NULL) + (q4 IS NOT NULL) + (q5 IS NOT NULL))) / (((q1 IS NOT NULL) + (q2 IS NOT NULL) + (q3 IS NOT NULL) + (q4 IS NOT NULL) + (q5 IS NOT NULL)) * 4.0) * 100.0
                ELSE NULL END
            ) as avg_csat
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

    public function getAnalysis($campaignSlug) {
        $stmt = $this->db->prepare("SELECT * FROM campaign_analysis WHERE campaign_slug = ?");
        $stmt->execute([$campaignSlug]);
        return $stmt->fetch();
    }

    public function saveAnalysis($campaignSlug, $text) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO campaign_analysis (campaign_slug, analysis_text, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
        return $stmt->execute([$campaignSlug, $text]);
    }

    public function addAttempt($token, $status, $error = null) {
        $stmt = $this->db->prepare("INSERT INTO survey_attempts (token, status, error_message) VALUES (?, ?, ?)");
        return $stmt->execute([$token, $status, $error]);
    }

    public function getAttempts($token) {
        $stmt = $this->db->prepare("SELECT * FROM survey_attempts WHERE token = ? ORDER BY created_at DESC");
        $stmt->execute([$token]);
        return $stmt->fetchAll();
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

            $sum = 0; $countQ = 0;
            for($i=1; $i<=5; $i++) {
                if (isset($r['q'.$i]) && $r['q'.$i] !== null) {
                    $sum += (int)$r['q'.$i];
                    $countQ++;
                }
            }
            $csat = ($countQ > 0) ? ($sum - $countQ) / ($countQ * 4.0) * 100.0 : 0;

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

    public function getSummaryPerCampaign() {
        $sql = "SELECT campaign_slug,
                COUNT(CASE WHEN sent_at IS NOT NULL THEN 1 END) as total_sent,
                COUNT(CASE WHEN sent_at IS NOT NULL AND read_at IS NOT NULL THEN 1 END) as total_read,
                (SELECT COUNT(*)
                    FROM survey_responses r
                    WHERE r.token IN (
                        SELECT token
                        FROM survey_tokens t2
                        WHERE t2.campaign_slug = t.campaign_slug
                          AND t2.sent_at IS NOT NULL
                    )
                ) as total_replied
                FROM survey_tokens t
                GROUP BY campaign_slug";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
