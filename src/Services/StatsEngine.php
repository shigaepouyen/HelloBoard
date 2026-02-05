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
        
        $groupOrder = [];
        $idx = 0;
        foreach ($this->rules as $rule) {
            $gn = !empty($rule['group']) ? $rule['group'] : "Divers";
            if (!isset($groupOrder[$gn])) $groupOrder[$gn] = $idx++;
        }

        // Tri chronologique pour la timeline
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
        
        // Tri décroissant (plus récent en haut)
        usort($recentList, function($a, $b) { return $b['ts'] - $a['ts']; });
        
        // --- MODIFICATION ICI : On renvoie TOUT ---
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
        if (!isset($groups[$gn])) $groups[$gn] = ['config' => $rule, 'data' => []];
        $groups[$gn]['data'][$label] = ($groups[$gn]['data'][$label] ?? 0) + $qty;
    }

    private function getPayerName($order, $item) {
        $f = $item['user']['firstName'] ?? $order['payer']['firstName'] ?? 'Anonyme';
        $l = $item['user']['lastName'] ?? $order['payer']['lastName'] ?? '';
        return trim($f . ( !empty($l) ? ' ' . strtoupper(substr($l, 0, 1)) . '.' : '' ));
    }
}