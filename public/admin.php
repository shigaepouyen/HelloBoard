<?php
session_start();
$srcPath = __DIR__ . '/../src/Services/';
require_once $srcPath . 'Storage.php';
require_once $srcPath . 'HelloAssoClient.php';

$globals = Storage::getGlobalSettings();
$adminPassword = $globals['adminPassword'] ?? null;

// --- 1. GESTION AUTHENTIFICATION ---
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
if (isset($_POST['login'])) {
    if ($_POST['password'] === $adminPassword) { $_SESSION['authenticated'] = true; } else { $loginError = "Mot de passe incorrect"; }
}

if ($adminPassword && !isset($_SESSION['authenticated'])) {
    ?>
    <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Admin</title><script src="https://cdn.tailwindcss.com"></script><style>@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&display=swap');body{font-family:'Plus Jakarta Sans',sans-serif;background:#0f172a;}</style></head><body class="min-h-screen flex items-center justify-center p-6"><div class="w-full max-w-md bg-white rounded-[3rem] p-10 text-center"><h2 class="text-3xl font-black mb-10 italic uppercase">Console Admin</h2><form method="POST" class="space-y-4"><input type="password" name="password" class="w-full bg-slate-50 border-2 border-transparent focus:border-blue-600 rounded-[1.5rem] p-5 text-2xl text-center outline-none" placeholder="••••••" required autofocus><button type="submit" name="login" class="w-full bg-blue-600 text-white py-5 rounded-[2rem] font-black uppercase text-xs">Accéder</button></form><?php if(isset($loginError)): ?><p class="text-red-500 font-bold mt-4"><?= $loginError ?></p><?php endif; ?></div></body></html>
    <?php exit;
}

$action = $_GET['action'] ?? 'list';
$localCampaigns = Storage::listCampaigns();
$client = new HelloAssoClient($globals['clientId']??'', $globals['clientSecret']??'', $globals['debugMode']??false);

// --- 2. TRAITEMENT DES ACTIONS ---

// Sauvegarde globale
if (isset($_POST['save_settings'])) {
    $newSettings = [
        'clientId' => trim($_POST['clientId']),
        'clientSecret' => trim($_POST['clientSecret']),
        'orgSlug' => trim($_POST['orgSlug']),
        'adminPassword' => $adminPassword,
        'debugMode' => isset($_POST['debugMode'])
    ];
    Storage::saveGlobalSettings($newSettings);
    header('Location: admin.php?action=settings&saved=1'); exit;
}

// Scan des campagnes
$scanResults = null;
if (isset($_POST['run_scan'])) {
    $scanResults = $client->discoverCampaigns($globals['orgSlug'] ?? '');
    $action = 'scan';
}

// Toggle Archive
if ($action === 'toggle_archive' && isset($_GET['campaign'])) {
    $slug = $_GET['campaign'];
    $campaigns = Storage::listCampaigns();
    foreach($campaigns as $conf) {
        if ($conf['slug'] === $slug) {
            $conf['archived'] = !($conf['archived'] ?? false);
            Storage::saveCampaign($slug, $conf);
            break;
        }
    }
    header('Location: admin.php'); exit;
}

// Delete
if ($action === 'delete' && isset($_GET['campaign'])) {
    Storage::deleteCampaign($_GET['campaign']);
    header('Location: admin.php'); exit;
}

// Clear logs
if ($action === 'clear_log') {
    $logFile = __DIR__ . '/../logs/debug_helloasso.log';
    if (file_exists($logFile)) unlink($logFile);
    header('Location: admin.php?action=settings'); exit;
}

// Download logs
if ($action === 'dl_log') {
    $logFile = __DIR__ . '/../logs/debug_helloasso.log';
    if (file_exists($logFile)) {
        header('Content-Type: text/plain'); header('Content-Disposition: attachment; filename="debug_helloasso.log"');
        readfile($logFile); exit;
    }
}

// Save Board
if (isset($_POST['save_campaign'])) {
    $config = json_decode($_POST['config'], true);
    if ($config) {
        if (empty($config['shareToken'])) $config['shareToken'] = bin2hex(random_bytes(16));
        Storage::saveCampaign($config['slug'], $config);
    }
    echo json_encode(['success' => true]); exit;
}

// API Analyze (for configuration screen)
if ($action === 'analyze') {
    header('Content-Type: application/json');
    $form = $_GET['form']; $org = $_GET['org'];
    $orders = $client->fetchAllOrders($org, $form, $_GET['type'] ?? 'Event');
    $itemsFound = [];
    foreach(array_slice($orders, 0, 100) as $o) {
        foreach($o['items'] ?? [] as $i) {
            if(!empty($i['name'])) {
                $name = trim($i['name']);
                if (!isset($itemsFound[$name])) {
                    $isProbableMain = ($i['amount'] > 0);
                    $itemsFound[$name] = ['pattern' => $name, 'category' => 'item', 'isMain' => $isProbableMain];
                }
            }
            foreach($i['customFields'] ?? [] as $cf) {
                if(!empty($cf['name'])) {
                    $name = trim($cf['name']);
                    if (!isset($itemsFound[$name])) {
                        $itemsFound[$name] = ['pattern' => $name, 'category' => 'field', 'isMain' => false];
                    }
                }
            }
        }
    }

    $configFile = __DIR__ . "/../config/campaigns/$form.json";
    $existing = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    echo json_encode([
        'rules' => $existing['rules'] ?? [],
        'goals' => $existing['goals'] ?? ['revenue'=>0, 'tickets'=>0, 'n1'=>0],
        'markers' => $existing['markers'] ?? [],
        'shareToken' => $existing['shareToken'] ?? null,
        'formType' => $existing['formType'] ?? null,
        'title' => $existing['title'] ?? null,
        'apiItems' => array_values($itemsFound)
    ]); exit;
}

// Exports
if (($action === 'export_csv' || $action === 'guestlist') && isset($_GET['campaign'])) {
    $slug = $_GET['campaign'];
    $currentCamp = null;
    foreach($localCampaigns as $c) { if($c['slug'] === $slug) $currentCamp = $c; }

    if ($currentCamp) {
        $orders = $client->fetchAllOrders($currentCamp['orgSlug'], $currentCamp['formSlug'], $currentCamp['formType']);
        $rules = $currentCamp['rules'] ?? [];
        $matchRule = function($text) use ($rules) {
            $text = mb_strtolower($text, 'UTF-8');
            foreach ($rules as $r) {
                if (strpos($text, mb_strtolower($r['pattern'], 'UTF-8')) !== false) return $r;
            }
            return null;
        };

        $participants = [];
        $groupByOrder = $currentCamp['guestlist']['groupByOrder'] ?? false;

        foreach($orders as $order) {
            $allValidItems = [];
            foreach($order['items'] as $item) {
                if (isset($item['state']) && $item['state'] === 'Canceled') continue;
                if ($item['type'] === 'Donation') continue;

                $rule = $matchRule($item['name']);
                $itemType = 'Option'; // Default
                if ($rule) {
                    $itemType = $rule['type'];
                } else if (($item['amount'] ?? 0) > 0) {
                    $itemType = 'Billet';
                }

                $allValidItems[] = array_merge($item, ['computedType' => $itemType]);
            }

            if (empty($allValidItems)) continue;

            if ($groupByOrder) {
                $aggregatedItems = [];
                $allOptions = [];
                $phone = '';

                foreach($allValidItems as $item) {
                    $name = $item['name'];
                    if (!isset($aggregatedItems[$name])) {
                        $aggregatedItems[$name] = ['name' => $name, 'qty' => 0, 'type' => $item['computedType']];
                    }
                    $aggregatedItems[$name]['qty']++;

                    foreach($item['customFields'] ?? [] as $field) {
                        $allOptions[] = $field['name'] . ': ' . $field['answer'];
                        if (empty($phone) && (strpos(mb_strtolower($field['name']), 'téléphone') !== false || $field['type'] === 'Phone')) {
                            $phone = $field['answer'];
                        }
                    }
                }

                $itemStrings = [];
                foreach($aggregatedItems as $ai) {
                    $itemStrings[] = ($ai['qty'] > 1 ? $ai['qty'] . 'x ' : '') . $ai['name'];
                }

                $participants[] = [
                    'date' => substr($order['date'], 0, 10),
                    'nom' => strtoupper($order['payer']['lastName'] ?? ''),
                    'prenom' => $order['payer']['firstName'] ?? '',
                    'formule' => implode(', ', $itemStrings),
                    'options' => implode(' | ', array_unique($allOptions)),
                    'email' => $order['payer']['email'] ?? '',
                    'phone' => $phone,
                    'ref_commande' => $order['id'],
                    'items_list' => array_values($aggregatedItems)
                ];
            } else {
                foreach($allValidItems as $item) {
                    $options = [];
                    $phone = '';
                    foreach($item['customFields'] ?? [] as $field) {
                        $options[] = $field['name'] . ': ' . $field['answer'];
                        if (strpos(mb_strtolower($field['name']), 'téléphone') !== false || $field['type'] === 'Phone') {
                            $phone = $field['answer'];
                        }
                    }
                    $participants[] = [
                        'date' => substr($order['date'], 0, 10),
                        'nom' => strtoupper($item['user']['lastName'] ?? $order['payer']['lastName'] ?? ''),
                        'prenom' => $item['user']['firstName'] ?? $order['payer']['firstName'] ?? '',
                        'formule' => $item['name'],
                        'options' => implode(' | ', $options),
                        'email' => $order['payer']['email'] ?? '',
                        'phone' => $phone,
                        'ref_commande' => $order['id'],
                        'items_list' => [['name' => $item['name'], 'qty' => 1, 'type' => $item['computedType']]]
                    ];
                }
            }
        }
        usort($participants, function($a, $b) { return strcmp($a['nom'], $b['nom']); });

        if ($action === 'export_csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=inscrits_' . $slug . '_' . date('Y-m-d') . '.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Nom', 'Prenom', 'Formule', 'Options', 'Email', 'Telephone', 'Ref']);
            foreach ($participants as $p) fputcsv($output, array_values($p)); exit;
        }
        if ($action === 'guestlist') {
            include __DIR__ . '/../templates/guestlist.php';
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>HelloBoard — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
        .admin-card { background: white; border-radius: 2rem; border: 1px solid #edf2f7; }
        .input-soft { background: #f1f5f9; border: 2px solid transparent; border-radius: 1.25rem; padding: 12px 16px; font-weight: 700; width: 100%; outline: none; transition: 0.2s; }
        .input-soft:focus { border-color: #2563eb; background: white; }
        .toggle-btn { width: 44px; height: 24px; background: #cbd5e1; border-radius: 20px; position: relative; cursor: pointer; }
        .toggle-btn.active { background: #2563eb; }
        .toggle-btn::after { content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; background: white; border-radius: 50%; transition: 0.3s; }
        .toggle-btn.active::after { transform: translateX(20px); }
        .animate-fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="pb-32">

    <nav class="p-6 bg-white border-b border-slate-100 sticky top-0 z-50 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-4">
            <a href="admin.php" class="flex items-center gap-2">
                <div class="w-8 h-8 bg-slate-900 rounded-lg text-white flex items-center justify-center font-black italic">H</div>
                <h1 class="font-black italic uppercase text-slate-900 hidden md:block">Console Admin</h1>
            </a>
        </div>
        <div class="flex items-center gap-6">
            <a href="admin.php" class="text-xs font-black uppercase tracking-widest <?= $action === 'list' ? 'text-blue-600' : 'text-slate-400' ?>">Boards</a>
            <a href="admin.php?action=settings" class="text-xs font-black uppercase tracking-widest <?= $action === 'settings' ? 'text-blue-600' : 'text-slate-400' ?>">Réglages</a>
            <div class="h-6 w-px bg-slate-200"></div>
            <a href="index.php" class="text-xs font-black uppercase text-slate-400 hover:text-red-500 transition">Quitter</a>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-12">

        <?php if ($action === 'list'): ?>
            <!-- LISTE DES BOARDS -->
            <div class="animate-fade-in">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-10">
                    <div>
                        <h2 class="text-3xl font-black italic uppercase text-slate-900">Mes Tableaux</h2>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Gérez vos boards et visualisations</p>
                    </div>
                    <div class="flex gap-3">
                        <form method="POST" class="inline">
                            <button type="submit" name="run_scan" class="bg-white border border-slate-200 text-slate-600 px-6 py-4 rounded-2xl font-black uppercase text-xs hover:bg-slate-50 transition shadow-sm">
                                <i class="fa-solid fa-sync-alt mr-2"></i> Scanner HelloAsso
                            </button>
                        </form>
                    </div>
                </div>

                <div class="grid gap-4">
                    <?php if(empty($localCampaigns)): ?>
                        <div class="text-center py-20 bg-slate-50 rounded-[3rem] border-2 border-dashed border-slate-200">
                            <p class="text-slate-400 font-bold mb-4 italic">Aucun board n'a été créé pour le moment.</p>
                            <p class="text-slate-300 text-[10px] uppercase font-black">Utilisez le bouton "Scanner" pour importer vos formulaires</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach($localCampaigns as $c): $isArchived = !empty($c['archived']); ?>
                        <div class="admin-card p-6 flex flex-col md:flex-row justify-between items-center gap-4 transition group hover:shadow-xl hover:shadow-slate-200/50 hover:border-blue-200 <?= $isArchived ? 'opacity-60 bg-slate-50 grayscale' : '' ?>">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <h3 class="font-black text-xl text-slate-800"><?= htmlspecialchars($c['title']) ?></h3>
                                    <?php if($isArchived): ?><span class="bg-slate-200 text-slate-500 text-[9px] font-black px-2 py-0.5 rounded uppercase">Archivé</span><?php endif; ?>
                                </div>
                                <div class="flex items-center gap-4 mt-2">
                                    <span class="text-[10px] font-black bg-slate-100 text-slate-500 px-2 py-1 rounded uppercase"><?= $c['formType'] ?></span>
                                    <a href="index.php?campaign=<?= $c['slug'] ?>" target="_blank" class="text-[10px] text-blue-500 font-black uppercase hover:underline"><i class="fa-solid fa-external-link-alt mr-1"></i> Voir</a>
                                    <a href="admin.php?action=guestlist&campaign=<?= $c['slug'] ?>" class="text-[10px] text-emerald-600 font-black uppercase hover:underline"><i class="fa-solid fa-clipboard-list mr-1"></i> Inscrits</a>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="admin.php?action=export_csv&campaign=<?= $c['slug'] ?>" class="w-12 h-12 flex items-center justify-center bg-slate-50 text-slate-400 rounded-xl hover:bg-slate-100 transition" title="Export CSV"><i class="fa-solid fa-download"></i></a>
                                <a href="admin.php?action=toggle_archive&campaign=<?= $c['slug'] ?>" class="w-12 h-12 flex items-center justify-center bg-slate-50 text-slate-400 rounded-xl hover:bg-slate-100 transition" title="<?= $isArchived ? 'Restaurer' : 'Archiver' ?>"><i class="fa-solid <?= $isArchived ? 'fa-box-open' : 'fa-box-archive' ?>"></i></a>
                                <button onclick="confirmDelete('<?= $c['slug'] ?>', '<?= htmlspecialchars(addslashes($c['title']), ENT_QUOTES) ?>')" class="w-12 h-12 flex items-center justify-center bg-red-50 text-red-300 rounded-xl hover:bg-red-500 hover:text-white transition" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                                <a href="admin.php?action=edit&campaign=<?= $c['slug'] ?>" class="bg-blue-600 text-white px-8 py-4 rounded-2xl text-xs font-black uppercase shadow-lg shadow-blue-100 transition transform active:scale-95 ml-2">Réglages</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php elseif ($action === 'settings'): ?>
            <!-- PARAMETRES GLOBAUX -->
            <div class="animate-fade-in max-w-2xl mx-auto">
                <div class="mb-10">
                    <h2 class="text-3xl font-black italic uppercase text-slate-900">Configuration</h2>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Paramètres de connexion API et sécurité</p>
                </div>

                <?php if(isset($_GET['saved'])): ?>
                    <div class="mb-8 p-4 bg-emerald-50 text-emerald-600 rounded-2xl font-black uppercase text-[10px] tracking-widest border border-emerald-100 flex items-center gap-3 animate-bounce">
                        <i class="fa-solid fa-check-circle text-lg"></i> Configuration enregistrée !
                    </div>
                <?php endif; ?>

                <div class="admin-card p-10 mb-8">
                    <form method="POST">
                        <div class="space-y-8">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase block mb-3 tracking-widest italic">Identifiants HelloAsso</label>
                                <div class="grid gap-4">
                                    <input type="text" name="clientId" placeholder="Client ID" value="<?= htmlspecialchars($globals['clientId']??'') ?>" class="input-soft" required>
                                    <input type="password" name="clientSecret" placeholder="Client Secret" value="<?= htmlspecialchars($globals['clientSecret']??'') ?>" class="input-soft" required>
                                    <input type="text" name="orgSlug" placeholder="Slug de l'organisation" value="<?= htmlspecialchars($globals['orgSlug']??'') ?>" class="input-soft" required>
                                </div>
                            </div>

                            <div class="pt-8 border-t border-slate-100">
                                <label class="text-[10px] font-black text-slate-400 uppercase block mb-3 tracking-widest italic">Maintenance & Logs</label>
                                <div class="flex items-center justify-between bg-slate-50 p-6 rounded-2xl border border-slate-100">
                                    <div class="flex items-center gap-4">
                                        <input type="checkbox" name="debugMode" id="debugMode" class="w-6 h-6 accent-blue-600" <?= ($globals['debugMode']??false) ? 'checked' : '' ?>>
                                        <div>
                                            <label for="debugMode" class="text-xs font-black uppercase text-slate-700 cursor-pointer block">Mode Débug</label>
                                            <p class="text-[10px] text-slate-400 font-bold">Enregistre les échanges API pour le support</p>
                                        </div>
                                    </div>
                                    <?php
                                    $logFile = __DIR__ . '/../logs/debug_helloasso.log';
                                    if(file_exists($logFile)):
                                    ?>
                                        <div class="flex gap-2">
                                            <a href="admin.php?action=dl_log" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-slate-50 transition">Télécharger Log</a>
                                            <a href="admin.php?action=clear_log" class="bg-red-50 text-red-500 px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-red-500 hover:text-white transition">Effacer</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-12">
                            <button type="submit" name="save_settings" class="w-full bg-slate-900 text-white py-5 rounded-[2rem] font-black uppercase text-xs tracking-widest shadow-xl hover:bg-blue-600 transition">
                                Mettre à jour la configuration
                            </button>
                        </div>
                    </form>
                </div>
                <div class="text-center">
                    <a href="admin.php" class="text-xs font-black text-slate-300 uppercase hover:text-slate-500 transition">Annuler les modifications</a>
                </div>
            </div>

        <?php elseif ($action === 'scan'): ?>
            <!-- RESULTATS DU SCAN -->
            <div class="animate-fade-in">
                <div class="mb-10 flex justify-between items-end">
                    <div>
                        <h2 class="text-3xl font-black italic uppercase text-slate-900">Campagnes Trouvées</h2>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Sélectionnez un formulaire à configurer</p>
                    </div>
                    <a href="admin.php" class="text-xs font-black text-slate-400 uppercase hover:text-slate-900 transition">Retour</a>
                </div>

                <div class="grid gap-4">
                    <?php if(!isset($scanResults) || empty($scanResults['forms'])): ?>
                        <div class="p-10 bg-orange-50 text-orange-600 rounded-[2.5rem] text-center border-2 border-dashed border-orange-200">
                            <i class="fa-solid fa-exclamation-triangle text-3xl mb-4"></i>
                            <p class="font-black uppercase text-sm italic">Aucun formulaire n'a été détecté sur ce compte.</p>
                            <p class="text-xs mt-2 opacity-70">Vérifiez vos identifiants API et le slug de l'organisation.</p>
                        </div>
                    <?php else:
                        $existingSlugs = array_column($localCampaigns, 'slug');
                        foreach($scanResults['forms'] as $form):
                            $isConfigured = in_array($form['slug'], $existingSlugs);
                    ?>
                        <div class="admin-card p-6 flex justify-between items-center animate-fade-in <?= $isConfigured ? 'bg-emerald-50/30 border-emerald-100' : '' ?>">
                            <div>
                                <div class="flex items-center gap-3">
                                    <h4 class="font-black text-lg text-slate-800"><?= htmlspecialchars($form['name']) ?></h4>
                                    <?php if($isConfigured): ?>
                                        <span class="text-[9px] font-black bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded uppercase flex items-center gap-1"><i class="fa-solid fa-check"></i> Configuré</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-[10px] font-black bg-slate-100 text-slate-500 px-2 py-1 rounded uppercase mt-2 inline-block"><?= $form['type'] ?></span>
                            </div>
                            <a href="admin.php?action=edit&campaign=<?= $form['slug'] ?>&org=<?= $scanResults['orgSlug'] ?>&type=<?= $form['type'] ?>&name=<?= urlencode($form['name']) ?>"
                               class="<?= $isConfigured ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-600 text-white shadow-lg shadow-blue-100' ?> px-8 py-4 rounded-2xl text-xs font-black uppercase transition transform active:scale-95">
                                <?= $isConfigured ? 'Modifier' : 'Configurer' ?>
                            </a>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        <?php elseif ($action === 'edit'): ?>
            <!-- EDITION D'UN BOARD -->
            <div id="config-zone" class="animate-fade-in">
                <div class="py-20 text-center animate-pulse text-slate-400 font-black uppercase tracking-widest italic">Analyse des données en cours...</div>
            </div>

            <script>
            window.onload = function() {
                const urlParams = new URLSearchParams(window.location.search);
                const campaign = urlParams.get('campaign');
                const org = urlParams.get('org') || '<?= $globals['orgSlug'] ?? '' ?>';
                const type = urlParams.get('type') || '';
                const name = urlParams.get('name') || '';

                // Si on a les infos nécessaires, on lance l'analyse
                if (campaign) {
                    editCamp(org, campaign, type, name);
                }
            };
            </script>
        <?php endif; ?>

    </main>

    <script>
    const labelsMap = {
        'Event': { main: '🎫 Billet', quota: 'Quota Billets' },
        'Shop': { main: '📦 Produit', quota: 'Quota Articles' },
        'Membership': { main: '🆔 Adhésion', quota: 'Quota Adhésions' },
        'Donation': { main: '❤️ Donateur', quota: 'Objectif Dons' },
        'Crowdfunding': { main: '🚀 Contributeur', quota: 'Objectif Contrib.' },
        'PaymentForm': { main: '💳 Article', quota: 'Quota Articles' },
        'Checkout': { main: '📦 Produit', quota: 'Quota Articles' }
    };

    function confirmDelete(slug, title) {
        if(confirm(`Voulez-vous vraiment supprimer définitivement le board "${title}" ?\nCette action est irréversible.`)) {
            window.location.href = `admin.php?action=delete&campaign=${slug}`;
        }
    }

    async function editCamp(org, slug, forceType = '', forceName = '') {
        const zone = document.getElementById('config-zone');
        if (!zone) return;

        try {
            const res = await fetch(`admin.php?action=analyze&org=${org}&form=${slug}${forceType ? '&type='+forceType : ''}`);
            const data = await res.json();

            // On retrouve le type et le nom soit depuis l'URL, soit depuis la config existante si dispo
            const currentType = forceType || data.formType || 'Event';
            const currentName = forceName || data.title || slug;
            const labels = labelsMap[currentType] || labelsMap['Event'];
            const isShop = (['Shop', 'Checkout', 'PaymentForm', 'Product', 'product'].includes(currentType));

            const goals = data.goals || { revenue: 0, tickets: 0, n1: 0 };
            const rules = data.rules || [];
            const guestlist = data.guestlist || { columns: ['nom', 'prenom', 'formule', 'options'], showCheckboxes: true, groupByOrder: false };
            const token = data.shareToken || '';

            // Fusionner les items trouvés dans l'API avec les règles existantes
            (data.apiItems || []).forEach(item => {
                if(!rules.find(r => r.pattern === item.pattern)) {
                    rules.push({
                        pattern: item.pattern,
                        displayLabel: item.pattern,
                        type: item.isMain ? 'Billet' : 'Option',
                        group: 'Divers',
                        chartType: 'pie',
                        transform: '',
                        hidden: false,
                        costPrice: 0,
                        sellingPrice: 0,
                        stock: 0
                    });
                }
            });

            zone.innerHTML = `
                <div class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div>
                        <a href="admin.php" class="text-[10px] font-black text-slate-400 uppercase hover:text-slate-900 transition flex items-center gap-2 mb-2">
                            <i class="fa-solid fa-arrow-left"></i> Retour aux boards
                        </a>
                        <h2 class="text-3xl font-black italic uppercase text-slate-900">${currentName}</h2>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Configuration du board et des indicateurs</p>
                    </div>
                    <div class="flex gap-3 w-full md:w-auto">
                        <button onclick="location.reload()" class="flex-1 md:flex-none text-slate-400 hover:text-slate-600 font-black uppercase text-xs tracking-widest transition px-6">Annuler</button>
                        <button id="save-main-btn" class="flex-1 md:flex-none bg-blue-600 text-white px-10 py-5 rounded-[1.5rem] font-black uppercase text-xs shadow-xl shadow-blue-100 transition transform active:scale-95">
                            Sauvegarder
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
                    <div class="admin-card p-8">
                        <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic tracking-widest border-b border-slate-50 pb-4">Objectifs & Quotas</h3>
                        <div class="grid gap-6">
                            <div>
                                <label class="text-[10px] font-black text-slate-500 uppercase block mb-2 tracking-tighter">Objectif de Recettes (€)</label>
                                <input type="number" id="goal-rev" class="input-soft" value="${goals.revenue}">
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-500 uppercase block mb-2 tracking-tighter">${labels.quota.toUpperCase()} (QTÉ)</label>
                                <input type="number" id="goal-tix" class="input-soft" value="${goals.tickets}">
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-500 uppercase block mb-2 tracking-tighter">Référence année précédente (N-1)</label>
                                <input type="number" id="goal-n1" class="input-soft" value="${goals.n1}">
                            </div>
                        </div>
                    </div>

                    <div class="admin-card p-8">
                        <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic tracking-widest border-b border-slate-50 pb-4">Marqueurs Temporels</h3>
                        <div id="markers-list" class="space-y-3 mb-6">
                            ${(data.markers || []).map(m => `
                                <div class="flex gap-3 marker-row animate-fade-in">
                                    <input type="text" placeholder="Événement (ex: Envoi Email)" class="marker-label input-soft !py-3 !text-xs" value="${m.label}">
                                    <input type="date" class="marker-date input-soft !py-3 !text-xs w-44" value="${m.date}">
                                    <button onclick="this.parentElement.remove()" class="text-slate-300 hover:text-red-500 transition px-2"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            `).join('')}
                        </div>
                        <button onclick="addMarkerRow()" class="w-full py-4 border-2 border-dashed border-slate-100 rounded-2xl text-[10px] font-black text-blue-600 uppercase hover:bg-blue-50 transition">
                            <i class="fa-solid fa-plus mr-2"></i> Ajouter un marqueur
                        </button>
                    </div>
                </div>

                <div class="admin-card p-8 mb-12">
                    <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic tracking-widest border-b border-slate-50 pb-4">Configuration Liste Inscrits / Émargement</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="text-[10px] font-black text-slate-500 uppercase block mb-4 tracking-tighter">Colonnes à afficher</label>
                            <div class="grid grid-cols-2 gap-3">
                                ${['date', 'nom', 'prenom', 'formule', 'options', 'email', 'phone'].map(col => `
                                    <label class="flex items-center gap-3 bg-slate-50 p-3 rounded-xl border border-slate-100 cursor-pointer hover:bg-white transition">
                                        <input type="checkbox" class="guestlist-col w-5 h-5 accent-blue-600" value="${col}" ${guestlist.columns.includes(col) ? 'checked' : ''}>
                                        <span class="text-[10px] font-black uppercase text-slate-600">${col}</span>
                                    </label>
                                `).join('')}
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="flex flex-col justify-center gap-4 bg-slate-50 p-6 rounded-[2rem] border border-slate-100">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-black uppercase text-slate-600">Afficher cases à cocher (Check-in)</span>
                                    <div class="toggle-btn guestlist-checkboxes ${guestlist.showCheckboxes ? 'active' : ''}" onclick="this.classList.toggle('active')"></div>
                                </div>
                                <p class="text-[9px] text-slate-400 font-bold uppercase leading-relaxed">Active le mode check-in interactif avec sauvegarde locale et barré des noms.</p>
                            </div>
                            <div class="flex flex-col justify-center gap-4 bg-slate-50 p-6 rounded-[2rem] border border-slate-100">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-black uppercase text-slate-600">Grouper par commande</span>
                                    <div class="toggle-btn guestlist-groupby ${guestlist.groupByOrder ? 'active' : ''}" onclick="this.classList.toggle('active')"></div>
                                </div>
                                <p class="text-[9px] text-slate-400 font-bold uppercase leading-relaxed">Affiche une seule ligne par acheteur avec tous ses articles (Recommandé pour les boutiques).</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-card overflow-hidden">
                    <div class="p-8 bg-slate-50/50 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-black uppercase text-slate-400 italic tracking-widest">Importation & Mapping</h3>
                            <p class="text-[10px] text-slate-300 font-bold uppercase mt-1">Organisez et renommez les articles de HelloAsso</p>
                        </div>
                        <span class="text-[10px] font-black text-slate-300 bg-white px-3 py-1 rounded-full shadow-sm"><i class="fa-solid fa-info-circle mr-1"></i> Glissez pour réorganiser</span>
                    </div>

                    <div class="p-4 lg:p-8">
                        <div class="hidden lg:flex items-center gap-4 px-6 mb-4 text-[9px] font-black uppercase text-slate-300 italic">
                            <div class="w-12 text-center">ORDRE / ACTIF</div>
                            <div class="flex-1 ml-4">ARTICLE SOURCE (HELLOASSO)</div>
                            <div class="w-48">NOM AFFICHÉ SUR LE BOARD</div>
                            <div class="w-32">BLOC / GROUPE</div>
                            <div class="w-24">TYPE</div>
                            <div class="w-24">CHART</div>
                    ${isShop ? '<div class="w-48 text-center">FINANCES / STOCK</div>' : ''}
                        </div>

                        <div id="rules-list" class="space-y-3">
                            ${rules.map(r => `
                                <div class="rule-tile bg-white border border-slate-100 rounded-2xl p-4 lg:p-5 flex flex-col lg:flex-row items-center gap-4 group hover:border-blue-300 hover:shadow-lg hover:shadow-blue-50 transition-all" data-item="${r.pattern}">
                                    <div class="flex items-center gap-4 w-full lg:w-auto">
                                        <div class="cursor-grab text-slate-200 group-hover:text-blue-400 transition-colors p-2"><i class="fa-solid fa-grip-vertical text-lg"></i></div>
                                        <div class="toggle-btn ${r.hidden ? '' : 'active'}" onclick="this.classList.toggle('active')" title="Activer/Désactiver l'importation"></div>
                                    </div>

                                    <div class="flex-1 w-full min-w-0">
                                        <div class="text-[9px] font-black text-slate-300 uppercase mb-1 truncate italic flex items-center gap-2">
                                            <i class="fa-solid fa-plug text-[8px]"></i> Source : ${r.pattern}
                                        </div>
                                        <input type="text" class="display-label input-soft !py-2 !text-sm border-transparent focus:border-blue-500 !bg-slate-50/50" value="${r.displayLabel}">
                                    </div>

                                    <div class="flex flex-wrap lg:flex-nowrap lg:items-center gap-2 w-full lg:w-auto">
                                        <div class="w-full lg:w-32">
                                            <input type="text" class="rule-group input-soft !py-2 !px-3 !text-[10px] uppercase text-slate-600" value="${r.group || 'Divers'}" placeholder="BLOC">
                                        </div>
                                        <div class="w-[48%] lg:w-24">
                                            <select class="rule-type input-soft !py-2 !px-2 !text-[10px] uppercase font-black">
                                                <option value="Billet" ${r.type==='Billet'?'selected':''}>${labels.main}</option>
                                                <option value="Option" ${r.type==='Option'?'selected':''}>Option</option>
                                                <option value="Ignorer" ${r.type==='Ignorer'?'selected':''}>Masquer</option>
                                            </select>
                                        </div>
                                        <div class="w-[48%] lg:w-24">
                                            <select class="rule-chart input-soft !py-2 !px-2 !text-[10px] uppercase font-black">
                                                <option value="pie" ${r.chartType==='pie'?'selected':''}>Pie</option>
                                                <option value="bar" ${r.chartType==='bar'?'selected':''}>Bar</option>
                                                <option value="doughnut" ${r.chartType==='doughnut'?'selected':''}>Donut</option>
                                            </select>
                                        </div>
                                        <div class="hidden">
                                            <input type="text" class="rule-transform" value="${r.transform || ''}">
                                        </div>

                                        ${isShop ? `
                                            <div class="flex gap-1 w-full lg:w-48 bg-slate-50 p-1 rounded-xl">
                                                <div class="flex-1 relative">
                                                    <span class="absolute -top-2 left-1/2 -translate-x-1/2 text-[6px] font-black text-slate-400">ACHAT</span>
                                                    <input type="number" step="0.01" class="rule-cost-price input-soft !p-1 !pt-3 !text-[10px] text-center !bg-transparent" value="${r.costPrice || 0}">
                                                </div>
                                                <div class="flex-1 relative">
                                                    <span class="absolute -top-2 left-1/2 -translate-x-1/2 text-[6px] font-black text-slate-400">VENTE</span>
                                                    <input type="number" step="0.01" class="rule-selling-price input-soft !p-1 !pt-3 !text-[10px] text-center !bg-transparent" value="${r.sellingPrice || 0}">
                                                </div>
                                                <div class="flex-1 relative">
                                                    <span class="absolute -top-2 left-1/2 -translate-x-1/2 text-[6px] font-black text-slate-400">STOCK</span>
                                                    <input type="number" class="rule-stock input-soft !p-1 !pt-3 !text-[10px] text-center !bg-transparent" value="${r.stock || 0}">
                                                </div>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('save-main-btn').onclick = () => save(org, slug, currentType, currentName, token);
            new Sortable(document.getElementById('rules-list'), {
                animation: 150,
                handle: '.cursor-grab',
                ghostClass: 'bg-blue-50',
                dragClass: 'shadow-2xl'
            });

        } catch (e) {
            console.error(e);
            zone.innerHTML = `<div class="p-10 bg-red-50 text-red-500 rounded-3xl text-center font-black uppercase text-sm">Erreur lors de l'analyse du formulaire.</div>`;
        }
    }

    function addMarkerRow() {
        const div = document.createElement('div');
        div.className = 'flex gap-3 marker-row mb-2 animate-fade-in';
        div.innerHTML = `
            <input type="text" placeholder="Action" class="marker-label input-soft !py-3 !text-xs">
            <input type="date" class="marker-date input-soft !py-3 !text-xs w-44">
            <button onclick="this.parentElement.remove()" class="text-slate-300 hover:text-red-500 transition px-2"><i class="fa-solid fa-trash-can"></i></button>
        `;
        document.getElementById('markers-list').appendChild(div);
    }

    async function save(org, slug, type, name, token) {
        const btn = document.getElementById('save-main-btn');
        const oldText = btn.innerText;
        btn.innerText = "Patientez...";
        btn.disabled = true;

        const rules = [];
        document.querySelectorAll('.rule-tile').forEach(row => {
            rules.push({
                pattern: row.dataset.item,
                displayLabel: row.querySelector('.display-label').value,
                type: row.querySelector('.rule-type').value,
                group: row.querySelector('.rule-group').value || 'Divers',
                chartType: row.querySelector('.rule-chart').value,
                transform: row.querySelector('.rule-transform') ? row.querySelector('.rule-transform').value : '',
                hidden: !row.querySelector('.toggle-btn').classList.contains('active'),
                costPrice: row.querySelector('.rule-cost-price') ? parseFloat(row.querySelector('.rule-cost-price').value) : 0,
                sellingPrice: row.querySelector('.rule-selling-price') ? parseFloat(row.querySelector('.rule-selling-price').value) : 0,
                stock: row.querySelector('.rule-stock') ? parseInt(row.querySelector('.rule-stock').value) : 0
            });
        });

        const markers = [];
        document.querySelectorAll('.marker-row').forEach(row => {
            const l = row.querySelector('.marker-label').value;
            const d = row.querySelector('.marker-date').value;
            if(l && d) markers.push({label: l, date: d});
        });

        const guestlistColumns = [];
        document.querySelectorAll('.guestlist-col:checked').forEach(cb => guestlistColumns.push(cb.value));

        const config = {
            slug,
            title: name,
            orgSlug: org,
            formSlug: slug,
            formType: type,
            shareToken: token,
            rules,
            markers,
            goals: {
                revenue: parseFloat(document.getElementById('goal-rev').value),
                tickets: parseInt(document.getElementById('goal-tix').value),
                n1: parseInt(document.getElementById('goal-n1').value)
            },
            guestlist: {
                columns: guestlistColumns,
                showCheckboxes: document.querySelector('.guestlist-checkboxes').classList.contains('active'),
                groupByOrder: document.querySelector('.guestlist-groupby').classList.contains('active')
            }
        };

        try {
            await fetch('admin.php', {
                method: 'POST',
                body: new URLSearchParams({
                    save_campaign: '1',
                    config: JSON.stringify(config)
                })
            });
            window.location.href = 'index.php?campaign=' + slug;
        } catch (e) {
            alert("Erreur lors de la sauvegarde.");
            btn.innerText = oldText;
            btn.disabled = false;
        }
    }
    </script>
</body>
</html>
