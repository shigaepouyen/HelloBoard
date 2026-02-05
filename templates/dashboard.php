<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($campaignConfig['title']) ?> — HelloBoard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap');
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #f1f5f9; 
            color: #1e293b; 
            -webkit-font-smoothing: antialiased;
        }

        .sexy-card {
            background: #ffffff;
            border-radius: 2.5rem;
            border: 1px solid rgba(255,255,255,0.7);
            box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            position: relative;
        }

        .kpi-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; color: #94a3b8; }
        .kpi-value { font-size: 3.5rem; font-weight: 800; letter-spacing: -0.05em; color: #0f172a; line-height: 1; }

        .gauge-track { background: #f1f5f9; height: 12px; border-radius: 20px; overflow: hidden; position: relative; margin-top: 1.5rem; }
        .gauge-bar { height: 100%; border-radius: 20px; transition: width 1.2s cubic-bezier(0.34, 1.56, 0.64, 1); }

        .btn-refresh {
            background: #2563eb; color: white; padding: 1.25rem 2.5rem; border-radius: 3rem;
            font-weight: 800; font-size: 0.9rem; shadow: 0 10px 25px rgba(37, 99, 235, 0.4);
            transition: all 0.2s;
        }
        .btn-refresh:active { transform: scale(0.95); }

        /* MODALE / POPIN */
        #recent-modal.active { display: flex; }
        .modal-content { animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }

        .search-input { background: #f1f5f9; border: 2px solid transparent; border-radius: 1.25rem; padding: 14px 20px; width: 100%; font-weight: 600; font-size: 0.9rem; outline: none; transition: all 0.2s; }
        .search-input:focus { background: white; border-color: #2563eb; }

        .loader-friendly {
            width: 20px; height: 20px; border: 3px solid rgba(255,255,255,0.3);
            border-top: 3px solid #fff; border-radius: 50%; animation: spin 0.8s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .reveal { animation: reveal 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        @keyframes reveal { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen pb-32">

    <!-- NAVIGATION BAR -->
    <header class="p-4">
        <div class="max-w-4xl mx-auto bg-white/70 backdrop-blur-xl rounded-[2.5rem] border border-white/50 px-6 py-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <img src="assets/img/logo.svg" alt="Logo" class="w-8 h-8" onerror="this.src='https://www.helloasso.com/assets/img/logos/helloasso-logo.svg'">
                <h1 class="text-sm font-black truncate max-w-[150px] italic uppercase tracking-tighter"><?= htmlspecialchars($campaignConfig['title']) ?></h1>
            </div>
            <div class="flex items-center gap-2">
                <p id="last-update" class="hidden sm:block text-[9px] font-black text-slate-400 uppercase tracking-widest"></p>
                <!-- Bouton admin visible uniquement si non en lecture seule -->
                <?php if (!isset($isReadOnly) || !$isReadOnly): ?>
                <a href="admin.php" class="w-10 h-10 flex items-center justify-center rounded-full bg-slate-100 text-slate-400 transition hover:bg-blue-50 hover:text-blue-600">
                    <i class="fa-solid fa-cog"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 mt-6 space-y-6">
        
        <!-- CARTES KPIs PRINCIPALES -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- REVENUS -->
            <div class="sexy-card p-10 reveal" style="animation-delay: 0.1s;">
                <div class="flex justify-between items-start mb-4">
                    <span class="kpi-label text-emerald-500/70">Volume Recettes</span>
                    <i class="fa-solid fa-euro-sign text-emerald-400 text-xl"></i>
                </div>
                <div id="val-revenue" class="kpi-value text-emerald-600">0 €</div>
                <div id="donations-box" class="mt-2 text-xs font-bold text-emerald-500/60 opacity-0 transition-opacity italic">
                    Dont <span id="val-donations">0 €</span> de dons
                </div>
                
                <div id="goal-revenue-container" class="hidden">
                    <div class="gauge-track">
                        <div id="goal-revenue-bar" class="gauge-bar bg-emerald-500 shadow-[0_0_15px_rgba(16,185,129,0.3)]"></div>
                    </div>
                    <div class="flex justify-between mt-3 text-[10px] font-black uppercase text-slate-400">
                        <span id="goal-revenue-text">Objectif : --</span>
                        <span id="goal-revenue-percent">0%</span>
                    </div>
                </div>
            </div>

            <!-- INSCRIPTIONS -->
            <div class="sexy-card p-10 reveal border-l-8 border-l-blue-500" style="animation-delay: 0.2s;">
                <div class="flex justify-between items-start mb-4">
                    <span class="kpi-label text-blue-500/70">Inscriptions</span>
                    <i class="fa-solid fa-user-check text-blue-400 text-xl"></i>
                </div>
                <div id="val-participants" class="kpi-value text-blue-600">0</div>
                <div id="n1-container" class="mt-2 text-[10px] font-bold text-slate-400 uppercase hidden italic">
                    Vs <span id="val-n1">0</span> l'an passé
                </div>

                <div id="goal-tickets-container" class="hidden">
                    <div class="gauge-track">
                        <div id="goal-tickets-bar" class="gauge-bar bg-blue-500 shadow-[0_0_15px_rgba(59,130,246,0.3)]"></div>
                    </div>
                    <div class="flex justify-between mt-3 text-[10px] font-black uppercase text-slate-400">
                        <span id="goal-tickets-text">Quotas : -- billets</span>
                        <span id="goal-tickets-percent">0%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TIMELINE HYBRIDE (STYLE IMAGE FOURNIE) -->
        <section class="sexy-card p-10 reveal" style="animation-delay: 0.3s;">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-10 gap-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400">Performance Quotidienne</h3>
                <div class="flex gap-4">
                    <div class="flex items-center gap-2 text-[9px] font-black uppercase text-blue-600"><span class="w-3 h-3 rounded bg-blue-600"></span> Inscriptions</div>
                    <div class="flex items-center gap-2 text-[9px] font-black uppercase text-emerald-500"><span class="w-3 h-3 border-2 border-emerald-500 border-dashed rounded"></span> Cumul (€)</div>
                </div>
            </div>
            <div class="h-80"><canvas id="timelineChart"></canvas></div>
        </section>

        <!-- GRAPHIQUES DE RÉPARTITION (NON CREUX) -->
        <div id="charts-grid" class="grid grid-cols-1 md:grid-cols-2 gap-6"></div>

        <!-- ACTIVITÉS RÉCENTES -->
        <section class="sexy-card p-10 reveal" style="animation-delay: 0.4s;">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400">Dernières commandes</h3>
                <button onclick="openRecentModal()" class="text-[10px] font-black text-blue-600 bg-blue-50 px-4 py-2 rounded-full uppercase tracking-widest active:scale-95 transition">Voir tout</button>
            </div>
            <div id="recent-list" class="space-y-3"></div>
        </section>

    </main>

    <!-- BOUTON REFRESH MOBILE FLOTTANT -->
    <div class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50">
        <button onclick="refresh()" id="refresh-btn" class="btn-refresh flex items-center gap-3 shadow-2xl">
            <span id="refresh-icon"><i class="fa-solid fa-sync-alt"></i></span>
            <span class="tracking-widest uppercase text-[10px] font-black">Actualiser le board</span>
        </button>
    </div>

    <!-- MODALE "VOIR TOUT" AVEC RECHERCHE -->
    <div id="recent-modal" class="fixed inset-0 z-[100] hidden items-end sm:items-center justify-center">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md" onclick="closeRecentModal()"></div>
        <div class="modal-content relative w-full sm:max-w-2xl bg-white sm:rounded-[3rem] rounded-t-[3rem] shadow-2xl p-8 max-h-[90vh] flex flex-col">
            
            <div class="flex justify-between items-center mb-6 shrink-0">
                <div>
                    <h2 class="text-xl font-black italic uppercase tracking-tighter">Historique des flux</h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><span id="modal-counter">0</span> lignes détectées</p>
                </div>
                <button onclick="closeRecentModal()" class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center active:scale-90 transition"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="mb-6 shrink-0">
                <input type="text" id="modal-search" placeholder="Chercher un nom ou un article..." class="search-input" oninput="filterModalList()">
            </div>

            <div id="modal-recent-list" class="overflow-y-auto space-y-3 pr-2 custom-scrollbar flex-1 pb-10">
                <!-- Rempli par JavaScript -->
            </div>
        </div>
    </div>

    <script>
    const State = { tChart: null, allRecent: [] };

    /**
     * Formate un montant en euros proprement
     */
    function formatValue(val) {
        return new Intl.NumberFormat('fr-FR', { 
            style: 'currency', 
            currency: 'EUR',
            minimumFractionDigits: Number(val) % 1 !== 0 ? 2 : 0
        }).format(val);
    }

    /**
     * Rafraîchit les données depuis l'API sécurisée
     */
    async function refresh() {
        const icon = document.getElementById('refresh-icon');
        const refreshBtn = document.getElementById('refresh-btn');
        const originalIcon = icon.innerHTML;
        icon.innerHTML = '<div class="loader-friendly"></div>';
        if(refreshBtn) refreshBtn.disabled = true;

        // Récupération du token de sécurité dans l'URL si présent
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token') || '';

        try {
            const response = await fetch(`api.php?campaign=<?= $campaignConfig['slug'] ?>&token=${token}`);
            const res = await response.json();
            if(!res.success) throw new Error(res.error);

            const d = res.data;
            const meta = res.meta;
            const goals = meta.goals || { revenue: 0, tickets: 0, n1: 0 };
            
            State.allRecent = d.recent || [];

            // 1. KPIs
            document.getElementById('val-revenue').innerText = formatValue(d.kpi.revenue);
            document.getElementById('val-participants').innerText = d.kpi.participants;

            if (d.kpi.donations > 0) {
                document.getElementById('donations-box').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('val-donations').innerText = formatValue(d.kpi.donations);
            }

            // 2. JAUGE REVENU (Carte 1)
            const gRev = parseFloat(goals.revenue);
            if (gRev > 0) {
                document.getElementById('goal-revenue-container').classList.remove('hidden');
                const pct = Math.min(100, (d.kpi.revenue / gRev) * 100);
                document.getElementById('goal-revenue-bar').style.width = pct + '%';
                document.getElementById('goal-revenue-text').innerText = `Objectif : ${formatValue(gRev)}`;
                document.getElementById('goal-revenue-percent').innerText = Math.round(pct) + '%';
            }

            // 3. JAUGE TICKETS (Carte 2)
            const gTix = parseInt(goals.tickets || 0);
            if (gTix > 0) {
                document.getElementById('goal-tickets-container').classList.remove('hidden');
                const pct = Math.min(100, (d.kpi.participants / gTix) * 100);
                document.getElementById('goal-tickets-bar').style.width = pct + '%';
                document.getElementById('goal-tickets-text').innerText = `Quotas : ${gTix} billets`;
                document.getElementById('goal-tickets-percent').innerText = Math.round(pct) + '%';
            }

            // 4. COMPARATIF N-1
            const n1Val = parseInt(goals.n1 || 0);
            if (n1Val > 0) {
                document.getElementById('n1-container').classList.remove('hidden');
                document.getElementById('val-n1').innerText = n1Val;
            }

            // 5. RENDU GRAPHIQUES & LISTES
            renderDynamicCharts(d.charts || []);
            if(d.timeline) renderTimeline(d.timeline);
            updateListView(document.getElementById('recent-list'), State.allRecent.slice(0, 8));

            document.getElementById('last-update').innerText = 'MAJ : ' + meta.lastUpdated;

        } catch (e) { 
            console.error(e); 
        } finally { 
            icon.innerHTML = originalIcon;
            if(refreshBtn) refreshBtn.disabled = false;
        }
    }

    /**
     * Met à jour une liste d'activités (Dashboard ou Modale)
     */
    function updateListView(container, data) {
        if (data.length === 0) {
            container.innerHTML = '<p class="text-center text-slate-300 text-sm py-4 italic">Aucune donnée trouvée.</p>';
            return;
        }
        container.innerHTML = data.map(r => `
            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-[1.5rem] border border-slate-100 reveal">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-white rounded-2xl flex items-center justify-center font-black text-blue-500 shadow-sm border border-slate-100 text-[11px] uppercase">${r.name ? r.name.charAt(0) : 'A'}</div>
                    <div>
                        <p class="text-sm font-extrabold text-slate-800">${r.name || 'Anonyme'}</p>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-tighter">${r.date}</p>
                    </div>
                </div>
                <span class="text-[9px] font-black text-blue-600 bg-blue-100/50 px-4 py-1.5 rounded-full uppercase tracking-widest">${r.desc}</span>
            </div>
        `).join('');
    }

    /**
     * Fonctions de la Modale
     */
    function openRecentModal() {
        const modal = document.getElementById('recent-modal');
        document.getElementById('modal-search').value = '';
        document.getElementById('modal-counter').innerText = State.allRecent.length;
        updateListView(document.getElementById('modal-recent-list'), State.allRecent);
        modal.classList.replace('hidden', 'flex');
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
        modal.classList.replace('flex', 'hidden');
        document.body.style.overflow = '';
    }

    /**
     * Rendu des graphiques de répartition configurés dans l'admin
     */
    function renderDynamicCharts(chartsData) {
        const grid = document.getElementById('charts-grid');
        grid.innerHTML = ''; 
        chartsData.forEach((c, i) => {
            const chartId = `chart-${i}`;
            const div = document.createElement('div');
            div.className = 'sexy-card p-10 reveal';
            div.style.animationDelay = (0.2 + (i * 0.1)) + 's';
            div.innerHTML = `
                <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-10">${c.title}</h4>
                <div class="${c.type === 'bar' ? 'h-72' : 'h-64'}"><canvas id="${chartId}"></canvas></div>
            `;
            grid.appendChild(div);

            let labels = Array.isArray(c.data) ? c.data.map(x => x.label) : Object.keys(c.data);
            let values = Array.isArray(c.data) ? c.data.map(x => x.count) : Object.values(c.data);
            const isBar = c.type === 'bar';

            new Chart(document.getElementById(chartId), {
                type: isBar ? 'bar' : 'pie', // Pie pour des disques pleins
                data: { 
                    labels: labels, 
                    datasets: [{ 
                        data: values, 
                        backgroundColor: isBar ? '#3b82f6' : ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#6366f1', '#f43f5e'], 
                        borderRadius: isBar ? 12 : 0,
                        borderWidth: 0
                    }] 
                },
                options: { 
                    indexAxis: isBar ? 'y' : 'x',
                    maintainAspectRatio: false, 
                    responsive: true,
                    plugins: { 
                        legend: { 
                            display: !isBar,
                            position: 'bottom', 
                            labels: { color: '#64748b', font: {size: 10, weight: '800'}, padding: 25, usePointStyle: true } 
                        },
                        tooltip: { backgroundColor: '#1e293b', padding: 15, cornerRadius: 15, titleFont: {size: 12, weight:'800'} }
                    },
                    scales: isBar ? { 
                        x: { display: false }, 
                        y: { ticks:{color:'#1e293b', font:{size: 11, weight:'800'}}, grid:{display:false} } 
                    } : {}
                }
            });
        });
    }

    /**
     * Rendu de la Timeline hybride (Aire Inscriptions + Pointillés CA)
     */
    function renderTimeline(data) {
        const ctx = document.getElementById('timelineChart').getContext('2d');
        if (State.tChart) State.tChart.destroy();
        
        State.tChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(x => x.date),
                datasets: [
                    { 
                        label: 'Inscriptions', 
                        data: data.map(x => x.pax), 
                        borderColor: '#2563eb', 
                        backgroundColor: 'rgba(37, 99, 235, 0.15)',
                        fill: true,
                        tension: 0.4, 
                        yAxisID: 'y', 
                        pointRadius: 5, 
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#2563eb',
                        pointBorderWidth: 3,
                        borderWidth: 5
                    },
                    { 
                        label: 'Cumul (€)', 
                        data: data.map(x => x.cumulative), 
                        borderColor: '#10b981',
                        borderDash: [6, 4],
                        fill: false, 
                        tension: 0.4, 
                        yAxisID: 'y1', 
                        pointRadius: 0, 
                        borderWidth: 3
                    }
                ]
            },
            options: { 
                responsive: true, maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: { legend: { display: false } },
                scales: { 
                    x: { ticks:{color:'#94a3b8', font:{size: 10, weight:'700'}}, grid:{display:false} }, 
                    y: { 
                        position:'left', 
                        ticks:{color:'#2563eb', font:{size: 10, weight:'800'}}, 
                        grid:{color:'rgba(0,0,0,0.02)'},
                        title: { display: true, text: 'Inscriptions', color: '#2563eb', font: {size: 9, weight: '800'} }
                    },
                    y1: { 
                        position:'right', 
                        grid:{display:false}, 
                        ticks:{color:'#10b981', font:{size: 10, weight:'800'}},
                        title: { display: true, text: 'Argent (€)', color: '#10b981', font: {size: 9, weight: '800'} }
                    }
                } 
            }
        });
    }

    // Init
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    refresh();
    setInterval(refresh, 5 * 60 * 1000);
    </script>
</body>
</html>