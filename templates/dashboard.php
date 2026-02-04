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
            background-color: #f0f4f8; 
            color: #1e293b; 
            -webkit-font-smoothing: antialiased;
        }

        .soft-card {
            background: #ffffff;
            border-radius: 2.5rem;
            border: 1px solid rgba(255,255,255,0.7);
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.05);
        }

        .kpi-value { font-size: 2.25rem; font-weight: 800; letter-spacing: -0.04em; color: #0f172a; line-height: 1; }
        .kpi-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; }

        .gauge-container { margin-top: 1.25rem; }
        .gauge-track { background: #f1f5f9; height: 10px; border-radius: 10px; overflow: hidden; }
        .gauge-fill { height: 100%; border-radius: 10px; transition: width 1s cubic-bezier(0.34, 1.56, 0.64, 1); }

        .btn-refresh {
            background: #2563eb; color: white; padding: 1.25rem 2.5rem; border-radius: 3rem;
            font-weight: 800; font-size: 0.8rem; box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
            transition: all 0.2s;
        }
        .btn-refresh:active { transform: scale(0.95); }

        .loader-friendly { width: 18px; height: 18px; border: 3px solid rgba(255,255,255,0.3); border-top: 3px solid #fff; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .reveal { animation: reveal 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        @keyframes reveal { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="min-h-screen pb-32">

    <nav class="p-4 sticky top-0 z-50">
        <div class="max-w-4xl mx-auto bg-white/70 backdrop-blur-xl rounded-[2.5rem] border border-white/50 px-6 py-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <img src="assets/img/logo.svg" alt="Logo" class="w-8 h-8" onerror="this.src='https://www.helloasso.com/assets/img/logos/helloasso-logo.svg'">
                <h1 class="text-sm font-black truncate max-w-[150px] italic uppercase tracking-tighter"><?= htmlspecialchars($campaignConfig['title']) ?></h1>
            </div>
            <a href="admin.php" class="w-10 h-10 flex items-center justify-center rounded-full bg-slate-100 text-slate-400"><i class="fa-solid fa-cog"></i></a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 mt-6 space-y-6">
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- RECETTES -->
            <div class="soft-card p-10 reveal" style="animation-delay: 0.1s;">
                <div class="flex justify-between items-start mb-6">
                    <span class="kpi-label">Recettes Totales</span>
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center"><i class="fa-solid fa-euro-sign"></i></div>
                </div>
                <div id="val-revenue" class="kpi-value">0 €</div>
                <div id="donations-box" class="mt-2 text-xs font-bold text-emerald-600 opacity-0 transition-opacity italic">Dont <span id="val-donations">0 €</span> de dons</div>
                
                <div id="goal-rev-container" class="gauge-container hidden">
                    <div class="gauge-track"><div id="goal-rev-bar" class="gauge-fill bg-emerald-500"></div></div>
                    <div class="flex justify-between mt-3 text-[10px] font-black uppercase text-slate-400">
                        <span id="goal-rev-text">Objectif : --</span>
                        <span id="goal-rev-pct">0%</span>
                    </div>
                </div>
            </div>

            <!-- INSCRIPTIONS -->
            <div class="soft-card p-10 reveal border-l-8 border-l-blue-500" style="animation-delay: 0.2s;">
                <div class="flex justify-between items-start mb-6">
                    <span class="kpi-label">Nombre d'Inscrits</span>
                    <div class="w-10 h-10 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center"><i class="fa-solid fa-users"></i></div>
                </div>
                <div id="val-participants" class="kpi-value">0</div>
                <div id="n1-container" class="mt-2 text-[10px] font-bold text-slate-400 uppercase hidden italic">Vs <span id="val-n1">0</span> l'an passé</div>

                <div id="goal-tix-container" class="gauge-container hidden">
                    <div class="gauge-track"><div id="goal-tix-bar" class="gauge-fill bg-blue-500"></div></div>
                    <div class="flex justify-between mt-3 text-[10px] font-black uppercase text-slate-400">
                        <span id="goal-tix-text">Quota : --</span>
                        <span id="goal-tix-pct">0%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TIMELINE STYLE IMAGE -->
        <section class="soft-card p-10 reveal" style="animation-delay: 0.3s;">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-10 gap-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400">Activité Quotidienne</h3>
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center gap-2 text-[9px] font-black uppercase text-blue-600">
                        <span class="w-3 h-3 rounded bg-blue-600"></span> Inscriptions
                    </div>
                    <div class="flex items-center gap-2 text-[9px] font-black uppercase text-emerald-500">
                        <span class="w-3 h-3 border-2 border-emerald-500 border-dashed rounded"></span> Cumul (€)
                    </div>
                </div>
            </div>
            <div class="h-80"><canvas id="timelineChart"></canvas></div>
        </section>

        <div id="charts-grid" class="grid grid-cols-1 md:grid-cols-2 gap-6"></div>

        <section class="soft-card p-10 reveal" style="animation-delay: 0.4s;">
            <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-8">Flux des commandes</h3>
            <div id="recent-list" class="space-y-4"></div>
        </section>

    </main>

    <div class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50">
        <button onclick="refresh()" id="refresh-btn" class="btn-refresh flex items-center gap-3">
            <span id="refresh-icon"><i class="fa-solid fa-sync-alt"></i></span>
            <span class="uppercase tracking-widest text-[10px] font-black">Actualiser le Board</span>
        </button>
    </div>

    <script>
    const State = { tChart: null };

    function formatValue(val) {
        return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: val % 1 !== 0 ? 2 : 0 }).format(val);
    }

    async function refresh() {
        const icon = document.getElementById('refresh-icon');
        const refreshBtn = document.getElementById('refresh-btn');
        icon.innerHTML = '<div class="loader-friendly"></div>';
        if(refreshBtn) refreshBtn.disabled = true;

        try {
            const response = await fetch(`api.php?campaign=<?= $campaignConfig['slug'] ?>`);
            const res = await response.json();
            if(!res.success) throw new Error(res.error);

            const d = res.data;
            const meta = res.meta;
            const goals = meta.goals || { revenue: 0, tickets: 0, n1: 0 };

            document.getElementById('val-revenue').innerText = formatValue(d.kpi.revenue);
            document.getElementById('val-participants').innerText = d.kpi.participants;

            if (d.kpi.donations > 0) {
                document.getElementById('donations-box').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('val-donations').innerText = formatValue(d.kpi.donations);
            }

            // GESTION JAUGE REVENU
            const gRev = parseFloat(goals.revenue);
            if (gRev > 0) {
                document.getElementById('goal-rev-container').classList.remove('hidden');
                const pct = Math.min(100, (d.kpi.revenue / gRev) * 100);
                document.getElementById('goal-rev-bar').style.width = pct + '%';
                document.getElementById('goal-rev-text').innerText = `Objectif : ${formatValue(gRev)}`;
                document.getElementById('goal-rev-pct').innerText = Math.round(pct) + '%';
            }

            // GESTION JAUGE TICKETS
            const gTix = parseInt(goals.tickets || goals.participants || 0);
            if (gTix > 0) {
                document.getElementById('goal-tix-container').classList.remove('hidden');
                const pct = Math.min(100, (d.kpi.participants / gTix) * 100);
                document.getElementById('goal-tix-bar').style.width = pct + '%';
                document.getElementById('goal-tix-text').innerText = `Quotas : ${gTix} billets`;
                document.getElementById('goal-tix-pct').innerText = Math.round(pct) + '%';
            }

            const n1 = parseInt(goals.n1 || 0);
            if (n1 > 0) {
                document.getElementById('n1-container').classList.remove('hidden');
                document.getElementById('val-n1').innerText = n1;
            }

            renderDynamicCharts(d.charts || []);
            if(d.timeline) renderTimeline(d.timeline);

            if(d.recent) {
                document.getElementById('recent-list').innerHTML = d.recent.map(r => `
                    <div class="flex items-center justify-between p-5 bg-slate-50 rounded-[1.5rem] border border-slate-100">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center font-black text-blue-500 shadow-sm text-[11px]">${r.name ? r.name.charAt(0) : 'A'}</div>
                            <div>
                                <p class="text-sm font-extrabold text-slate-800">${r.name || 'Anonyme'}</p>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">${r.date}</p>
                            </div>
                        </div>
                        <span class="text-[9px] font-black text-blue-600 bg-blue-100/50 px-3 py-1.5 rounded-full uppercase tracking-widest">${r.desc}</span>
                    </div>
                `).join('');
            }

        } catch (e) { console.error(e); } finally { 
            icon.innerHTML = '<i class="fa-solid fa-sync-alt"></i>';
            if(refreshBtn) refreshBtn.disabled = false;
        }
    }

    function renderDynamicCharts(chartsData) {
        const grid = document.getElementById('charts-grid');
        grid.innerHTML = ''; 
        chartsData.forEach((c, i) => {
            const chartId = `chart-${i}`;
            const div = document.createElement('div');
            div.className = 'soft-card p-10 reveal';
            div.style.animationDelay = (0.2 + (i * 0.1)) + 's';
            div.innerHTML = `
                <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-8">${c.title}</h4>
                <div class="${c.type === 'bar' ? 'h-72' : 'h-64'}"><canvas id="${chartId}"></canvas></div>
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
                        backgroundColor: isBar ? '#2563eb' : ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#6366f1', '#f43f5e'], 
                        borderRadius: isBar ? 12 : 0, borderWidth: 0, cutout: '80%'
                    }] 
                },
                options: { 
                    indexAxis: isBar ? 'y' : 'x',
                    maintainAspectRatio: false, 
                    responsive: true,
                    plugins: { 
                        legend: { display: !isBar, position: 'bottom', labels: { color: '#64748b', font: {size: 10, weight: '800'}, padding: 25, usePointStyle: true } },
                        tooltip: { backgroundColor: '#1e293b', padding: 15, cornerRadius: 15 }
                    },
                    scales: isBar ? { x: { display: false }, y: { ticks:{color:'#1e293b', font:{size: 11, weight:'800'}}, grid:{display:false} } } : {}
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
                        label: 'Inscriptions', 
                        data: data.map(x => x.pax), 
                        borderColor: '#2563eb', 
                        backgroundColor: 'rgba(37, 99, 235, 0.15)',
                        fill: true, // Courbe pleine comme sur l'image
                        tension: 0.4, 
                        yAxisID: 'y', 
                        pointRadius: 4, 
                        pointBackgroundColor: '#2563eb',
                        borderWidth: 4
                    },
                    { 
                        label: 'Cumul (€)', 
                        data: data.map(x => x.cumulative), 
                        borderColor: '#10b981',
                        borderDash: [6, 4], // Ligne en pointillés comme sur l'image
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
                        title: { display: true, text: 'Participants', color: '#2563eb', font: {size: 9, weight: '800'} }
                    },
                    y1: { 
                        position:'right', 
                        grid:{display:false}, 
                        ticks:{color:'#10b981', font:{size: 10, weight:'800'}},
                        title: { display: true, text: 'Cumul (€)', color: '#10b981', font: {size: 9, weight: '800'} }
                    }
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