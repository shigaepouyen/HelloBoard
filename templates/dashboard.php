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
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        
        :root {
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --accent: #2563eb;
            --success: #059669;
            --border: #e2e8f0;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg-main); 
            color: var(--text-main); 
            min-height: 100vh;
        }

        .card { 
            background: var(--card-bg); 
            border: 1px solid var(--border); 
            border-radius: 1.5rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card:hover {
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.05), 0 8px 10px -6px rgb(0 0 0 / 0.05);
            border-color: #cbd5e1;
        }

        .kpi-value {
            letter-spacing: -0.04em;
            font-weight: 800;
            color: var(--text-main);
        }

        .stat-label {
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 700;
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        .animate-reveal { animation: reveal 0.6s ease-out forwards; opacity: 0; }
        @keyframes reveal { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        .loader-spin {
            border: 2px solid #e2e8f0;
            border-top: 2px solid var(--accent);
            border-radius: 50%;
            width: 18px;
            height: 18px;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="p-4 md:p-8 lg:p-12 pb-20">

    <div class="max-w-7xl mx-auto">
        <!-- HEADER CLEAN -->
        <header class="flex flex-col md:flex-row justify-between items-center mb-16 gap-6 animate-reveal">
            <div class="flex items-center gap-5">
                <div class="bg-white p-3 rounded-2xl shadow-sm border border-slate-200">
                    <img src="assets/img/logo.svg" alt="Logo" class="w-10 h-10 object-contain" onerror="this.innerHTML='<i class=\'fa-solid fa-chart-line text-blue-600 text-2xl\'></i>'; this.type='icon';">
                </div>
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">
                        <?= htmlspecialchars($campaignConfig['title']) ?>
                    </h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <p class="stat-label !text-emerald-600">Données en direct</p>
                        <span id="last-update" class="text-[10px] text-slate-400 font-medium ml-2"></span>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-3 bg-white p-2 rounded-2xl shadow-sm border border-slate-200">
                <a href="admin.php" class="p-3 rounded-xl hover:bg-slate-50 transition text-slate-400 hover:text-slate-900" title="Réglages">
                    <i class="fa-solid fa-gear text-lg"></i>
                </a>
                <button onclick="refresh()" id="refresh-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-bold text-sm shadow-lg shadow-blue-200 transition-all active:scale-95 flex items-center gap-2">
                    <span id="refresh-icon"><i class="fa-solid fa-arrows-rotate"></i></span>
                    <span>Actualiser</span>
                </button>
            </div>
        </header>

        <!-- KPI GRID -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
            <!-- CARTE RECETTES -->
            <div class="card p-10 animate-reveal" style="animation-delay: 0.1s;">
                <div class="flex justify-between items-center mb-8">
                    <span class="stat-label">Chiffre d'Affaires</span>
                    <i class="fa-solid fa-wallet text-slate-300 text-xl"></i>
                </div>
                <div id="val-revenue" class="text-6xl kpi-value mb-4">0 €</div>
                
                <div id="donations-line" class="opacity-0 transition-all duration-500 flex items-center gap-2">
                    <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100 italic">Dons : <span id="val-donations">0 €</span></span>
                </div>

                <div id="goal-container" class="mt-10 hidden">
                    <div class="flex justify-between mb-3 text-[11px] font-bold text-slate-500">
                        <span id="goal-text">Objectif : 0 €</span>
                        <span id="goal-percent" class="text-blue-600">0%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                        <div id="goal-bar" class="bg-blue-600 h-full w-0 rounded-full transition-all duration-1000"></div>
                    </div>
                </div>
            </div>

            <!-- CARTE INSCRIPTIONS -->
            <div class="card p-10 animate-reveal border-l-4 border-l-blue-600" style="animation-delay: 0.2s;">
                <div class="flex justify-between items-center mb-8">
                    <span class="stat-label">Inscriptions</span>
                    <i class="fa-solid fa-user-check text-slate-300 text-xl"></i>
                </div>
                <div id="val-participants" class="text-6xl kpi-value mb-4">0</div>
                
                <div id="n1-container" class="mt-10 hidden">
                    <div class="flex items-center gap-3 py-3 px-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="stat-label !text-slate-400">Comparatif N-1</span>
                        <span id="val-n1" class="text-lg font-extrabold text-slate-900">0</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TIMELINE SECTION -->
        <div class="card p-10 mb-12 animate-reveal" style="animation-delay: 0.3s;">
            <div class="flex items-center justify-between mb-10">
                <h3 class="stat-label flex items-center gap-2">
                    <i class="fa-solid fa-chart-area text-blue-500"></i> Rythme de croissance
                </h3>
            </div>
            <div class="h-80 w-full"><canvas id="timelineChart"></canvas></div>
        </div>

        <!-- GRAPHS GRID -->
        <div id="charts-grid" class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12"></div>

        <!-- ACTIVITY FEED -->
        <div class="card p-10 animate-reveal" style="animation-delay: 0.4s;">
            <div class="flex justify-between items-center mb-10">
                <h3 class="stat-label flex items-center gap-2">
                    <i class="fa-solid fa-list-ul text-blue-500"></i> Activités Récentes
                </h3>
            </div>
            <div id="recent-list" class="divide-y divide-slate-100"></div>
        </div>
    </div>

    <script>
    const State = { tChart: null };

    function formatValue(val) {
        return new Intl.NumberFormat('fr-FR', { 
            style: 'currency', currency: 'EUR',
            minimumFractionDigits: Number(val) % 1 !== 0 ? 2 : 0
        }).format(val);
    }

    async function refresh() {
        const icon = document.getElementById('refresh-icon');
        const refreshBtn = document.getElementById('refresh-btn');
        const originalIcon = icon.innerHTML;
        icon.innerHTML = '<div class="loader-spin"></div>';
        if(refreshBtn) refreshBtn.disabled = true;

        try {
            const response = await fetch(`api.php?campaign=<?= $campaignConfig['slug'] ?>`);
            const res = await response.json();
            if(!res.success) throw new Error(res.error);

            const d = res.data;
            const meta = res.meta;
            const goals = meta.goals || { revenue: 0, n1: 0 };

            document.getElementById('val-revenue').innerText = formatValue(d.kpi.revenue);
            document.getElementById('val-participants').innerText = d.kpi.participants;

            const donLine = document.getElementById('donations-line');
            if (d.kpi.donations > 0) {
                donLine.style.opacity = '1';
                document.getElementById('val-donations').innerText = formatValue(d.kpi.donations);
            }

            const goalVal = parseFloat(goals.revenue);
            if (goalVal > 0) {
                document.getElementById('goal-container').classList.remove('hidden');
                const pct = Math.min(100, (d.kpi.revenue / goalVal) * 100);
                document.getElementById('goal-bar').style.width = pct + '%';
                document.getElementById('goal-text').innerText = `Objectif : ${formatValue(goalVal)}`;
                document.getElementById('goal-percent').innerText = Math.round(pct) + '%';
            }

            const n1Val = parseInt(goals.n1 || 0);
            if (n1Val > 0) {
                document.getElementById('n1-container').classList.remove('hidden');
                document.getElementById('val-n1').innerText = n1Val;
            }

            renderDynamicCharts(d.charts || []);
            if(d.timeline) renderTimeline(d.timeline);

            if(d.recent) {
                document.getElementById('recent-list').innerHTML = d.recent.map(r => `
                    <div class="flex justify-between items-center py-5 group transition">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center border border-slate-100 text-slate-400 group-hover:text-blue-600 transition">
                                <i class="fa-solid fa-user text-xs"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-900">${r.name || 'Anonyme'}</p>
                                <p class="text-[10px] font-bold text-slate-400 uppercase">${r.date}</p>
                            </div>
                        </div>
                        <span class="px-3 py-1.5 rounded-lg bg-slate-50 text-slate-600 text-[10px] font-bold border border-slate-100 group-hover:bg-blue-50 group-hover:text-blue-700 transition">
                            ${r.desc}
                        </span>
                    </div>
                `).join('');
            }
            document.getElementById('last-update').innerText = meta.lastUpdated ? ('MAJ ' + meta.lastUpdated) : '';

        } catch (e) { console.error(e); } finally { 
            icon.innerHTML = originalIcon;
            if(refreshBtn) refreshBtn.disabled = false;
        }
    }

    function renderDynamicCharts(chartsData) {
        const grid = document.getElementById('charts-grid');
        grid.innerHTML = ''; 
        chartsData.forEach((c, i) => {
            const chartId = `chart-${i}`;
            const div = document.createElement('div');
            div.className = 'card p-10 animate-reveal';
            div.style.animationDelay = (0.2 + (i * 0.1)) + 's';
            div.innerHTML = `
                <h4 class="stat-label mb-8">${c.title}</h4>
                <div class="${c.type === 'bar' ? 'h-80' : 'h-64'}"><canvas id="${chartId}"></canvas></div>
            `;
            grid.appendChild(div);

            let labels = Array.isArray(c.data) ? c.data.map(x => x.label) : Object.keys(c.data);
            let values = Array.isArray(c.data) ? c.data.map(x => x.count) : Object.values(c.data);
            const isBar = c.type === 'bar';

            new Chart(document.getElementById(chartId), {
                type: c.type || 'doughnut',
                data: { 
                    labels: labels, 
                    datasets: [{ 
                        data: values, 
                        backgroundColor: isBar ? '#3b82f6' : ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#64748b', '#ef4444'], 
                        borderRadius: isBar ? 4 : 0,
                        borderWidth: 0,
                        hoverOffset: 10
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
                            labels: { color: '#64748b', font: {size: 10, weight: '700'}, padding: 20, usePointStyle: true } 
                        },
                        tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8 }
                    },
                    scales: isBar ? { 
                        x: { ticks:{color:'#94a3b8'}, grid:{color:'#f1f5f9'} }, 
                        y: { ticks:{color:'#1e293b', font:{weight:'700'}}, grid:{display:false} } 
                    } : {}
                }
            });
        });
    }

    function renderTimeline(data) {
        const ctx = document.getElementById('timelineChart').getContext('2d');
        if (State.tChart) State.tChart.destroy();
        
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.1)');
        gradient.addColorStop(1, 'rgba(37, 99, 235, 0)');

        State.tChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(x => x.date),
                datasets: [
                    { 
                        label: 'Ventes Cumulées', 
                        data: data.map(x => x.cumulative), 
                        borderColor: '#2563eb', 
                        backgroundColor: gradient, 
                        fill: true, 
                        tension: 0.3, 
                        yAxisID: 'y',
                        pointRadius: 0,
                        borderWidth: 3
                    },
                    { 
                        label: 'Inscriptions', 
                        data: data.map(x => x.participants), 
                        borderColor: '#10b981',
                        tension: 0.3, 
                        yAxisID: 'y1',
                        pointRadius: 4,
                        pointBackgroundColor: '#fff',
                        pointBorderWidth: 2,
                        borderWidth: 2
                    }
                ]
            },
            options: { 
                responsive: true, maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: { legend: { display: false } },
                scales: { 
                    x: { ticks:{color:'#94a3b8'}, grid:{display:false} }, 
                    y: { position:'left', ticks:{color:'#2563eb', font:{weight:'700'}}, grid:{color:'#f1f5f9'} },
                    y1: { position:'right', grid:{display:false}, ticks:{color:'#10b981', font:{weight:'700'}, stepSize: 1} }
                } 
            }
        });
    }

    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    refresh();
    setInterval(refresh, 5 * 60 * 1000);
    </script>
</body>
</html>