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
        .collapsible-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .collapsible-content.open { max-height: 1000px; }
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

            <!-- COLONNE GAUCHE : CONFIGURATION & VERBATIMS -->
            <div class="lg:col-span-2 space-y-8">

                <!-- MODELE D'EMAIL (COLLAPSIBLE) -->
                <div class="admin-card overflow-hidden">
                    <button onclick="toggleCollapsible('email-draft-collapse')" class="w-full p-8 flex justify-between items-center bg-white hover:bg-slate-50 transition border-b border-slate-50">
                        <div class="flex items-center gap-4">
                            <i class="fa-solid fa-envelope-open-text text-amber-500"></i>
                            <h3 class="text-xs font-black uppercase italic tracking-widest text-slate-400">Modèle d'Email de sollicitation</h3>
                        </div>
                        <i class="fa-solid fa-chevron-down transition-transform duration-300 text-slate-300" id="email-draft-icon"></i>
                    </button>
                    <div id="email-draft-collapse" class="collapsible-content">
                        <div class="p-8 space-y-4">
                            <div>
                                <label class="text-[9px] font-black text-slate-500 uppercase block mb-1 tracking-widest">Objet du mail</label>
                                <input type="text" id="email-subject" class="input-soft" value="<?= htmlspecialchars($mailingDraft['subject']) ?>">
                            </div>
                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <label class="text-[9px] font-black text-slate-500 uppercase block tracking-widest">Message</label>
                                    <?php if (!empty($globals['mistralApiKey'])): ?>
                                        <button type="button" onclick="toggleAiPrompt()" class="bg-indigo-500 text-white px-3 py-1 rounded-lg font-black uppercase text-[10px] hover:bg-indigo-600 transition flex items-center gap-2 shadow-sm">
                                            <i class="fa-solid fa-wand-magic-sparkles"></i> Rédiger avec l'IA
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($globals['mistralApiKey'])): ?>
                                    <div id="ai-prompt-container" class="hidden mb-4 p-4 bg-slate-50 rounded-2xl border border-slate-100 animate-fade-in">
                                        <label class="text-[9px] font-black text-slate-400 uppercase block mb-2 italic text-center">Que doit contenir l'email de satisfaction ?</label>
                                        <div class="flex gap-2">
                                            <input type="text" id="ai-prompt-input" class="input-soft !bg-white !text-xs flex-1" placeholder="Ex: Remercie chaleureusement, explique que l'avis aide l'asso.">
                                            <button onclick="generateWithAi()" id="btn-generate-ai" class="bg-blue-600 text-white px-4 py-2 rounded-xl font-black uppercase text-[10px] hover:bg-blue-700 transition">Générer</button>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <textarea id="email-body" rows="8" class="input-soft font-medium leading-relaxed"><?= htmlspecialchars($mailingDraft['body']) ?></textarea>
                                <div class="flex flex-wrap gap-1 mt-2">
                                    <span class="text-[8px] bg-slate-100 text-slate-400 px-1.5 py-0.5 rounded font-black">#{{PRENOM}}</span>
                                    <span class="text-[8px] bg-slate-100 text-slate-400 px-1.5 py-0.5 rounded font-black">#{{NOM}}</span>
                                    <span class="text-[8px] bg-slate-100 text-slate-400 px-1.5 py-0.5 rounded font-black">#{{NOM_CAMPAGNE}}</span>
                                    <span class="text-[8px] bg-blue-100 text-blue-400 px-1.5 py-0.5 rounded font-black">#{{SURVEY_URL}}</span>
                                </div>
                            </div>
                            <button onclick="saveMailingDraft()" id="btn-save-draft" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black uppercase text-xs shadow-lg shadow-blue-900/20 hover:bg-blue-500 transition">
                                Sauvegarder le modèle
                            </button>
                        </div>
                    </div>
                </div>

                <!-- QUESTIONS (COLLAPSIBLE) -->
                <div class="admin-card overflow-hidden">
                    <button onclick="toggleCollapsible('questions-collapse')" class="w-full p-8 flex justify-between items-center bg-white hover:bg-slate-50 transition">
                        <div class="flex items-center gap-4">
                            <i class="fa-solid fa-list-check text-blue-600"></i>
                            <h3 class="text-xs font-black uppercase text-slate-400 italic tracking-widest">Structure du questionnaire</h3>
                        </div>
                        <i class="fa-solid fa-chevron-down transition-transform duration-300" id="questions-icon"></i>
                    </button>
                    <div id="questions-collapse" class="collapsible-content">
                        <div class="p-8 pt-0">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach($questions as $i => $q): ?>
                                    <div>
                                        <label class="text-[9px] font-black text-slate-400 uppercase block mb-1 italic">Question <?= $i+1 ?></label>
                                        <input type="text" class="question-input input-soft !py-3 !text-xs" value="<?= htmlspecialchars($q['label']) ?>" data-index="<?= $i ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button onclick="saveQuestions()" id="btn-save-questions" class="w-full bg-slate-100 text-slate-600 py-4 rounded-2xl font-black uppercase text-xs hover:bg-slate-200 transition mt-6">
                                Enregistrer les questions
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- COLONNE DROITE : STATS & ENVOI -->
            <div class="space-y-8">

                <!-- ENVOI & PROGRESSION -->
                <div class="admin-card p-8">
                    <div id="sending-progress-container" class="hidden mb-6">
                        <div class="flex justify-between text-[10px] font-black uppercase mb-2">
                            <span class="text-blue-600">Envoi en cours</span>
                            <span id="sending-percent" class="text-slate-400">0%</span>
                        </div>
                        <div class="progress-bar"><div id="sending-progress-fill" class="progress-fill"></div></div>
                    </div>

                    <button onclick="startSending()" id="btn-send-all" class="w-full bg-slate-900 text-white py-6 rounded-[2rem] font-black uppercase text-xs tracking-widest shadow-xl hover:bg-amber-600 transition disabled:opacity-50" disabled>
                        Lancer la campagne
                    </button>
                    <div id="sending-status" class="mt-4 hidden text-center">
                        <p class="text-[10px] font-black uppercase text-slate-400 animate-pulse italic">Traitement par paquets...</p>
                    </div>
                </div>

                <!-- FILTRES ET LISTE -->
                <div class="admin-card p-8">
                    <div class="flex justify-between items-start mb-6 border-b border-slate-50 pb-4">
                        <h3 class="text-xs font-black uppercase text-slate-400 italic tracking-widest">Liste des payeurs</h3>
                        <a href="admin.php?action=satisfaction_export_csv&campaign=<?= $slug ?>" class="text-[9px] font-black uppercase text-blue-500 hover:underline">
                            <i class="fa-solid fa-file-csv mr-1"></i> Export Logs
                        </a>
                    </div>

                    <div class="space-y-4 mb-6">
                        <div class="flex items-center justify-between">
                            <span class="text-[9px] font-black uppercase text-slate-400">Ignorer si déjà envoyé pour ce board</span>
                            <div class="toggle-btn active" id="filter-exclude-sent" onclick="this.classList.toggle('active'); fetchRecipients()"></div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[9px] font-black uppercase text-slate-400">Exclure si déjà sollicité auparavant (global)</span>
                            <div class="toggle-btn" id="filter-exclude-ever" onclick="this.classList.toggle('active'); fetchRecipients()"></div>
                        </div>
                    </div>

                    <div class="max-h-[300px] overflow-y-auto pr-2 space-y-2 mb-6" id="recipients-list">
                        <div class="py-10 text-center text-slate-200 font-black uppercase text-[10px] italic">Chargement...</div>
                    </div>

                    <button onclick="fetchRecipients()" class="w-full py-3 bg-slate-50 text-slate-400 rounded-xl font-black uppercase text-[10px] hover:bg-slate-100 transition">
                        <i class="fa-solid fa-sync-alt mr-2"></i> Actualiser
                    </button>
                </div>

                <!-- MODE TEST (BAT) -->
                <div class="admin-card p-8 bg-blue-50/30 border-blue-100">
                    <h3 class="text-xs font-black uppercase text-blue-400 mb-6 italic tracking-widest border-b border-blue-50 pb-4">Envoi d'un test (BAT)</h3>
                    <div class="space-y-3">
                        <input type="text" id="test-firstname" class="input-soft !bg-white !text-[10px] !py-3" placeholder="Prénom (Test)">
                        <input type="text" id="test-lastname" class="input-soft !bg-white !text-[10px] !py-3" placeholder="Nom (Test)">
                        <input type="email" id="test-email" class="input-soft !bg-white !text-[10px] !py-3" placeholder="Email de test">
                        <button onclick="sendTest()" id="btn-send-test" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black uppercase text-[10px] shadow-lg shadow-blue-200 hover:bg-blue-700 transition flex items-center justify-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i> Tester le rendu
                        </button>
                    </div>
                </div>

                <!-- ENVOI MANUEL (UNITAIRE) -->
                <div class="admin-card p-8 border-emerald-100 bg-emerald-50/20">
                    <h3 class="text-xs font-black uppercase text-emerald-600 mb-6 italic tracking-widest border-b border-emerald-50 pb-4">Envoi manuel unitaire</h3>
                    <div class="space-y-3">
                        <input type="text" id="manual-firstname" class="input-soft !bg-white !text-[10px] !py-3" placeholder="Prénom">
                        <input type="text" id="manual-lastname" class="input-soft !bg-white !text-[10px] !py-3" placeholder="Nom">
                        <input type="email" id="manual-email" class="input-soft !bg-white !text-[10px] !py-3" placeholder="Email du destinataire">
                        <button onclick="sendManual()" id="btn-send-manual" class="w-full bg-emerald-600 text-white py-4 rounded-2xl font-black uppercase text-[10px] shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition flex items-center justify-center gap-2">
                            <i class="fa-solid fa-user-plus"></i> Envoyer le questionnaire
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        const campaign = "<?= $slug ?>";
        let recipients = [];

        function toggleAiPrompt() {
            const container = document.getElementById('ai-prompt-container');
            container.classList.toggle('hidden');
            if (!container.classList.contains('hidden')) {
                document.getElementById('ai-prompt-input').focus();
            }
        }

        async function generateWithAi() {
            const prompt = document.getElementById('ai-prompt-input').value;
            if (!prompt) return notify("Veuillez saisir une instruction.", "error");

            const btn = document.getElementById('btn-generate-ai');
            const oldHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>';

            try {
                const res = await fetch('admin.php?action=ai_generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        campaign: campaign,
                        prompt: prompt,
                        context: 'Satisfaction'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('email-body').value = data.body;
                    document.getElementById('ai-prompt-container').classList.add('hidden');
                    notify("Message généré !", "success");
                } else {
                    notify("Erreur : " + data.error, "error");
                }
            } catch (e) {
                console.error(e);
                notify("Erreur technique : " + e.message, "error");
            } finally {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        }

        function toggleCollapsible(id) {
            const content = document.getElementById(id);
            const icon = document.getElementById(id.replace('collapse', 'icon'));
            content.classList.toggle('open');
            if (icon) icon.style.transform = content.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
        }

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
                notify("Structure du questionnaire enregistrée !", "success");
            } catch (e) {
                notify("Erreur lors de l'enregistrement.", "error");
            } finally {
                btn.innerText = oldText;
                btn.disabled = false;
            }
        }

        const tokenMap = <?= json_encode($tokens) ?>;
        const responseMap = [];

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
            listContainer.innerHTML = '<div class="py-10 text-center text-slate-200 font-black uppercase text-[10px] italic animate-pulse">Scan HelloAsso...</div>';

            const excludeSent = document.getElementById('filter-exclude-sent').classList.contains('active') ? '1' : '0';
            const excludeEver = document.getElementById('filter-exclude-ever').classList.contains('active') ? '1' : '0';

            try {
                const res = await fetch(`admin.php?action=satisfaction_recipients&campaign=${campaign}&exclude_sent=${excludeSent}&exclude_ever=${excludeEver}`);
                const data = await res.json();

                if (!data.isEligible) {
                    document.getElementById('btn-send-all').disabled = true;
                    document.getElementById('btn-send-all').innerText = "Non éligible";
                    listContainer.innerHTML = `<div class="py-10 px-4 text-center"><p class="text-slate-400 font-bold text-[10px] uppercase">${data.reason}</p></div>`;
                    return;
                }

                recipients = data.recipients || [];
                document.getElementById('btn-send-all').disabled = (recipients.length === 0);
                document.getElementById('btn-send-all').innerText = recipients.length > 0 ? `Lancer pour ${recipients.length} contacts` : "Aucun destinataire";

                if (recipients.length === 0) {
                    listContainer.innerHTML = '<div class="py-10 text-center text-slate-200 font-black uppercase text-[10px] italic">Tout a déjà été envoyé.</div>';
                    return;
                }

                listContainer.innerHTML = recipients.map(r => {
                    const haToken = tokenMap.find(t => t.order_id == r.orderId);
                    const haResp = responseMap.find(resp => resp.order_id == r.orderId);
                    const isSent = !!haToken;
                    const isRead = !!(haToken && haToken.read_at);
                    const isReplied = !!haResp;

                    let statusHtml = `
                        <div class="flex gap-1 shrink-0">
                            ${isSent ? `
                                <button onclick="resendOne('${r.orderId}', '${r.email}', '${r.firstName.replace(/'/g, "\\'")}', '${r.lastName.replace(/'/g, "\\'")}', '${r.itemName.replace(/'/g, "\\'")}')" class="w-6 h-6 flex items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 hover:bg-emerald-500 hover:text-white transition" title="Déjà envoyé le ${haToken.sent_at}. Cliquez pour renvoyer.">
                                    <i class="fa-solid fa-sync-alt text-[8px]"></i>
                                </button>
                            ` : `
                                <span class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-200 text-slate-400" title="Non envoyé">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </span>
                            `}
                            <span class="w-6 h-6 flex items-center justify-center rounded-lg ${isRead ? 'bg-blue-100 text-blue-600' : 'bg-slate-200 text-slate-400'}" title="${isRead ? 'Lu le ' + haToken.read_at : 'Non lu'}">
                                <i class="fa-solid fa-eye"></i>
                            </span>
                            ${isReplied ? `
                                <span class="w-6 h-6 flex items-center justify-center rounded-lg bg-amber-100 text-amber-600" title="Répondu le ${haResp.submitted_at}">
                                    <i class="fa-solid fa-star"></i>
                                </span>
                            ` : ''}
                        </div>
                    `;

                    return `
                        <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between gap-3 text-[10px] border border-transparent transition ${isSent ? 'opacity-60' : ''}" id="row-${r.orderId}">
                            <div class="truncate">
                                <p class="font-black text-slate-700 truncate uppercase">${r.lastName} ${r.firstName}</p>
                                <p class="text-slate-400 truncate">${r.email}</p>
                            </div>
                            <div class="flex items-center">
                                ${statusHtml}
                            </div>
                        </div>
                    `;
                }).join('');
            } catch (e) {
                listContainer.innerHTML = '<div class="py-10 text-center text-red-400 font-bold italic text-sm">Erreur scan.</div>';
            }
        }

        async function sendTest() {
            const email = document.getElementById('test-email').value;
            if (!email) return notify("Email de test requis.", "error");

            const btn = document.getElementById('btn-send-test');
            const oldHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>';

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
                    notify("BAT envoyé !", "success");
                } else {
                    notify("Erreur : " + data.error, "error");
                }
            } catch (e) {
                notify("Erreur technique.", "error");
            } finally {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        }

        async function sendManual() {
            const email = document.getElementById('manual-email').value;
            const firstName = document.getElementById('manual-firstname').value;
            const lastName = document.getElementById('manual-lastname').value;
            if (!email || !firstName || !lastName) return notify("Tous les champs sont requis.", "error");

            const btn = document.getElementById('btn-send-manual');
            const oldHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>';

            try {
                const res = await fetch('admin.php?action=satisfaction_send_one', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        campaign: campaign,
                        email: email,
                        firstName: firstName,
                        lastName: lastName,
                        itemName: "Envoi manuel",
                        subject: document.getElementById('email-subject').value,
                        body: document.getElementById('email-body').value
                    })
                });
                const data = await res.json();
                if (data.success) {
                    notify("Email envoyé !", "success");
                    document.getElementById('manual-email').value = "";
                    document.getElementById('manual-firstname').value = "";
                    document.getElementById('manual-lastname').value = "";
                    fetchRecipients();
                } else {
                    notify("Erreur : " + data.error, "error");
                }
            } catch (e) {
                notify("Erreur technique.", "error");
            } finally {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        }

        async function resendOne(orderId, email, firstName, lastName, itemName) {
            if (!confirm(`Renvoyer l'email à ${email} ?`)) return;

            notify("Renvoi en cours...", "info");

            try {
                const res = await fetch('admin.php?action=satisfaction_send_one', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        campaign: campaign,
                        orderId: orderId,
                        email: email,
                        firstName: firstName,
                        lastName: lastName,
                        itemName: itemName,
                        subject: document.getElementById('email-subject').value,
                        body: document.getElementById('email-body').value,
                        force: '1'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    notify("Email renvoyé avec succès !", "success");
                } else {
                    notify("Erreur : " + data.error, "error");
                }
            } catch (e) {
                notify("Erreur technique.", "error");
            }
        }

        async function startSending() {
            if (!confirm(`Envoyer le questionnaire à ${recipients.length} personnes ?`)) return;

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
                    row.classList.add('bg-blue-50');
                }

                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000);

                try {
                    currentInBatch++;
                    statusContainer.innerHTML = `<p class="text-[10px] font-black uppercase text-slate-400 text-center animate-pulse">Envoi du paquet ${batchNum} (${currentInBatch}/10)...</p>`;

                    const res = await fetch('admin.php?action=satisfaction_send_one', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        signal: controller.signal,
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
                            row.classList.remove('bg-blue-50');
                            row.classList.add('opacity-40', 'bg-emerald-50');
                        }
                    } else {
                        console.error("Erreur pour " + r.email + ": " + data.error);
                        if (row) row.classList.replace('bg-blue-50', 'bg-red-50');
                    }
                } catch (e) {
                    console.error("Erreur technique pour " + r.email, e);
                    if (row) row.classList.replace('bg-blue-50', 'bg-red-50');
                } finally {
                    clearTimeout(timeoutId);
                    const pct = Math.round(((i + 1) / recipients.length) * 100);
                    progressFill.style.width = pct + '%';
                    percentLabel.innerText = pct + '%';
                }

                if (currentInBatch >= 10) {
                    batchNum++;
                    currentInBatch = 0;
                    statusContainer.innerHTML = `<p class="text-[10px] font-black uppercase text-blue-500 text-center">Pause anti-spam (5s)...</p>`;
                    await new Promise(r => setTimeout(r, 5000));
                } else {
                    await new Promise(r => setTimeout(r, 800));
                }
            }

            statusContainer.innerHTML = '<p class="text-[10px] font-black uppercase text-emerald-500 text-center">Terminé !</p>';
            btn.innerText = "Campagne terminée";
            notify(`${sentCount} emails envoyés !`, "success");
        }

        window.onload = fetchRecipients;
    </script>
</body>
</html>
