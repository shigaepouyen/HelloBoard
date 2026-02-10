<?php
$campaign = $_GET['c'] ?? null;
$token = $_GET['t'] ?? null;

if ($campaign && $token) {
    require_once __DIR__ . '/../src/Services/Storage.php';
    $history = Storage::getMailingHistory($campaign);
    $changed = false;

    foreach ($history as $email => &$data) {
        if (($data['token'] ?? '') === $token) {
            if (empty($data['read_at'])) {
                $data['read_at'] = date('Y-m-d H:i:s');
                $changed = true;
            }
            break;
        }
    }

    if ($changed) {
        Storage::saveMailingHistory($campaign, $history);
    }
}

// Serve a 1x1 transparent GIF
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
