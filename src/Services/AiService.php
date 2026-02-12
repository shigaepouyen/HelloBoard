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
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $time = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$time] $message\n", FILE_APPEND);
    }

    /**
     * Generate an email body based on a user prompt and context.
     *
     * @param string $prompt The user's specific request for the email content.
     * @param string $context "Mailing" or "Satisfaction" to provide context to the AI.
     * @param string $campaignTitle The title of the campaign.
     * @return string The generated email body.
     * @throws Exception If the API call fails.
     */
    public function generateEmailBody(string $prompt, string $context, string $campaignTitle): string {
        if (empty($this->apiKey)) {
            throw new Exception("Clé API Mistral manquante. Veuillez la configurer dans les réglages.");
        }

        $systemPrompt = "Tu es un assistant expert en communication pour les associations.
        Ta mission est de rédiger uniquement le CORPS d'un email professionnel et engageant.
        Contexte : " . ($context === 'Satisfaction' ? "Demande d'avis/satisfaction après un événement ou achat." : "Email de rappel ou information générale.") . "
        Nom de la campagne/événement : " . $campaignTitle . "

        Règles cruciales :
        1. Tu DOIS utiliser les variables de personnalisation suivantes quand c'est pertinent : {{PRENOM}}, {{NOM}}, {{NOM_CAMPAGNE}}" . ($context === 'Satisfaction' ? ", {{SURVEY_URL}}" : "") . ".
        2. Ne fournis QUE le corps de l'email, pas d'objet, pas de salutations introductives hors de l'email (ex: 'Voici votre email :').
        3. Utilise un ton chaleureux, associatif et professionnel.
        4. Respecte scrupuleusement les instructions du prompt utilisateur ci-dessous.";

        $data = [
            "model" => "mistral-small-latest",
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => $prompt]
            ],
            "temperature" => 0.7
        ];

        $this->log("REQUEST to Mistral: " . json_encode($data, JSON_PRETTY_PRINT));

        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->log("RESPONSE from Mistral (HTTP $httpCode): " . $response);

        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            throw new Exception("Erreur Mistral AI (" . $httpCode . ") : " . ($error['message'] ?? $response));
        }

        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? "";
    }
}
