<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

try {
    $srcPath = __DIR__ . '/../src/Services/';
    require_once $srcPath . 'HelloAssoClient.php';
    require_once $srcPath . 'Storage.php';
    require_once $srcPath . 'StatsEngine.php';

    $globals = Storage::getGlobalSettings();
    $campaignId = $_GET['campaign'] ?? null;
    $providedToken = $_GET['token'] ?? null;

    if (!$campaignId) throw new Exception("Paramètres manquants.");

    $configPath = __DIR__ . "/../config/campaigns/$campaignId.json";
    if (!file_exists($configPath)) throw new Exception("Board introuvable.");
    
    $campaignConfig = json_decode(file_get_contents($configPath), true);
    
    $isAdmin = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    $isValidToken = ($providedToken && $providedToken === ($campaignConfig['shareToken'] ?? ''));

    if (!$isAdmin && !$isValidToken) throw new Exception("Accès non autorisé.");

    // On passe le booléen debugMode (false par défaut)
    $client = new HelloAssoClient(
        $globals['clientId'], 
        $globals['clientSecret'], 
        $globals['debugMode'] ?? false
    );
    $orders = $client->fetchAllOrders($campaignConfig['orgSlug'], $campaignConfig['formSlug'], $campaignConfig['formType']);
    $engine = new StatsEngine($campaignConfig['rules']);
    $stats = $engine->process($orders, $campaignConfig['goals'] ?? []);

    echo json_encode([
        'success' => true,
        'data' => $stats,
        'meta' => [
            'lastUpdated' => date('H:i:s'),
            'title' => $campaignConfig['title'] ?? 'Tableau de Bord',
            'formType' => $campaignConfig['formType'] ?? 'Event',
            'goals' => $campaignConfig['goals'] ?? [],
            'markers' => $campaignConfig['markers'] ?? []
        ]
    ]);
} catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }