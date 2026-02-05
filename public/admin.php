<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

$srcPath = __DIR__ . '/../src/Services/';
require_once $srcPath . 'Storage.php';
require_once $srcPath . 'HelloAssoClient.php';

$globals = Storage::getGlobalSettings();
$adminPassword = $globals['adminPassword'] ?? null;

// LOGIQUE DE DÉCONNEXION
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php'); exit;
}

// LOGIQUE DE CONNEXION ADMIN
if (isset($_POST['login'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['authenticated'] = true;
    } else {
        $loginError = "Mot de passe incorrect";
    }
}

// PROTECTION : On n'affiche le login QUE si un mot de passe a été défini au préalable
if ($adminPassword && !isset($_SESSION['authenticated'])) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Privé — HelloBoard</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap');
            body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0f172a; }
            .login-card { background: white; border-radius: 3rem; }
            .input-sexy { background: #f8fafc; border: 2px solid transparent; border-radius: 1.5rem; padding: 1.25rem; font-weight: 700; text-align: center; transition: all 0.2s; }
            .input-sexy:focus { background: white; border-color: #2563eb; outline: none; }
        </style>
    </head>
    <body class="min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-md login-card p-10 md:p-14 text-center">
            <div class="w-20 h-20 bg-blue-50 text-blue-600 rounded-[2rem] flex items-center justify-center mx-auto mb-8 shadow-inner"><i class="fa-solid fa-lock text-3xl"></i></div>
            <h2 class="text-3xl font-black italic uppercase tracking-tighter mb-2">Console Admin</h2>
            <p class="text-slate-400 font-bold mb-10 text-sm">Entrez le mot de passe maître pour modifier les réglages.</p>
            <form method="POST" class="space-y-4">
                <input type="password" name="password" class="w-full input-sexy text-2xl" placeholder="••••••" required autofocus>
                <button type="submit" name="login" class="w-full bg-blue-600 text-white py-5 rounded-[2rem] font-black uppercase tracking-widest text-xs shadow-xl shadow-blue-200 transition-all">Accéder</button>
                <?php if(isset($loginError)): ?><p class="text-red-500 font-bold text-xs mt-4"><?= $loginError ?></p><?php endif; ?>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$action = $_GET['action'] ?? 'list';
$localCampaigns = Storage::listCampaigns();
$client = new HelloAssoClient($globals['clientId']??'', $globals['clientSecret']??'');

// ACTIONS
if (isset($_POST['save_globals'])) {
    $globals['clientId'] = $_POST['clientId'];
    $globals['clientSecret'] = $_POST['clientSecret'];
    $globals['orgSlug'] = $_POST['orgSlug'];
    $globals['adminPassword'] = $_POST['adminPassword'];
    Storage::saveGlobalSettings($globals);
    header('Location: admin.php?msg=saved'); exit;
}

if (isset($_POST['delete_campaign'])) {
    Storage::deleteCampaign($_POST['slug']);
    header('Location: admin.php?msg=deleted'); exit;
}

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
    try {
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
        $finalRules = $existing['rules'] ?? [];
        foreach ($apiItems as $item) {
            $found = false;
            foreach($finalRules as $r) if($r['pattern'] === $item) $found = true;
            if (!$found) $finalRules[] = ['pattern' => $item, 'displayLabel' => $item, 'type' => 'Option', 'group' => 'Divers', 'chartType' => 'pie', 'transform' => '', 'hidden' => false];
        }
        echo json_encode(['rules' => $finalRules, 'goals' => $existing['goals'] ?? ['revenue'=>0, 'tickets'=>0, 'n1'=>0], 'shareToken' => $existing['shareToken'] ?? bin2hex(random_bytes(16))]);
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
        .admin-card { background: white; border-radius: 2rem; border: 1px solid #edf2f7; box-shadow: 0 10px 20px rgba(0,0,0,0.02); }
        .input-soft { background: #f1f5f9; border: 2px solid transparent; border-radius: 1.25rem; padding: 14px 20px; font-weight: 700; width: 100%; transition: all 0.2s; }
        .input-soft:focus { background: white; border-color: #2563eb; outline: none; }
        .rule-tile { background: white; border: 1px solid #edf2f7; border-radius: 1.5rem; margin-bottom: 0.75rem; transition: all 0.2s; }
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
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-xs font-bold text-slate-400">Quitter</a>
                <a href="?logout=1" class="text-xs font-bold text-red-400">Sortir</a>
            </div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-12">
        
        <section class="mb-16">
            <h2 class="text-2xl font-black mb-8 italic uppercase tracking-tight">Paramètres API</h2>
            <form method="POST" class="admin-card p-10 space-y-6">
                <input type="hidden" name="save_globals" value="1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label class="text-[10px] font-black uppercase text-slate-400 mb-2 block">Client ID</label><input type="text" name="clientId" class="input-soft" value="<?= $globals['clientId'] ?? '' ?>" required></div>
                    <div><label class="text-[10px] font-black uppercase text-slate-400 mb-2 block">Client Secret</label><input type="password" name="clientSecret" class="input-soft" value="<?= $globals['clientSecret'] ?? '' ?>" required></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><label class="text-[10px] font-black uppercase text-slate-400 mb-2 block">Slug Association</label><input type="text" name="orgSlug" class="input-soft" value="<?= $globals['orgSlug'] ?? '' ?>" required></div>
                    <div><label class="text-[10px] font-black uppercase text-blue-600 mb-2 block italic">Mot de passe de supervision</label><input type="text" name="adminPassword" class="input-soft border-blue-100" value="<?= $globals['adminPassword'] ?? '' ?>" placeholder="Définir pour verrouiller" required></div>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-8 py-4 rounded-2xl font-black uppercase text-[10px] shadow-lg shadow-blue-100">Enregistrer les réglages</button>
            </form>
        </section>

        <?php if ($action === 'new'): ?>
            <header class="mb-12"><h2 class="text-3xl font-extrabold tracking-tight uppercase italic">Scanner HelloAsso</h2></header>
            <div class="space-y-4">
                <?php 
                $discovery = $client->discoverCampaigns($globals['orgSlug'] ?? '');
                if ($discovery && !empty($discovery['forms'])): foreach($discovery['forms'] as $f): 
                ?>
                    <div class="admin-card p-8 flex items-center justify-between">
                        <div><h3 class="font-extrabold text-lg"><?= htmlspecialchars($f['name']) ?></h3><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?= $f['type'] ?></p></div>
                        <button onclick='editCamp("<?= $discovery["orgSlug"] ?>", "<?= $f["slug"] ?>", "<?= $f["type"] ?>", <?= htmlspecialchars(json_encode($f["name"])) ?>)' class="bg-blue-600 text-white px-8 py-4 rounded-2xl font-black uppercase text-[10px]">Configurer</button>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        <?php else: ?>
            <h2 class="text-2xl font-black mb-8 italic uppercase tracking-tight">Mes Boards</h2>
            <div class="grid gap-4">
                <?php foreach($localCampaigns as $c): ?>
                <?php 
                // Correction du double slash ici via rtrim sur dirname
                $baseDir = rtrim(dirname($_SERVER['PHP_SELF']), '/');
                $shareUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . $baseDir . "/index.php?campaign=$c[slug]&token=".($c['shareToken'] ?? ''); 
                ?>
                <div class="admin-card p-8 group">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center font-black text-xs">0<?= array_search($c, $localCampaigns) + 1 ?></div>
                            <h3 class="font-extrabold text-lg"><?= htmlspecialchars($c['title']) ?></h3>
                        </div>
                        <div class="flex items-center gap-3">
                            <button onclick='editCamp("<?= $c["orgSlug"] ?>", "<?= $c["slug"] ?>", "<?= $c["formType"] ?>", <?= htmlspecialchars(json_encode($c["title"])) ?>)' class="bg-blue-600 text-white px-6 py-3 rounded-xl text-xs font-black">Réglages</button>
                            <form method="POST" onsubmit="return confirm('Supprimer ?');"><input type="hidden" name="delete_campaign" value="1"><input type="hidden" name="slug" value="<?= $c['slug'] ?>"><button class="p-3 text-slate-300 hover:text-red-500"><i class="fa-solid fa-trash-can"></i></button></form>
                        </div>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl flex items-center justify-between gap-4">
                        <div class="flex-1 truncate text-[9px] font-mono text-slate-400"><?= $shareUrl ?></div>
                        <button onclick="copyToClipboard('<?= $shareUrl ?>')" class="shrink-0 text-[10px] font-black text-blue-600 uppercase bg-white px-3 py-1.5 rounded-lg border border-blue-100 shadow-sm">Lien Public</button>
                    </div>
                </div>
                <?php endforeach; ?>
                <a href="admin.php?action=new" class="mt-4 flex items-center justify-center p-12 border-2 border-dashed border-slate-200 rounded-[3rem] text-slate-400 font-extrabold hover:text-blue-600 transition uppercase text-xs tracking-widest italic"><i class="fa-solid fa-plus mr-3"></i> Scanner mon compte</a>
            </div>
        <?php endif; ?>

        <div id="config-zone"></div>
    </main>

    <script>
    function copyToClipboard(text) {
        const el = document.createElement('textarea'); el.value = text; document.body.appendChild(el); el.select(); document.execCommand('copy'); document.body.removeChild(el);
        alert('Lien copié ! Partagez-le avec votre équipe.');
    }
    async function editCamp(org, slug, type, name) {
        const zone = document.getElementById('config-zone');
        zone.innerHTML = '<div class="py-20 text-center text-blue-600 font-bold animate-pulse">Sync...</div>';
        zone.scrollIntoView({ behavior: 'smooth' });
        const res = await fetch(`admin.php?action=analyze&org=${org}&form=${slug}&type=${type}`);
        const data = await res.json();
        zone.innerHTML = `
            <div class="mt-20 pt-20 border-t border-slate-200 animate-reveal">
                <div class="flex items-center justify-between mb-12">
                    <h2 class="text-2xl font-black italic uppercase">${name}</h2>
                    <button id="save-main-btn" class="bg-blue-600 text-white px-8 py-4 rounded-2xl font-black uppercase text-xs">Sauvegarder</button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-12">
                    <div class="admin-card p-6"><label class="text-[10px] font-black text-slate-400 uppercase mb-3 block">Obj. Recettes (€)</label><input type="number" id="goal-rev" class="input-soft" value="${data.goals.revenue || 0}"></div>
                    <div class="admin-card p-6"><label class="text-[10px] font-black text-slate-400 uppercase mb-3 block">Obj. Billets (Nb)</label><input type="number" id="goal-tix" class="input-soft" value="${data.goals.tickets || 0}"></div>
                    <div class="admin-card p-6"><label class="text-[10px] font-black text-slate-400 uppercase mb-3 block">Réf. Année N-1</label><input type="number" id="goal-n1" class="input-soft" value="${data.goals.n1 || 0}"></div>
                </div>
                <div id="rules-list">${data.rules.map(r => `
                    <div class="rule-tile p-6 flex flex-col sm:flex-row items-center gap-6" data-item="${r.pattern}">
                        <div class="cursor-grab text-slate-200 px-2"><i class="fa-solid fa-grip-lines"></i></div>
                        <div class="toggle-btn ${r.hidden ? '' : 'active'}" onclick="this.classList.toggle('active')"></div>
                        <div class="flex-1 w-full"><input type="text" class="display-label input-soft !py-2 !text-sm" value="${r.displayLabel}"><p class="text-[9px] font-bold text-slate-300 uppercase mt-1 truncate italic">Source : ${r.pattern}</p></div>
                        <div class="flex gap-2">
                            <select class="rule-type input-soft !py-2 !px-3 !w-auto !text-[10px] uppercase font-black"><option value="Billet" ${r.type==='Billet'?'selected':''}>Billet</option><option value="Option" ${r.type==='Option'?'selected':''}>Option</option><option value="Ignorer" ${r.type==='Ignorer'?'selected':''}>Cacher</option></select>
                            <select class="rule-chart input-soft !py-2 !px-3 !w-auto !text-[10px] uppercase font-black"><option value="pie" ${r.chartType==='pie'?'selected':''}>Disque</option><option value="bar" ${r.chartType==='bar'?'selected':''}>Barres</option></select>
                        </div>
                        <input type="text" class="rule-transform input-soft !py-2 !px-3 !w-32 !text-[10px] font-mono !text-blue-500" value="${r.transform || ''}" placeholder="TRANS">
                    </div>`).join('')}</div>
            </div>`;
        document.getElementById('save-main-btn').onclick = () => save(org, slug, type, name, data.shareToken);
        if (data.rules.length > 0) new Sortable(document.getElementById('rules-list'), { animation: 150, handle: '.cursor-grab' });
    }
    async function save(org, slug, type, name, token) {
        const rules = [];
        document.querySelectorAll('.rule-tile').forEach(row => {
            rules.push({ pattern: row.dataset.item, displayLabel: row.querySelector('.display-label').value, type: row.querySelector('.rule-type').value, group: 'Divers', chartType: row.querySelector('.rule-chart').value, transform: row.querySelector('.rule-transform').value, hidden: !row.querySelector('.toggle-btn').classList.contains('active') });
        });
        const config = { slug, title: name, orgSlug: org, formSlug: slug, formType: type, rules, shareToken: token, goals: { revenue: parseFloat(document.getElementById('goal-rev').value) || 0, tickets: parseInt(document.getElementById('goal-tix').value) || 0, n1: parseInt(document.getElementById('goal-n1').value) || 0 } };
        const btn = document.getElementById('save-main-btn'); btn.innerText = 'Sync...';
        await fetch('admin.php', { method:'POST', body: new URLSearchParams({save_campaign: 1, config: JSON.stringify(config)}) });
        window.location.href = 'index.php?campaign=' + slug;
    }
    </script>
</body>
</html>