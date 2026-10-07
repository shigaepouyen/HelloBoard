<?php

require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/SatisfactionService.php';

/**
 * Durée de conservation des données personnelles (RGPD).
 *
 * Une campagne sans activité (envoi, pointage, réponse) depuis `retentionMonths`
 * mois (12 par défaut) perd ses données nominatives :
 * - pointages et historique d'envoi supprimés ;
 * - questionnaires de satisfaction anonymisés (les notes et commentaires restent).
 *
 * Lancée au plus une fois par jour depuis l'admin, ou par scripts/purge.php (cron).
 */
class RetentionService {
    const DEFAULT_MONTHS = 12;

    private static $stampFile = __DIR__ . '/../../config/.last_purge';

    public static function months(array $globals) {
        $months = (int)($globals['retentionMonths'] ?? self::DEFAULT_MONTHS);
        return $months >= 1 ? $months : self::DEFAULT_MONTHS;
    }

    /** Lance la purge si elle n'a pas tourné depuis 24 h. Ne bloque jamais l'admin. */
    public static function runDaily(array $globals) {
        if (is_file(self::$stampFile) && filemtime(self::$stampFile) > time() - 86400) return null;
        try {
            $report = self::run(self::months($globals), true);
            @touch(self::$stampFile);
            return $report;
        } catch (Exception $e) {
            error_log('RetentionService: ' . $e->getMessage());
            return null;
        }
    }

    /** @return array{cutoff:string, campaigns:array, legacyReadCleaned:int} */
    public static function run($months, $apply) {
        $cutoff = strtotime("-{$months} months");
        $report = ['cutoff' => date('Y-m-d', $cutoff), 'campaigns' => [], 'legacyReadCleaned' => 0];

        foreach (Storage::listSlugsWithPersonalData() as $slug) {
            if ($apply) $report['legacyReadCleaned'] += Storage::stripLegacyReadTracking($slug);
            $last = Storage::lastPersonalDataActivity($slug);
            if ($last >= $cutoff) continue;
            $report['campaigns'][$slug]['files'] = $apply ? Storage::deletePersonalData($slug) : 'à supprimer';
            $report['campaigns'][$slug]['lastActivity'] = $last ? date('Y-m-d', $last) : 'inconnue';
        }

        $satisfaction = new SatisfactionService();
        foreach ($satisfaction->lastActivityByCampaign() as $slug => $last) {
            if ($last >= $cutoff) continue;
            $report['campaigns'][$slug]['surveyTokens'] = $apply ? $satisfaction->anonymizeCampaign($slug) : 'à anonymiser';
            $report['campaigns'][$slug]['lastActivity'] = date('Y-m-d', $last);
        }

        return $report;
    }
}
