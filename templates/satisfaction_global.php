<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reporting Satisfaction — HelloBoard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
        .admin-card { background: white; border-radius: 2rem; border: 1px solid #edf2f7; }
        .animate-fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="pb-32">
    <nav class="p-4 md:p-6 bg-white border-b border-slate-100 sticky top-0 z-50 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-4">
            <a href="admin.php" class="flex items-center gap-2">
                <img src="assets/img/logo.svg" alt="HelloBoard" class="w-8 h-8">
                <h1 class="font-black italic uppercase text-slate-900 hidden md:block">Console Admin</h1>
            </a>
        </div>
        <div class="flex items-center gap-6">
            <a href="admin.php" class="text-xs font-black uppercase tracking-widest text-slate-400">Boards</a>
            <a href="admin.php?action=satisfaction_global" class="text-xs font-black uppercase tracking-widest text-blue-600">Satisfaction</a>
            <a href="admin.php?action=settings" class="text-xs font-black uppercase tracking-widest text-slate-400">Réglages</a>
            <div class="h-6 w-px bg-slate-200"></div>
            <a href="index.php" class="text-xs font-black uppercase text-slate-400 hover:text-red-500 transition">Quitter</a>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-12">
        <?php
        $questions = null;
        if ($filterSlug) {
            $currentCamp = null;
            foreach($localCampaigns as $lc) {
                if ($lc['slug'] === $filterSlug) {
                    $currentCamp = $lc;
                    break;
                }
            }
            if ($currentCamp) {
                $questions = $satService->getQuestions($filterSlug, $currentCamp['formType']);
            }
        }
        $campaignsQuestions = [];
        foreach ($localCampaigns as $lc) {
            $campaignsQuestions[$lc['slug']] = $satService->getQuestions($lc['slug'], $lc['formType']);
        }

        $genericLabels = [
            'Interaction',
            'Processus',
            'Attentes',
            'Fidélité',
            'Qualité'
        ];
        ?>

        <div class="animate-fade-in">
            <div class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-end gap-6">
                <div>
                    <h2 class="text-3xl font-black italic uppercase text-slate-900">Module Satisfaction</h2>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Indicateurs de satisfaction et retours clients</p>
                </div>
                <div class="flex flex-col md:flex-row items-center gap-4 w-full md:w-auto">
                    <form method="GET" class="flex items-center gap-2 w-full md:w-auto">
                        <input type="hidden" name="action" value="satisfaction_global">
                        <select name="campaign_filter" onchange="this.form.submit()" class="bg-white border border-slate-200 text-slate-600 px-4 py-3 rounded-xl font-bold text-xs uppercase outline-none focus:border-blue-500 shadow-sm w-full md:w-64">
                            <option value="">Toutes les campagnes</option>
                            <?php foreach($localCampaigns as $lc): ?>
                                <option value="<?= $lc['slug'] ?>" <?= ($filterSlug === $lc['slug']) ? 'selected' : '' ?>><?= htmlspecialchars($lc['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php if ($filterSlug): ?>
                        <button onclick="analyzeSatisfaction()" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-indigo-700 transition shadow-lg shadow-indigo-100 flex items-center gap-2">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Analyse IA
                        </button>
                    <?php endif; ?>
                    <a href="admin.php" class="text-xs font-black text-slate-400 uppercase hover:text-slate-900 transition">Retour</a>
                </div>
            </div>

            <!-- AI ANALYSIS -->
            <div id="ai-analysis-container" class="hidden mb-12 animate-fade-in">
                <div class="admin-card p-8 border-2 border-indigo-50 bg-indigo-50/10">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-indigo-600 text-white rounded-xl flex items-center justify-center shadow-lg shadow-indigo-200">
                            <i class="fa-solid fa-robot text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-xs font-black uppercase text-indigo-600 italic tracking-widest">Analyse Intelligente</h3>
                            <p class="text-[10px] text-slate-400 font-bold uppercase mt-0.5">Expert en Expérience Client (Mistral AI)</p>
                        </div>
                    </div>
                    <div id="ai-analysis-content" class="text-slate-700 text-sm leading-relaxed whitespace-pre-wrap">
                        <!-- Content will be injected here -->
                    </div>
                </div>
            </div>

            <!-- KPI CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
                <div class="admin-card p-8 flex flex-col justify-center items-center text-center">
                    <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-2 italic">Score CSAT Global</p>
                    <h3 class="text-5xl font-black <?= $stats['avg_csat'] >= 75 ? 'text-emerald-500' : ($stats['avg_csat'] >= 50 ? 'text-amber-500' : 'text-red-500') ?>">
                        <?= round($stats['avg_csat'] ?? 0) ?>%
                    </h3>
                </div>
                <div class="admin-card p-8 flex flex-col justify-center items-center text-center">
                    <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-2 italic">Réponses collectées</p>
                    <h3 class="text-5xl font-black text-slate-900"><?= $stats['total_responses'] ?></h3>
                </div>

                <?php
                $labelsMap = [
                    'Event' => 'Événements',
                    'Shop' => 'Boutique',
                    'Checkout' => 'Boutique',
                    'Membership' => 'Adhésions',
                    'Donation' => 'Dons'
                ];
                foreach ($statsBySource as $type => $data):
                    $label = $labelsMap[$type] ?? $type;
                ?>
                <div class="admin-card p-8 flex flex-col justify-center items-center text-center">
                    <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-2 italic"><?= $label ?></p>
                    <div class="flex items-baseline gap-2">
                        <h3 class="text-3xl font-black text-slate-900"><?= round($data['avg']) ?>%</h3>
                        <span class="text-[10px] text-slate-300 font-bold">(<?= $data['count'] ?>)</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- STATS PAR QUESTION -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-12">
                <?php for($i=1; $i<=5; $i++):
                    $avg = $stats['avg_q'.$i] ?? 0;
                    $label = ($questions && isset($questions[$i-1])) ? $questions[$i-1]['label'] : $genericLabels[$i-1];
                ?>
                    <div class="admin-card p-6 flex flex-col justify-center items-center text-center">
                        <p class="text-slate-400 text-[9px] font-black uppercase tracking-widest mb-2 italic line-clamp-2 h-6" title="<?= htmlspecialchars($label) ?>">
                            <?= htmlspecialchars($label) ?>
                        </p>
                        <h3 class="text-2xl font-black text-slate-900"><?= $avg ? number_format($avg, 1) : '-' ?><span class="text-xs text-slate-300 ml-1">/5</span></h3>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-3 overflow-hidden">
                            <div class="bg-blue-600 h-full rounded-full transition-all duration-500" style="width: <?= ($avg/5)*100 ?>%"></div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- RESPONSES FEED -->
            <div class="admin-card overflow-hidden">
                <div class="p-8 bg-slate-50 border-b border-slate-100">
                    <h3 class="text-xs font-black uppercase text-slate-400 italic tracking-widest">Flux des Verbatims</h3>
                </div>
                <div class="divide-y divide-slate-50">
                    <?php if (empty($responses)): ?>
                        <div class="p-20 text-center text-slate-300 font-bold italic">Aucun avis trouvé pour cette sélection.</div>
                    <?php else: foreach ($responses as $r):
                        $sum = 0; $countQ = 0;
                        for($i=1; $i<=5; $i++) {
                            if (isset($r['q'.$i]) && $r['q'.$i] !== null) {
                                $sum += (int)$r['q'.$i];
                                $countQ++;
                            }
                        }
                        $avg = ($countQ > 0) ? ($sum - $countQ) / ($countQ * 4.0) * 100.0 : 0;
                    ?>
                        <div class="p-8 hover:bg-slate-50 transition group">
                            <div class="flex flex-col md:flex-row justify-between gap-6">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h4 class="font-black text-slate-800">Avis de <?= htmlspecialchars($r['payer_name']) ?></h4>
                                        <span class="text-[9px] font-black px-2 py-0.5 rounded bg-slate-100 text-slate-400 uppercase"><?= htmlspecialchars($r['email']) ?></span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase mb-4 italic">
                                        <i class="fa-solid fa-shopping-bag mr-1"></i> <?= htmlspecialchars($r['item_name']) ?>
                                    </p>
                                    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm italic text-slate-600 leading-relaxed">
                                        "<?= htmlspecialchars($r['comment']) ?: 'Pas de commentaire' ?>."
                                    </div>
                                    <!-- Détails des réponses -->
                                    <div id="details-<?= $r['token'] ?>" class="hidden mt-4 p-4 bg-slate-50 rounded-2xl border border-slate-100 animate-fade-in">
                                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                            <?php
                                            $qLabels = $campaignsQuestions[$r['campaign_slug']] ?? $genericLabels;
                                            for($i=1; $i<=5; $i++):
                                                $score = $r['q'.$i];
                                                $qItem = $qLabels[$i-1] ?? $genericLabels[$i-1];
                                                $label = is_array($qItem) ? ($qItem['label'] ?? '') : $qItem;
                                            ?>
                                                <div class="flex flex-col items-center text-center">
                                                    <p class="text-[8px] font-black uppercase text-slate-400 mb-1 h-8 line-clamp-2" title="<?= htmlspecialchars($label) ?>"><?= htmlspecialchars($label) ?></p>
                                                    <div class="flex items-center gap-1">
                                                        <span class="text-sm font-black text-slate-700"><?= $score ?: '-' ?></span>
                                                        <i class="fa-solid fa-star text-[10px] <?= $score ? 'text-amber-400' : 'text-slate-200' ?>"></i>
                                                    </div>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="w-full md:w-48 flex flex-row md:flex-col items-center md:items-end justify-between md:justify-center gap-2">
                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <div class="text-3xl font-black <?= $avg >= 75 ? 'text-emerald-500' : ($avg >= 50 ? 'text-amber-500' : 'text-red-500') ?>">
                                                <?= round($avg) ?>%
                                            </div>
                                            <div class="flex text-[8px] gap-0.5 justify-end">
                                                <?php
                                                $starRating = ($countQ > 0) ? $sum / $countQ : 0;
                                                for($i=1;$i<=5;$i++):
                                                ?>
                                                    <i class="fa-solid fa-star <?= $starRating >= $i ? 'text-amber-400' : 'text-slate-200' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button onclick="toggleDetails('<?= $r['token'] ?>')" class="w-10 h-10 flex items-center justify-center bg-blue-50 text-blue-400 rounded-xl hover:bg-blue-600 hover:text-white transition shadow-sm" title="Détails des réponses">
                                                <i class="fa-solid fa-info text-xs"></i>
                                            </button>
                                            <a href="admin.php?action=satisfaction_global&delete=<?= $r['token'] ?><?= $filterSlug ? '&campaign_filter='.$filterSlug : '' ?>" onclick="return confirm('Supprimer cette participation ?')" class="w-10 h-10 flex items-center justify-center bg-red-50 text-red-300 rounded-xl hover:bg-red-500 hover:text-white transition shadow-sm">
                                                <i class="fa-solid fa-trash-can text-xs"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <p class="text-[9px] text-slate-300 font-black uppercase mt-2"><?= date('d/m/Y à H:i', strtotime($r['submitted_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
    function toggleDetails(token) {
        const el = document.getElementById('details-' + token);
        if (el) {
            el.classList.toggle('hidden');
        }
    }

    function showLoader() {
        const loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.innerHTML = `
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] flex items-center justify-center">
                <div class="bg-white p-8 rounded-[2rem] text-center shadow-2xl animate-fade-in">
                    <div class="w-16 h-16 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                    <p class="font-black uppercase text-xs tracking-widest text-slate-900">Analyse en cours...</p>
                    <p class="text-[10px] text-slate-400 font-bold uppercase mt-2">L'IA parcourt vos retours, un instant</p>
                </div>
            </div>
        `;
        document.body.appendChild(loader);
    }

    async function analyzeSatisfaction() {
        const container = document.getElementById('ai-analysis-container');
        const content = document.getElementById('ai-analysis-content');
        const campaignSlug = '<?= $filterSlug ?>';

        if (!campaignSlug) return;

        showLoader();
        container.classList.add('hidden');

        try {
            const formData = new FormData();
            formData.append('campaign', campaignSlug);

            const res = await fetch('admin.php?action=ai_analyze_satisfaction', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                content.innerText = data.analysis;
                container.classList.remove('hidden');
                container.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                alert("Erreur : " + data.error);
            }
        } catch (e) {
            alert("Erreur lors de l'analyse IA.");
        } finally {
            const loader = document.getElementById('global-loader');
            if (loader) loader.remove();
        }
    }
    </script>
</body>
</html>
