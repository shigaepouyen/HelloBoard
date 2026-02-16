<?php

class AiService {
    private string $apiKey;
    private bool $debugMode;
    private string $baseUrl = "https://api.mistral.ai/v1/chat/completions";
    private string $logFile = __DIR__ . '/../../logs/debug_ai.log';

    public function __construct(string $apiKey, bool $debugMode = false) {
        $this->apiKey = $apiKey;
        $this->debugMode = $debugMode;
    }

    private function log($message) {
        if (!$this->debugMode) return;
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log("AiService: Impossible de créer le répertoire de logs $dir");
                return;
            }
        }
        $time = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$time] $message\n", FILE_APPEND);
    }

    /**
     * Generate an email body based on a user prompt and context.
     */
    public function generateEmailBody(string $prompt, string $context, string $campaignTitle): string {
        $systemPrompt = "Tu es un assistant expert en communication pour les associations.
        Ta mission est de rédiger uniquement le CORPS d'un email professionnel et engageant.
        Important : Produis du texte brut (plain text), n'utilise AUCUNE balise HTML ni syntaxe Markdown.
        Contexte : " . ($context === 'Satisfaction' ? "Demande d'avis/satisfaction après un événement ou achat." : "Email de rappel ou information générale.") . "
        Nom de la campagne/événement : " . $campaignTitle . "

        Règles cruciales :
        1. Tu DOIS utiliser les variables de personnalisation suivantes quand c'est pertinent : {{PRENOM}}, {{NOM}}, {{NOM_CAMPAGNE}}" . ($context === 'Satisfaction' ? ", {{SURVEY_URL}}" : "") . ".
        2. Ne fournis QUE le corps de l'email, pas d'objet, pas de salutations introductives hors de l'email (ex: 'Voici votre email :').
        3. Pour les liens, utilise simplement la variable entre accolades, par exemple : 'Cliquez ici : {{SURVEY_URL}}'.
        4. Utilise un ton chaleureux, associatif et professionnel.
        5. Respecte scrupuleusement les instructions du prompt utilisateur ci-dessous.";

        return $this->callMistral($systemPrompt, $prompt, 0.7, "EMAIL_GEN [$context] $campaignTitle");
    }

    /**
     * Analyze satisfaction responses using AI.
     */
    public function analyzeSatisfaction(string $campaignTitle, array $questions, array $responses): string {
        if (empty($responses)) {
            return "Aucun retour à analyser pour le moment.";
        }

        $formattedQuestions = "";
        foreach ($questions as $i => $q) {
            $label = is_array($q) ? ($q['label'] ?? '') : $q;
            $formattedQuestions .= "- Question " . ($i+1) . " : " . $label . "\n";
        }

        $formattedResponses = "";
        foreach ($responses as $r) {
            $scores = [];
            for($i=1; $i<=5; $i++) {
                if (isset($r['q'.$i]) && $r['q'.$i] !== null) $scores[] = "Q$i: " . $r['q'.$i] . "/5";
            }
            $formattedResponses .= "--- Avis de " . ($r['payer_name'] ?? 'Anonyme') . " ---\n";
            $formattedResponses .= "Notes : " . (empty($scores) ? 'N/A' : implode(', ', $scores)) . "\n";
            $formattedResponses .= "Commentaire : " . ($r['comment'] ?: 'Pas de commentaire') . "\n\n";
        }

        $systemPrompt = "Tu es un Expert en Expérience Client et Analyse de Satisfaction, spécialisé dans le secteur associatif et de la jeunesse.
        Ta mission est d'analyser les retours de satisfaction d'une campagne nommée : \"$campaignTitle\".

        Voici les questions qui ont été posées aux participants :
        $formattedQuestions

        Voici les données brutes des réponses (notes sur 5 et verbatims) :
        $formattedResponses

        Ton analyse doit être structurée ainsi :
        1. Synthèse globale de la satisfaction.
        2. Signaux forts (points positifs marquants).
        3. Signaux faibles et axes d'amélioration.
        4. Recommandations concrètes pour l'association.

        Règles importantes :
        - Sois constructif, analytique et bienveillant.
        - Cite des passages de verbatims pour illustrer tes points.
        - Utilise un ton professionnel adapté au monde associatif.
        - N'hésite pas à souligner des points spécifiques à la jeunesse si pertinent.
        - Produis du texte clair, tu peux utiliser des tirets pour les listes.
        - Ne dépasse pas 500 mots.";

        return $this->callMistral($systemPrompt, "Peux-tu me faire l'analyse de cette campagne ?", 0.5, "SATISFACTION_ANALYSIS $campaignTitle");
    }

    /**
     * Common method to call Mistral API
     */
    private function callMistral(string $systemPrompt, string $userPrompt, float $temperature, string $logContext): string {
        if (empty($this->apiKey)) {
            throw new Exception("Clé API Mistral manquante. Veuillez la configurer dans les réglages.");
        }

        $data = [
            "model" => "mistral-small-latest",
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => $userPrompt]
            ],
            "temperature" => $temperature
        ];

        $this->log("--- NOUVELLE REQUÊTE [$logContext] ---");
        $this->log("REQUEST DATA: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->log("CURL ERROR: " . $curlError);
            throw new Exception("Erreur de connexion à Mistral AI : " . $curlError);
        }

        $this->log("RESPONSE (HTTP $httpCode): " . $response);

        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            $msg = $error['message'] ?? $response;
            throw new Exception("Erreur Mistral AI ($httpCode) : " . $msg);
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON DECODE ERROR: " . json_last_error_msg());
            throw new Exception("Erreur de lecture de la réponse Mistral AI (JSON invalide).");
        }

        $content = $result['choices'][0]['message']['content'] ?? "";

        if (empty($content)) {
            $this->log("ALERTE: Réponse vide de l'IA");
            throw new Exception("L'IA a retourné une réponse vide.");
        }

        return $content;
    }
}
