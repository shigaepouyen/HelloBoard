<?php
session_start();
require_once __DIR__ . '/../src/Services/Storage.php';

// --- CONFIGURATION ---
$globals = Storage::getGlobalSettings();
$adminPassword = $globals['adminPassword'] ?? null;
$isAdmin = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

// --- FONCTIONS UTILITAIRES ---
function getCleanUrl($campaignSlug = null, $token = null) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME']; 
    $baseUrl = preg_replace('/(?<!:)\/\//', '/', $protocol . '://' . $host . $script);
    
    if (!$campaignSlug) return $baseUrl;
    
    $params = ['campaign' => $campaignSlug];
    if (!empty($token)) $params['token'] = $token;
    
    return $baseUrl . '?' . http_build_query($params);
}

// --- GESTION DECONNEXION ---
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// --- GESTION LOGIN ADMIN ---
$loginError = null;
if (isset($_POST['login'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['authenticated'] = true;
        // On redirige pour nettoyer le POST
        header("Location: index.php" . (isset($_GET['campaign']) ? "?campaign=".urlencode($_GET['campaign']) : "")); 
        exit;
    } else {
        $loginError = "Mot de passe incorrect";
    }
}

// --- ROUTAGE PRINCIPAL ---

$campaignSlug = $_GET['campaign'] ?? null;
$providedToken = $_GET['token'] ?? null;

// CAS 1 : Affichage d'un Board spécifique
if ($campaignSlug) {
    // On cherche la config du board
    $campaigns = Storage::listCampaigns();
    $campaignConfig = null;
    foreach ($campaigns as $c) {
        if ($c['slug'] === $campaignSlug) { 
            $campaignConfig = $c; 
            break; 
        }
    }

    if ($campaignConfig) {
        // Vérification des droits (Admin OU Token valide)
        $storedToken = $campaignConfig['shareToken'] ?? null;
        $isValidToken = (!empty($providedToken) && !empty($storedToken) && $providedToken === $storedToken);
        
        if ($isAdmin || $isValidToken) {
            // C'est validé, on charge le template
            require __DIR__ . '/../templates/dashboard.php';
            exit;
        } else {
            // Board existe mais accès refusé => on renvoie vers le login
            $loginError = "Lien invalide ou expiré. Veuillez vous connecter.";
        }
    } else {
        // Board introuvable
        header("HTTP/1.0 404 Not Found");
        echo "Board introuvable.";
        exit;
    }
}

// CAS 2 : Accueil (Si Admin => Liste, Sinon => Login)
if ($isAdmin) {
    // --- MODE ADMIN : CONSOLE DE SUPERVISION ---
    $campaigns = Storage::listCampaigns();
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Supervision — HelloBoard</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap'); body{font-family:'Plus Jakarta Sans',sans-serif;background:#f1f5f9;}</style>
    </head>
    <body class="min-h-screen pb-20">
        <nav class="p-6 bg-white sticky top-0 z-50 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-slate-900 rounded-lg text-white flex items-center justify-center font-black italic">H</div>
                <h1 class="font-black italic uppercase text-slate-900">Console Admin</h1>
            </div>
            <div class="flex gap-4">
                <a href="admin.php" class="bg-blue-600 text-white px-5 py-2 rounded-xl text-xs font-black uppercase hover:bg-blue-700 transition">Configuration</a>
                <a href="index.php?logout=1" class="w-8 h-8 flex items-center justify-center rounded-full bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition"><i class="fa-solid fa-power-off"></i></a>
            </div>
        </nav>

        <main class="max-w-5xl mx-auto px-6 py-12">
            <h2 class="text-3xl font-black italic uppercase mb-8">Vos Boards Actifs</h2>
            
            <?php if(empty($campaigns)): ?>
                <div class="text-center py-20 bg-white rounded-[2rem] border border-dashed border-slate-300">
                    <p class="text-slate-400 font-bold mb-4">Aucun board configuré.</p>
                    <a href="admin.php" class="text-blue-600 font-black uppercase text-xs underline">Configurer un board</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($campaigns as $c): 
                        // --- AJOUT : Si le board est archivé, on ne l'affiche pas ici ---
                        if (!empty($c['archived'])) continue; 
                        
                        $tokenLink = getCleanUrl($c['slug'], $c['shareToken']);
                    ?>
                    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 group hover:border-blue-500 transition relative">
                        <div class="flex justify-between items-start mb-6">
                            <h3 class="text-xl font-black text-slate-800"><?= htmlspecialchars($c['title']) ?></h3>
                            <span class="bg-slate-100 text-slate-500 text-[10px] font-bold px-2 py-1 rounded uppercase"><?= $c['formType'] ?></span>
                        </div>
                        
                        <div class="flex gap-3 mt-8">
                            <a href="?campaign=<?= $c['slug'] ?>" class="flex-1 bg-blue-50 text-blue-600 py-3 rounded-xl text-xs font-black uppercase text-center hover:bg-blue-600 hover:text-white transition">
                                Ouvrir
                            </a>
                            <button onclick="copyLink('<?= $tokenLink ?>', this)" class="px-4 bg-slate-100 text-slate-400 rounded-xl hover:bg-emerald-500 hover:text-white transition" title="Copier le lien public">
                                <i class="fa-solid fa-link"></i>
                            </button>
                            <a href="admin.php" class="px-4 bg-slate-100 text-slate-400 rounded-xl hover:bg-slate-800 hover:text-white transition flex items-center" title="Réglages">
                                <i class="fa-solid fa-gear"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
        <script>
        function copyLink(url, btn) {
            navigator.clipboard.writeText(url);
            let icon = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i>';
            setTimeout(() => btn.innerHTML = icon, 2000);
        }
        </script>
    </body>
    </html>
    <?php
} else {
    // --- MODE PUBLIC : LOGIN UNIQUEMENT ---
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Accès Sécurisé — HelloBoard</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap'); body{font-family:'Plus Jakarta Sans',sans-serif;background:#0f172a;}</style>
    </head>
    <body class="min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-md bg-white rounded-[2.5rem] p-10 text-center shadow-2xl">
            <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-8 text-white text-2xl shadow-lg shadow-blue-200">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h2 class="text-2xl font-black italic uppercase text-slate-900 mb-2">Espace Privé</h2>
            <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-8">HelloBoard Admin</p>
            
            <form method="POST" class="space-y-4">
                <input type="password" name="password" class="w-full bg-slate-50 border-2 border-transparent focus:border-blue-600 rounded-2xl p-4 text-center font-black outline-none transition" placeholder="Code d'accès" required autofocus>
                <button type="submit" name="login" class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black uppercase text-xs hover:bg-blue-600 transition shadow-xl">
                    Connexion
                </button>
                <?php if($loginError): ?>
                    <div class="p-3 bg-red-50 text-red-500 rounded-xl text-xs font-bold mt-4 animate-bounce">
                        <?= htmlspecialchars($loginError) ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </body>
    </html>
    <?php
}
?>