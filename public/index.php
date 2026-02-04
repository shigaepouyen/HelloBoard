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
    <title>HelloBoard — APEL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f0f4f8; color: #1e293b; }
        .hero-title { font-size: 3.5rem; font-weight: 800; letter-spacing: -0.05em; line-height: 1; color: #0f172a; }
        .campaign-card { background: white; border-radius: 2.5rem; border: 1px solid #edf2f7; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .campaign-card:hover { transform: translateY(-10px); box-shadow: 0 30px 40px -10px rgba(0,0,0,0.05); border-color: #2563eb; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav class="p-8 sticky top-0 bg-white/50 backdrop-blur-xl z-50">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-200">
                    <img src="assets/img/logo.svg" alt="Logo" class="w-6 h-6 brightness-0 invert" onerror="this.style.display='none'">
                </div>
                <span class="text-sm font-black uppercase tracking-widest italic">HelloBoard</span>
            </div>
            <a href="admin.php" class="bg-white px-5 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest border border-slate-200 shadow-sm">Réglages</a>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-6 py-20 flex-1 w-full">
        <div class="mb-20">
            <h1 class="hero-title mb-6">Vos Tableaux<br>de Bord</h1>
            <p class="text-slate-400 font-bold text-lg max-w-lg">Sélectionnez un événement pour voir les chiffres en direct.</p>
        </div>

        <?php if (empty($campaigns)): ?>
            <div class="bg-white rounded-[3rem] p-20 text-center border border-slate-100 shadow-sm">
                <div class="w-24 h-24 bg-slate-50 rounded-[2rem] flex items-center justify-center mx-auto mb-8 text-slate-200">
                    <i class="fa-solid fa-folder-open text-4xl"></i>
                </div>
                <h2 class="text-2xl font-black mb-4">Aucun board configuré</h2>
                <p class="text-slate-400 mb-10 font-bold">Rendez-vous dans les réglages pour commencer.</p>
                <a href="admin.php?action=new" class="bg-blue-600 text-white px-10 py-5 rounded-[2rem] font-black shadow-xl shadow-blue-100 uppercase tracking-widest text-xs">Scanner HelloAsso</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($campaigns as $campaign): ?>
                    <a href="?campaign=<?= htmlspecialchars($campaign['slug']) ?>" 
                       class="campaign-card group p-10 flex flex-col justify-between min-h-[300px]">
                        <div>
                            <div class="flex justify-between items-start mb-12">
                                <div class="w-16 h-16 bg-slate-50 rounded-3xl flex items-center justify-center text-slate-300 group-hover:bg-blue-50 group-hover:text-blue-500 transition duration-300">
                                    <i class="fa-solid fa-<?= $campaign['icon'] ?? 'star' ?> text-2xl"></i>
                                </div>
                                <span class="text-[9px] font-black uppercase tracking-widest bg-slate-100 px-3 py-1 rounded-full text-slate-400">
                                    <?= $campaign['formType'] ?? 'Event' ?>
                                </span>
                            </div>
                            <h3 class="text-2xl font-black text-slate-900 leading-tight mb-2 group-hover:text-blue-600 transition"><?= htmlspecialchars($campaign['title']) ?></h3>
                            <p class="text-sm font-bold text-slate-400">Analyse live & KPIs.</p>
                        </div>
                        <div class="flex items-center gap-2 text-blue-600 font-black text-[10px] uppercase tracking-widest mt-8">
                            Ouvrir le board <i class="fa-solid fa-arrow-right-long group-hover:translate-x-2 transition"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="p-12 text-center border-t border-slate-100 mt-20">
        <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.4em] italic">HelloBoard &middot; APEL Saint Joseph</p>
    </footer>

</body>
</html>