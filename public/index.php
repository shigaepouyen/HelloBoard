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
    <title>HelloBoard — Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #ffffff; color: #000; }
        .campaign-link { border-bottom: 1px solid #f1f5f9; transition: all 0.2s; }
        .campaign-link:hover { background-color: #f8fafc; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav class="p-8 border-b border-slate-100 sticky top-0 bg-white/80 backdrop-blur-md z-50">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <img src="assets/img/logo.svg" alt="Logo" class="w-6 h-6" onerror="this.style.display='none'">
                <span class="text-sm font-bold tracking-tighter uppercase">HelloBoard <span class="text-slate-300 ml-2">/ Analytics</span></span>
            </div>
            <a href="admin.php" class="text-[10px] font-bold uppercase tracking-widest text-slate-400 hover:text-black transition">Configuration</a>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-8 py-24 flex-1 w-full">
        <div class="mb-24">
            <h1 class="text-4xl font-extrabold tracking-tight mb-4">Tableaux de bord</h1>
            <p class="text-slate-500 font-medium">Sélectionnez une campagne active pour visualiser les données en temps réel.</p>
        </div>

        <?php if (empty($campaigns)): ?>
            <div class="py-20 text-center border-2 border-dashed border-slate-100 rounded-2xl">
                <p class="text-slate-400 font-medium mb-6">Aucune campagne configurée pour le moment.</p>
                <a href="admin.php?action=new" class="text-xs font-bold uppercase tracking-widest underline decoration-slate-200 underline-offset-8 hover:decoration-black transition">Initialiser un board</a>
            </div>
        <?php else: ?>
            <div class="flex flex-col">
                <?php foreach ($campaigns as $campaign): ?>
                    <a href="?campaign=<?= htmlspecialchars($campaign['slug']) ?>" 
                       class="campaign-link flex items-center justify-between py-8 group">
                        <div class="flex items-center gap-6">
                            <span class="text-[10px] font-bold text-slate-300 group-hover:text-black transition">0<?= array_search($campaign, $campaigns) + 1 ?></span>
                            <div>
                                <h3 class="text-xl font-bold tracking-tight group-hover:translate-x-1 transition-transform"><?= htmlspecialchars($campaign['title']) ?></h3>
                                <p class="text-xs text-slate-400 font-medium mt-1 uppercase tracking-widest"><?= $campaign['formType'] ?? 'Event' ?></p>
                            </div>
                        </div>
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                            <i class="fa-solid fa-arrow-right text-sm"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="p-12 text-center text-slate-300 text-[10px] font-bold uppercase tracking-[0.3em]">
        APEL Saint Joseph &copy; <?= date('Y') ?>
    </footer>

</body>
</html>