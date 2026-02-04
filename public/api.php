<?php
// On désactive l'affichage des erreurs HTML pour ne pas casser le JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Fonction de shutdown pour attraper les erreurs fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Erreur Critique PHP: ' . $error['message']]);
        exit;
    }
});

header('Content-Type: application/json');

try {
    $srcPath = __DIR__ . '/../src/Services/';
    require_once $srcPath . 'HelloAssoClient.php';
    require_once $srcPath . 'Storage.php';
    require_once $srcPath . 'StatsEngine.php';

    $globals = Storage::getGlobalSettings();
    $campaignId = $_GET['campaign'] ?? null;

    if (!$campaignId) throw new Exception("Aucune campagne spécifiée.");
    if (empty($globals['clientId'])) throw new Exception("Configuration API manquante.");

    $configPath = __DIR__ . "/../config/campaigns/$campaignId.json";
    if (!file_exists($configPath)) throw new Exception("Fichier de configuration introuvable.");
    
    $campaignConfig = json_decode(file_get_contents($configPath), true);
    if (!$campaignConfig) throw new Exception("Configuration corrompue.");

    $client = new HelloAssoClient($globals['clientId'], $globals['clientSecret']);
    
    $orders = $client->fetchAllOrders(
        $campaignConfig['orgSlug'], 
        $campaignConfig['formSlug'], 
        $campaignConfig['formType']
    );

    $engine = new StatsEngine($campaignConfig['rules']);
    $stats = $engine->process($orders);

    echo json_encode([
        'success' => true,
        'data' => $stats,
        'meta' => [
            'lastUpdated' => date('H:i:s'),
            'title' => $campaignConfig['title'] ?? 'Tableau de Bord',
            // AJOUT ICI : On transmet les objectifs à l'interface
            'goals' => $campaignConfig['goals'] ?? ['revenue' => 0, 'n1' => 0]
        ],
        'debug' => [
            'orderCount' => count($orders)
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}