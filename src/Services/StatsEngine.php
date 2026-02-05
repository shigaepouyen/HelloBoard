<?php

class StatsEngine {
    private $rules;

    public function __construct($rules) {
        $this->rules = $rules;
    }

    public function process($orders, $goals = []) {
        $stats = [
            'kpi' => [
                'revenue' => 0,
                'participants' => 0,
                'donations' => 0,
                'orderCount' => count($orders),
                'orders_with_tickets' => 0, // Nouveau : Compteur de commandes avec billets
                'orders_with_both' => 0,    // Nouveau : Commandes avec Billet + Don
                'attachment_rate' => 0      // Nouveau : % de générosité
            ],
            'charts' => [],
            'timeline' => [],
            'recent' => [],
            'heatmap' => [],
            'pacing' => [
                'velocity7d' => 0,
                'velocityGlobal' => 0,
                'projectedDate' => null,
                'isSlowingDown' => false,
                'trend' => 'stable'
            ]
        ];

        for($i=0; $i<7; $i++) { $stats['heatmap'][$i] = array_fill(0, 24, 0); }

        $groups = [];
        $groupOrder = [];
        $recentList = [];
        
        // On définit l'ordre des groupes basé sur l'ordre des règles dans l'admin
        $i = 0;
        foreach ($this->rules as $r) {
            $gn = $r['group'] ?: "Divers";
            if (!isset($groupOrder[$gn])) {
                $groupOrder[$gn] = $i++;
            }
        }

        usort($orders, function($a, $b) { return strtotime($a['date']) - strtotime($b['date']); });

        $dailyStats = [];
        $cumulativeRevenue = 0;

        foreach ($orders as $order) {
            $ts = strtotime($order['date']);
            $dateKey = substr($order['date'], 0, 10);
            $dayOfWeek = (int)date('w', $ts);
            $hour = (int)date('H', $ts);

            if (!isset($dailyStats[$dateKey])) $dailyStats[$dateKey] = ['rev' => 0, 'pax' => 0];
            
            $hasTicketInOrder = false;
            $hasDonationInOrder = false;

            foreach ($order['items'] ?? [] as $item) {
                $amount = ($item['amount'] ?? 0) / 100;
                $rawName = trim($item['name'] ?? 'Inconnu');
                
                if ($this->isDonation($item)) {
                    $hasDonationInOrder = true;
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
                        $hasTicketInOrder = true;
                        $stats['kpi']['participants']++;
                        $dailyStats[$dateKey]['pax']++;
                        $stats['heatmap'][$dayOfWeek][$hour]++;
                        
                        if (!($rule['hidden'] ?? false)) {
                            $this->addToGroup($groups, $rule, $rule['displayLabel'] ?: $rawName, 1);
                        }

                        $recentList[] = [
                            'date' => date('d/m H:i', $ts),
                            'ts' => $ts,
                            'name' => $this->getPayerName($order, $item),
                            'desc' => $rule['displayLabel'] ?: $rawName,
                            'amount' => $amount
                        ];
                    }
                }

                // Gestion des options (champs personnalisés)
                foreach ($item['customFields'] ?? [] as $field) {
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

            // Calcul intelligent de l'attachement au niveau de la commande
            if ($hasTicketInOrder) {
                $stats['kpi']['orders_with_tickets']++;
                if ($hasDonationInOrder) {
                    $stats['kpi']['orders_with_both']++;
                }
            }
        }

        // Calcul final du taux de générosité
        if ($stats['kpi']['orders_with_tickets'] > 0) {
            $stats['kpi']['attachment_rate'] = round(($stats['kpi']['orders_with_both'] / $stats['kpi']['orders_with_tickets']) * 100, 1);
        }
        
        // PACING (Logique de projection et tendances)
        $now = time();
        if (!empty($dailyStats)) {
            $firstDate = strtotime(min(array_keys($dailyStats)));
            $daysSinceStart = max(1, round(($now - $firstDate) / 86400));
            $stats['pacing']['velocityGlobal'] = $stats['kpi']['revenue'] / $daysSinceStart;

            $rev7Days = 0; $rev48h = 0;
            foreach ($dailyStats as $dateStr => $data) {
                $diffDays = ($now - strtotime($dateStr)) / 86400;
                if ($diffDays <= 7) $rev7Days += $data['rev'];
                if ($diffDays <= 2) $rev48h += $data['rev'];
            }
            $stats['pacing']['velocity7d'] = $rev7Days / 7;

            $goalRev = floatval($goals['revenue'] ?? 0);
            if ($goalRev > $stats['kpi']['revenue'] && $stats['pacing']['velocity7d'] > 0) {
                $daysNeeded = ceil(($goalRev - $stats['kpi']['revenue']) / $stats['pacing']['velocity7d']);
                $stats['pacing']['projectedDate'] = date('d/m/Y', strtotime("+$daysNeeded days"));
            } elseif ($stats['kpi']['revenue'] >= $goalRev && $goalRev > 0) {
                $stats['pacing']['projectedDate'] = "Atteint";
            }

            if (($rev48h / 2) < ($stats['pacing']['velocityGlobal'] * 0.6) && $daysSinceStart > 3) {
                $stats['pacing']['isSlowingDown'] = true;
                $stats['pacing']['trend'] = 'down';
            } elseif ($stats['pacing']['velocity7d'] > ($stats['pacing']['velocityGlobal'] * 1.2)) {
                $stats['pacing']['trend'] = 'up';
            }
        }

        foreach ($dailyStats as $day => $val) {
            $cumulativeRevenue += $val['rev'];
            $stats['timeline'][] = [
                'date' => date('d/m', strtotime($day)),
                'pax' => $val['pax'],
                'rev' => $val['rev'],
                'cumulative' => $cumulativeRevenue
            ];
        }

        // TRI DES GROUPES PAR ORDRE ADMIN
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
        // On garde la config de la règle pour savoir quel type de graphique utiliser
        if (!isset($groups[$gn])) $groups[$gn] = ['config' => $rule, 'data' => []];
        $groups[$gn]['data'][$label] = ($groups[$gn]['data'][$label] ?? 0) + $qty;
    }

    private function getPayerName($order, $item) {
        $f = $item['user']['firstName'] ?? $order['payer']['firstName'] ?? 'Anonyme';
        $l = $item['user']['lastName'] ?? $order['payer']['lastName'] ?? '';
        return trim($f . ( !empty($l) ? ' ' . strtoupper(substr($l, 0, 1)) . '.' : '' ));
    }
}