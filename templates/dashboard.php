<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($campaignConfig['title']) ?> — HelloBoard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f1f5f9; color: #1e293b; -webkit-font-smoothing: antialiased; }
        .sexy-card { background: #ffffff; border-radius: 2.5rem; border: 1px solid rgba(255,255,255,0.7); box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.06); overflow: hidden; }
        .kpi-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; }
        .kpi-value { font-size: 3.5rem; font-weight: 800; color: #0f172a; line-height: 1; letter-spacing: -0.05em; }
        .gauge-track { background: #f1f5f9; height: 12px; border-radius: 20px; overflow: hidden; margin-top: 1.5rem; }
        .gauge-bar { height: 100%; border-radius: 20px; transition: width 1.2s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .reveal { animation: reveal 0.8s forwards; opacity: 0; }
        @keyframes reveal { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .loader-friendly { width: 20px; height: 20px; border: 3px solid rgba(255,255,255,0.3); border-top: 3px solid #fff; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* LOADER GLOBAL */
        #global-loader { transition: opacity 0.5s cubic-bezier(0.4, 0, 0.2, 1); }
        #global-loader.fade-out { opacity: 0; pointer-events: none; }
        .loader-lg { width: 60px; height: 60px; border-width: 5px; }

        /* HEATMAP RESPONSIVE */
        .grid-heatmap { display: grid; gap: 2px; }
        /* MOBILE : On inverse les axes (Transposition) */
        @media (max-width: 768px) {
            .grid-heatmap { grid-template-rows: repeat(25, auto); grid-template-columns: repeat(8, 1fr); grid-auto-flow: column; }
        }
        /* ACCORDEON MOBILE */
        .mobile-collapse { max-height: 0; overflow: hidden; transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .mobile-collapse.open { max-height: 1000px; } /* Valeur arbitraire suffisante */
        .chevron-mobile { transition: transform 0.3s; }
        .chevron-mobile.rotate { transform: rotate(180deg); }

        /* SUR PC : On force l'ouverture et on cache les flèches */
        @media (min-width: 768px) {
            .grid-heatmap { grid-template-columns: repeat(25, 1fr); }
            .mobile-collapse { max-height: none !important; overflow: visible !important; }
            .chevron-mobile { display: none !important; }
            .cursor-mobile-pointer { cursor: default !important; }
        }

        /* MODALE ACTIVITÉS */
        #recent-modal.active { display: flex; }
        .modal-content { animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .search-input { background: #f1f5f9; border: 2px solid transparent; border-radius: 1.25rem; padding: 14px 20px; width: 100%; font-weight: 600; font-size: 0.9rem; outline: none; transition: all 0.2s; }
        .search-input:focus { background: white; border-color: #2563eb; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen pb-32">

    <div id="global-loader" class="fixed inset-0 z-[100] bg-slate-50/80 backdrop-blur-md flex items-center justify-center">
        <div class="flex flex-col items-center gap-6">
            <div class="loader-friendly loader-lg border-blue-200 border-t-blue-600"></div>
            <div class="text-center">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-blue-600 animate-pulse">Chargement</p>
                <p class="text-[9px] font-bold text-slate-400 mt-2">Récupération des données HelloAsso...</p>
            </div>
        </div>
    </div>

    <header class="p-4 sticky top-0 z-50">
        <div class="max-w-4xl mx-auto bg-white/80 backdrop-blur-xl rounded-[2.5rem] border border-white px-6 py-4 flex items-center justify-between shadow-sm">
            
            <div class="flex items-center gap-3 relative min-w-0">
                <?php if (count($campaigns) > 1): ?>
                    <div class="relative group max-w-full">
                        <select onchange="window.location.href=this.value" class="appearance-none bg-transparent pl-0 pr-6 py-1 text-sm font-black italic uppercase tracking-tighter cursor-pointer focus:outline-none text-slate-900 hover:text-blue-600 transition truncate max-w-[200px] sm:max-w-md">
                            <?php foreach ($campaigns as $c): 
                                // --- CORRECTION ICI : MASQUER ARCHIVÉS ---
                                if (!empty($c['archived'])) continue; 
                                
                                // Si admin, on utilise le lien propre (session active). 
                                // Si visiteur, on injecte le token du board cible pour l'autoriser.
                                $targetToken = $isAdmin ? null : ($c['shareToken'] ?? null);
                                $url = getCleanUrl($c['slug'], $targetToken);
                                $isSelected = ($c['slug'] === $campaignConfig['slug']);
                            ?>
                                <option value="<?= $url ?>" <?= $isSelected ? 'selected' : '' ?> class="text-slate-700 not-italic font-bold">
                                    <?= htmlspecialchars($c['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute right-0 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                            <i class="fa-solid fa-chevron-down text-[10px]"></i>
                        </div>
                    </div>
                <?php else: ?>
                    <h1 class="text-sm font-black italic uppercase tracking-tighter truncate"><?= htmlspecialchars($campaignConfig['title']) ?></h1>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-4 shrink-0">
                <p id="last-update" class="text-[9px] font-black text-slate-400 uppercase tracking-widest hidden sm:block"></p>
                
                <?php if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true): ?>
                    <a href="index.php" class="bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-slate-300 transition hidden sm:inline-block">Liste</a>
                    <a href="admin.php" class="bg-slate-900 text-white px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-blue-600 transition-colors">Admin</a>
                    <a href="index.php?logout=1" class="w-10 h-10 flex items-center justify-center rounded-full bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition-all"><i class="fa-solid fa-power-off"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 mt-6 space-y-6">
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="sexy-card p-10 reveal" style="animation-delay: 0.1s;">
                <div class="flex justify-between items-start mb-4"><span class="kpi-label text-emerald-500/70">Recettes Totales</span><i class="fa-solid fa-euro-sign text-emerald-400 text-xl"></i></div>
                <div id="val-revenue" class="kpi-value text-emerald-600">0 €</div>
                <div id="donations-box-container" class="mt-2 flex justify-between items-center opacity-0 transition-opacity">
                    <span class="text-xs font-bold text-emerald-500/60 italic">
                        Dont <span id="val-donations">0 €</span> de dons
                    </span>
                    <span id="val-attachment-container" class="bg-emerald-50 text-emerald-600 text-[10px] font-black px-2 py-1 rounded-lg">
                        <i class="fa-solid fa-heart mr-1"></i> <span id="val-attachment">0</span>% de générosité
                    </span>
                </div>

                <div id="pacing-container" class="mt-4 flex items-center gap-3 hidden">
                    <div class="bg-slate-50 border border-slate-100 px-3 py-2 rounded-xl flex flex-col leading-none">
                        <span class="text-[8px] font-black uppercase text-slate-400">Projection estimée</span>
                        <span id="pacing-date" class="text-xs font-black text-slate-700">--/--</span>
                    </div>
                    <div id="alert-slowdown" class="hidden w-8 h-8 rounded-full bg-red-50 text-red-500 flex items-center justify-center animate-pulse"><i class="fa-solid fa-arrow-trend-down"></i></div>
                    <div id="alert-speedup" class="hidden w-8 h-8 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center"><i class="fa-solid fa-fire"></i></div>
                </div>

                <div id="goal-revenue-container" class="hidden">
                    <div class="gauge-track"><div id="goal-revenue-bar" class="gauge-bar bg-emerald-500"></div></div>
                    <div class="flex justify-between mt-3 text-[10px] font-black uppercase text-slate-400"><span id="goal-revenue-text"></span><span id="goal-revenue-percent">0%</span></div>
                </div>
            </div>

            <div class="sexy-card p-10 reveal border-l-8 border-l-blue-500" style="animation-delay: 0.2s;">
                <div class="flex justify-between items-start mb-4"><span id="label-participants" class="kpi-label text-blue-500/70">Inscriptions</span><i class="fa-solid fa-user-check text-blue-400 text-xl"></i></div>
                <div id="val-participants" class="kpi-value text-blue-600">0</div>
                <div id="n1-container" class="mt-2 text-[10px] font-bold text-slate-400 uppercase hidden italic">Vs <span id="val-n1">0</span> l'an passé</div>

                <div id="goal-tickets-container" class="hidden">
                    <div class="gauge-track"><div id="goal-tickets-bar" class="gauge-bar bg-blue-500"></div></div>
                    <div class="flex justify-between mt-3 text-[10px] font-black uppercase text-slate-400">
                        <span id="goal-tickets-text"></span>
                        <span id="goal-tickets-percent">0%</span>
                    </div>
                </div>
            </div>
        </div>

        <section class="sexy-card p-10 reveal" style="animation-delay: 0.3s;">
            <h3 class="text-xs font-black uppercase text-slate-400 mb-8">Performance & Événements (Email/Com)</h3>
            <div class="h-80"><canvas id="timelineChart"></canvas></div>
        </section>

        <div id="shop-breakdown-grid" class="hidden space-y-6"></div>

        <div id="charts-grid" class="grid grid-cols-1 md:grid-cols-2 gap-6"></div>

        <section class="sexy-card p-6 md:p-10 reveal" style="animation-delay: 0.4s;">
            <div onclick="toggleSection('heatmap-wrap', this)" class="flex justify-between items-center mb-4 md:mb-8 cursor-pointer cursor-mobile-pointer select-none">
                <h3 id="label-heatmap" class="text-xs font-black uppercase text-slate-400 italic">Heatmap : Densité des inscriptions</h3>
                <i class="fa-solid fa-chevron-down text-slate-300 md:hidden chevron-mobile"></i>
            </div>
            
            <div id="heatmap-wrap" class="mobile-collapse">
                <div id="heatmap-container" class="w-full grid-heatmap text-center"></div>
            </div>
        </section>

        <section class="sexy-card p-10 reveal" style="animation-delay: 0.45s;">
            <div onclick="toggleSection('recent-wrap', this)" class="flex justify-between items-center mb-4 md:mb-8 cursor-pointer cursor-mobile-pointer select-none">
                <div class="flex items-center gap-3">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-400">Activités Récentes</h3>
                    <i class="fa-solid fa-chevron-down text-slate-300 md:hidden chevron-mobile"></i>
                </div>
                <button onclick="event.stopPropagation(); openRecentModal()" class="text-[10px] font-black text-blue-600 bg-blue-50 px-4 py-2 rounded-full uppercase tracking-widest active:scale-95 transition">Tout voir</button>
            </div>

            <div id="recent-wrap" class="mobile-collapse">
                <div id="recent-list" class="space-y-3 pt-2"></div>
            </div>
        </section>

    </main>

    <div id="recent-modal" class="fixed inset-0 z-[100] hidden items-end sm:items-center justify-center">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md" onclick="closeRecentModal()"></div>
        <div class="modal-content relative w-full sm:max-w-2xl bg-white sm:rounded-[3rem] rounded-t-[3rem] shadow-2xl p-8 max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center mb-6 shrink-0">
                <div>
                    <h2 class="text-xl font-black italic uppercase tracking-tighter">Historique complet</h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><span id="modal-counter">0</span> <span id="label-modal-unit">inscriptions</span></p>
                </div>
                <button onclick="closeRecentModal()" class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center active:scale-90 transition"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="mb-6 shrink-0">
                <input type="text" id="modal-search" placeholder="Rechercher un nom ou un article..." class="search-input" oninput="filterModalList()">
            </div>
            <div id="modal-recent-list" class="overflow-y-auto space-y-3 pr-2 custom-scrollbar flex-1 pb-10"></div>
        </div>
    </div>

    <div class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50">
        <button onclick="refresh()" id="refresh-btn" class="bg-blue-600 text-white px-10 py-5 rounded-full font-black text-xs uppercase shadow-2xl active:scale-95 flex items-center gap-3">
            <span id="refresh-icon"><i class="fa-solid fa-sync-alt"></i></span>
            <span>Actualiser les flux</span>
        </button>
    </div>

    <script>
    const State = { tChart: null, mChart: null, allRecent: [] };

    function updateListView(container, data) {
        if (!data || data.length === 0) {
            container.innerHTML = '<p class="text-center text-slate-300 text-sm py-8 italic">Aucune donnée trouvée.</p>';
            return;
        }
        container.innerHTML = data.map(r => `
            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-[1.5rem] border border-slate-100 reveal">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-white rounded-2xl flex items-center justify-center font-black text-blue-500 shadow-sm text-[11px] uppercase">${r.name ? r.name.charAt(0) : 'A'}</div>
                    <div>
                        <p class="text-sm font-extrabold text-slate-800">${r.name || 'Anonyme'}</p>
                        <p class="text-[10px] font-black text-slate-400 uppercase">${r.date}</p>
                    </div>
                </div>
                <span class="text-[9px] font-black text-blue-600 bg-blue-100/50 px-4 py-1.5 rounded-full uppercase">${r.desc}</span>
            </div>
        `).join('');
    }

    function updateUI(res) {
        const d = res.data; const meta = res.meta;
        const goals = meta.goals || { revenue: 0, tickets: 0, n1: 0 };

        const labelsMap = {
            'Event': { main: 'Inscriptions', unit: 'billets', list: 'inscriptions', timeline: 'Inscr.' },
            'Shop': { main: 'Ventes', unit: 'commandes', list: 'ventes', timeline: 'Ventes' },
            'Membership': { main: 'Adhésions', unit: 'adhésions', list: 'adhésions', timeline: 'Adh.' },
            'Donation': { main: 'Dons', unit: 'donateurs', list: 'dons', timeline: 'Dons' },
            'Crowdfunding': { main: 'Contributions', unit: 'contributeurs', list: 'contributions', timeline: 'Contrib.' },
            'PaymentForm': { main: 'Ventes', unit: 'commandes', list: 'ventes', timeline: 'Ventes' },
            'Checkout': { main: 'Ventes', unit: 'commandes', list: 'ventes', timeline: 'Ventes' }
        };
        const formTypeKey = meta.formType ? (meta.formType.charAt(0).toUpperCase() + meta.formType.slice(1)) : 'Event';
        const labels = labelsMap[formTypeKey] || labelsMap['Event'];

        if (document.getElementById('label-participants')) document.getElementById('label-participants').innerText = labels.main;
        if (document.getElementById('label-heatmap')) document.getElementById('label-heatmap').innerText = `Heatmap : Densité des ${labels.list}`;
        if (document.getElementById('label-modal-unit')) {
            const count = (d.recent || []).length;
            document.getElementById('label-modal-unit').innerText = labels.list + (count > 1 ? 's' : '');
        }

        State.allRecent = d.recent || [];

        // KPIS
        if (document.getElementById('val-revenue')) document.getElementById('val-revenue').innerText = new Intl.NumberFormat('fr-FR', {style:'currency', currency:'EUR', minimumFractionDigits:0}).format(d.kpi.revenue);

        const isShop = (['Shop', 'Checkout', 'PaymentForm', 'Product', 'product'].includes(formTypeKey));
        if (document.getElementById('val-participants')) document.getElementById('val-participants').innerText = isShop ? d.kpi.orderCount : d.kpi.participants;

        const n1Container = document.getElementById('n1-container');
        if (n1Container) {
            if (isShop) {
                n1Container.classList.remove('hidden');
                const artCount = d.kpi.participants || 0;
                n1Container.innerHTML = `<i class="fa-solid fa-box-open mr-1"></i> ${artCount} article${artCount > 1 ? 's' : ''} vendu${artCount > 1 ? 's' : ''}`;
            } else if (goals.n1 > 0) {
                n1Container.classList.remove('hidden');
                n1Container.innerHTML = `Vs ${goals.n1} l'an passé`;
            } else {
                n1Container.classList.add('hidden');
            }
        }

        if (d.kpi.donations > 0) {
            const container = document.getElementById('donations-box-container');
            if (container) {
                container.classList.remove('opacity-0');
                container.classList.add('opacity-100');
                if (document.getElementById('val-donations')) {
                    document.getElementById('val-donations').innerText = new Intl.NumberFormat('fr-FR', {
                        style: 'currency',
                        currency: 'EUR',
                        minimumFractionDigits: 0
                    }).format(d.kpi.donations);
                }
            }
        }

        if (d.kpi.attachment_rate !== undefined) {
            const attachmentEl = document.getElementById('val-attachment');
            if (attachmentEl) attachmentEl.innerText = d.kpi.attachment_rate;
        }

        // PACING
        if (d.pacing && goals.revenue > 0) {
            if (document.getElementById('pacing-container')) document.getElementById('pacing-container').classList.remove('hidden');
            if (document.getElementById('pacing-date')) document.getElementById('pacing-date').innerText = d.pacing.projectedDate || '--';
            if (document.getElementById('alert-slowdown')) document.getElementById('alert-slowdown').classList.toggle('hidden', !d.pacing.isSlowingDown);
            if (document.getElementById('alert-speedup')) document.getElementById('alert-speedup').classList.toggle('hidden', d.pacing.trend !== 'up');
        }

        // JAUGES
        if (goals.revenue > 0) {
            if (document.getElementById('goal-revenue-container')) document.getElementById('goal-revenue-container').classList.remove('hidden');
            const pct = Math.min(100, (d.kpi.revenue / goals.revenue) * 100);
            if (document.getElementById('goal-revenue-bar')) document.getElementById('goal-revenue-bar').style.width = pct + '%';
            if (document.getElementById('goal-revenue-text')) document.getElementById('goal-revenue-text').innerText = `Objectif : ${goals.revenue}€`;
            if (document.getElementById('goal-revenue-percent')) document.getElementById('goal-revenue-percent').innerText = Math.round(pct) + '%';
        }
        if (goals.tickets > 0) {
            if (document.getElementById('goal-tickets-container')) document.getElementById('goal-tickets-container').classList.remove('hidden');
            const pct = Math.min(100, (d.kpi.participants / goals.tickets) * 100);
            if (document.getElementById('goal-tickets-bar')) document.getElementById('goal-tickets-bar').style.width = pct + '%';
            const unitLabel = isShop ? 'articles' : labels.unit;
            if (document.getElementById('goal-tickets-text')) document.getElementById('goal-tickets-text').innerText = `Quotas : ${goals.tickets} ${unitLabel}`;
            if (document.getElementById('goal-tickets-percent')) document.getElementById('goal-tickets-percent').innerText = Math.round(pct) + '%';
        }
        if (goals.n1 > 0) {
            if (document.getElementById('n1-container')) document.getElementById('n1-container').classList.remove('hidden');
            if (document.getElementById('val-n1')) document.getElementById('val-n1').innerText = goals.n1;
        }

        // RENDU
        renderTimeline(d.timeline, meta.markers || [], labels.timeline);
        renderHeatmap(d.heatmap);

        // Shop specific: Product breakdown card
        const shopBreakdownGrid = document.getElementById('shop-breakdown-grid');
        if (isShop && d.kpi.productBreakdown && Object.keys(d.kpi.productBreakdown).length > 0) {
            shopBreakdownGrid.classList.remove('hidden');

            let inventoryHtml = `
                <section class="sexy-card p-10 reveal">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-xs font-black uppercase text-slate-400 italic">Répartition des quantités par produit</h3>
                        <span class="bg-blue-50 text-blue-600 text-[10px] font-black px-3 py-1 rounded-full uppercase italic">Inventaire</span>
                    </div>
                    <div class="space-y-4">
                        ${Object.entries(d.kpi.productBreakdown).sort((a,b) => b[1].count - a[1].count).map(([name, data]) => {
                            const isSoldOut = data.stock > 0 && data.count >= data.stock;
                            const percent = data.stock > 0 ? Math.min(100, (data.count / data.stock * 100)) : (data.count / d.kpi.participants * 100);
                            return `
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col min-w-0">
                                    <span class="text-sm font-bold text-slate-600 truncate mr-4">${name}</span>
                                    ${isSoldOut ? '<span class="text-[8px] font-black text-red-500 uppercase tracking-widest leading-none mt-0.5 animate-pulse">Indisponible (Stock épuisé)</span>' : ''}
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <div class="h-1.5 w-24 bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full ${isSoldOut ? 'bg-red-500' : 'bg-blue-500'} rounded-full transition-all duration-1000" style="width: ${percent}%"></div>
                                    </div>
                                    <div class="text-right min-w-[3rem]">
                                        <span class="text-sm font-black text-slate-900">${data.count}</span>
                                        ${data.stock > 0 ? `<span class="text-[10px] font-bold text-slate-400">/${data.stock}</span>` : ''}
                                    </div>
                                </div>
                            </div>`;
                        }).join('')}
                    </div>
                </section>
            `;

            let performanceHtml = `
                <section class="sexy-card p-10 reveal overflow-x-auto">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-xs font-black uppercase text-slate-400 italic">Détail Performance par Produit</h3>
                        <span class="bg-emerald-50 text-emerald-600 text-[10px] font-black px-3 py-1 rounded-full uppercase italic">Rentabilité</span>
                    </div>
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] font-black uppercase text-slate-400 border-b border-slate-100">
                                <th class="pb-4">Produit</th>
                                <th class="pb-4 text-right">CA</th>
                                <th class="pb-4 text-right">Bénéfice</th>
                                <th class="pb-4 text-right">Marge</th>
                                <th class="pb-4 text-right text-xs">Contrib.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            ${Object.entries(d.kpi.productBreakdown).sort((a,b) => b[1].revenue - a[1].revenue).map(([name, data]) => `
                                <tr class="group hover:bg-slate-50 transition-colors">
                                    <td class="py-4 text-sm font-bold text-slate-700">${name}</td>
                                    <td class="py-4 text-sm font-black text-slate-900 text-right">${Math.round(data.revenue)}€</td>
                                    <td class="py-4 text-sm font-black text-emerald-600 text-right">${Math.round(data.benefit)}€</td>
                                    <td class="py-4 text-sm font-black text-slate-500 text-right">${data.marginRate.toFixed(1)}%</td>
                                    <td class="py-4 text-right">
                                        <span class="text-[10px] font-black bg-blue-50 text-blue-600 px-2 py-1 rounded-md">${data.contribution.toFixed(1)}%</span>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </section>
            `;

            let matrixHtml = `
                <section class="sexy-card p-10 reveal">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-xs font-black uppercase text-slate-400 italic">Matrice Rentabilité (Volume vs Bénéfice)</h3>
                        <span class="bg-indigo-50 text-indigo-600 text-[10px] font-black px-3 py-1 rounded-full uppercase italic">Analyse</span>
                    </div>
                    <div class="h-80 w-full">
                        <canvas id="matrixChart"></canvas>
                    </div>
                    <p class="text-center text-[10px] font-bold text-slate-400 mt-6 italic">
                        Plus un point est haut et à droite, meilleur est le produit !
                    </p>
                </section>
            `;

            shopBreakdownGrid.innerHTML = `${inventoryHtml}${performanceHtml}${matrixHtml}`;
            setTimeout(() => renderMatrix(d.kpi.productBreakdown), 100);
        } else {
            shopBreakdownGrid.classList.add('hidden');
        }

        renderCharts(d.charts);

        // MASQUER LE LOADER
        const loader = document.getElementById('global-loader');
        if (loader) {
            setTimeout(() => {
                loader.classList.add('fade-out');
                setTimeout(() => loader.remove(), 500);
            }, 300);
        }

        const recentListEl = document.getElementById('recent-list');
        if (recentListEl) {
            updateListView(recentListEl, State.allRecent.slice(0, 5));
        }

        document.getElementById('last-update').innerText = 'MAJ : ' + (meta.lastUpdated || 'A l\'instant');
    }

    async function refresh() {
        const icon = document.getElementById('refresh-icon');
        const btn = document.getElementById('refresh-btn');
        icon.innerHTML = '<div class="loader-friendly"></div>';
        btn.disabled = true;

        try {
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token') || '';

            const res = await (await fetch(`api.php?campaign=<?= $campaignConfig['slug'] ?>&token=${token}`)).json();

            if(!res.success) throw new Error(res.error);
            updateUI(res);
        } catch (e) { console.error(e); } 
        finally { icon.innerHTML = '<i class="fa-solid fa-sync-alt"></i>'; btn.disabled = false; }
    }

    function openRecentModal() {
        const modal = document.getElementById('recent-modal');
        if (!modal) return;
        
        document.getElementById('modal-search').value = '';
        document.getElementById('modal-counter').innerText = State.allRecent.length;
        updateListView(document.getElementById('modal-recent-list'), State.allRecent);
        modal.classList.add('active');
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function filterModalList() {
        const query = document.getElementById('modal-search').value.toLowerCase();
        const filtered = State.allRecent.filter(r => 
            (r.name && r.name.toLowerCase().includes(query)) || 
            (r.desc && r.desc.toLowerCase().includes(query))
        );
        updateListView(document.getElementById('modal-recent-list'), filtered);
    }

    function closeRecentModal() {
        const modal = document.getElementById('recent-modal');
        modal.classList.remove('active');
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    function renderMatrix(productBreakdown) {
        const ctx = document.getElementById('matrixChart').getContext('2d');
        if (State.mChart) State.mChart.destroy();

        const maxRev = Math.max(...Object.values(productBreakdown).map(d => d.revenue), 1);
        const dataPoints = Object.entries(productBreakdown).map(([name, data]) => ({
            x: data.count,
            y: data.benefit,
            r: Math.max(5, (data.revenue / maxRev) * 35),
            label: name
        }));

        State.mChart = new Chart(ctx, {
            type: 'bubble',
            data: {
                datasets: [{
                    label: 'Produits',
                    data: dataPoints,
                    backgroundColor: 'rgba(16, 185, 129, 0.6)',
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const p = context.raw;
                                return `${p.label}: ${p.x} vendus, ${Math.round(p.y)}€ bénéfice`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Volume Vendu (Qté)', font: { size: 10, weight: '800' } },
                        beginAtZero: true,
                        ticks: { font: { size: 10, weight: '700' } }
                    },
                    y: {
                        title: { display: true, text: 'Bénéfice Généré (€)', font: { size: 10, weight: '800' } },
                        beginAtZero: true,
                        ticks: { font: { size: 10, weight: '700' } }
                    }
                }
            }
        });
    }

    function renderTimeline(data, markers, labelUnit) {
        const ctx = document.getElementById('timelineChart').getContext('2d');
        if (State.tChart) State.tChart.destroy();
        const annotations = markers.map(m => {
            const dateStr = new Date(m.date).toLocaleDateString('fr-FR', {day:'2-digit', month:'2-digit'});
            return {
                type: 'line', xMin: dateStr, xMax: dateStr, borderColor: 'rgba(244, 63, 94, 0.8)', borderWidth: 2, borderDash: [5, 5],
                label: { display: true, content: m.label, backgroundColor: 'rgba(244, 63, 94, 0.9)', color: '#fff', font: { size: 10, weight: 'bold' }, position: 'start' }
            };
        });
        State.tChart = new Chart(ctx, {
            type: 'line', data: { labels: data.map(x => x.date), datasets: [{ label:labelUnit, data:data.map(x=>x.pax), borderColor:'#2563eb', backgroundColor:'rgba(37, 99, 235, 0.1)', fill:true, tension:0.4, yAxisID:'y' }, { label:'Cumul €', data:data.map(x=>x.cumulative), borderColor:'#10b981', borderDash:[5,5], yAxisID:'y1', tension:0.4 }] },
            options: { responsive:true, maintainAspectRatio:false, plugins:{ annotation:{ annotations }, legend:{ display:false } }, scales:{ x:{ ticks:{color:'#94a3b8', font:{size:10, weight:'700'}}, grid:{display:false} }, y:{ ticks:{color:'#2563eb', font:{size:10, weight:'800'}}, grid:{color:'rgba(0,0,0,0.02)'} }, y1:{ position:'right', grid:{display:false}, ticks:{color:'#10b981', font:{size:10, weight:'800'}} } } }
        });
    }

    function renderHeatmap(data) {
        const container = document.getElementById('heatmap-container');
        const days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        
        // Coin supérieur gauche
        let html = '<div class="h-6"></div>';
        
        // En-tête des heures (0h - 23h)
        for(let h=0; h<24; h++) html += `<div class="text-[8px] font-black text-slate-300 self-center">${h}h</div>`;
        
        data.forEach((hours, dayIdx) => {
            // text-center sur mobile, text-right sur desktop
            html += `<div class="text-[10px] font-black text-slate-400 uppercase text-center md:text-right md:pr-2 self-center">${days[dayIdx]}</div>`;
            
            hours.forEach(count => {
                const intensity = count === 0 ? 'bg-slate-50' : (count < 3 ? 'bg-blue-100' : (count < 8 ? 'bg-blue-300' : 'bg-blue-600'));
                html += `<div class="${intensity} h-7 rounded-sm flex items-center justify-center text-[9px] font-bold ${count > 5 ? 'text-white' : 'text-blue-900/20'}">${count || ''}</div>`;
            });
        });
        container.innerHTML = html;
    }

    function renderCharts(chartsData) {
        const grid = document.getElementById('charts-grid'); grid.innerHTML = '';
        chartsData.forEach((c, i) => {
            const chartId = `c-${i}`;
            const div = document.createElement('div'); div.className = 'sexy-card p-10 reveal';
            div.innerHTML = `<h4 class="text-[10px] font-black uppercase text-slate-400 mb-8">${c.title}</h4><div class="h-64"><canvas id="${chartId}"></canvas></div>`;
            grid.appendChild(div);
            
            const isBar = (c.type === 'bar');
            new Chart(document.getElementById(chartId), { 
                type: isBar ? 'bar' : 'pie', 
                data: { labels: Object.keys(c.data), datasets: [{ data: Object.values(c.data), backgroundColor: isBar ? '#3b82f6' : ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#f43f5e'], borderRadius: isBar ? 8 : 0 }] }, 
                options: { 
                    maintainAspectRatio:false, 
                    indexAxis: isBar ? 'y' : 'x',
                    plugins:{ legend:{ position:'bottom', display: !isBar, labels:{ usePointStyle:true, font:{size:10, weight:'700'} } } },
                    scales: isBar ? { x:{ display:false }, y:{ ticks:{font:{size:10, weight:'800'}}, grid:{display:false} } } : {}
                } 
            });
        });
    }

    function toggleSection(id, btn) {
        // Sécurité : ne rien faire sur PC (même si le CSS bloque déjà)
        if (window.innerWidth >= 768) return; 

        const content = document.getElementById(id);
        const icon = btn.querySelector('.chevron-mobile');
        
        content.classList.toggle('open');
        if (icon) icon.classList.toggle('rotate');
    }

    <?php if (!isset($data)): ?>
    refresh();
    <?php else: ?>
    updateUI(<?= json_encode($data) ?>);
    <?php endif; ?>
    setInterval(refresh, 5 * 60 * 1000);
    </script>
</body>
</html>