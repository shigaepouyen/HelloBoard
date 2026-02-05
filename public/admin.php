<?php
session_start();
$srcPath = __DIR__ . '/../src/Services/';
require_once $srcPath . 'Storage.php';
require_once $srcPath . 'HelloAssoClient.php';

$globals = Storage::getGlobalSettings();
$adminPassword = $globals['adminPassword'] ?? null;

// --- AJOUT : Traitement du Scan et des Paramètres ---
$scanResults = null;
if (isset($_POST['run_scan'])) {
    // 1. Sauvegarde des réglages
    $newSettings = [
        'clientId' => trim($_POST['clientId']),
        'clientSecret' => trim($_POST['clientSecret']),
        'orgSlug' => trim($_POST['orgSlug']),
        'adminPassword' => $adminPassword // On garde le mot de passe admin
    ];
    Storage::saveGlobalSettings($newSettings);
    $globals = $newSettings; // Mise à jour immédiate pour l'affichage

    // 2. Lancement du scan HelloAsso (CORRECTION ICI)
    $client = new HelloAssoClient($globals['clientId'], $globals['clientSecret']);
    $scanResults = $client->discoverCampaigns($globals['orgSlug']);
}

if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
if (isset($_POST['login'])) {
    if ($_POST['password'] === $adminPassword) { $_SESSION['authenticated'] = true; } else { $loginError = "Mot de passe incorrect"; }
}

if ($adminPassword && !isset($_SESSION['authenticated'])) {
    ?>
    <!DOCTYPE html>
    <html lang="fr"><head><meta charset="UTF-8"><title>Admin Privé</title><script src="https://cdn.tailwindcss.com"></script><style>@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&display=swap');body{font-family:'Plus Jakarta Sans',sans-serif;background:#0f172a;}</style></head>
    <body class="min-h-screen flex items-center justify-center p-6"><div class="w-full max-w-md bg-white rounded-[3rem] p-10 text-center"><h2 class="text-3xl font-black mb-10 italic uppercase">Console Admin</h2><form method="POST" class="space-y-4"><input type="password" name="password" class="w-full bg-slate-50 border-2 border-transparent focus:border-blue-600 rounded-[1.5rem] p-5 text-2xl text-center outline-none" placeholder="••••••" required autofocus><button type="submit" name="login" class="w-full bg-blue-600 text-white py-5 rounded-[2rem] font-black uppercase text-xs">Accéder</button></form></div></body></html>
    <?php exit;
}

$action = $_GET['action'] ?? 'list';
$localCampaigns = Storage::listCampaigns();
// Initialisation standard (hors scan)
$client = new HelloAssoClient($globals['clientId']??'', $globals['clientSecret']??'');

if (isset($_POST['save_campaign'])) {
    $config = json_decode($_POST['config'], true);
    if ($config) {
        if (empty($config['shareToken'])) $config['shareToken'] = bin2hex(random_bytes(16));
        Storage::saveCampaign($config['slug'], $config);
    }
    echo json_encode(['success' => true]); exit;
}

if ($action === 'analyze') {
    header('Content-Type: application/json');
    $form = $_GET['form']; $org = $_GET['org'];
    $orders = $client->fetchAllOrders($org, $form, $_GET['type'] ?? 'Event');
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
    echo json_encode(['rules' => $existing['rules'] ?? [], 'goals' => $existing['goals'] ?? ['revenue'=>0, 'tickets'=>0, 'n1'=>0], 'markers' => $existing['markers'] ?? [], 'apiItems' => array_values($apiItems)]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><title>Admin — HelloBoard</title><script src="https://cdn.tailwindcss.com"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><style>@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&display=swap');body{font-family:'Plus Jakarta Sans',sans-serif;background:#f8fafc;}.admin-card{background:white;border-radius:2rem;border:1px solid #edf2f7;}.input-soft{background:#f1f5f9;border:2px solid transparent;border-radius:1.25rem;padding:12px 16px;font-weight:700;width:100%;}.toggle-btn{width:44px;height:24px;background:#cbd5e1;border-radius:20px;position:relative;cursor:pointer;}.toggle-btn.active{background:#2563eb;}.toggle-btn::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;background:white;border-radius:50%;transition:0.3s;}.toggle-btn.active::after{transform:translateX(20px);}</style></head>
<body class="pb-32">
    <nav class="p-6 bg-white border-b border-slate-100 sticky top-0 z-50 flex justify-between items-center">
        <h1 class="font-black italic uppercase">Console Admin</h1>
        <a href="index.php" class="text-xs font-bold text-slate-400">Sortir</a>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-12">
        
        <?php if ($action === 'new' || isset($scanResults)): ?>
            <div class="mb-10">
                <a href="admin.php" class="text-slate-400 text-xs font-bold uppercase mb-4 inline-block"><i class="fa-solid fa-arrow-left"></i> Retour</a>
                <h2 class="text-2xl font-black italic uppercase mb-6">Configuration HelloAsso</h2>
                
                <div class="admin-card p-8 mb-10">
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-2 md:col-span-1">
                            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-2">Client ID</label>
                            <input type="text" name="clientId" value="<?= htmlspecialchars($globals['clientId']??'') ?>" class="input-soft" required placeholder="Ex: 4421...">
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-2">Client Secret</label>
                            <input type="password" name="clientSecret" value="<?= htmlspecialchars($globals['clientSecret']??'') ?>" class="input-soft" required placeholder="••••••••">
                        </div>
                        <div class="col-span-2">
                            <label class="text-[10px] font-bold text-slate-400 uppercase block mb-2">Slug de l'organisation</label>
                            <div class="flex gap-4">
                                <input type="text" name="orgSlug" value="<?= htmlspecialchars($globals['orgSlug']??'') ?>" class="input-soft" required placeholder="Ex: mon-asso-sportive">
                                <button type="submit" name="run_scan" class="bg-blue-600 text-white px-8 rounded-2xl font-black uppercase text-xs whitespace-nowrap">
                                    <i class="fa-solid fa-sync-alt mr-2"></i> Scanner
                                </button>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 italic">Le slug est la partie de l'URL HelloAsso après /associations/ (ex: helloasso.com/associations/<strong>mon-asso</strong>)</p>
                        </div>
                    </form>
                </div>

                <?php if (isset($scanResults) && is_array($scanResults)): ?>
                    <h3 class="text-xl font-black italic uppercase mb-6">Campagnes détectées</h3>
                    <div class="grid gap-4">
                        <?php if(empty($scanResults['forms'])): ?>
                            <div class="p-4 bg-orange-50 text-orange-500 rounded-xl font-bold text-sm">Aucun formulaire trouvé pour cette organisation.</div>
                        <?php else: ?>
                            <?php foreach($scanResults['forms'] as $form): ?>
                                <div class="admin-card p-6 flex justify-between items-center animate-fade-in">
                                    <div>
                                        <h4 class="font-black text-lg"><?= htmlspecialchars($form['name']) ?></h4>
                                        <span class="text-[10px] font-bold bg-slate-100 text-slate-500 px-2 py-1 rounded uppercase"><?= $form['type'] ?></span>
                                    </div>
                                    <button onclick="configureForm('<?= $scanResults['orgSlug'] ?>', '<?= $form['slug'] ?>', '<?= $form['type'] ?>', '<?= htmlspecialchars(addslashes($form['name'])) ?>')" 
                                            class="bg-emerald-500 text-white px-6 py-3 rounded-xl text-xs font-black shadow-lg shadow-emerald-200">
                                        Configurer
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <h2 class="text-2xl font-black mb-8 italic uppercase">Boards Configurés</h2>
            <div class="grid gap-4 mb-20">
                <?php if(empty($localCampaigns)): ?>
                    <div class="text-center py-10 text-slate-400">Aucun board configuré. Commencez par scanner votre compte.</div>
                <?php endif; ?>
                
                <?php foreach($localCampaigns as $c): ?>
                    <div class="admin-card p-6 flex justify-between items-center">
                        <div>
                            <h3 class="font-black"><?= htmlspecialchars($c['title']) ?></h3>
                            <a href="index.php?campaign=<?= $c['slug'] ?>" target="_blank" class="text-[10px] text-blue-500 font-bold uppercase hover:underline">Voir le board <i class="fa-solid fa-external-link-alt"></i></a>
                        </div>
                        <button onclick='editCamp("<?= $c["orgSlug"] ?>", "<?= $c["slug"] ?>", "<?= $c["formType"] ?>", <?= htmlspecialchars(json_encode($c["title"])) ?>)' class="bg-blue-600 text-white px-6 py-3 rounded-xl text-xs font-black">Réglages</button>
                    </div>
                <?php endforeach; ?>
                
                <a href="admin.php?action=new" class="mt-4 flex items-center justify-center p-10 border-2 border-dashed border-slate-200 rounded-[2.5rem] text-slate-400 font-black hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50 transition uppercase text-xs italic">
                    <i class="fa-solid fa-plus-circle text-2xl mr-3"></i> Scanner mon compte / Nouveau Board
                </a>
            </div>
        <?php endif; ?>

        <div id="config-zone"></div>
    </main>

    <script>
    async function editCamp(org, slug, type, name) {
        const zone = document.getElementById('config-zone');
        zone.innerHTML = '<div class="py-20 text-center animate-pulse">Chargement de la configuration...</div>';
        zone.scrollIntoView({ behavior: 'smooth' });
        
        const res = await fetch(`admin.php?action=analyze&org=${org}&form=${slug}&type=${type}`);
        const data = await res.json();
        const goals = data.goals || { revenue: 0, tickets: 0, n1: 0 };
        const rules = data.rules || [];

        // On fusionne les items HelloAsso détectés pour proposer les nouvelles règles
        (data.apiItems || []).forEach(pattern => {
            if(!rules.find(r => r.pattern === pattern)) {
                rules.push({ pattern, displayLabel: pattern, type: 'Option', group: 'Divers', chartType: 'pie', transform: '', hidden: false });
            }
        });

        zone.innerHTML = `
            <div class="mt-20 pt-20 border-t border-slate-200">
                <div class="flex justify-between items-center mb-12"><h2 class="text-2xl font-black italic uppercase">${name}</h2><button id="save-main-btn" class="bg-blue-600 text-white px-8 py-4 rounded-2xl font-black uppercase text-xs">Sauvegarder</button></div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                    <div class="admin-card p-8">
                        <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic">Objectifs financiers & Quotas</h3>
                        <div class="grid gap-4">
                            <div><label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Cible Financière (€)</label><input type="number" id="goal-rev" class="input-soft" value="${goals.revenue}"></div>
                            <div><label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Quota Billets (Qté)</label><input type="number" id="goal-tix" class="input-soft" value="${goals.tickets}"></div>
                            <div><label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Ref N-1</label><input type="number" id="goal-n1" class="input-soft" value="${goals.n1}"></div>
                        </div>
                    </div>
                    <div class="admin-card p-8">
                        <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic">Marqueurs Timeline (Impact Com)</h3>
                        <div id="markers-list" class="space-y-2 mb-4">
                            ${(data.markers || []).map(m => `
                                <div class="flex gap-2 marker-row">
                                    <input type="text" placeholder="Action" class="marker-label input-soft !py-2 !text-xs" value="${m.label}">
                                    <input type="date" class="marker-date input-soft !py-2 !text-xs w-36" value="${m.date}">
                                    <button onclick="this.parentElement.remove()" class="text-red-400 px-2"><i class="fa-solid fa-times"></i></button>
                                </div>
                            `).join('')}
                        </div>
                        <button onclick="addMarkerRow()" class="text-[10px] font-black text-blue-600 uppercase">+ Nouveau marqueur</button>
                    </div>
                </div>

                <div id="rules-list" class="space-y-2">
                    <h3 class="text-xs font-black uppercase text-slate-400 mb-4 pl-2">Configuration de l'importation (Ordre Drag & Drop)</h3>
                    ${rules.map(r => `
                    <div class="rule-tile admin-card p-6 flex flex-col md:flex-row items-center gap-4" data-item="${r.pattern}">
                        <div class="cursor-grab text-slate-300"><i class="fa-solid fa-grip-lines"></i></div>
                        <div class="toggle-btn ${r.hidden ? '' : 'active'}" onclick="this.classList.toggle('active')"></div>
                        
                        <div class="flex-1 w-full">
                            <input type="text" class="display-label input-soft !py-2 !text-sm" value="${r.displayLabel}">
                            <p class="text-[9px] font-bold text-slate-300 uppercase mt-1 truncate italic">Source : ${r.pattern}</p>
                        </div>

                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 w-full lg:w-auto">
                            <input type="text" class="rule-group input-soft !py-2 !px-3 !text-[10px] uppercase" value="${r.group || 'Divers'}" placeholder="NOM DU BLOC">
                            <select class="rule-type input-soft !py-2 !px-3 !text-[10px] uppercase font-black">
                                <option value="Billet" ${r.type==='Billet'?'selected':''}>Billet</option>
                                <option value="Option" ${r.type==='Option'?'selected':''}>Option</option>
                                <option value="Ignorer" ${r.type==='Ignorer'?'selected':''}>Cacher</option>
                            </select>
                            <select class="rule-chart input-soft !py-2 !px-3 !text-[10px] uppercase font-black">
                                <option value="pie" ${r.chartType==='pie'?'selected':''}>Secteurs</option>
                                <option value="bar" ${r.chartType==='bar'?'selected':''}>Barres</option>
                            </select>
                            <input type="text" class="rule-transform input-soft !py-2 !px-3 !text-[10px] font-mono text-blue-500" value="${r.transform || ''}" placeholder="REGEX/TRANS">
                        </div>
                    </div>`).join('')}
                </div>
            </div>`;
        document.getElementById('save-main-btn').onclick = () => save(org, slug, type, name);
        new Sortable(document.getElementById('rules-list'), { animation: 150, handle: '.cursor-grab' });
    }

    function addMarkerRow() {
        const div = document.createElement('div'); div.className = 'flex gap-2 marker-row mb-2';
        div.innerHTML = `<input type="text" placeholder="Action" class="marker-label input-soft !py-2 !text-xs"><input type="date" class="marker-date input-soft !py-2 !text-xs w-36"><button onclick="this.parentElement.remove()" class="text-red-400 px-2"><i class="fa-solid fa-times"></i></button>`;
        document.getElementById('markers-list').appendChild(div);
    }

    async function save(org, slug, type, name) {
        const rules = []; document.querySelectorAll('.rule-tile').forEach(row => {
            rules.push({ 
                pattern: row.dataset.item, 
                displayLabel: row.querySelector('.display-label').value, 
                type: row.querySelector('.rule-type').value, 
                group: row.querySelector('.rule-group').value || 'Divers', 
                chartType: row.querySelector('.rule-chart').value, 
                transform: row.querySelector('.rule-transform').value, 
                hidden: !row.querySelector('.toggle-btn').classList.contains('active') 
            });
        });
        const markers = []; document.querySelectorAll('.marker-row').forEach(row => {
            const l = row.querySelector('.marker-label').value; const d = row.querySelector('.marker-date').value;
            if(l && d) markers.push({label: l, date: d});
        });
        const config = { slug, title: name, orgSlug: org, formSlug: slug, formType: type, rules, markers, goals: { revenue: parseFloat(document.getElementById('goal-rev').value), tickets: parseInt(document.getElementById('goal-tix').value), n1: parseInt(document.getElementById('goal-n1').value) } };
        await fetch('admin.php', { method: 'POST', body: new URLSearchParams({save_campaign: 1, config: JSON.stringify(config)}) });
        window.location.href = 'index.php?campaign=' + slug;
    }
    </script>
    
    <?php require_once __DIR__ . '/../templates/admin_form.php'; ?>
</body></html>