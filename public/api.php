<?php
// On désactive l'affichage des erreurs HTML pour ne pas casser le JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Fonction de shutdown pour attraper les erreurs fatales (Time out, Memory limit, etc.)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        // Force le header JSON même en cas de crash
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Erreur Critique PHP: ' . $error['message']]);
        exit;
    }
});

header('Content-Type: application/json');

try {
    // Vérification des fichiers
    $srcPath = __DIR__ . '/../src/Services/';
    if (!file_exists($srcPath . 'HelloAssoClient.php')) throw new Exception("Fichier HelloAssoClient.php manquant");
    if (!file_exists($srcPath . 'Storage.php')) throw new Exception("Fichier Storage.php manquant");
    if (!file_exists($srcPath . 'StatsEngine.php')) throw new Exception("Fichier StatsEngine.php manquant");

    require_once $srcPath . 'HelloAssoClient.php';
    require_once $srcPath . 'Storage.php';
    require_once $srcPath . 'StatsEngine.php';

    $globals = Storage::getGlobalSettings();
    $campaignId = $_GET['campaign'] ?? null;

    if (!$campaignId) throw new Exception("Aucune campagne spécifiée.");
    if (empty($globals['clientId'])) throw new Exception("Configuration API manquante.");

    // Chargement Config Campagne
    $configPath = __DIR__ . "/../config/campaigns/$campaignId.json";
    if (!file_exists($configPath)) throw new Exception("Fichier de configuration introuvable pour : $campaignId");
    
    $jsonContent = file_get_contents($configPath);
    if (!$jsonContent) throw new Exception("Fichier de configuration vide.");
    
    $campaignConfig = json_decode($jsonContent, true);
    if (!$campaignConfig) throw new Exception("Configuration corrompue (JSON invalide).");

    // Client HelloAsso
    $client = new HelloAssoClient($globals['clientId'], $globals['clientSecret']);
    
    // Récupération des données
    $orders = $client->fetchAllOrders(
        $campaignConfig['orgSlug'], 
        $campaignConfig['formSlug'], 
        $campaignConfig['formType']
    );

    // Calculs
    $engine = new StatsEngine($campaignConfig['rules']);
    $stats = $engine->process($orders);

    echo json_encode([
        'success' => true,
        'data' => $stats,
        'meta' => [
            'lastUpdated' => date('H:i:s'),
            'title' => $campaignConfig['title'] ?? 'Tableau de Bord'
        ],
        'debug' => [
            'orderCount' => count($orders),
            // On ne renvoie les logs que si demandé pour alléger le JSON
            'logs' => isset($_GET['debug']) ? $client->getLogs() : [] 
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}