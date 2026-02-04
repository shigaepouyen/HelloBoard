<?php
require_once __DIR__ . '/../src/Services/Storage.php';

$campaigns = Storage::listCampaigns();
$currentCampaignId = $_GET['campaign'] ?? null;

// Si une campagne est sélectionnée, on charge le template du dashboard
if ($currentCampaignId) {
    $campaignConfig = null;
    foreach ($campaigns as $c) {
        if ($c['slug'] === $currentCampaignId) {
            $campaignConfig = $c;
            break;
        }
    }
    
    if ($campaignConfig) {
        include __DIR__ . '/../templates/dashboard.php';
        exit;
    }
}

// Sinon, on affiche la liste des campagnes disponibles
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelloBoard - APEL Saint Joseph</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700;900&display=swap');
        body { font-family: 'Outfit', sans-serif; background-color: #0f172a; }
    </style>
</head>
<body class="text-slate-200 min-h-screen flex flex-col">

    <nav class="p-6 border-b border-slate-800 bg-[#1e1b4b]/50 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <img src="assets/img/logo.svg" alt="Logo" class="w-10 h-10">
                <span class="text-xl font-black tracking-tight uppercase">HelloBoard</span>
            </div>
            <a href="admin.php" class="text-slate-400 hover:text-white transition text-sm font-bold">
                <i class="fa-solid fa-gear mr-1"></i> Admin
            </a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-12 flex-1 w-full">
        <div class="mb-12 text-center">
            <h1 class="text-4xl font-black text-white mb-4">Tableaux de Bord</h1>
            <p class="text-slate-400">Sélectionnez une campagne de l'APEL Saint Joseph pour suivre les ventes en direct.</p>
        </div>

        <?php if (empty($campaigns)): ?>
            <div class="bg-slate-800/50 rounded-3xl p-12 text-center border border-slate-700 max-w-2xl mx-auto">
                <div class="bg-slate-900 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fa-solid fa-folder-open text-slate-600 text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold mb-2">Aucune campagne configurée</h2>
                <p class="text-slate-500 mb-8 text-sm">Commencez par ajouter votre première campagne HelloAsso dans l'espace d'administration.</p>
                <a href="admin.php?action=new" class="bg-purple-600 hover:bg-purple-500 text-white font-bold py-3 px-8 rounded-full transition">
                    Créer une campagne
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($campaigns as $campaign): ?>
                    <a href="?campaign=<?= htmlspecialchars($campaign['slug']) ?>" 
                       class="group bg-slate-800/40 border border-slate-700 p-8 rounded-3xl hover:border-purple-500/50 hover:bg-slate-800/60 transition duration-300">
                        <div class="flex justify-between items-start mb-6">
                            <div class="bg-purple-600/20 p-3 rounded-2xl text-purple-400 group-hover:scale-110 transition duration-300">
                                <i class="fa-solid fa-<?= $campaign['icon'] ?? 'mask' ?> text-2xl"></i>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-widest bg-slate-900 px-3 py-1 rounded-full text-slate-500 border border-slate-700">
                                <?= $campaign['formType'] ?? 'Event' ?>
                            </span>
                        </div>
                        <h3 class="text-xl font-black text-white mb-2"><?= htmlspecialchars($campaign['title']) ?></h3>
                        <p class="text-sm text-slate-500 mb-6">Suivi des inscriptions et statistiques en temps réel.</p>
                        <div class="flex items-center text-purple-400 font-bold text-sm uppercase tracking-wider">
                            Ouvrir le Board <i class="fa-solid fa-arrow-right ml-2 group-hover:translate-x-2 transition"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="p-8 text-center text-slate-600 text-xs border-t border-slate-900">
        &copy; <?= date('Y') ?> HelloBoard - APEL Saint Joseph. Respect strict du RGPD : aucune donnée personnelle stockée localement.
    </footer>

</body>
</html>