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

// DÉCOUVERTE
$discovery = null;
if ($action === 'new' && !empty($globals['orgSlug'])) {
    $discovery = $client->discoverCampaigns($globals['orgSlug']);
}

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
        $form = $_GET['form']; $org = $_GET['org']; $type = $_GET['type'] ?? 'Event';
        $orders = $client->fetchAllOrders($org, $form, $type);
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
        echo json_encode(['rules' => $finalRules, 'goals' => isset($existing['goals']) ? $existing['goals'] : ['revenue'=>0, 'tickets'=>0, 'n1'=>0]]);
    } catch(Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration — HelloBoard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .admin-card { background: white; border-radius: 2rem; border: 1px solid #edf2f7; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.02); }
        .input-soft { background: #f1f5f9; border: none; border-radius: 1.25rem; padding: 14px 20px; font-weight: 700; width: 100%; transition: all 0.2s; }
        .input-soft:focus { background: white; outline: 2px solid #2563eb; }
        .rule-tile { background: white; border: 1px solid #edf2f7; border-radius: 1.5rem; margin-bottom: 0.75rem; transition: all 0.2s; }
        .rule-tile:hover { border-color: #cbd5e1; }
        .toggle-btn { width: 48px; height: 26px; background: #cbd5e1; border-radius: 20px; position: relative; cursor: pointer; transition: all 0.3s; }
        .toggle-btn.active { background: #2563eb; }
        .toggle-btn::after { content: ''; position: absolute; top: 4px; left: 4px; width: 18px; height: 18px; background: white; border-radius: 50%; transition: all 0.3s; }
        .toggle-btn.active::after { transform: translateX(22px); }
    </style>
</head>
<body class="pb-32">

    <nav class="p-6 bg-white border-b border-slate-100 sticky top-0 z-50">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <h1 class="text-sm font-black uppercase tracking-widest italic">Console Admin</h1>
            <a href="index.php" class="text-xs font-bold text-slate-400">Quitter</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-12">
        
        <?php if ($action === 'new'): ?>
            <header class="mb-12"><h2 class="text-3xl font-extrabold tracking-tight uppercase italic">Nouveau Board</h2></header>
            <div class="space-y-4">
                <?php if ($discovery && !empty($discovery['forms'])): ?>
                    <?php foreach($discovery['forms'] as $f): ?>
                    <div class="admin-card p-8 flex items-center justify-between">
                        <div><h3 class="font-extrabold text-lg"><?= htmlspecialchars($f['name']) ?></h3><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?= $f['type'] ?></p></div>
                        <button onclick='editCamp("<?= $discovery["orgSlug"] ?>", "<?= $f["slug"] ?>", "<?= $f["type"] ?>", <?= htmlspecialchars(json_encode($f["name"])) ?>)' class="bg-blue-600 text-white px-8 py-4 rounded-2xl font-black uppercase text-[10px] shadow-lg shadow-blue-100">Configurer</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <header class="mb-12"><h2 class="text-3xl font-extrabold tracking-tight uppercase italic">Mes Campagnes</h2></header>
            <div class="grid gap-4">
                <?php foreach($localCampaigns as $c): ?>
                <div class="admin-card p-8 flex items-center justify-between group">
                    <div class="flex items-center gap-6">
                        <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center font-black">0<?= array_search($c, $localCampaigns) + 1 ?></div>
                        <h3 class="font-extrabold text-lg"><?= htmlspecialchars($c['title']) ?></h3>
                    </div>
                    <div class="flex items-center gap-4">
                        <button onclick='editCamp("<?= $c["orgSlug"] ?>", "<?= $c["slug"] ?>", "<?= $c["formType"] ?>", <?= htmlspecialchars(json_encode($c["title"])) ?>)' class="bg-slate-100 px-6 py-4 rounded-2xl font-extrabold hover:bg-blue-600 hover:text-white transition text-xs">Modifier</button>
                        <form method="POST" onsubmit="return confirm('Supprimer ce board ?');"><input type="hidden" name="delete_campaign" value="1"><input type="hidden" name="slug" value="<?= $c['slug'] ?>"><button class="p-3 text-slate-300 hover:text-red-500"><i class="fa-solid fa-trash-can"></i></button></form>
                    </div>
                </div>
                <?php endforeach; ?>
                <a href="admin.php?action=new" class="mt-8 flex items-center justify-center p-16 border-2 border-dashed border-slate-200 rounded-[3rem] text-slate-400 font-extrabold hover:text-blue-600 hover:border-blue-200 transition">
                    <i class="fa-solid fa-magnifying-glass mr-3"></i> Scanner HelloAsso
                </a>
            </div>
        <?php endif; ?>

        <div id="config-zone"></div>
    </main>

    <script>
    async function editCamp(org, slug, type, name) {
        const zone = document.getElementById('config-zone');
        zone.innerHTML = '<div class="py-20 text-center text-blue-600 font-bold animate-pulse">Sync Engine...</div>';
        zone.scrollIntoView({ behavior: 'smooth' });

        const res = await fetch(`admin.php?action=analyze&org=${org}&form=${slug}&type=${type}`);
        const data = await res.json();

        zone.innerHTML = `
            <div class="mt-20 pt-20 border-t border-slate-200 animate-reveal">
                <div class="flex flex-col sm:flex-row items-center justify-between mb-12 gap-6">
                    <h2 class="text-3xl font-black italic uppercase tracking-tighter">${name}</h2>
                    <button id="save-main-btn" class="bg-blue-600 text-white px-10 py-5 rounded-[2rem] font-black shadow-xl shadow-blue-100 uppercase text-xs tracking-widest active:scale-95 transition">Enregistrer le Board</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-12">
                    <div class="admin-card p-8"><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 block">Obj. Recettes (€)</label><input type="number" id="goal-rev" class="input-soft" value="${data.goals.revenue || 0}"></div>
                    <div class="admin-card p-8"><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 block">Obj. Billets (Nb)</label><input type="number" id="goal-tix" class="input-soft" value="${data.goals.tickets || 0}"></div>
                    <div class="admin-card p-8"><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 block">Année N-1 (Nb)</label><input type="number" id="goal-n1" class="input-soft" value="${data.goals.n1 || 0}"></div>
                </div>

                <div class="mb-6"><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Ordre et Visibilité des Blocs</p></div>
                <div id="rules-list">
                    ${data.rules.map(r => `
                    <div class="rule-tile p-6 flex flex-col sm:flex-row items-center gap-6" data-item="${r.pattern}">
                        <div class="cursor-grab text-slate-200 px-2"><i class="fa-solid fa-grip-lines"></i></div>
                        <div class="toggle-btn ${r.hidden ? '' : 'active'}" onclick="this.classList.toggle('active')"></div>
                        <div class="flex-1 w-full">
                            <input type="text" class="display-label input-soft !py-3 !text-sm" value="${r.displayLabel}" placeholder="Label Board">
                            <p class="text-[9px] font-bold text-slate-300 uppercase mt-2 px-1 truncate italic">Source : ${r.pattern}</p>
                        </div>
                        <div class="flex gap-2">
                            <select class="rule-type input-soft !py-3 !px-4 !w-auto !text-[10px] uppercase">
                                <option value="Billet" ${r.type==='Billet'?'selected':''}>🎫 Billet</option>
                                <option value="Option" ${r.type==='Option'?'selected':''}>📊 Option</option>
                                <option value="Ignorer" ${r.type==='Ignorer'?'selected':''}>🚫 Cacher</option>
                            </select>
                            <input type="text" class="rule-group input-soft !py-3 !px-4 !w-24 !text-[10px] uppercase" value="${r.group || 'Divers'}" placeholder="Groupe">
                        </div>
                        <div class="w-full sm:w-32">
                             <input type="text" class="rule-transform input-soft !py-3 !px-4 !text-[10px] font-mono !text-blue-500" value="${r.transform || ''}" placeholder="REGEX/TRANSFORM">
                        </div>
                    </div>`).join('')}
                </div>
            </div>
        `;

        document.getElementById('save-main-btn').onclick = () => save(org, slug, type, name);
        if (data.rules.length > 0) new Sortable(document.getElementById('rules-list'), { animation: 150, handle: '.cursor-grab' });
    }

    async function save(org, slug, type, name) {
        const rules = [];
        document.querySelectorAll('.rule-tile').forEach(row => {
            rules.push({
                pattern: row.dataset.item,
                displayLabel: row.querySelector('.display-label').value,
                type: row.querySelector('.rule-type').value,
                group: row.querySelector('.rule-group').value,
                chartType: 'doughnut',
                transform: row.querySelector('.rule-transform').value,
                hidden: !row.querySelector('.toggle-btn').classList.contains('active')
            });
        });
        
        const config = {
            slug, title: name, orgSlug: org, formSlug: slug, formType: type, rules,
            goals: { 
                revenue: parseFloat(document.getElementById('goal-rev').value) || 0, 
                tickets: parseInt(document.getElementById('goal-tix').value) || 0,
                n1: parseInt(document.getElementById('goal-n1').value) || 0 
            }
        };

        const btn = document.getElementById('save-main-btn');
        btn.innerText = 'ENREGISTREMENT...';

        await fetch('admin.php', { method:'POST', body: new URLSearchParams({save_campaign: 1, config: JSON.stringify(config)}) });
        window.location.href = 'index.php?campaign=' + slug;
    }
    </script>
</body>
</html>