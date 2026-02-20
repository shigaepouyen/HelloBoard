<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reporting Satisfaction — HelloBoard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
        .admin-card { background: white; border-radius: 2rem; border: 1px solid #edf2f7; }
        .animate-fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Styles pour le rendu Markdown de l'IA */
        .prose-ai h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 1rem; color: #1e1b4b; margin-top: 1.5rem; }
        .prose-ai h2 { font-size: 1.25rem; font-weight: 800; margin-top: 1.5rem; margin-bottom: 0.75rem; color: #1e1b4b; }
        .prose-ai h3 { font-size: 1.125rem; font-weight: 800; margin-top: 1.25rem; margin-bottom: 0.5rem; color: #1e1b4b; }
        .prose-ai h4 { font-size: 1rem; font-weight: 800; margin-top: 1rem; margin-bottom: 0.5rem; color: #1e1b4b; }
        .prose-ai p { margin-bottom: 1rem; }
        .prose-ai ul { list-style-type: disc; margin-left: 1.5rem; margin-bottom: 1rem; }
        .prose-ai li { margin-bottom: 0.25rem; }
        .prose-ai strong { font-weight: 800; color: #312e81; }
        .prose-ai em { font-style: italic; }
        .prose-ai blockquote { border-left: 4px solid #e0e7ff; padding-left: 1rem; font-style: italic; margin-bottom: 1rem; color: #4338ca; }

        @media print {
            @page { margin: 1cm; }

            /* Masquer les éléments inutiles */
            nav, .mb-10 > div:last-child, button, .text-right a, .fa-trash-can, #global-loader,
            #ai-modal .fixed.inset-0.bg-slate-900\/60,
            #ai-modal .p-8.border-b button,
            #ai-modal .p-6.bg-slate-50,
            .divide-y .flex.gap-2 {
                display: none !important;
            }

            body { background: white !important; padding: 0 !important; margin: 0 !important; }
            .max-w-6xl { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
            main { display: flex !important; flex-direction: column !important; }
            .animate-fade-in { animation: none !important; transform: none !important; display: flex !important; flex-direction: column !important; }

            /* Section Analyse IA - En haut lors de l'impression */
            .mb-10 { order: -2 !important; }
            #ai-modal {
                display: block !important;
                position: static !important;
                visibility: visible !important;
                opacity: 1 !important;
                inset: auto !important;
                width: 100% !important;
                max-width: none !important;
                max-height: none !important;
                overflow: visible !important;
                box-shadow: none !important;
                z-index: auto !important;
                order: -1 !important;
                break-after: page !important;
                page-break-after: always !important;
            }
            #ai-modal:not(.is-ready-for-print) { display: none !important; }

            #ai-modal .bg-white {
                border: none !important;
                margin-bottom: 0 !important;
                box-shadow: none !important;
                max-height: none !important;
                overflow: visible !important;
                display: block !important;
            }
            #ai-modal .p-8.border-b { border: none !important; padding-left: 0 !important; padding-right: 0 !important; }
            #ai-modal .flex-1 { padding: 0 !important; overflow: visible !important; }
            #ai-modal-content { font-size: 11pt !important; line-height: 1.6 !important; color: black !important; }

            /* Grid layout fixes for print */
            .grid { display: grid !important; gap: 1rem !important; }
            .md\:grid-cols-2 { grid-template-columns: repeat(2, 1fr) !important; }
            .md\:grid-cols-4 { grid-template-columns: repeat(4, 1fr) !important; }
            .md\:grid-cols-3 { grid-template-columns: repeat(3, 1fr) !important; }
            .lg\:grid-cols-4 { grid-template-columns: repeat(4, 1fr) !important; }
            .lg\:grid-cols-5 { grid-template-columns: repeat(5, 1fr) !important; }

            /* Missing Tailwind Print Utilities */
            .print\:hidden { display: none !important; }
            .print\:flex { display: flex !important; }
            .print\:inline { display: inline !important; }
            .print\:flex-1 { flex: 1 1 0% !important; }
            .print\:shrink-0 { flex-shrink: 0 !important; }
            .print\:w-\[45\%\] { width: 45% !important; }
            .print\:text-sm { font-size: 0.875rem !important; }
            .print\:mb-0 { margin-bottom: 0 !important; }
            .print\:mb-1 { margin-bottom: 0.25rem !important; }
            .print\:mb-2 { margin-bottom: 0.5rem !important; }
            .print\:mt-0 { margin-top: 0 !important; }
            .print\:mt-4 { margin-top: 1rem !important; }
            .print\:p-0 { padding: 0 !important; }
            .print\:px-6 { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
            .print\:py-4 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
            .print\:pl-6 { padding-left: 1.5rem !important; }
            .print\:gap-2 { gap: 0.5rem !important; }
            .print\:gap-4 { gap: 1rem !important; }
            .print\:gap-8 { gap: 2rem !important; }
            .print\:gap-x-8 { column-gap: 2rem !important; }
            .print\:gap-y-2 { row-gap: 0.5rem !important; }
            .print\:flex-row { flex-direction: row !important; }
            .print\:text-left { text-align: left !important; }
            .print\:h-auto { height: auto !important; }
            .print\:line-clamp-none { -webkit-line-clamp: unset !important; }
            .print\:border-none { border-style: none !important; }
            .print\:shadow-none { box-shadow: none !important; }
            .print\:bg-transparent { background-color: transparent !important; }
            .print\:border-slate-100 { border-color: #f1f5f9 !important; }
            .print\:grid-cols-1 { grid-template-columns: repeat(1, 1fr) !important; }
            .print\:grid-cols-2 { grid-template-columns: repeat(2, 1fr) !important; }
            .print\:grid-cols-5 { grid-template-columns: repeat(5, 1fr) !important; }

            /* Verbatims */
            .admin-card { border: 1px solid #eee !important; border-radius: 2rem !important; padding: 2rem !important; }
            .grid .admin-card { break-inside: avoid; } /* Avoid breaking KPI cards */
            .divide-y > div { break-inside: avoid; padding: 1.5rem !important; border-bottom: 1px solid #eee !important; }
            [id^="details-"] { display: block !important; }
            .p-8 { padding: 1.5rem !important; }
            .print\:p-8 { padding: 2rem !important; }
            .print\:p-4 { padding: 1rem !important; }

            /* Expert Print Style */
            [id^="details-"] { display: block !important; margin-top: 0 !important; background: transparent !important; border: none !important; }
            [id^="details-"] p { font-size: 8pt !important; line-height: 1.2 !important; height: auto !important; margin-bottom: 0 !important; text-transform: none !important; letter-spacing: normal !important; font-weight: 600 !important; color: #475569 !important; text-align: left !important; }
            [id^="details-"] .fa-star { font-size: 7pt !important; }
            [id^="details-"] span { font-size: 9pt !important; }
            [id^="details-"] .grid { gap: 0.5rem 1.5rem !important; }

            .print\:border-l-4 { border-left: 4px solid #f1f5f9 !important; }
            .print\:italic { font-style: italic !important; }
            .print\:text-slate-600 { color: #475569 !important; }
            .print\:leading-relaxed { line-height: 1.6 !important; }

            /* Page breaks */
            h2, h3 { break-after: avoid; }
        }
    </style>
</head>
<body class="pb-32">
    <nav class="p-4 md:p-6 bg-white border-b border-slate-100 sticky top-0 z-50 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-4">
            <a href="admin.php" class="flex items-center gap-2">
                <img src="<?= $globals['customLogo'] ?? 'assets/img/logo.svg' ?>" alt="HelloBoard" class="w-8 h-8 object-contain">
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
                        <button onclick="printSatisfaction()" class="bg-slate-800 text-white px-6 py-3 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-slate-900 transition shadow-lg flex items-center gap-2">
                            <i class="fa-solid fa-print"></i> Imprimer
                        </button>
                    <?php endif; ?>
                    <a href="admin.php" class="text-xs font-black text-slate-400 uppercase hover:text-slate-900 transition">Retour</a>
                </div>
            </div>

    <!-- AI ANALYSIS MODAL -->
    <div id="ai-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeAiModal()"></div>
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden relative z-10 flex flex-col animate-fade-in">
            <div class="p-8 border-b border-slate-100 flex justify-between items-center bg-indigo-50/30">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-indigo-600 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-200">
                        <i class="fa-solid fa-robot text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-black uppercase text-indigo-600 italic tracking-widest">Rapport d'Analyse Intelligente</h3>
                        <p id="ai-modal-date" class="text-[10px] text-slate-400 font-bold uppercase mt-0.5">Chargement...</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="analyzeSatisfaction(true)" class="bg-white border border-indigo-100 text-indigo-600 px-4 py-2 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-indigo-600 hover:text-white transition flex items-center gap-2">
                        <i class="fa-solid fa-sync-alt"></i> Relancer
                    </button>
                    <button onclick="closeAiModal()" class="w-10 h-10 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-red-500 hover:text-white transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-8 md:p-12">
                <div id="ai-modal-content" class="text-slate-700 text-sm leading-relaxed prose-ai">
                    <!-- Content will be injected here -->
                </div>
            </div>
            <div class="p-6 bg-slate-50 border-t border-slate-100 text-center">
                <p class="text-[9px] text-slate-400 font-bold uppercase italic">Analyse générée par Mistral AI — Expert en Expérience Client</p>
            </div>
        </div>
    </div>

            <!-- KPI CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 print:grid-cols-4 gap-6 mb-12">
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

            <!-- SUIVI PAR CAMPAGNE -->
            <div class="admin-card overflow-hidden mb-12">
                <div class="p-8 bg-slate-50 border-b border-slate-100">
                    <h3 class="text-xs font-black uppercase text-slate-400 italic tracking-widest">Suivi des envois par campagne</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] font-black uppercase text-slate-400 border-b border-slate-50">
                                <th class="p-6">Campagne</th>
                                <th class="p-6 text-center">Envoyés</th>
                                <th class="p-6 text-center">Lus (%)</th>
                                <th class="p-6 text-center">Réponses (%)</th>
                                <th class="p-6 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if (empty($campaignSummary)): ?>
                                <tr><td colspan="5" class="p-10 text-center text-slate-300 font-bold italic">Aucune donnée d'envoi.</td></tr>
                            <?php else: foreach ($campaignSummary as $row):
                                $cTitle = $row['campaign_slug'];
                                foreach($localCampaigns as $lc) if($lc['slug'] === $row['campaign_slug']) $cTitle = $lc['title'];

                                $readPct = $row['total_sent'] > 0 ? round(($row['total_read'] / $row['total_sent']) * 100) : 0;
                                $repliedPct = $row['total_sent'] > 0 ? round(($row['total_replied'] / $row['total_sent']) * 100) : 0;
                            ?>
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="p-6">
                                        <p class="font-black text-slate-800 text-sm"><?= htmlspecialchars($cTitle) ?></p>
                                        <p class="text-[9px] text-slate-300 font-bold uppercase"><?= $row['campaign_slug'] ?></p>
                                    </td>
                                    <td class="p-6 text-center font-black text-slate-600"><?= $row['total_sent'] ?></td>
                                    <td class="p-6 text-center">
                                        <span class="font-black text-blue-600"><?= $readPct ?>%</span>
                                        <p class="text-[8px] text-slate-300 font-bold uppercase"><?= $row['total_read'] ?> ouvertures</p>
                                    </td>
                                    <td class="p-6 text-center">
                                        <span class="font-black text-emerald-600"><?= $repliedPct ?>%</span>
                                        <p class="text-[8px] text-slate-300 font-bold uppercase"><?= $row['total_replied'] ?> réponses</p>
                                    </td>
                                    <td class="p-6 text-right">
                                        <a href="admin.php?action=satisfaction&campaign=<?= $row['campaign_slug'] ?>" class="text-[10px] font-black uppercase text-blue-500 hover:underline">Gérer</a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- STATS PAR QUESTION -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 print:grid-cols-5 gap-4 mb-12">
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
                        <div class="p-8 hover:bg-slate-50 transition group print:p-8">
                            <div class="flex flex-col md:flex-row justify-between gap-6 print:gap-2">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between md:justify-start gap-3 mb-2 print:mb-1">
                                        <?php
                                            $nameParts = explode(' ', trim($r['payer_name']));
                                            $fName = $nameParts[0] ?? '';
                                            $lInitial = isset($nameParts[1]) ? ' ' . mb_substr($nameParts[1], 0, 1) . '.' : '';
                                            $anonymizedName = $fName . $lInitial;
                                        ?>
                                        <div class="flex items-center gap-3">
                                            <h4 class="font-black text-slate-800">
                                                <span class="print:hidden">Avis de <?= htmlspecialchars($r['payer_name']) ?></span>
                                                <span class="hidden print:inline text-sm">Avis de <?= htmlspecialchars($anonymizedName) ?></span>
                                            </h4>
                                            <span class="text-[9px] font-black px-2 py-0.5 rounded bg-slate-100 text-slate-400 uppercase print:hidden"><?= htmlspecialchars($r['email']) ?></span>
                                        </div>
                                        <div class="hidden print:flex items-center gap-4">
                                            <span class="text-xl font-black <?= $avg >= 75 ? 'text-emerald-500' : ($avg >= 50 ? 'text-amber-500' : 'text-red-500') ?>"><?= round($avg) ?>%</span>
                                            <span class="text-[9px] text-slate-300 font-black uppercase"><?= date('d/m/Y', strtotime($r['submitted_at'])) ?></span>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase mb-4 italic print:mb-2 print:text-[8px]">
                                        <i class="fa-solid fa-shopping-bag mr-1"></i> <?= htmlspecialchars($r['item_name']) ?>
                                    </p>

                                    <?php
                                        $hasComment = isset($r['comment']) && $r['comment'] !== '';
                                        $qLabels = $campaignsQuestions[$r['campaign_slug']] ?? $genericLabels;
                                    ?>

                                    <div class="print:flex print:gap-8 print:items-start print:mt-4">
                                        <?php if ($hasComment): ?>
                                            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm italic text-slate-600 leading-relaxed print:p-4 print:border-none print:shadow-none print:bg-transparent print:flex-1 print:border-l-4 print:border-slate-100 print:pl-6 print:italic print:text-slate-600 print:leading-relaxed print:text-sm">
                                                "<?= htmlspecialchars($r['comment']) ?>."
                                            </div>
                                        <?php endif; ?>

                                        <!-- Détails des réponses -->
                                        <div id="details-<?= $r['token'] ?>" class="hidden mt-4 p-4 bg-slate-50 rounded-2xl border border-slate-100 animate-fade-in print:mt-0 print:p-4 print:bg-transparent print:border-none <?= $hasComment ? 'print:w-[45%] print:shrink-0' : 'print:w-full' ?>">
                                            <div class="grid grid-cols-1 md:grid-cols-5 <?= $hasComment ? 'print:grid-cols-1' : 'print:grid-cols-2' ?> gap-4 print:gap-x-8 print:gap-y-2">
                                                <?php
                                                for($i=1; $i<=5; $i++):
                                                    $score = $r['q'.$i];
                                                    $qItem = $qLabels[$i-1] ?? $genericLabels[$i-1];
                                                    $label = is_array($qItem) ? ($qItem['label'] ?? '') : $qItem;
                                                ?>
                                                    <div class="flex flex-col items-center text-center print:flex-row print:text-left print:gap-2">
                                                        <div class="hidden print:flex items-center gap-1 w-7 shrink-0">
                                                            <span class="font-black text-slate-700"><?= $score ?: '-' ?></span>
                                                            <i class="fa-solid fa-star text-amber-400 <?= $score ? '' : 'opacity-20' ?>"></i>
                                                        </div>
                                                        <p class="text-[8px] font-black uppercase text-slate-400 mb-1 h-8 line-clamp-2 print:h-auto print:line-clamp-none print:mb-0" title="<?= htmlspecialchars($label) ?>"><?= htmlspecialchars($label) ?></p>
                                                        <div class="flex items-center gap-1 print:hidden">
                                                            <span class="text-sm font-black text-slate-700"><?= $score ?: '-' ?></span>
                                                            <i class="fa-solid fa-star text-[10px] <?= $score ? 'text-amber-400' : 'text-slate-200' ?>"></i>
                                                        </div>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="w-full md:w-48 flex flex-row md:flex-col items-center md:items-end justify-between md:justify-center gap-2 print:hidden">
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

    function closeAiModal() {
        document.getElementById('ai-modal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    async function printSatisfaction() {
        const campaignSlug = '<?= $filterSlug ?>';
        if (!campaignSlug) return;

        const content = document.getElementById('ai-modal-content');
        const modal = document.getElementById('ai-modal');

        // Si l'analyse n'est pas chargée ou vide
        if (!content.innerText.trim() || content.innerText === 'Chargement...') {
            await analyzeSatisfaction();
        }

        // On marque le modal comme prêt pour l'impression (pour le CSS)
        modal.classList.add('is-ready-for-print');

        // Petit délai pour s'assurer que le rendu est fini
        setTimeout(() => {
            window.print();
            // On nettoie après l'impression (certains navigateurs bloquent le JS pendant l'impression)
            modal.classList.remove('is-ready-for-print');
        }, 500);
    }

    async function analyzeSatisfaction(refresh = false) {
        const modal = document.getElementById('ai-modal');
        const content = document.getElementById('ai-modal-content');
        const dateEl = document.getElementById('ai-modal-date');
        const campaignSlug = '<?= $filterSlug ?>';

        if (!campaignSlug) return;

        showLoader();

        try {
            const formData = new FormData();
            formData.append('campaign', campaignSlug);
            if (refresh) formData.append('refresh', '1');

            const res = await fetch('admin.php?action=ai_analyze_satisfaction', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                content.innerHTML = marked.parse(data.analysis);
                if (data.updated_at) {
                    const date = new Date(data.updated_at.replace(' ', 'T'));
                    dateEl.innerText = "Dernière analyse : " + date.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                }
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
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
