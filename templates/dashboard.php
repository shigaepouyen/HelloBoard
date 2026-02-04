<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($campaignConfig['title']) ?> - HelloBoard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700;900&display=swap');
        
        body { 
            font-family: 'Outfit', sans-serif; 
            background-color: #0f172a; 
            color: #e2e8f0; 
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(59, 130, 246, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(139, 92, 246, 0.05) 0%, transparent 20%);
        }

        .glass { 
            background: rgba(30, 41, 59, 0.7); 
            backdrop-filter: blur(12px); 
            border: 1px solid rgba(255,255,255,0.05); 
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
        }

        .kpi-glow { text-shadow: 0 0 20px rgba(250, 204, 21, 0.2); }
        
        .animate-fade-in { animation: fadeIn 0.6s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .loader { border: 2px solid rgba(255,255,255,0.1); border-top: 2px solid #3b82f6; border-radius: 50%; width: 16px; height: 16px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="p-4 md:p-10 pb-32">

    <div class="max-w-7xl mx-auto">
        <!-- HEADER -->
        <header class="flex justify-between items-center mb-12">
            <div class="flex items-center gap-5">
                <div class="bg-gradient-to-br from-yellow-400 to-amber-600 p-4 rounded-2xl shadow-xl shadow-amber-500/10 transform -rotate-2">
                    <i class="fa-solid fa-mask text-3xl text-purple-900"></i>
                </div>
                <div>
                    <h1 class="text-4xl font-black italic uppercase tracking-tighter text-white drop-shadow-md">
                        <?= htmlspecialchars($campaignConfig['title']) ?>
                    </h1>
                    <div class="flex items-center gap-3 mt-1">
                        <p class="text-[11px] text-purple-400 font-bold uppercase tracking-[0.4em] ml-1">Live Dashboard</p>
                        <span id="last-update" class="text-[9px] text-slate-500 uppercase font-mono"></span>
                    </div>
                </div>
            </div>
            <div class="flex gap-4">
                <a href="admin.php" class="bg-slate-800/50 p-4 rounded-full hover:bg-slate-700 transition border border-white/5" title="Paramètres">
                    <i class="fa-solid fa-gear text-slate-400"></i>
                </a>
                <button onclick="refresh()" id="refresh-btn" class="bg-blue-600 hover:bg-blue-500 px-8 py-3 rounded-full font-bold shadow-lg shadow-blue-900/30 transition flex items-center gap-3 group">
                    <span id="refresh-icon"><i class="fa-solid fa-rotate group-hover:rotate-180 transition-transform duration-500"></i></span>
                    <span class="hidden md:inline">Actualiser</span>
                </button>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
            <!-- 1. CARTE RECETTES -->
            <div class="glass p-10 rounded-[3rem] relative overflow-hidden flex flex-col justify-center min-h-[240px] group animate-fade-in">
                <div class="absolute -right-10 -top-10 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl group-hover:bg-emerald-500/20 transition duration-700"></div>
                <div class="flex justify-between items-center mb-4">
                    <span class="text-slate-500 text-xs font-bold uppercase tracking-widest border-b border-emerald-500/30 pb-1">Recettes Totales</span>
                    <i class="fa-solid fa-wallet text-emerald-500 text-2xl"></i>
                </div>
                <div id="val-revenue" class="text-7xl font-black leading-none mb-2 tabular-nums">0 €</div>
                
                <!-- DONS (SOUS-TITRE) -->
                <div id="donations-line" class="opacity-0 transition-opacity duration-700">
                    <p class="text-sm text-emerald-400 font-medium italic">
                        Dont <span id="val-donations" class="font-bold">0 €</span> de dons
                    </p>
                </div>

                <!-- OBJECTIF -->
                <div id="goal-container" class="mt-8 hidden">
                    <div class="w-full bg-slate-900/60 rounded-full h-2 overflow-hidden border border-white/5">
                        <div id="goal-bar" class="bg-emerald-500 h-full w-0 transition-all duration-1000 shadow-[0_0_15px_rgba(16,185,129,0.5)]"></div>
                    </div>
                    <div class="flex justify-between mt-3 text-[11px] font-mono text-slate-500 uppercase tracking-widest">
                        <span>Progression</span>
                        <span id="goal-text">Objectif: -- €</span>
                    </div>
                </div>
            </div>

            <!-- 2. CARTE PARTICIPANTS -->
            <div class="glass p-10 rounded-[3rem] flex flex-col justify-center min-h-[240px] border-l-8 border-purple-600 relative overflow-hidden group animate-fade-in" style="animation-delay: 0.1s;">
                <div class="absolute -right-10 -top-10 w-48 h-48 bg-purple-500/10 rounded-full blur-3xl group-hover:bg-purple-500/20 transition duration-700"></div>
                <div class="flex justify-between items-center mb-4">
                    <span class="text-slate-500 text-xs font-bold uppercase tracking-widest border-b border-purple-500/30 pb-1">Inscriptions</span>
                    <i class="fa-solid fa-users text-purple-500 text-2xl"></i>
                </div>
                <div id="val-participants" class="text-8xl font-black leading-none kpi-glow">0</div>
                
                <!-- N-1 -->
                <div id="n1-container" class="mt-6 hidden">
                    <div class="inline-block px-5 py-2 rounded-full bg-slate-900 text-[11px] font-black border border-slate-700 uppercase tracking-widest">
                        VS Année N-1 : <span id="val-n1" class="text-white ml-1">0</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. TIMELINE -->
        <div class="glass p-10 rounded-[3rem] mb-10 border-t-4 border-blue-500 animate-fade-in" style="animation-delay: 0.2s;">
            <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-[0.4em] mb-10 flex items-center gap-3">
                <span class="bg-blue-500/20 p-2 rounded-lg"><i class="fa-solid fa-chart-line text-blue-400"></i></span> 
                Rythme des Ventes & Inscriptions
            </h3>
            <div class="h-80 w-full"><canvas id="timelineChart"></canvas></div>
        </div>

        <!-- 4. GRAPHIQUES DYNAMIQUES -->
        <div id="charts-grid" class="grid grid-cols-1 lg:grid-cols-2 gap-10 mb-10"></div>

        <!-- 5. FIL D'ACTU -->
        <div class="glass p-10 rounded-[3rem] animate-fade-in shadow-2xl" style="animation-delay: 0.3s;">
            <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-[0.4em] mb-10 flex items-center justify-between">
                <span><i class="fa-solid fa-clock text-pink-500 mr-3"></i> Activités Récentes</span>
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse shadow-[0_0_10px_#22c55e]"></span>
            </h3>
            <div id="recent-list" class="space-y-4 font-mono text-sm"></div>
        </div>
    </div>

    <script>
    const State = { tChart: null };

    /**
     * Formate un montant en euros, sans décimales si c'est un nombre entier.
     */
    function formatValue(val) {
        const n = Number(val);
        const hasDecimals = n % 1 !== 0;
        return new Intl.NumberFormat('fr-FR', { 
            style: 'currency', 
            currency: 'EUR',
            minimumFractionDigits: hasDecimals ? 2 : 0,
            maximumFractionDigits: 2
        }).format(n);
    }

    async function refresh() {
        console.log("Démarrage du scan...");
        const icon = document.getElementById('refresh-icon');
        const refreshBtn = document.getElementById('refresh-btn');
        const originalIcon = icon.innerHTML;
        
        icon.innerHTML = '<div class="loader"></div>';
        if(refreshBtn) refreshBtn.disabled = true;

        try {
            const response = await fetch(`api.php?campaign=<?= $campaignConfig['slug'] ?>`);
            const res = await response.json();
            
            console.log("Données reçues de l'API :", res);

            if(!res.success) throw new Error(res.error || "Erreur lors du chargement.");

            const d = res.data;
            const meta = res.meta;
            // On vérifie plusieurs noms de clés possibles pour les objectifs (n1 ou participants)
            const goals = meta.goals || { revenue: 0, n1: 0 };

            // 1. KPIs
            document.getElementById('val-revenue').innerText = formatValue(d.kpi.revenue);
            document.getElementById('val-participants').innerText = d.kpi.participants;

            // 2. Gestion des Dons
            const donLine = document.getElementById('donations-line');
            if (d.kpi.donations > 0) {
                donLine.classList.remove('opacity-0');
                donLine.classList.add('opacity-100');
                document.getElementById('val-donations').innerText = formatValue(d.kpi.donations);
            } else {
                donLine.classList.add('opacity-0');
            }

            // 3. Objectif de Recettes
            const goalBox = document.getElementById('goal-container');
            const goalVal = parseFloat(goals.revenue);
            console.log("Valeur de l'objectif recettes :", goalVal);

            if (goalVal > 0) {
                goalBox.classList.remove('hidden');
                const pct = Math.min(100, (d.kpi.revenue / goalVal) * 100);
                document.getElementById('goal-bar').style.width = pct + '%';
                document.getElementById('goal-text').innerText = `Objectif: ${formatValue(goalVal)} (${Math.round(pct)}%)`;
            } else {
                goalBox.classList.add('hidden');
            }

            // 4. Comparaison N-1
            const n1Box = document.getElementById('n1-container');
            const n1Val = parseInt(goals.n1 || goals.participants || 0);
            if (n1Val > 0) {
                n1Box.classList.remove('hidden');
                document.getElementById('val-n1').innerText = n1Val;
            } else {
                n1Box.classList.add('hidden');
            }

            // 5. Graphiques Dynamiques
            renderDynamicCharts(d.charts || []);

            // 6. Timeline
            if(d.timeline) renderTimeline(d.timeline);

            // 7. Liste Récente
            if(d.recent) {
                document.getElementById('recent-list').innerHTML = d.recent.map(r => `
                    <div class="flex justify-between items-center border-b border-white/5 pb-3 last:border-0 group animate-fade-in">
                        <div class="flex items-center gap-4 min-w-0">
                            <span class="text-slate-600 text-[10px] uppercase tracking-tighter w-14 shrink-0">${r.date || ''}</span>
                            <span class="font-bold text-white group-hover:text-pink-400 transition truncate">${r.name || 'Anonyme'}</span>
                        </div>
                        <span class="text-pink-300 text-[10px] font-black uppercase tracking-widest bg-pink-900/20 px-3 py-1.5 rounded-full border border-pink-500/10 shrink-0 ml-2">
                            ${r.desc || ''}
                        </span>
                    </div>
                `).join('');
            }

            document.getElementById('last-update').innerText = meta.lastUpdated ? ('Scan : ' + meta.lastUpdated) : '';

        } catch (e) { 
            console.error("Erreur Dashboard:", e);
        } finally { 
            icon.innerHTML = originalIcon;
            if(refreshBtn) refreshBtn.disabled = false;
        }
    }

    function renderDynamicCharts(chartsData) {
        const grid = document.getElementById('charts-grid');
        grid.innerHTML = ''; 

        if (!chartsData || chartsData.length === 0) {
            grid.innerHTML = '<div class="col-span-full p-10 text-center text-slate-600 italic">Aucun bloc configuré.</div>';
            return;
        }

        chartsData.forEach((c, i) => {
            const chartId = `chart-${i}`;
            const div = document.createElement('div');
            div.className = 'glass p-10 rounded-[3rem] border-t-4 border-indigo-500 shadow-2xl transition hover:border-indigo-400 animate-fade-in';
            div.style.animationDelay = (0.2 + (i * 0.1)) + 's';
            
            div.innerHTML = `
                <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-[0.4em] mb-10 flex items-center gap-3">
                    <i class="fa-solid fa-chart-pie text-indigo-400"></i> ${c.title}
                </h4>
                <div class="h-64"><canvas id="${chartId}"></canvas></div>
            `;
            grid.appendChild(div);

            // Gestion formats données (Tableau ou Objet)
            let labels, values;
            if (Array.isArray(c.data)) {
                labels = c.data.map(item => item.label);
                values = c.data.map(item => item.count);
            } else {
                labels = Object.keys(c.data);
                values = Object.values(c.data);
            }

            new Chart(document.getElementById(chartId), {
                type: c.type || 'doughnut',
                data: { 
                    labels: labels, 
                    datasets: [{ 
                        data: values, 
                        backgroundColor: ['#818cf8', '#e879f9', '#facc15', '#34d399', '#f43f5e', '#3b82f6', '#fb923c'], 
                        borderWidth: 0,
                        borderRadius: 5
                    }] 
                },
                options: { 
                    maintainAspectRatio: false, 
                    responsive: true,
                    plugins: { 
                        legend: { 
                            position: 'right', 
                            labels: { color: '#94a3b8', font: {family:'Outfit', size: 11}, usePointStyle: true, padding: 15 } 
                        } 
                    },
                    scales: (c.type === 'bar') ? { 
                        x: { ticks:{color:'#94a3b8'}, grid:{display:false} }, 
                        y: { ticks:{color:'#e2e8f0'}, grid:{color:'rgba(255,255,255,0.05)'} } 
                    } : {}
                }
            });
        });
    }

    function renderTimeline(data) {
        const ctx = document.getElementById('timelineChart').getContext('2d');
        if (State.tChart) State.tChart.destroy();
        
        State.tChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(x => x.date),
                datasets: [
                    { 
                        label: 'Cumul (€)', 
                        data: data.map(x => x.cumulative), 
                        borderColor: '#fbbf24', 
                        backgroundColor: 'rgba(251, 191, 36, 0.05)', 
                        fill: true, 
                        tension: 0.4, 
                        yAxisID: 'y',
                        pointRadius: 2,
                        pointHoverRadius: 6
                    },
                    { 
                        label: 'Inscriptions', 
                        data: data.map(x => x.participants), 
                        type: 'bar', 
                        backgroundColor: '#3b82f6', 
                        borderRadius: 4, 
                        barThickness: 12, 
                        yAxisID: 'y1' 
                    }
                ]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { position: 'top', labels: { color: '#94a3b8', font: {family:'Outfit'} } } },
                scales: { 
                    x: { ticks:{color:'#64748b'}, grid:{display:false} }, 
                    y: { 
                        position: 'left',
                        ticks:{color:'#fbbf24', font:{weight:'bold'}}, 
                        grid:{color:'rgba(255,255,255,0.05)'} 
                    },
                    y1: { 
                        display:true, 
                        position:'right', 
                        grid:{display:false}, 
                        ticks:{color:'#3b82f6', stepSize: 1} 
                    }
                } 
            }
        });
    }

    Chart.defaults.font.family = 'Outfit';
    refresh();
    
    // Auto-refresh toutes les 5 minutes
    setInterval(refresh, 5 * 60 * 1000);
    </script>
</body>
</html>