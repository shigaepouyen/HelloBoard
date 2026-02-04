<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$srcPath = __DIR__ . '/../src/Services/';
require_once $srcPath . 'Storage.php';
require_once $srcPath . 'HelloAssoClient.php';

$globals = Storage::getGlobalSettings();
$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';
$localCampaigns = Storage::listCampaigns();

// TEST CONNEXION
$client = new HelloAssoClient($globals['clientId']??'', $globals['clientSecret']??'');
$connectionStatus = $client->testConnection($globals['orgSlug'] ?? null);

// ACTIONS
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
            if (!$found) $finalRules[] = ['pattern' => $item, 'displayLabel' => $item, 'type' => 'Option', 'group' => 'Divers', 'chartType' => 'doughnut', 'transform' => ''];
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
        body { background-color: #0f172a; color: #e2e8f0; font-family: sans-serif; }
        input, select { background-color: #1e293b; border: 1px solid #334155; color: white; padding: 6px; border-radius: 6px; }
        .sortable-ghost { opacity: 0.3; background: #3b82f6; }
    </style>
</head>
<body class="p-8">
    <div class="max-w-7xl mx-auto">
        
        <div class="flex justify-between items-center mb-10 border-b border-slate-800 pb-6">
            <h1 class="text-3xl font-black italic">CONFIGURATION</h1>
            <a href="index.php" class="bg-slate-800 px-4 py-2 rounded-lg text-sm font-bold hover:bg-slate-700 transition">Voir le site</a>
        </div>

        <!-- BOARD EXISTANTS VISIBLES IMMEDIATEMENT -->
        <div class="bg-slate-800/50 p-6 rounded-[2rem] border border-slate-700 mb-10 shadow-xl">
            <h2 class="text-xl font-bold mb-6 flex items-center gap-2"><i class="fa-solid fa-layer-group text-blue-400"></i> Vos Boards Actifs</h2>
            <div class="grid gap-3">
                <?php foreach($localCampaigns as $c): ?>
                <div class="bg-slate-900 p-5 rounded-2xl border border-slate-800 flex justify-between items-center hover:border-blue-500/50 transition">
                    <div>
                        <div class="font-bold text-lg"><?= htmlspecialchars($c['title']) ?></div>
                        <div class="text-xs text-slate-500 font-mono"><?= $c['slug'] ?></div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="editCamp('<?= $c['orgSlug'] ?>','<?= $c['slug'] ?>','<?= $c['formType'] ?>','<?= addslashes($c['title']) ?>')" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-xl text-xs font-bold transition">Modifier / Ordonner</button>
                        <form method="POST" onsubmit="return confirm('Supprimer ?');"><input type="hidden" name="delete_campaign" value="1"><input type="hidden" name="slug" value="<?= $c['slug'] ?>"><button class="bg-red-900/20 hover:bg-red-600 text-red-500 hover:text-white px-3 py-2 rounded-xl transition"><i class="fa-solid fa-trash"></i></button></form>
                    </div>
                </div>
                <?php endforeach; ?>
                <a href="admin.php?action=new" class="mt-4 border-2 border-dashed border-slate-700 p-6 rounded-2xl text-center text-slate-500 hover:border-emerald-500 hover:text-emerald-400 transition font-bold">
                    <i class="fa-solid fa-plus mr-2"></i> Scanner HelloAsso pour un nouveau board
                </a>
            </div>
        </div>

        <div id="config-zone"></div>
    </div>

    <script>
    async function editCamp(org, slug, type, name) {
        const zone = document.getElementById('config-zone');
        zone.scrollIntoView({ behavior: 'smooth' });
        zone.innerHTML = '<div class="p-20 text-center text-blue-400 animate-pulse font-bold"><i class="fa-solid fa-spinner fa-spin text-2xl mb-4"></i><br>Synchronisation des données...</div>';

        const res = await fetch(`admin.php?action=analyze&org=${org}&form=${slug}&type=${type}`);
        const data = await res.json();

        zone.innerHTML = `
            <div class="bg-slate-900 p-8 rounded-[2.5rem] border border-blue-500/30 animate-fade-in mt-10 shadow-2xl">
                <h3 class="text-2xl font-black mb-8">Board : ${name}</h3>
                
                <!-- OBJECTIFS PAR CAMPAGNE -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10 p-6 bg-black/20 rounded-2xl border border-slate-800 text-center">
                    <div>
                        <label class="block text-xs font-bold text-yellow-500 uppercase mb-2">Objectif de Recettes (€)</label>
                        <input type="number" id="goal-rev" class="w-full text-center text-xl font-mono" value="${data.goals.revenue}">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-purple-500 uppercase mb-2">Participants année N-1</label>
                        <input type="number" id="goal-n1" class="w-full text-center text-xl font-mono" value="${data.goals.n1}">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-slate-500 uppercase text-[10px] tracking-wider">
                            <tr><th class="p-3 w-8"></th><th class="p-3">Item HelloAsso</th><th class="p-3">Label Affiché</th><th class="p-3">Action</th><th class="p-3">Groupe (Bloc)</th><th class="p-3">Graph</th><th class="p-3">Transform</th></tr>
                        </thead>
                        <tbody id="rules-list">
                            ${data.rules.map(r => `
                            <tr class="rule-row border-b border-slate-800 group hover:bg-white/5" data-item="${r.pattern}">
                                <td class="p-3 cursor-grab text-slate-700 group-hover:text-blue-400 transition"><i class="fa-solid fa-grip-vertical"></i></td>
                                <td class="p-3 font-mono text-[10px] text-slate-500 truncate max-w-[150px]">${r.pattern}</td>
                                <td class="p-3"><input type="text" class="display-label w-full" value="${r.displayLabel}"></td>
                                <td class="p-3">
                                    <select class="rule-type">
                                        <option value="Billet" ${r.type==='Billet'?'selected':''}>Billet</option>
                                        <option value="Option" ${r.type==='Option'?'selected':''}>Option</option>
                                        <option value="Ignorer" ${r.type==='Ignorer'?'selected':''}>🚫 Ignorer</option>
                                    </select>
                                </td>
                                <td class="p-3"><input type="text" class="rule-group w-full" value="${r.group || 'Divers'}"></td>
                                <td class="p-3"><select class="rule-chart"><option value="doughnut" ${r.chartType==='doughnut'?'selected':''}>Camembert</option><option value="bar" ${r.chartType==='bar'?'selected':''}>Barres</option></select></td>
                                <td class="p-3"><input type="text" class="rule-transform w-full" value="${r.transform || ''}" placeholder="ex: FIRST_LETTER"></td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>
                <div class="mt-10 flex justify-end gap-4 border-t border-slate-800 pt-8">
                    <button onclick="location.reload()" class="px-6 py-3 text-slate-500 font-bold hover:text-white transition">Annuler</button>
                    <button onclick="save('${org}','${slug}','${type}','${name.replace(/'/g, "\\'")}')" class="bg-emerald-600 hover:bg-emerald-500 text-white px-10 py-4 rounded-full font-black shadow-xl shadow-emerald-900/20 transition transform hover:scale-105 flex items-center gap-3">
                        <i class="fa-solid fa-floppy-disk"></i> Sauvegarder
                    </button>
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
                transform: row.querySelector('.rule-transform').value
            });
        });
        
        const config = {
            slug, title: name, orgSlug: org, formSlug: slug, formType: type,
            rules,
            goals: { revenue: parseFloat(document.getElementById('goal-rev').value), n1: parseInt(document.getElementById('goal-n1').value) }
        };

        const btn = document.querySelector('button[onclick^="save"]');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sauvegarde...';

        await fetch('admin.php', { method:'POST', body: new URLSearchParams({save_campaign: 1, config: JSON.stringify(config)}) });
        window.location.href = 'index.php?campaign=' + slug;
    }
    </script>
</body>
</html>