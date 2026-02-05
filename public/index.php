<?php
session_start();
require_once __DIR__ . '/../src/Services/Storage.php';

// --- CONFIGURATION ET UTILITAIRES ---
$globals = Storage::getGlobalSettings();
$adminPassword = $globals['adminPassword'] ?? null;
$campaigns = Storage::listCampaigns();

/**
 * Nettoie et construit une URL sans double slash et sans paramètres vides
 */
function getCleanUrl($campaignSlug = null, $token = null) {
    // On récupère le protocole et l'hôte
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME']; // ex: /index.php ou /sous-dossier/index.php
    
    // On nettoie le script pour éviter les doubles slashes éventuels
    $script = '/' . ltrim($script, '/');
    $baseUrl = $protocol . '://' . $host . $script;

    if (!$campaignSlug) return $baseUrl;

    $params = ['campaign' => $campaignSlug];
    if ($token) {
        $params['token'] = $token;
    }

    return $baseUrl . '?' . http_build_query($params);
}

$currentCampaignId = $_GET['campaign'] ?? null;
$providedToken = $_GET['token'] ?? null;

// --- LOGIQUE DE CONNEXION ---
if (isset($_POST['login'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['authenticated'] = true;
        // Redirection propre
        $redir = 'index.php' . ($currentCampaignId ? "?campaign=" . urlencode($currentCampaignId) : "");
        header("Location: $redir"); 
        exit;
    } else {
        $loginError = "Mot de passe incorrect";
    }
}

// CAS 1 : Accès à une campagne spécifique via un lien partagé
if ($currentCampaignId) {
    $campaignConfig = null;
    foreach ($campaigns as $c) {
        if ($c['slug'] === $currentCampaignId) { 
            $campaignConfig = $c; 
            break; 
        }
    }

    if ($campaignConfig) {
        $isAdmin = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
        
        // Sécurité : Un token vide dans la config ne doit pas permettre l'accès sans token
        $storedToken = $campaignConfig['shareToken'] ?? null;
        $isValidToken = (!empty($providedToken) && !empty($storedToken) && $providedToken === $storedToken);

        if ($isAdmin || $isValidToken) {
            $isReadOnly = !$isAdmin;
            include __DIR__ . '/../templates/dashboard.php';
            exit;
        }
    }
}

// CAS 2 : Protection Admin obligatoire si un mot de passe est configuré
if ($adminPassword && !isset($_SESSION['authenticated'])) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Accès Sécurisé — HelloBoard</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap');
            body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; }
            .login-card { background: white; border-radius: 3rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1); }
            .input-sexy { background: #f8fafc; border: 2px solid transparent; border-radius: 1.5rem; padding: 1.25rem; font-weight: 700; text-align: center; transition: all 0.2s; }
            .input-sexy:focus { background: white; border-color: #2563eb; outline: none; }
        </style>
    </head>
    <body class="min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-md login-card p-10 md:p-14 text-center">
            <div class="w-20 h-20 bg-blue-50 text-blue-600 rounded-[2rem] flex items-center justify-center mx-auto mb-8 shadow-inner">
                <i class="fa-solid fa-shield-halved text-3xl"></i>
            </div>
            <h2 class="text-3xl font-black italic uppercase tracking-tighter mb-2">Espace Privé</h2>
            <p class="text-slate-400 font-bold mb-10 text-sm">Veuillez saisir votre code d'accès pour consulter les statistiques de l'APEL.</p>
            
            <form method="POST" class="space-y-4">
                <div class="relative">
                    <input type="password" name="password" class="w-full input-sexy text-2xl" placeholder="••••••" required autofocus>
                </div>
                <button type="submit" name="login" class="w-full bg-blue-600 text-white py-5 rounded-[2rem] font-black uppercase tracking-widest text-xs shadow-xl shadow-blue-200 active:scale-95 transition-all">
                    Se connecter
                </button>
                <?php if(isset($loginError)): ?>
                    <div class="mt-4 p-3 bg-red-50 text-red-500 rounded-xl text-xs font-bold animate-bounce">
                        <i class="fa-solid fa-circle-exclamation mr-2"></i> <?= htmlspecialchars($loginError) ?>
                    </div>
                <?php endif; ?>
            </form>
            <p class="mt-12 text-[10px] font-black text-slate-300 uppercase tracking-[0.3em]">HelloBoard Security</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervision — HelloBoard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; color: #1e293b; }
        .campaign-card { background: white; border-radius: 2.5rem; border: 1px solid #edf2f7; box-shadow: 0 10px 20px rgba(0,0,0,0.02); transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); position: relative; overflow: hidden; }
        .campaign-card:hover { transform: translateY(-8px); border-color: #2563eb; box-shadow: 0 20px 40px rgba(0,0,0,0.05); }
        .btn-share { opacity: 0; transition: all 0.2s; }
        .campaign-card:hover .btn-share { opacity: 1; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav class="p-8 sticky top-0 bg-white/50 backdrop-blur-xl z-50">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white italic font-black">H</div>
                <span class="text-sm font-black uppercase tracking-widest italic text-slate-900">HelloBoard</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin.php" class="bg-slate-900 text-white px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-blue-600 transition-colors">Admin</a>
                <a href="admin.php?logout=1" class="w-10 h-10 flex items-center justify-center rounded-full bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition-all"><i class="fa-solid fa-power-off"></i></a>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-6 py-20 flex-1 w-full">
        <header class="mb-16">
            <h1 class="text-5xl font-black italic tracking-tighter mb-4">Console de<br>Supervision</h1>
            <p class="text-slate-400 font-bold text-lg max-w-lg">Sélectionnez une campagne active pour suivre les flux en temps réel.</p>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($campaigns as $campaign): 
                $shareToken = $campaign['shareToken'] ?? null;
                $publicLink = getCleanUrl($campaign['slug'], $shareToken);
            ?>
                <div class="campaign-card group flex flex-col">
                    <a href="?campaign=<?= htmlspecialchars($campaign['slug']) ?>" class="p-10 flex-1">
                        <div class="w-14 h-14 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-300 group-hover:bg-blue-50 group-hover:text-blue-500 transition mb-10">
                            <i class="fa-solid fa-chart-pie text-xl"></i>
                        </div>
                        <h3 class="text-2xl font-black text-slate-900 group-hover:text-blue-600 transition leading-tight mb-2"><?= htmlspecialchars($campaign['title']) ?></h3>
                        <div class="flex items-center gap-2 text-blue-600 font-black text-[10px] uppercase tracking-widest mt-8">
                            Ouvrir le board <i class="fa-solid fa-arrow-right"></i>
                        </div>
                    </a>
                    
                    <?php if ($shareToken): ?>
                    <button onclick="copyToClipboard('<?= $publicLink ?>')" 
                            class="btn-share absolute top-6 right-6 w-10 h-10 bg-slate-100 rounded-xl text-slate-500 hover:bg-blue-600 hover:text-white flex items-center justify-center"
                            title="Copier le lien public sécurisé">
                        <i class="fa-solid fa-share-nodes"></i>
                    </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        function copyToClipboard(text) {
            const el = document.createElement('textarea');
            el.value = text;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            
            // Notification visuelle rapide
            const btn = event.currentTarget;
            const icon = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i>';
            btn.classList.add('bg-green-500', 'text-white');
            setTimeout(() => {
                btn.innerHTML = icon;
                btn.classList.remove('bg-green-500', 'text-white');
            }, 2000);
        }
    </script>

</body>
</html>