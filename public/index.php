<?php
require_once __DIR__ . '/../src/Services/Storage.php';

$campaigns = Storage::listCampaigns();
$currentCampaignId = $_GET['campaign'] ?? null;

if ($currentCampaignId) {
    $campaignConfig = null;
    foreach ($campaigns as $c) {
        if ($c['slug'] === $currentCampaignId) { $campaignConfig = $c; break; }
    }
    if ($campaignConfig) { include __DIR__ . '/../templates/dashboard.php'; exit; }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelloBoard — APEL Saint Joseph</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #0f172a; }
        .hero-title {
            letter-spacing: -0.05em;
            background: linear-gradient(135deg, #1e293b 0%, #475569 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav class="py-6 px-8 bg-white/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <img src="assets/img/logo.svg" alt="Logo" class="w-8 h-8" onerror="this.innerHTML='<i class=\'fa-solid fa-chart-simple text-blue-600 text-xl\'></i>'; this.type='icon';">
                <span class="text-lg font-extrabold tracking-tight text-slate-900">HelloBoard</span>
            </div>
            <a href="admin.php" class="bg-slate-900 text-white px-5 py-2.5 rounded-xl text-xs font-bold hover:bg-slate-800 transition">
                Console Administration
            </a>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-6 py-20 flex-1 w-full">
        <div class="mb-20">
            <h1 class="text-6xl font-extrabold hero-title mb-4">Tableaux de Bord</h1>
            <p class="text-slate-500 text-lg max-w-2xl">
                Visualisez les inscriptions et les revenus de vos événements HelloAsso en temps réel.
            </p>
        </div>

        <?php if (empty($campaigns)): ?>
            <div class="bg-white rounded-[2rem] p-16 text-center border border-slate-200 shadow-sm">
                <div class="w-20 h-20 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-8 border border-slate-100">
                    <i class="fa-solid fa-folder-plus text-slate-300 text-3xl"></i>
                </div>
                <h2 class="text-xl font-extrabold mb-3">Aucune campagne configurée</h2>
                <p class="text-slate-500 mb-8">Rendez-vous dans l'administration pour scanner votre compte HelloAsso.</p>
                <a href="admin.php?action=new" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-10 rounded-xl transition shadow-lg shadow-blue-100">
                    Ajouter une campagne <i class="fa-solid fa-plus"></i>
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($campaigns as $campaign): ?>
                    <a href="?campaign=<?= htmlspecialchars($campaign['slug']) ?>" 
                       class="group bg-white border border-slate-200 p-8 rounded-[1.5rem] shadow-sm hover:shadow-xl hover:border-blue-200 transition-all duration-300">
                        
                        <div class="flex justify-between items-start mb-12">
                            <div class="bg-slate-50 p-4 rounded-xl text-slate-400 border border-slate-100 group-hover:bg-blue-50 group-hover:text-blue-600 transition duration-300">
                                <i class="fa-solid fa-<?= $campaign['icon'] ?? 'calendar' ?> text-xl"></i>
                            </div>
                            <span class="text-[9px] font-extrabold uppercase tracking-widest text-slate-400 bg-slate-100 px-3 py-1 rounded-full">
                                <?= $campaign['formType'] ?? 'Event' ?>
                            </span>
                        </div>
                        
                        <h3 class="text-xl font-extrabold text-slate-900 mb-2 group-hover:text-blue-600 transition"><?= htmlspecialchars($campaign['title']) ?></h3>
                        <p class="text-sm text-slate-400 mb-8 font-medium">Analyses live & KPIs financiers.</p>
                        
                        <div class="flex items-center gap-2 text-blue-600 font-bold text-xs uppercase tracking-wider">
                            Ouvrir le board <i class="fa-solid fa-arrow-right-long transition-all group-hover:translate-x-2"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="p-12 text-center bg-white border-t border-slate-200 mt-20">
        <p class="text-xs text-slate-400 font-bold uppercase tracking-[0.3em] mb-3">HelloBoard &middot; APEL Saint Joseph</p>
        <p class="text-[10px] text-slate-300 italic font-medium">Propulsé par le moteur de synchronisation HelloAsso V5</p>
    </footer>

</body>
</html>