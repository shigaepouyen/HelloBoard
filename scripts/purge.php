<?php
// Purge RGPD des campagnes inactives (voir src/Services/RetentionService.php).
//
//   php scripts/purge.php            simulation : affiche ce qui serait purgé
//   php scripts/purge.php --apply    purge réelle
//
// Cron conseillé (quotidien) : 15 4 * * * php /chemin/vers/helloboard/scripts/purge.php --apply
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once __DIR__ . '/../src/Services/RetentionService.php';

$apply = in_array('--apply', $argv, true);
$months = RetentionService::months(Storage::getGlobalSettings() ?: []);
$report = RetentionService::run($months, $apply);

echo ($apply ? 'PURGE' : 'SIMULATION') . " — conservation {$months} mois, seuil {$report['cutoff']}\n";
if ($apply) echo "Dates d'ouverture héritées retirées : {$report['legacyReadCleaned']}\n";
if (!$report['campaigns']) { echo "Aucune campagne à purger.\n"; exit; }
foreach ($report['campaigns'] as $slug => $r) {
    echo "- {$slug} (dernière activité {$r['lastActivity']}) : "
        . 'fichiers ' . ($r['files'] ?? 0) . ', jetons satisfaction ' . ($r['surveyTokens'] ?? 0) . "\n";
}
