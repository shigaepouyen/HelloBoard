<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$srcPath = __DIR__ . '/../src/Services/';
require_once $srcPath . 'Storage.php';
require_once $srcPath . 'HelloAssoClient.php';

$globals = Storage::getGlobalSettings();
$action = $_GET['action'] ?? 'list';
$localCampaigns = Storage::listCampaigns();
$client = new HelloAssoClient($globals['clientId']??'', $globals['clientSecret']??'');

if (isset($_POST['delete_campaign'])) {
    Storage::deleteCampaign($_POST['slug']);
    header('Location: admin.php?msg=deleted'); exit;
}

if (isset($_POST['save_campaign'])) {
    $config = json_decode($_POST['config'], true);
    if ($config) Storage::saveCampaign($config['slug'], $config);
    echo json_encode(['success' => true]); exit;
}

if ($action === 'analyze') {
    header('Content-Type: application/json');
    try {
        $form = $_GET['form'];
        $orders = $client->fetchAllOrders($_GET['org'], $form, $_GET['type'] ?? 'Event');
        $apiItems = [];
        foreach(array_slice($orders, 0, 100) as $o) {
            foreach($o['items'] ?? [] as $i) {
                if(!empty($i['name'])) $apiItems[] = trim($i['name']);
                foreach($i['customFields'] ?? [] as $cf) if(!empty($cf['name'])) $apiItems[] = trim($cf['name']);
            }
        }
        $apiItems = array_unique($apiItems);
        $configFile = __DIR__ . "/../config/campaigns/$form.json";
        $existing = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
        $finalRules = isset($existing['rules']) ? $existing['rules'] : [];
        foreach ($apiItems as $item) {
            $found = false;
            foreach($finalRules as $r) if($r['pattern'] === $item) $found = true;
            if (!$found) $finalRules[] = ['pattern' => $item, 'displayLabel' => $item, 'type' => 'Option', 'group' => 'Divers', 'chartType' => 'doughnut', 'transform' => '', 'hidden' => false];
        }
        echo json_encode(['rules' => $finalRules, 'goals' => isset($existing['goals']) ? $existing['goals'] : ['revenue'=>0, 'n1'=>0]]);
    } catch(Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin HelloBoard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { background-color: #f8fafc; color: #1e293b; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        input, select { 
            background-color: #fff; 
            border: 1px solid #e2e8f0; 
            color: #1e293b; 
            padding: 8px 12px; 
            border-radius: 10px; 
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        input:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

        .card-admin { background: #fff; border: 1px solid #e2e8f0; border-radius: 1.5rem; }

        .tooltip { position: relative; cursor: help; }
        .tooltip:hover::after {
            content: attr(data-tip);
            position: absolute; bottom: 130%; left: 50%; transform: translateX(-50%);
            background: #1e293b; color: #fff; padding: 10px 14px; border-radius: 10px;
            font-size: 11px; width: 200px; z-index: 50; line-height: 1.5; font-weight: 500;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }

        .sortable-ghost { opacity: 0.3; background: #eff6ff !important; border: 1px dashed #2563eb !important; }
        .loader-spin { border: 2px solid #f1f5f9; border-top: 2px solid #2563eb; border-radius: 50%; width: 24px; height: 24px; animation: spin 0.8s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="p-4 md:p-8 lg:p-12">
    <div class="max-w-7xl mx-auto">
        
        <header class="flex justify-between items-center mb-16">
            <div class="flex items-center gap-4">
                <img src="assets/img/logo.svg" alt="Logo" class="w-10 h-10" onerror="this.innerHTML='<i class=\'fa-solid fa-gear text-slate-400 text-2xl\'></i>'; this.type='icon';">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Console d'Administration</h1>
                    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-0.5">Configuration & Moteur de règles</p>
                </div>
            </div>
            <a href="index.php" class="bg-white border border-slate-200 px-6 py-3 rounded-xl text-xs font-bold hover:bg-slate-50 transition flex items-center gap-2">
                <i class="fa-solid fa-house"></i> Accueil
            </a>
        </header>

        <!-- DASHBOARD STATUS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <div class="card-admin p-6 border-l-4 border-l-blue-500">
                <h4 class="text-slate-400 font-bold text-[10px] uppercase mb-1">Billets</h4>
                <p class="text-xs text-slate-600">Comptabilisés comme inscriptions réelles.</p>
            </div>
            <div class="card-admin p-6 border-l-4 border-l-emerald-500">
                <h4 class="text-slate-400 font-bold text-[10px] uppercase mb-1">Options</h4>
                <p class="text-xs text-slate-600">Analysées sous forme de graphiques.</p>
            </div>
            <div class="card-admin p-6 border-l-4 border-l-amber-500">
                <h4 class="text-slate-400 font-bold text-[10px] uppercase mb-1">Transform</h4>
                <p class="text-xs text-slate-600">Nettoyage par Regex ou Mots-clés.</p>
            </div>
        </div>

        <div class="card-admin p-8 mb-12 shadow-sm">
            <h2 class="text-lg font-extrabold mb-8 flex items-center gap-2 text-slate-900"><i class="fa-solid fa-layer-group text-blue-500"></i> Vos Boards Actifs</h2>
            <div class="grid gap-3">
                <?php foreach($localCampaigns as $c): ?>
                <div class="bg-slate-50 p-6 rounded-xl border border-slate-100 flex justify-between items-center hover:border-blue-200 transition group">
                    <div class="flex items-center gap-5">
                         <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center border border-slate-200 text-slate-300 group-hover:text-blue-500 transition">
                            <i class="fa-solid fa-<?= $c['icon'] ?? 'file-lines' ?>"></i>
                        </div>
                        <div>
                            <div class="font-bold text-slate-900"><?= htmlspecialchars($c['title']) ?></div>
                            <div class="text-[9px] text-slate-400 font-bold tracking-widest uppercase"><?= $c['slug'] ?></div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="editCamp('<?= $c['orgSlug'] ?>','<?= $c['slug'] ?>','<?= $c['formType'] ?>','<?= addslashes($c['title']) ?>')" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-xs font-bold transition">Configurer</button>
                        <form method="POST" onsubmit="return confirm('Confirmer la suppression ?');">
                            <input type="hidden" name="delete_campaign" value="1"><input type="hidden" name="slug" value="<?= $c['slug'] ?>">
                            <button class="bg-white hover:bg-red-50 text-slate-300 hover:text-red-500 px-4 py-2.5 rounded-xl transition border border-slate-200 hover:border-red-200"><i class="fa-solid fa-trash-can"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <a href="admin.php?action=new" class="mt-4 border-2 border-dashed border-slate-200 p-8 rounded-2xl text-center text-slate-400 hover:border-blue-300 hover:text-blue-500 transition font-bold text-xs uppercase tracking-widest">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i> Scanner HelloAsso pour un nouveau board
                </a>
            </div>
        </div>

        <div id="config-zone"></div>
    </div>

    <script>
    async function editCamp(org, slug, type, name) {
        const zone = document.getElementById('config-zone');
        zone.scrollIntoView({ behavior: 'smooth' });
        zone.innerHTML = '<div class="p-20 text-center"><div class="loader-spin mx-auto mb-4"></div><p class="text-blue-600 font-bold uppercase tracking-widest text-xs">Analyse des articles HelloAsso...</p></div>';

        const res = await fetch(`admin.php?action=analyze&org=${org}&form=${slug}&type=${type}`);
        const data = await res.json();

        zone.innerHTML = `
            <div class="card-admin p-10 mt-10 shadow-xl border-t-4 border-t-blue-600 animate-reveal">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-12 gap-6">
                    <div>
                        <h3 class="text-2xl font-extrabold text-slate-900">${name}</h3>
                        <p class="text-slate-400 text-xs font-bold mt-1 uppercase tracking-widest">Configuration des règles d'affichage</p>
                    </div>
                    <div class="flex gap-3">
                        <button onclick="location.reload()" class="px-6 py-3 text-slate-400 font-bold hover:text-slate-900 transition text-xs">ANNULER</button>
                        <button onclick="save('${org}','${slug}','${type}','${name.replace(/'/g, "\\'")}')" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-xl font-bold text-xs shadow-lg shadow-blue-100 transition transform active:scale-95 uppercase tracking-wider">
                            Sauvegarder
                        </button>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16 p-8 bg-slate-50 rounded-2xl border border-slate-100">
                    <div>
                        <label class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-3">Objectif Recettes (€)</label>
                        <input type="number" id="goal-rev" class="w-full text-xl font-bold py-3 px-5 shadow-sm" value="${data.goals.revenue}">
                    </div>
                    <div>
                        <label class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-3">Participants Année N-1</label>
                        <input type="number" id="goal-n1" class="w-full text-xl font-bold py-3 px-5 shadow-sm" value="${data.goals.n1}">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-separate border-spacing-y-2">
                        <thead>
                            <tr class="text-slate-400 uppercase text-[9px] font-extrabold tracking-widest">
                                <th class="px-4 pb-4">Ordre</th>
                                <th class="px-4 pb-4">Visible</th>
                                <th class="px-4 pb-4">Source</th>
                                <th class="px-4 pb-4">Label Board</th>
                                <th class="px-4 pb-4">Type</th>
                                <th class="px-4 pb-4">Bloc</th>
                                <th class="px-4 pb-4">Graphe</th>
                                <th class="px-4 pb-4">Transform</th>
                            </tr>
                        </thead>
                        <tbody id="rules-list">
                            ${data.rules.map(r => `
                            <tr class="rule-row bg-slate-50/50 hover:bg-slate-50 transition" data-item="${r.pattern}">
                                <td class="py-4 px-4 cursor-grab text-slate-300 hover:text-blue-500 transition"><i class="fa-solid fa-grip-lines"></i></td>
                                <td class="py-4 px-4 text-center">
                                    <input type="checkbox" class="rule-visible w-5 h-5 accent-blue-600" ${r.hidden ? '' : 'checked'}>
                                </td>
                                <td class="py-4 px-4 font-mono text-[9px] text-slate-400 truncate max-w-[100px] italic">${r.pattern}</td>
                                <td class="py-4 px-4"><input type="text" class="display-label w-full font-bold" value="${r.displayLabel}"></td>
                                <td class="py-4 px-4">
                                    <select class="rule-type w-full font-semibold">
                                        <option value="Billet" ${r.type==='Billet'?'selected':''}>Billet</option>
                                        <option value="Option" ${r.type==='Option'?'selected':''}>Option</option>
                                        <option value="Ignorer" ${r.type==='Ignorer'?'selected':''}>Ignorer</option>
                                    </select>
                                </td>
                                <td class="py-4 px-4"><input type="text" class="rule-group w-full" value="${r.group || 'Divers'}"></td>
                                <td class="py-4 px-4">
                                    <select class="rule-chart w-full">
                                        <option value="doughnut" ${r.chartType==='doughnut'?'selected':''}>Cercle</option>
                                        <option value="bar" ${r.chartType==='bar'?'selected':''}>Barres</option>
                                    </select>
                                </td>
                                <td class="py-4 px-4"><input type="text" class="rule-transform w-full font-mono text-[9px] text-blue-600" value="${r.transform || ''}" placeholder="REGEX:..."></td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        new Sortable(document.getElementById('rules-list'), { animation: 150, handle: '.cursor-grab' });
    }

    async function save(org, slug, type, name) {
        const rules = [];
        document.querySelectorAll('.rule-row').forEach(row => {
            rules.push({
                pattern: row.dataset.item,
                displayLabel: row.querySelector('.display-label').value,
                type: row.querySelector('.rule-type').value,
                group: row.querySelector('.rule-group').value,
                chartType: row.querySelector('.rule-chart').value,
                transform: row.querySelector('.rule-transform').value,
                hidden: !row.querySelector('.rule-visible').checked
            });
        });
        
        const config = {
            slug, title: name, orgSlug: org, formSlug: slug, formType: type,
            rules,
            goals: { revenue: parseFloat(document.getElementById('goal-rev').value), n1: parseInt(document.getElementById('goal-n1').value) }
        };

        const btn = document.querySelector('button[onclick^="save"]');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Enregistrement...';

        await fetch('admin.php', { method:'POST', body: new URLSearchParams({save_campaign: 1, config: JSON.stringify(config)}) });
        window.location.href = 'index.php?campaign=' + slug;
    }
    </script>
</body>
</html>