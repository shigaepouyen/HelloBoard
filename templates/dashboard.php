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
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #ffffff; 
            color: #000000; 
            -webkit-font-smoothing: antialiased;
        }

        /* Suppression des boîtes lourdes au profit de séparateurs légers */
        .section-border { border-bottom: 1px solid #f1f5f9; }
        .grid-divider { border-right: 1px solid #f1f5f9; }

        .kpi-small-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }

        .kpi-compact-value {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .chart-container {
            padding: 2rem 0;
        }

        button#refresh-btn {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        button#refresh-btn:hover { background-color: #000; color: #fff; }

        .loader-minimal {
            width: 14px;
            height: 14px;
            border: 2px solid #f1f5f9;
            border-top: 2px solid #000;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="min-h-screen">

    <!-- TOP NAVIGATION & COMPACT KPIs -->
    <nav class="sticky top-0 bg-white/80 backdrop-blur-md z-50 section-border">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                
                <!-- Logo & Titre -->
                <div class="flex items-center gap-4">
                    <img src="assets/img/logo.svg" alt="Logo" class="w-8 h-8" onerror="this.style.display='none'">
                    <div>
                        <h1 class="text-sm font-bold tracking-tight"><?= htmlspecialchars($campaignConfig['title']) ?></h1>
                        <p id="last-update" class="text-[10px] text-slate-400 font-medium"></p>
                    </div>
                </div>

                <!-- KPIs Compacts intégrés à la nav -->
                <div class="flex items-center gap-12">
                    <div class="flex flex-col">
                        <span class="kpi-small-label">Recettes</span>
                        <div id="val-revenue" class="kpi-compact-value">0 €</div>
                    </div>
                    <div class="flex flex-col">
                        <span class="kpi-small-label">Inscriptions</span>
                        <div id="val-participants" class="kpi-compact-value">0</div>
                    </div>
                    <div id="goal-status" class="hidden md:flex flex-col w-32">
                        <span class="kpi-small-label">Objectif</span>
                        <div class="flex items-center gap-2 mt-1">
                             <div class="flex-1 bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                <div id="goal-bar" class="bg-black h-full w-0 transition-all duration-1000"></div>
                             </div>
                             <span id="goal-percent" class="text-[10px] font-bold">0%</span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2">
                    <button onclick="refresh()" id="refresh-btn" class="border border-slate-200 px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-2">
                        <span id="refresh-icon"><i class="fa-solid fa-sync-alt"></i></span>
                        Actualiser
                    </button>
                    <a href="admin.php" class="p-2 text-slate-400 hover:text-black transition"><i class="fa-solid fa-cog"></i></a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-12">
        
        <!-- TIMELINE SECTION - FULL WIDTH, NO BOX -->
        <section class="mb-20">
            <div class="flex items-center gap-3 mb-8">
                <span class="kpi-small-label">Évolution des ventes</span>
                <div class="h-[1px] flex-1 bg-slate-100"></div>
            </div>
            <div class="h-72 w-full"><canvas id="timelineChart"></canvas></div>
        </section>

        <!-- GRAPHS GRID - MINIMALIST -->
        <div id="charts-grid" class="grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-20 mb-24"></div>

        <!-- RECENT ACTIVITY - LIST STYLE -->
        <section class="max-w-3xl">
             <div class="flex items-center gap-3 mb-8">
                <span class="kpi-small-label">Dernières inscriptions</span>
                <div class="h-[1px] flex-1 bg-slate-100"></div>
            </div>
            <div id="recent-list" class="space-y-1"></div>
        </section>

    </main>

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
        const originalIcon = icon.innerHTML;
        icon.innerHTML = '<div class="loader-minimal"></div>';

        try {
            const response = await fetch(`api.php?campaign=<?= $campaignConfig['slug'] ?>`);
            const res = await response.json();
            if(!res.success) throw new Error(res.error);

            const d = res.data;
            const meta = res.meta;
            const goals = meta.goals || { revenue: 0, n1: 0 };

            document.getElementById('val-revenue').innerText = formatValue(d.kpi.revenue);
            document.getElementById('val-participants').innerText = d.kpi.participants;

            const goalVal = parseFloat(goals.revenue);
            if (goalVal > 0) {
                document.getElementById('goal-status').classList.remove('hidden');
                const pct = Math.min(100, (d.kpi.revenue / goalVal) * 100);
                document.getElementById('goal-bar').style.width = pct + '%';
                document.getElementById('goal-percent').innerText = Math.round(pct) + '%';
            }

            renderDynamicCharts(d.charts || []);
            if(d.timeline) renderTimeline(d.timeline);

            if(d.recent) {
                document.getElementById('recent-list').innerHTML = d.recent.map(r => `
                    <div class="flex items-center justify-between py-3 group border-b border-transparent hover:border-slate-100 transition">
                        <div class="flex items-center gap-4">
                            <span class="text-[10px] font-bold text-slate-300 w-12">${r.date.split(' ')[0]}</span>
                            <span class="text-sm font-semibold">${r.name || 'Anonyme'}</span>
                        </div>
                        <span class="text-[10px] font-bold text-slate-400 bg-slate-50 px-2 py-1 rounded uppercase tracking-tighter">${r.desc}</span>
                    </div>
                `).join('');
            }
            document.getElementById('last-update').innerText = meta.lastUpdated ? ('MAJ ' + meta.lastUpdated) : '';

        } catch (e) { console.error(e); } finally { 
            icon.innerHTML = originalIcon;
        }
    }

    function renderDynamicCharts(chartsData) {
        const grid = document.getElementById('charts-grid');
        grid.innerHTML = ''; 
        chartsData.forEach((c, i) => {
            const chartId = `chart-${i}`;
            const div = document.createElement('div');
            div.className = 'chart-container';
            div.innerHTML = `
                <h4 class="kpi-small-label mb-8">${c.title}</h4>
                <div class="${c.type === 'bar' ? 'h-64' : 'h-56'}"><canvas id="${chartId}"></canvas></div>
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
                        backgroundColor: isBar ? '#000000' : ['#000000', '#64748b', '#94a3b8', '#cbd5e1', '#e2e8f0', '#f1f5f9'], 
                        borderRadius: isBar ? 2 : 0,
                        borderWidth: 0,
                        cutout: '80%'
                    }] 
                },
                options: { 
                    indexAxis: isBar ? 'y' : 'x',
                    maintainAspectRatio: false, 
                    responsive: true,
                    plugins: { 
                        legend: { 
                            display: !isBar,
                            position: 'right', 
                            labels: { color: '#64748b', font: {size: 10, weight: '600'}, usePointStyle: true, padding: 15 } 
                        },
                        tooltip: { backgroundColor: '#000', padding: 12, cornerRadius: 4 }
                    },
                    scales: isBar ? { 
                        x: { display: false }, 
                        y: { ticks:{color:'#000', font:{size: 11, weight:'600'}}, grid:{display:false} } 
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
                        label: 'Ventes', 
                        data: data.map(x => x.cumulative), 
                        borderColor: '#000', 
                        fill: false, 
                        tension: 0, 
                        yAxisID: 'y',
                        pointRadius: 0,
                        borderWidth: 2
                    },
                    { 
                        label: 'Pax', 
                        data: data.map(x => x.participants), 
                        borderColor: '#94a3b8',
                        borderDash: [5, 5],
                        fill: false,
                        tension: 0, 
                        yAxisID: 'y1',
                        pointRadius: 0,
                        borderWidth: 1
                    }
                ]
            },
            options: { 
                responsive: true, maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: { legend: { display: false } },
                scales: { 
                    x: { ticks:{color:'#94a3b8', font:{size: 10}}, grid:{display:false} }, 
                    y: { position:'left', ticks:{color:'#000', font:{size: 10, weight:'700'}}, grid:{color:'#f8fafc'} },
                    y1: { position:'right', display: false }
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