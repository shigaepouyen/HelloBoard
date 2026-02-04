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

// GESTION DU SCAN (DÉCOUVERTE)
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
        $form = $_GET['form'];
        $org = $_GET['org'];
        $type = $_GET['type'] ?? 'Event';
        
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
        
        echo json_encode(['rules' => $finalRules, 'goals' => isset($existing['goals']) ? $existing['goals'] : ['revenue'=>0, 'n1'=>0]]);
    } catch(Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin Studio — HelloBoard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #ffffff; 
            color: #000000;
            -webkit-font-smoothing: antialiased;
        }

        .studio-input {
            background: transparent;
            border: none;
            border-bottom: 1px solid transparent;
            padding: 4px 0;
            font-weight: 500;
            transition: all 0.2s;
        }
        .studio-input:hover { border-bottom-color: #e2e8f0; }
        .studio-input:focus { border-bottom-color: #000; outline: none; }

        .studio-select {
            background: #f8fafc;
            border: none;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }

        .rule-row { border-bottom: 1px solid #f8fafc; transition: background 0.2s; }
        .rule-row:hover { background-color: #fafafa; }
        .sortable-ghost { opacity: 0.2; background: #000 !important; }

        .loader-studio {
            width: 16px; height: 16px;
            border: 2px solid #f1f5f9; border-top: 2px solid #000;
            border-radius: 50%; animation: spin 0.6s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .toggle-hidden {
            width: 32px; height: 18px;
            background: #e2e8f0; border-radius: 20px;
            position: relative; cursor: pointer;
            transition: background 0.3s;
        }
        .toggle-hidden.active { background: #000; }
        .toggle-hidden::after {
            content: ''; position: absolute;
            top: 2px; left: 2px;
            width: 14px; height: 14px;
            background: white; border-radius: 50%;
            transition: transform 0.3s;
        }
        .toggle-hidden.active::after { transform: translateX(14px); }
    </style>
</head>
<body class="min-h-screen">

    <nav class="sticky top-0 bg-white/90 backdrop-blur-md z-50 border-b border-slate-100">
        <div class="max-w-6xl mx-auto px-8 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <img src="assets/img/logo.svg" alt="Logo" class="w-6 h-6" onerror="this.style.display='none'">
                <span class="text-xs font-bold uppercase tracking-widest">Console <span class="text-slate-300">/ Admin</span></span>
            </div>
            <div class="flex items-center gap-6">
                <a href="index.php" class="text-[10px] font-bold uppercase tracking-widest text-slate-400 hover:text-black transition">Quitter</a>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-8 py-16">
        
        <?php if ($action === 'new'): ?>
            <header class="mb-16">
                <h1 class="text-3xl font-extrabold tracking-tight mb-2 uppercase">Scanner HelloAsso</h1>
                <p class="text-slate-400 font-medium">Sélectionnez le formulaire à importer pour créer un nouveau board.</p>
            </header>

            <section class="mb-32">
                <?php if ($discovery && !empty($discovery['forms'])): ?>
                    <div class="grid grid-cols-1 gap-1">
                        <?php foreach($discovery['forms'] as $f): ?>
                        <div class="flex items-center justify-between py-6 group border-b border-slate-50">
                            <div>
                                <h3 class="font-bold text-lg"><?= htmlspecialchars($f['name']) ?></h3>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5"><?= $f['type'] ?> • <?= $f['slug'] ?></p>
                            </div>
                            <button onclick='editCamp("<?= $discovery["orgSlug"] ?>", "<?= $f["slug"] ?>", "<?= $f["type"] ?>", <?= htmlspecialchars(json_encode($f["name"])) ?>)' 
                                    class="bg-black text-white px-6 py-2 rounded-lg text-xs font-bold active:scale-95 transition">
                                Configurer
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="py-20 text-center border-2 border-dashed border-slate-100 rounded-2xl">
                        <p class="text-slate-400 font-medium mb-6">Aucun formulaire trouvé. Vérifiez votre Slug Organisation dans les paramètres.</p>
                        <a href="admin.php" class="text-xs font-bold uppercase tracking-widest underline underline-offset-8">Retour</a>
                    </div>
                <?php endif; ?>
            </section>

        <?php else: ?>
            <header class="mb-20">
                <h1 class="text-3xl font-extrabold tracking-tight mb-2 uppercase italic">Vos Boards</h1>
                <p class="text-slate-400 font-medium">Gérez vos campagnes actives ou créez-en de nouvelles.</p>
            </header>

            <section class="mb-32">
                <div class="grid grid-cols-1 gap-1">
                    <?php if (empty($localCampaigns)): ?>
                         <div class="py-10 text-center text-slate-300 italic text-sm">Aucun board configuré.</div>
                    <?php endif; ?>
                    <?php foreach($localCampaigns as $c): ?>
                    <div class="flex items-center justify-between py-6 group border-b border-slate-50">
                        <div class="flex items-center gap-6">
                            <div class="text-[10px] font-bold text-slate-200">0<?= array_search($c, $localCampaigns) + 1 ?></div>
                            <div>
                                <h3 class="font-bold text-lg"><?= htmlspecialchars($c['title']) ?></h3>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5"><?= $c['slug'] ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick='editCamp("<?= $c["orgSlug"] ?>", "<?= $c["slug"] ?>", "<?= $c["formType"] ?>", <?= htmlspecialchars(json_encode($c["title"])) ?>)' 
                                    class="text-xs font-bold px-4 py-2 hover:bg-slate-50 rounded-lg transition">Éditer</button>
                            <form method="POST" onsubmit="return confirm('Supprimer ce board ?');" class="inline">
                                <input type="hidden" name="delete_campaign" value="1"><input type="hidden" name="slug" value="<?= $c['slug'] ?>">
                                <button class="p-2 text-slate-300 hover:text-red-500 transition"><i class="fa-solid fa-trash-can text-sm"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <a href="admin.php?action=new" class="mt-8 flex items-center justify-center py-10 border-2 border-dashed border-slate-100 rounded-2xl text-slate-400 hover:text-black hover:border-slate-300 transition group">
                        <span class="text-xs font-bold uppercase tracking-widest flex items-center gap-2">
                            <i class="fa-solid fa-plus group-hover:rotate-90 transition-transform"></i> Nouveau Board
                        </span>
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <div id="config-zone"></div>
    </main>

    <script>
    async function editCamp(org, slug, type, name) {
        const zone = document.getElementById('config-zone');
        zone.innerHTML = '<div class="py-20 flex justify-center"><div class="loader-studio"></div></div>';
        zone.scrollIntoView({ behavior: 'smooth' });

        try {
            const res = await fetch(`admin.php?action=analyze&org=${org}&form=${slug}&type=${type}`);
            const data = await res.json();

            if (data.error) throw new Error(data.error);

            let rulesHtml = '';
            if (data.rules.length === 0) {
                rulesHtml = '<div class="py-12 px-6 text-center bg-slate-50 rounded-xl text-slate-400 text-sm italic">Aucune commande détectée pour le moment. Vendez au moins un billet sur HelloAsso pour voir les options s\'afficher ici.</div>';
            } else {
                rulesHtml = data.rules.map(r => `
                <div class="rule-row flex items-center py-4 px-2 group" data-item="${r.pattern}">
                    <div class="w-8 cursor-grab text-slate-200 group-hover:text-slate-400 transition"><i class="fa-solid fa-grip-lines text-xs"></i></div>
                    <div class="w-12 flex justify-center">
                        <div class="toggle-hidden ${r.hidden ? '' : 'active'}" onclick="this.classList.toggle('active')"></div>
                    </div>
                    <div class="w-48 px-4">
                        <p class="text-[9px] font-bold text-slate-300 uppercase truncate" title="${r.pattern}">${r.pattern}</p>
                    </div>
                    <div class="flex-1 px-4">
                        <input type="text" class="display-label w-full studio-input text-sm" value="${r.displayLabel}" placeholder="Label">
                    </div>
                    <div class="flex items-center gap-3 px-4">
                        <select class="rule-type studio-select">
                            <option value="Billet" ${r.type==='Billet'?'selected':''}>BILLET</option>
                            <option value="Option" ${r.type==='Option'?'selected':''}>OPTION</option>
                            <option value="Ignorer" ${r.type==='Ignorer'?'selected':''}>CACHER</option>
                        </select>
                        <input type="text" class="rule-group studio-select w-20" value="${r.group || 'Divers'}">
                        <select class="rule-chart studio-select">
                            <option value="doughnut" ${r.chartType==='doughnut'?'selected':''}>CERCLE</option>
                            <option value="bar" ${r.chartType==='bar'?'selected':''}>BARRES</option>
                        </select>
                    </div>
                    <div class="w-32 px-4 text-right">
                        <input type="text" class="rule-transform studio-input text-[10px] font-mono text-slate-400 text-right" value="${r.transform || ''}" placeholder="TRANSFORM">
                    </div>
                </div>`).join('');
            }

            zone.innerHTML = `
                <div class="animate-reveal py-20 border-t border-slate-100 mt-20">
                    <div class="flex items-center justify-between mb-12">
                        <div>
                            <h2 class="text-xl font-bold tracking-tight uppercase">${name}</h2>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Configuration du board</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <button onclick="location.reload()" class="text-xs font-bold text-slate-400 hover:text-black transition uppercase">Annuler</button>
                            <button id="save-main-btn" class="bg-black text-white px-8 py-3 rounded-xl text-xs font-bold shadow-xl shadow-slate-200 active:scale-95 transition uppercase">Enregistrer</button>
                        </div>
                    </div>

                    <div class="flex gap-12 mb-20">
                        <div class="flex flex-col">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Objectif Recettes (€)</label>
                            <input type="number" id="goal-rev" class="text-2xl font-extrabold tracking-tighter w-40 studio-input" value="${data.goals.revenue || 0}">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Référence N-1</label>
                            <input type="number" id="goal-n1" class="text-2xl font-extrabold tracking-tighter w-40 studio-input" value="${data.goals.n1 || 0}">
                        </div>
                    </div>

                    <div id="rules-list-container">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Items détectés sur HelloAsso</span>
                            <div class="h-[1px] flex-1 bg-slate-100"></div>
                        </div>
                        <div id="rules-list">${rulesHtml}</div>
                    </div>
                </div>
            `;

            document.getElementById('save-main-btn').onclick = () => save(org, slug, type, name);
            if (data.rules.length > 0) {
                new Sortable(document.getElementById('rules-list'), { animation: 150, handle: '.cursor-grab' });
            }

        } catch (e) {
            console.error(e);
            zone.innerHTML = `<div class="py-20 text-center"><p class="text-red-500 font-bold">Erreur : ${e.message}</p><button onclick="location.reload()" class="mt-4 text-xs underline">Réessayer</button></div>`;
        }
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
                hidden: !row.querySelector('.toggle-hidden').classList.contains('active')
            });
        });
        
        const config = {
            slug, title: name, orgSlug: org, formSlug: slug, formType: type,
            rules,
            goals: { 
                revenue: parseFloat(document.getElementById('goal-rev').value) || 0, 
                n1: parseInt(document.getElementById('goal-n1').value) || 0 
            }
        };

        const btn = document.getElementById('save-main-btn');
        btn.innerText = 'PATIENCE...';
        btn.disabled = true;

        const res = await fetch('admin.php', { method:'POST', body: new URLSearchParams({save_campaign: 1, config: JSON.stringify(config)}) });
        window.location.href = 'index.php?campaign=' + slug;
    }
    </script>
</body>
</html>