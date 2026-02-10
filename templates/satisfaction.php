<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Satisfaction — <?= htmlspecialchars($currentCamp['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
        #notifications-container { position: fixed; bottom: 2rem; right: 2rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.75rem; }
        .notification { background: white; padding: 1rem 1.5rem; border-radius: 1.25rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); border-left: 4px solid #2563eb; display: flex; align-items: center; gap: 0.75rem; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; animation: slideIn 0.3s ease-out forwards; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .admin-card { background: white; border-radius: 2rem; border: 1px solid #edf2f7; }
        .input-soft { background: #f1f5f9; border: 2px solid transparent; border-radius: 1.25rem; padding: 12px 16px; font-weight: 700; width: 100%; outline: none; transition: 0.2s; }
        .input-soft:focus { border-color: #2563eb; background: white; }
        .toggle-btn { width: 44px; height: 24px; background: #cbd5e1; border-radius: 20px; position: relative; cursor: pointer; }
        .toggle-btn.active { background: #2563eb; }
        .toggle-btn::after { content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; background: white; border-radius: 50%; transition: 0.3s; }
        .toggle-btn.active::after { transform: translateX(20px); }
    </style>
</head>
<body class="pb-32">
    <div id="notifications-container"></div>
    <nav class="p-6 bg-white border-b border-slate-100 sticky top-0 z-50 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-4">
            <a href="admin.php" class="flex items-center gap-2">
                <img src="assets/img/logo.svg" alt="HelloBoard" class="w-8 h-8">
                <h1 class="font-black italic uppercase text-slate-900">Console Admin</h1>
            </a>
            <div class="h-6 w-px bg-slate-200 mx-2"></div>
            <h2 class="font-black italic uppercase text-amber-600 text-sm tracking-widest"><?= htmlspecialchars($currentCamp['title']) ?> — Satisfaction</h2>
        </div>
        <a href="admin.php" class="text-xs font-black uppercase text-slate-400 hover:text-slate-900 transition">Retour</a>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- CONFIGURATION & FILTRES -->
            <div class="lg:col-span-1 space-y-8">
                <div class="admin-card p-8">
                    <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic tracking-widest border-b border-slate-50 pb-4">Questions du questionnaire</h3>
                    <div class="space-y-4">
                        <?php foreach($questions as $i => $q): ?>
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase block mb-1">Question <?= $i+1 ?></label>
                                <input type="text" class="question-input input-soft !py-3 !text-xs" value="<?= htmlspecialchars($q['label']) ?>" data-index="<?= $i ?>">
                            </div>
                        <?php endforeach; ?>
                        <button onclick="saveQuestions()" id="btn-save-questions" class="w-full bg-slate-100 text-slate-600 py-4 rounded-2xl font-black uppercase text-xs hover:bg-slate-200 transition mt-4">
                            Enregistrer les questions
                        </button>
                    </div>
                </div>

                <div class="admin-card p-8 bg-slate-50/50">
                    <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic tracking-widest border-b border-slate-50 pb-4">Ciblage des destinataires</h3>
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase text-slate-600">Exclure si déjà envoyé (cette camp.)</span>
                            <div class="toggle-btn active" id="filter-exclude-sent" onclick="this.classList.toggle('active'); fetchRecipients()"></div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase text-slate-600">Exclure si déjà sollicité (global)</span>
                            <div class="toggle-btn" id="filter-exclude-ever" onclick="this.classList.toggle('active'); fetchRecipients()"></div>
                        </div>
                        <div class="pt-4 border-t border-slate-100">
                            <button onclick="fetchRecipients()" id="btn-refresh-recipients" class="w-full bg-white border border-slate-200 text-slate-600 py-4 rounded-2xl font-black uppercase text-xs hover:bg-slate-50 transition">
                                <i class="fa-solid fa-sync-alt mr-2"></i> Actualiser la liste
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LISTE DESTINATAIRES & ENVOI -->
            <div class="lg:col-span-2 space-y-8">
                <div class="admin-card p-8">
                    <div class="flex justify-between items-center mb-8 border-b border-slate-50 pb-4">
                        <h3 class="text-xs font-black uppercase text-slate-400 italic tracking-widest">Destinataires éligibles</h3>
                        <span class="bg-blue-100 text-blue-600 text-[10px] font-black px-3 py-1 rounded-full" id="recipient-count">0</span>
                    </div>

                    <div class="overflow-hidden mb-8">
                        <div class="max-h-[400px] overflow-y-auto pr-4 space-y-2" id="recipients-list">
                            <div class="py-10 text-center text-slate-300 font-bold italic text-sm">Chargement des destinataires...</div>
                        </div>
                    </div>

                    <div class="pt-8 border-t border-slate-50 flex flex-col items-center">
                        <button onclick="startSending()" id="btn-send-all" class="w-full max-w-md bg-slate-900 text-white py-6 rounded-[2rem] font-black uppercase text-xs tracking-widest shadow-xl hover:bg-amber-600 transition disabled:opacity-50" disabled>
                            Lancer la campagne de satisfaction
                        </button>
                        <div id="sending-status" class="mt-4 hidden text-center">
                            <p class="text-[10px] font-black uppercase text-slate-400 animate-pulse">Envoi en cours, ne fermez pas cette page...</p>
                        </div>
                    </div>
                </div>

                <!-- VERBATIMS -->
                <div class="admin-card p-8">
                    <h3 class="text-xs font-black uppercase text-slate-400 mb-8 italic tracking-widest border-b border-slate-50 pb-4">Derniers Verbatims</h3>
                    <div class="space-y-6">
                        <?php if (empty($responses)): ?>
                            <div class="text-center py-10 bg-slate-50 rounded-[2rem] border-2 border-dashed border-slate-100">
                                <p class="text-slate-300 font-black uppercase text-[10px] italic">Aucune réponse collectée pour le moment.</p>
                            </div>
                        <?php else: foreach($responses as $r):
                            $avg = ($r['q1'] + $r['q2'] + $r['q3'] + $r['q4'] + $r['q5'] - 5) / 20.0 * 100.0;
                        ?>
                            <div class="bg-slate-50 p-6 rounded-[1.5rem] border border-slate-100">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <p class="text-xs font-black text-slate-900">Avis de <?= htmlspecialchars($r['payer_name']) ?></p>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase mt-1">Sur : <?= htmlspecialchars($r['item_name']) ?> — <?= date('d/m/Y', strtotime($r['submitted_at'])) ?></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg font-black <?= $avg >= 75 ? 'text-emerald-500' : ($avg >= 50 ? 'text-amber-500' : 'text-red-500') ?>"><?= round($avg) ?>%</span>
                                        <div class="flex text-[8px] gap-0.5">
                                            <?php for($i=1;$i<=5;$i++): ?>
                                                <i class="fa-solid fa-star <?= ($r['q1']+$r['q2']+$r['q3']+$r['q4']+$r['q5'])/5 >= $i ? 'text-amber-400' : 'text-slate-200' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-sm text-slate-600 italic leading-relaxed">"<?= htmlspecialchars($r['comment']) ?: 'Pas de commentaire' ?>."</p>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const campaign = "<?= $slug ?>";
        let recipients = [];

        function notify(message, type = 'info') {
            const container = document.getElementById('notifications-container');
            const div = document.createElement('div');
            div.className = 'notification';
            if (type === 'success') div.style.borderColor = '#10b981';
            if (type === 'error') div.style.borderColor = '#ef4444';

            div.innerHTML = `
                <i class="fa-solid ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-circle-exclamation' : 'fa-info-circle')}" style="color: ${type === 'success' ? '#10b981' : (type === 'error' ? '#ef4444' : '#2563eb')}"></i>
                <span class="flex-1">${message}</span>
            `;
            container.appendChild(div);
            setTimeout(() => {
                div.style.opacity = '0';
                div.style.transform = 'translateX(100%)';
                div.style.transition = '0.3s';
                setTimeout(() => div.remove(), 300);
            }, 5000);
        }

        async function saveQuestions() {
            const btn = document.getElementById('btn-save-questions');
            const oldText = btn.innerText;
            btn.innerText = "Enregistrement...";
            btn.disabled = true;

            const questions = [];
            document.querySelectorAll('.question-input').forEach(input => {
                questions.push({ label: input.value });
            });

            try {
                const res = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        save_satisfaction_questions: '1',
                        campaign: campaign,
                        'questions': JSON.stringify(questions)
                    })
                });
                notify("Questions enregistrées avec succès !", "success");
            } catch (e) {
                notify("Erreur lors de l'enregistrement.", "error");
            } finally {
                btn.innerText = oldText;
                btn.disabled = false;
            }
        }

        async function fetchRecipients() {
            const listContainer = document.getElementById('recipients-list');
            listContainer.innerHTML = '<div class="py-10 text-center text-slate-300 font-bold italic text-sm animate-pulse">Scan HelloAsso en cours...</div>';

            const excludeSent = document.getElementById('filter-exclude-sent').classList.contains('active') ? '1' : '0';
            const excludeEver = document.getElementById('filter-exclude-ever').classList.contains('active') ? '1' : '0';

            try {
                const res = await fetch(`admin.php?action=satisfaction_recipients&campaign=${campaign}&exclude_sent=${excludeSent}&exclude_ever=${excludeEver}`);
                recipients = await res.json();

                document.getElementById('recipient-count').innerText = recipients.length;
                document.getElementById('btn-send-all').disabled = (recipients.length === 0);
                document.getElementById('btn-send-all').innerText = recipients.length > 0 ? `Lancer pour ${recipients.length} destinataires` : "Aucun destinataire éligible";

                if (recipients.length === 0) {
                    listContainer.innerHTML = '<div class="py-10 text-center text-slate-300 font-bold italic text-sm">Tous les contacts éligibles ont déjà été sollicités ou aucun contact trouvé.</div>';
                    return;
                }

                listContainer.innerHTML = recipients.map(r => `
                    <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between gap-3 text-[10px] border border-transparent" id="row-${r.orderId}">
                        <div class="truncate">
                            <p class="font-black text-slate-700 truncate uppercase">${r.lastName} ${r.firstName}</p>
                            <p class="text-slate-400 truncate">${r.email}</p>
                        </div>
                        <span class="text-[9px] font-black text-slate-300 bg-white px-2 py-1 rounded-lg border border-slate-100">${r.date.substring(0, 10)}</span>
                    </div>
                `).join('');
            } catch (e) {
                listContainer.innerHTML = '<div class="py-10 text-center text-red-400 font-bold italic text-sm">Erreur lors du chargement des destinataires.</div>';
            }
        }

        async function startSending() {
            if (!confirm(`Voulez-vous vraiment envoyer le questionnaire à ${recipients.length} personnes ?`)) return;

            const btn = document.getElementById('btn-send-all');
            btn.disabled = true;
            const statusContainer = document.getElementById('sending-status');
            statusContainer.classList.remove('hidden');

            let sentCount = 0;
            let currentInBatch = 0;
            let batchNum = 1;

            for (const r of recipients) {
                const row = document.getElementById('row-' + r.orderId);
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('bg-blue-50', 'border-blue-200');
                }

                try {
                    currentInBatch++;
                    statusContainer.innerHTML = `<p class="text-[10px] font-black uppercase text-slate-400 text-center animate-pulse">Envoi du paquet ${batchNum} (${currentInBatch}/10)...</p>`;

                    const res = await fetch('admin.php?action=satisfaction_send_one', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            campaign: campaign,
                            orderId: r.orderId,
                            email: r.email,
                            firstName: r.firstName,
                            lastName: r.lastName,
                            itemName: r.itemName
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        sentCount++;
                        if (row) {
                            row.classList.remove('bg-blue-50', 'border-blue-200');
                            row.classList.add('opacity-40', 'bg-emerald-50/30');
                        }
                    } else {
                        if (row) row.classList.replace('bg-blue-50', 'bg-red-50');
                    }
                } catch (e) {
                    console.error(e);
                }

                if (currentInBatch >= 10) {
                    batchNum++;
                    currentInBatch = 0;
                    statusContainer.innerHTML = `<p class="text-[10px] font-black uppercase text-blue-500 text-center">Temporisation de 5s...</p>`;
                    await new Promise(r => setTimeout(r, 5000));
                } else {
                    await new Promise(r => setTimeout(r, 800));
                }
            }

            statusContainer.innerHTML = '<p class="text-[10px] font-black uppercase text-emerald-500 text-center">Campagne terminée !</p>';
            btn.innerText = "Campagne terminée";
            notify(`${sentCount} emails envoyés avec succès !`, "success");
        }

        window.onload = fetchRecipients;
    </script>
</body>
</html>
