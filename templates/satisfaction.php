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
        .progress-bar { height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; background: #2563eb; width: 0%; transition: width 0.3s ease; }
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
        <!-- STATS BAR -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-12 animate-fade-in">
            <div class="admin-card p-6 text-center">
                <p class="text-[9px] font-black uppercase text-slate-400 mb-1 italic tracking-widest">Emails envoyés</p>
                <h4 class="text-3xl font-black text-slate-900"><?= $stats['total_sent'] ?></h4>
            </div>
            <div class="admin-card p-6 text-center border-b-4 border-blue-500">
                <p class="text-[9px] font-black uppercase text-slate-400 mb-1 italic tracking-widest">Emails ouverts</p>
                <div class="flex items-center justify-center gap-2">
                    <h4 class="text-3xl font-black text-slate-900"><?= $stats['total_read'] ?></h4>
                    <span class="text-[10px] font-black text-blue-500">(<?= $stats['total_sent'] > 0 ? round($stats['total_read']/$stats['total_sent']*100) : 0 ?>%)</span>
                </div>
            </div>
            <div class="admin-card p-6 text-center border-b-4 border-emerald-500">
                <p class="text-[9px] font-black uppercase text-slate-400 mb-1 italic tracking-widest">Réponses</p>
                <div class="flex items-center justify-center gap-2">
                    <h4 class="text-3xl font-black text-slate-900"><?= $stats['total_responses'] ?></h4>
                    <span class="text-[10px] font-black text-emerald-500">(<?= $stats['total_sent'] > 0 ? round($stats['total_responses']/$stats['total_sent']*100) : 0 ?>%)</span>
                </div>
            </div>
            <div class="admin-card p-6 text-center border-b-4 border-amber-500">
                <p class="text-[9px] font-black uppercase text-slate-400 mb-1 italic tracking-widest">Satisfaction</p>
                <h4 class="text-3xl font-black text-slate-900"><?= round($stats['avg_csat'] ?? 0) ?>%</h4>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- CONFIGURATION & FILTRES -->
            <div class="lg:col-span-1 space-y-8">
                <div class="admin-card p-8 bg-slate-900 text-white">
                    <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic tracking-widest border-b border-slate-800 pb-4">Modèle d'Email</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="text-[9px] font-black text-slate-500 uppercase block mb-1">Objet du mail</label>
                            <input type="text" id="email-subject" class="w-full bg-slate-800 border-2 border-transparent focus:border-blue-500 rounded-xl p-3 text-xs font-bold outline-none transition" value="<?= htmlspecialchars($mailingDraft['subject']) ?>">
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-500 uppercase block mb-1">Message</label>
                            <textarea id="email-body" rows="8" class="w-full bg-slate-800 border-2 border-transparent focus:border-blue-500 rounded-xl p-4 text-[11px] font-medium outline-none transition leading-relaxed"><?= htmlspecialchars($mailingDraft['body']) ?></textarea>
                            <div class="flex flex-wrap gap-1 mt-2">
                                <span class="text-[8px] bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded font-black">#{{PRENOM}}</span>
                                <span class="text-[8px] bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded font-black">#{{NOM}}</span>
                                <span class="text-[8px] bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded font-black">#{{NOM_CAMPAGNE}}</span>
                                <span class="text-[8px] bg-blue-900 text-blue-300 px-1.5 py-0.5 rounded font-black">#{{SURVEY_URL}}</span>
                            </div>
                        </div>
                        <button onclick="saveMailingDraft()" id="btn-save-draft" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black uppercase text-xs shadow-lg shadow-blue-900/20 hover:bg-blue-500 transition">
                            Sauvegarder le modèle
                        </button>
                    </div>
                </div>

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

                <div class="admin-card p-8 bg-blue-50/30 border-blue-100">
                    <h3 class="text-xs font-black uppercase text-blue-400 mb-6 italic tracking-widest border-b border-blue-50 pb-4">Mode Test (BAT)</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4">
                            <input type="text" id="test-firstname" class="input-soft !bg-white !text-xs !py-3" placeholder="Prénom (Test)">
                            <input type="text" id="test-lastname" class="input-soft !bg-white !text-xs !py-3" placeholder="Nom (Test)">
                            <input type="email" id="test-email" class="input-soft !bg-white !text-xs !py-3" placeholder="Email de test">
                        </div>
                        <button onclick="sendTest()" id="btn-send-test" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black uppercase text-[10px] shadow-lg shadow-blue-200 hover:bg-blue-700 transition flex items-center justify-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i> Envoyer un test
                        </button>
                    </div>
                </div>
            </div>

            <!-- LISTE DESTINATAIRES & ENVOI -->
            <div class="lg:col-span-2 space-y-8">
                <div class="admin-card p-8">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-xs font-black uppercase text-slate-400 italic tracking-widest">Destinataires éligibles</h3>
                            <p class="text-[9px] text-slate-300 font-bold uppercase mt-1">Sont exclus : annulés, déjà sollicités, ou événement futur.</p>
                        </div>
                        <span class="bg-blue-100 text-blue-600 text-[10px] font-black px-3 py-1 rounded-full" id="recipient-count">0</span>
                    </div>

                    <div id="sending-progress-container" class="hidden mb-8">
                        <div class="flex justify-between text-[10px] font-black uppercase mb-2">
                            <span class="text-blue-600">Progression de l'envoi</span>
                            <span id="sending-percent" class="text-slate-400">0%</span>
                        </div>
                        <div class="progress-bar"><div id="sending-progress-fill" class="progress-fill"></div></div>
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
                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <span class="text-lg font-black <?= $avg >= 75 ? 'text-emerald-500' : ($avg >= 50 ? 'text-amber-500' : 'text-red-500') ?>"><?= round($avg) ?>%</span>
                                            <div class="flex text-[8px] gap-0.5">
                                                <?php for($i=1;$i<=5;$i++): ?>
                                                    <i class="fa-solid fa-star <?= ($r['q1']+$r['q2']+$r['q3']+$r['q4']+$r['q5'])/5 >= $i ? 'text-amber-400' : 'text-slate-200' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <a href="admin.php?action=satisfaction&campaign=<?= $slug ?>&delete=<?= $r['token'] ?>" onclick="return confirm('Supprimer cette participation ?')" class="w-8 h-8 flex items-center justify-center bg-red-50 text-red-300 rounded-lg hover:bg-red-500 hover:text-white transition shadow-sm">
                                            <i class="fa-solid fa-trash-can text-[10px]"></i>
                                        </a>
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

        const tokenMap = <?= json_encode($tokens) ?>;
        const responseMap = <?= json_encode($responses) ?>;

        async function saveMailingDraft() {
            const btn = document.getElementById('btn-save-draft');
            const oldText = btn.innerText;
            btn.innerText = "Sauvegarde...";
            btn.disabled = true;

            try {
                await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        save_satisfaction_mailing_draft: '1',
                        campaign: campaign,
                        subject: document.getElementById('email-subject').value,
                        body: document.getElementById('email-body').value
                    })
                });
                notify("Modèle d'email enregistré !", "success");
            } catch (e) {
                notify("Erreur lors de la sauvegarde.", "error");
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
                const data = await res.json();

                if (!data.isEligible) {
                    document.getElementById('recipient-count').innerText = "0";
                    document.getElementById('btn-send-all').disabled = true;
                    document.getElementById('btn-send-all').innerText = "Non éligible";
                    listContainer.innerHTML = `<div class="py-10 px-6 text-center"><i class="fa-solid fa-clock text-3xl text-slate-100 mb-4"></i><p class="text-slate-400 font-bold text-sm">${data.reason}</p><p class="text-slate-300 text-[10px] uppercase mt-2 font-black italic">Le questionnaire pourra être envoyé après cette date.</p></div>`;
                    return;
                }

                recipients = data.recipients || [];
                document.getElementById('recipient-count').innerText = recipients.length;
                document.getElementById('btn-send-all').disabled = (recipients.length === 0);
                document.getElementById('btn-send-all').innerText = recipients.length > 0 ? `Lancer pour ${recipients.length} destinataires` : "Aucun destinataire éligible";

                if (recipients.length === 0) {
                    listContainer.innerHTML = '<div class="py-10 text-center text-slate-300 font-bold italic text-sm">Tous les contacts éligibles ont déjà été sollicités ou aucun contact trouvé.</div>';
                    return;
                }

                listContainer.innerHTML = recipients.map(r => {
                    const haToken = tokenMap.find(t => t.order_id === r.orderId);
                    const haResp = responseMap.find(resp => resp.order_id === r.orderId);

                    let statusHtml = '';
                    if (haToken) {
                        statusHtml += `<i class="fa-solid fa-paper-plane text-emerald-500 mr-2" title="Envoyé le ${haToken.sent_at}"></i>`;
                        if (haToken.read_at) statusHtml += `<i class="fa-solid fa-eye text-blue-500 mr-2" title="Lu le ${haToken.read_at}"></i>`;
                        if (haResp) statusHtml += `<i class="fa-solid fa-star text-amber-500 mr-2" title="Répondu"></i>`;
                    }

                    return `
                        <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between gap-3 text-[10px] border border-transparent" id="row-${r.orderId}">
                            <div class="truncate">
                                <p class="font-black text-slate-700 truncate uppercase">${r.lastName} ${r.firstName}</p>
                                <p class="text-slate-400 truncate">${r.email}</p>
                            </div>
                            <div class="flex items-center">
                                ${statusHtml}
                                <span class="text-[9px] font-black text-slate-300 bg-white px-2 py-1 rounded-lg border border-slate-100">${r.date.substring(0, 10)}</span>
                            </div>
                        </div>
                    `;
                }).join('');
            } catch (e) {
                listContainer.innerHTML = '<div class="py-10 text-center text-red-400 font-bold italic text-sm">Erreur lors du chargement des destinataires.</div>';
            }
        }

        async function sendTest() {
            const email = document.getElementById('test-email').value;
            if (!email) return notify("Veuillez saisir un email de test.", "error");

            const btn = document.getElementById('btn-send-test');
            const oldHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Envoi...';

            try {
                const res = await fetch('admin.php?action=satisfaction_send_one', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        campaign: campaign,
                        email: email,
                        firstName: document.getElementById('test-firstname').value || 'Test',
                        lastName: document.getElementById('test-lastname').value || 'TestUser',
                        subject: document.getElementById('email-subject').value,
                        body: document.getElementById('email-body').value,
                        is_test: '1'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    notify("Email de test envoyé avec succès !", "success");
                } else {
                    notify("Erreur : " + data.error, "error");
                }
            } catch (e) {
                notify("Erreur technique lors de l'envoi.", "error");
            } finally {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        }

        async function startSending() {
            if (!confirm(`Voulez-vous vraiment envoyer le questionnaire à ${recipients.length} personnes ?`)) return;

            const btn = document.getElementById('btn-send-all');
            btn.disabled = true;
            const statusContainer = document.getElementById('sending-status');
            statusContainer.classList.remove('hidden');

            const progressContainer = document.getElementById('sending-progress-container');
            progressContainer.classList.remove('hidden');
            const progressFill = document.getElementById('sending-progress-fill');
            const percentLabel = document.getElementById('sending-percent');

            let sentCount = 0;
            let currentInBatch = 0;
            let batchNum = 1;

            const subject = document.getElementById('email-subject').value;
            const body = document.getElementById('email-body').value;

            for (let i = 0; i < recipients.length; i++) {
                const r = recipients[i];
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
                            itemName: r.itemName,
                            subject: subject,
                            body: body
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

                    // Update Progress
                    const pct = Math.round(((i + 1) / recipients.length) * 100);
                    progressFill.style.width = pct + '%';
                    percentLabel.innerText = pct + '%';

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
