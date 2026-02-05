<?php
// Fichier : src/Services/StatsEngine.php

class StatsEngine {
    private $rules;

    public function __construct($rules) {
        $this->rules = $rules;
    }

    // AJOUT : on passe $goals en paramètre
    public function process($orders, $goals = []) {
        // ... [Le code existant d'initialisation reste identique] ...
        $stats = [
            'kpi' => [
                'revenue' => 0,
                'participants' => 0,
                'donations' => 0,
                'orderCount' => count($orders)
            ],
            'charts' => [],
            'timeline' => [],
            'recent' => [],
            // AJOUT : Structure pour le pacing
            'pacing' => [
                'velocity7d' => 0,      // Moyenne € / jour (7 derniers jours)
                'velocityGlobal' => 0,  // Moyenne € / jour (depuis le début)
                'projectedDate' => null,// Date estimée d'atteinte de l'objectif
                'isSlowingDown' => false, // Alerte ralentissement
                'trend' => 'stable'     // 'up', 'down', 'stable'
            ]
        ];

        // ... [Le code existant des boucles foreach et dailyStats reste identique] ...
        // Je remets ici la boucle dailyStats pour contexte, car on va s'en servir
        
        $groups = [];
        $recentList = [];
        $groupOrder = [];
        // ... (Initialisation groups/orders) ...
        
        // Tri chronologique
        usort($orders, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        $dailyStats = [];
        $cumulativeRevenue = 0;

        // ... (Logique de remplissage de dailyStats existante) ...
        foreach ($orders as $order) {
            $dateKey = substr($order['date'], 0, 10);
            if (!isset($dailyStats[$dateKey])) $dailyStats[$dateKey] = ['rev' => 0, 'pax' => 0];
            // ... (Calcul des montants, items, etc. IDENTIQUE À L'ORIGINAL) ...
            // ... Copiez-collez tout le bloc foreach ($orders as $order) existant ici ...
             $items = isset($order['items']) ? $order['items'] : [];
             foreach ($items as $item) {
                 // ... Votre logique de matching de règles existante ...
                 $amount = (isset($item['amount']) ? $item['amount'] : 0) / 100;
                 $rawName = isset($item['name']) ? trim($item['name']) : 'Inconnu';
                 
                 if ($this->isDonation($item)) {
                    $stats['kpi']['donations'] += $amount;
                    $stats['kpi']['revenue'] += $amount;
                    $dailyStats[$dateKey]['rev'] += $amount;
                    continue; 
                 }
                 
                 $rule = $this->matchRule($rawName);
                 if ($rule && $rule['type'] !== 'Ignorer') {
                    $stats['kpi']['revenue'] += $amount;
                    $dailyStats[$dateKey]['rev'] += $amount;
                    if ($rule['type'] === 'Billet') {
                        $stats['kpi']['participants']++;
                        $dailyStats[$dateKey]['pax']++;
                         if (!($rule['hidden'] ?? false)) {
                            $this->addToGroup($groups, $rule, $rule['displayLabel'] ?: $rawName, 1);
                        }
                        $recentList[] = [
                            'date' => date('d/m H:i', strtotime($order['date'])),
                            'ts' => strtotime($order['date']),
                            'name' => $this->getPayerName($order, $item),
                            'desc' => $rule['displayLabel'] ?: $rawName,
                            'amount' => $amount
                        ];
                    }
                 }
                 // ... (Matching customFields) ...
                 if (!empty($item['customFields'])) {
                    foreach ($item['customFields'] as $field) {
                        $q = trim($field['name'] ?? '');
                        $a = trim((string)($field['answer'] ?? ''));
                        if (empty($a)) continue;

                        $optRule = $this->matchRule($q);
                        if ($optRule && $optRule['type'] === 'Option' && !($optRule['hidden'] ?? false)) {
                            $label = $this->applyTransform($a, $optRule['transform'] ?? '');
                            $this->addToGroup($groups, $optRule, $label, 1);
                        }
                    }
                }
             }
        }
        
        // --- NOUVEAU BLOC : CALCULS PRÉDICTIFS ---
        
        $now = time();
        $rev7Days = 0;
        $rev48h = 0;
        $count7Days = 0;
        
        // On remplit les trous des dates (si pas de vente un jour) pour avoir des moyennes justes
        if (!empty($dailyStats)) {
            $firstDateStr = min(array_keys($dailyStats));
            $firstDate = strtotime($firstDateStr);
            $daysSinceStart = max(1, round(($now - $firstDate) / (60 * 60 * 24)));
            
            // Calcul Vélocité Globale
            $stats['pacing']['velocityGlobal'] = $stats['kpi']['revenue'] / $daysSinceStart;

            // Parcours des stats journalières pour les fenêtres glissantes
            foreach ($dailyStats as $dateStr => $data) {
                $ts = strtotime($dateStr);
                $diffDays = ($now - $ts) / (60 * 60 * 24);

                // 7 derniers jours
                if ($diffDays <= 7) {
                    $rev7Days += $data['rev'];
                    $count7Days++; // Compte le nombre de jours actifs (approximatif)
                }
                
                // 48 dernières heures
                if ($diffDays <= 2) {
                    $rev48h += $data['rev'];
                }
            }

            // Calcul Vélocité 7J (Lissée sur 7 jours réels, pas juste les jours avec ventes)
            $stats['pacing']['velocity7d'] = $rev7Days / 7;

            // 1. PROJECTION (Revenue)
            $goalRev = floatval($goals['revenue'] ?? 0);
            $currentRev = $stats['kpi']['revenue'];
            
            if ($goalRev > $currentRev && $stats['pacing']['velocity7d'] > 0) {
                $remaining = $goalRev - $currentRev;
                $daysNeeded = ceil($remaining / $stats['pacing']['velocity7d']);
                $stats['pacing']['projectedDate'] = date('d/m/Y', strtotime("+$daysNeeded days"));
            } elseif ($currentRev >= $goalRev) {
                $stats['pacing']['projectedDate'] = "Atteint";
            } else {
                $stats['pacing']['projectedDate'] = "Jamais (vitesse nulle)";
            }

            // 2. ALERTE RALENTISSEMENT (Si 48h < 50% de la moyenne globale)
            // On ramène les 48h à une moyenne journalière
            $dailyAvg48h = $rev48h / 2;
            
            // Si la moyenne récente est inférieure à 60% de la moyenne globale, c'est une grosse baisse
            if ($dailyAvg48h < ($stats['pacing']['velocityGlobal'] * 0.6) && $daysSinceStart > 3) {
                $stats['pacing']['isSlowingDown'] = true;
                $stats['pacing']['trend'] = 'down';
            } elseif ($stats['pacing']['velocity7d'] > ($stats['pacing']['velocityGlobal'] * 1.2)) {
                $stats['pacing']['trend'] = 'up'; // Accélération
            }
        }
        
        // --- FIN NOUVEAU BLOC ---

        // ... (Reste du code process : timeline, chart sorting, recentList sorting...)
         foreach ($dailyStats as $day => $val) {
            $cumulativeRevenue += $val['rev'];
            $stats['timeline'][] = [
                'date' => date('d/m', strtotime($day)),
                'pax' => $val['pax'],
                'rev' => $val['rev'],
                'cumulative' => $cumulativeRevenue
            ];
        }

        uksort($groups, function($a, $b) use ($groupOrder) {
            return ($groupOrder[$a] ?? 99) - ($groupOrder[$b] ?? 99);
        });

        foreach ($groups as $name => $g) {
            arsort($g['data']); 
            $stats['charts'][] = [
                'title' => $name,
                'type' => strtolower($g['config']['chartType'] ?? 'pie'),
                'data' => $g['data']
            ];
        }
        
        usort($recentList, function($a, $b) { return $b['ts'] - $a['ts']; });
        $stats['recent'] = $recentList;

        return $stats;
    }
    
    // ... (Méthodes privées inchangées) ...
    private function isDonation($item) {
        $t = strtolower($item['type'] ?? '');
        $n = strtolower($item['name'] ?? '');
        return ($t === 'donation' || strpos($n, 'don ') !== false || strpos($n, 'contribution') !== false);
    }

    private function matchRule($text) {
        $text = mb_strtolower($text, 'UTF-8');
        foreach ($this->rules as $r) {
            if (strpos($text, mb_strtolower($r['pattern'], 'UTF-8')) !== false) return $r;
        }
        return null;
    }

    private function applyTransform($val, $trans) {
        $trans = strtoupper(trim($trans));
        if (empty($trans)) return $val;
        if ($trans === 'FIRST_LETTER') return mb_substr($val, 0, 1);
        if ($trans === 'UPPER') return mb_strtoupper($val);
        if (strpos($trans, 'REGEX:') === 0) {
            $p = substr($trans, 6);
            if (@preg_match("/$p/iu", $val, $m)) return $m[1] ?? $m[0];
        }
        return $val;
    }

    private function addToGroup(&$groups, $rule, $label, $qty) {
        $gn = $rule['group'] ?: "Divers";
        if (!isset($groups[$gn])) $groups[$gn] = ['config' => $rule, 'data' => []];
        $groups[$gn]['data'][$label] = ($groups[$gn]['data'][$label] ?? 0) + $qty;
    }

    private function getPayerName($order, $item) {
        $f = $item['user']['firstName'] ?? $order['payer']['firstName'] ?? 'Anonyme';
        $l = $item['user']['lastName'] ?? $order['payer']['lastName'] ?? '';
        return trim($f . ( !empty($l) ? ' ' . strtoupper(substr($l, 0, 1)) . '.' : '' ));
    }
}