<?php

class StatsEngine {
    private $rules;

    public function __construct($rules) {
        $this->rules = $rules;
    }

    public function process($orders) {
        $stats = [
            'kpi' => [
                'revenue' => 0,
                'participants' => 0,
                'donations' => 0,
                'orderCount' => count($orders)
            ],
            'charts' => [],
            'timeline' => [],
            'recent' => []
        ];

        $groups = [];
        $recentList = [];
        
        // 1. Ordre des blocs défini par l'admin
        $groupOrder = [];
        $idx = 0;
        foreach ($this->rules as $rule) {
            $gn = !empty($rule['group']) ? $rule['group'] : "Divers";
            if (!isset($groupOrder[$gn])) $groupOrder[$gn] = $idx++;
        }

        // 2. Tri pour la timeline
        usort($orders, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        $dailyStats = [];
        $cumulativeRevenue = 0;

        foreach ($orders as $order) {
            $dateKey = substr($order['date'], 0, 10);
            if (!isset($dailyStats[$dateKey])) $dailyStats[$dateKey] = ['rev' => 0, 'pax' => 0];

            $items = isset($order['items']) ? $order['items'] : [];

            foreach ($items as $item) {
                $amount = (isset($item['amount']) ? $item['amount'] : 0) / 100;
                $rawName = isset($item['name']) ? trim($item['name']) : 'Inconnu';

                // Gestion des Dons
                if ($this->isDonation($item)) {
                    $stats['kpi']['donations'] += $amount;
                    $stats['kpi']['revenue'] += $amount;
                    $dailyStats[$dateKey]['rev'] += $amount;
                    continue; 
                }

                $rule = $this->matchRule($rawName);
                
                if ($rule && !in_array($rule['type'], ['Ignorer', 'Info'])) {
                    $stats['kpi']['revenue'] += $amount;
                    $dailyStats[$dateKey]['rev'] += $amount;

                    if ($rule['type'] === 'Billet') {
                        $stats['kpi']['participants']++;
                        $dailyStats[$dateKey]['pax']++;
                        $this->addToGroup($groups, $rule, $rule['displayLabel'] ?: $rawName, 1);
                        
                        $recentList[] = [
                            'date' => date('d/m H:i', strtotime($order['date'])),
                            'ts' => strtotime($order['date']),
                            'name' => $this->getPayerName($order, $item),
                            'desc' => $rule['displayLabel'] ?: $rawName,
                            'amount' => $amount
                        ];
                    }
                }

                if (!empty($item['customFields'])) {
                    foreach ($item['customFields'] as $field) {
                        $q = trim($field['name'] ?? '');
                        $a = trim((string)($field['answer'] ?? ''));
                        if (empty($a)) continue;

                        $optRule = $this->matchRule($q);
                        if ($optRule && $optRule['type'] === 'Option') {
                            $label = $this->applyTransform($a, $optRule['transform'] ?? '');
                            $this->addToGroup($groups, $optRule, $label, 1);
                        }
                    }
                }
            }
        }

        // Timeline
        foreach ($dailyStats as $day => $val) {
            $cumulativeRevenue += $val['rev'];
            $stats['timeline'][] = [
                'date' => $day,
                'participants' => $val['pax'],
                'revenue' => $val['rev'],
                'cumulative' => $cumulativeRevenue
            ];
        }

        // Formatage Graphiques
        uksort($groups, function($a, $b) use ($groupOrder) {
            return (isset($groupOrder[$a]) ? $groupOrder[$a] : 99) - (isset($groupOrder[$b]) ? $groupOrder[$b] : 99);
        });

        foreach ($groups as $name => $g) {
            arsort($g['data']); 
            $stats['charts'][] = [
                'title' => isset($g['config']['displayLabel']) ? $g['config']['displayLabel'] : $name,
                'type' => strtolower(isset($g['config']['chartType']) ? $g['config']['chartType'] : 'doughnut'),
                'data' => $g['data']
            ];
        }
        
        usort($recentList, function($a, $b) { return $b['ts'] - $a['ts']; });
        $stats['recent'] = array_slice($recentList, 0, 10);

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
            $pattern = mb_strtolower($r['pattern'], 'UTF-8');
            if (strpos($text, $pattern) !== false) return $r;
        }
        return null;
    }

    private function applyTransform($val, $trans) {
        $trans = strtoupper(trim($trans));
        if ($trans === 'FIRST_LETTER') return mb_substr($val, 0, 1);
        if ($trans === 'UPPER') return mb_strtoupper($val);
        if (str_starts_with($trans, 'REGEX:')) {
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