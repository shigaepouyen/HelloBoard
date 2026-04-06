<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mailing — <?= htmlspecialchars($currentCamp['title']) ?></title>
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
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); z-index: 100; display: flex; items-center: center; justify-content: center; opacity: 0; pointer-events: none; transition: 0.3s; }
        .modal-overlay.open { opacity: 1; pointer-events: auto; }
        .modal-content { background: white; width: 100%; max-width: 500px; border-radius: 2.5rem; transform: translateY(20px); transition: 0.3s; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .modal-overlay.open .modal-content { transform: translateY(0); }
        .step-pill { padding: 4px 12px; border-radius: 99px; font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }
        .step-pill.done { background: #ecfdf5; color: #10b981; }
        .step-pill.todo { background: #f1f5f9; color: #94a3b8; }
        .step-line { flex: 1; height: 2px; background: #f1f5f9; position: relative; }
        .step-line.done { background: #10b981; }
    </style>
</head>
<body class="pb-32">
    <div id="notifications-container"></div>
    <nav class="p-6 bg-white border-b border-slate-100 sticky top-0 z-50 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-4">
            <a href="admin.php" class="flex items-center gap-2">
                <img src="<?= $globals['customLogo'] ?? 'assets/img/logo.svg' ?>" alt="HelloBoard" class="w-8 h-8 object-contain">
                <h1 class="font-black italic uppercase text-slate-900">Console Admin</h1>
            </a>
            <div class="h-6 w-px bg-slate-200 mx-2"></div>
            <h2 class="font-black italic uppercase text-blue-600 text-sm tracking-widest"><?= htmlspecialchars($currentCamp['title']) ?> — Rappel Mail</h2>
        </div>
        <a href="admin.php" class="text-xs font-black uppercase text-slate-400 hover:text-slate-900 transition">Retour</a>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- COLONNE GAUCHE : REDACTION -->
            <div class="lg:col-span-2 space-y-8">
                <div class="admin-card p-8">
                    <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic tracking-widest border-b border-slate-50 pb-4">Rédaction du message</h3>
                    <div class="space-y-6">
                        <div>
                            <label class="text-[10px] font-black text-slate-500 uppercase block mb-2 tracking-tighter">Objet de l'email</label>
                            <input type="text" id="mail-subject" class="input-soft" value="<?= htmlspecialchars($mailingDraft['subject']) ?>" placeholder="Ex: Rappel : Votre inscription à {{NOM_CAMPAGNE}}">
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-[10px] font-black text-slate-500 uppercase block tracking-tighter">Corps du message (HTML supporté)</label>
                                <?php if (!empty($globals['mistralApiKey'])): ?>
                                    <button onclick="toggleAiPrompt()" class="text-[10px] font-black text-blue-600 uppercase flex items-center gap-1 hover:text-blue-800 transition">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i> Rédiger avec l'IA
                                    </button>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($globals['mistralApiKey'])): ?>
                                <div id="ai-prompt-container" class="hidden mb-4 p-4 bg-blue-50 rounded-2xl border border-blue-100 animate-fade-in">
                                    <label class="text-[9px] font-black text-blue-400 uppercase block mb-2 italic">Que doit contenir cet email ?</label>
                                    <div class="flex gap-2">
                                        <input type="text" id="ai-prompt-input" class="input-soft !bg-white !text-xs flex-1" placeholder="Ex: Un rappel amical pour l'événement de demain, mentionne qu'il reste des places.">
                                        <button onclick="generateWithAi()" id="btn-generate-ai" class="bg-blue-600 text-white px-4 py-2 rounded-xl font-black uppercase text-[10px] hover:bg-blue-700 transition">Générer</button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <textarea id="mail-body" rows="12" class="input-soft font-mono text-sm" placeholder="Bonjour {{PRENOM}}, ..."><?= htmlspecialchars($mailingDraft['body']) ?></textarea>
                            <p class="text-[10px] text-slate-400 font-bold uppercase mt-3">Variables : {{NOM}}, {{PRENOM}}, {{NOM_CAMPAGNE}}</p>
                        </div>

                        <div class="pt-6 border-t border-slate-50">
                            <label class="text-[10px] font-black text-slate-500 uppercase block mb-2 tracking-tighter">Pièces jointes</label>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <?php foreach($attachments as $file): ?>
                                    <div class="flex items-center gap-2 bg-slate-100 px-3 py-2 rounded-xl text-[10px] font-bold text-slate-600">
                                        <i class="fa-solid fa-file-alt"></i>
                                        <span><?= htmlspecialchars($file['name']) ?> (<?= round($file['size']/1024, 1) ?> KB)</span>
                                        <a href="admin.php?action=delete_attachment&campaign=<?= $slug ?>&file=<?= urlencode($file['name']) ?>" class="text-slate-300 hover:text-red-500 transition ml-2"><i class="fa-solid fa-times-circle"></i></a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="file" id="mail-attachment" class="hidden" onchange="document.getElementById('file-name-display').innerText = this.files[0].name">
                            <label for="mail-attachment" class="inline-flex items-center gap-2 bg-white border-2 border-dashed border-slate-200 px-6 py-4 rounded-2xl text-[10px] font-black text-slate-400 uppercase cursor-pointer hover:border-blue-400 hover:text-blue-500 transition">
                                <i class="fa-solid fa-paperclip"></i>
                                <span id="file-name-display">Ajouter un fichier</span>
                            </label>
                        </div>

                        <div class="flex gap-4 pt-6 border-t border-slate-50">
                            <button onclick="saveDraft()" id="btn-save-draft" class="bg-slate-100 text-slate-600 px-6 py-4 rounded-2xl font-black uppercase text-xs hover:bg-slate-200 transition">
                                Enregistrer le brouillon
                            </button>
                        </div>
                    </div>
                </div>

                <div class="admin-card p-8 bg-blue-50/30 border-blue-100">
                    <h3 class="text-xs font-black uppercase text-blue-400 mb-6 italic tracking-widest border-b border-blue-50 pb-4">Mode Test (BAT)</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="text" id="test-firstname" class="input-soft !bg-white" placeholder="Prénom (Test)">
                            <input type="text" id="test-lastname" class="input-soft !bg-white" placeholder="Nom (Test)">
                        </div>
                        <div class="flex gap-4">
                            <input type="email" id="test-email" class="input-soft !bg-white flex-1" placeholder="Votre email de test">
                            <button onclick="sendTest()" id="btn-send-test" class="bg-blue-600 text-white px-8 py-4 rounded-2xl font-black uppercase text-xs shadow-lg shadow-blue-200 hover:bg-blue-700 transition flex items-center gap-2">
                                <i class="fa-solid fa-paper-plane"></i> Envoyer un test
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLONNE DROITE : STATS & ENVOI -->
            <div class="space-y-8">
                <div class="admin-card p-8">
                    <div class="flex justify-between items-start mb-6 border-b border-slate-50 pb-4">
                        <h3 class="text-xs font-black uppercase text-slate-400 italic tracking-widest">Progression de l'envoi</h3>
                        <a href="admin.php?action=mailing_export_csv&campaign=<?= $slug ?>" class="text-[9px] font-black uppercase text-blue-500 hover:underline">
                            <i class="fa-solid fa-file-csv mr-1"></i> Export Logs
                        </a>
                    </div>

                    <?php
                        $total = count($payers);
                        $sentCount = 0;
                        $readCount = 0;
                        foreach ($payers as $p) {
                            if (!empty($history[$p['email']]['sent_at'])) $sentCount++;
                            if (!empty($history[$p['email']]['read_at'])) $readCount++;
                        }
                        $remaining = $total - $sentCount;
                    ?>

                    <div class="space-y-6">
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <p class="text-2xl font-black text-slate-900"><?= $total ?></p>
                                <p class="text-[8px] font-black uppercase text-slate-400">Total</p>
                            </div>
                            <div>
                                <p class="text-2xl font-black text-emerald-500" id="stat-sent"><?= $sentCount ?></p>
                                <p class="text-[8px] font-black uppercase text-slate-400">Envoyés</p>
                            </div>
                            <div>
                                <p class="text-2xl font-black text-blue-500" id="stat-read"><?= $readCount ?></p>
                                <p class="text-[8px] font-black uppercase text-slate-400">Lus</p>
                            </div>
                        </div>

                        <div class="relative pt-4">
                            <div class="overflow-hidden h-4 text-xs flex rounded-full bg-slate-100">
                                <div id="progress-bar" style="width: <?= $total > 0 ? ($sentCount / $total * 100) : 0 ?>%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-emerald-500 transition-all duration-500"></div>
                            </div>
                        </div>

                        <button onclick="startSending()" id="btn-send-all" class="w-full bg-slate-900 text-white py-6 rounded-[2rem] font-black uppercase text-xs tracking-widest shadow-xl hover:bg-emerald-600 transition disabled:opacity-50 disabled:cursor-not-allowed" <?= $remaining == 0 ? 'disabled' : '' ?>>
                            <?= $remaining > 0 ? "Envoyer aux $remaining restants" : "Terminé" ?>
                        </button>

                        <div id="sending-status" class="hidden">
                            <p class="text-[10px] font-black uppercase text-slate-400 text-center animate-pulse">Envoi en cours, ne fermez pas cette page...</p>
                        </div>
                    </div>
                </div>

                <div class="admin-card p-6 overflow-hidden">
                    <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic tracking-widest border-b border-slate-50 pb-4"><?= htmlspecialchars($mailingWording['recipientListTitle']) ?></h3>
                    <div class="max-h-[400px] overflow-y-auto pr-2 space-y-2" id="payers-list">
                        <?php foreach ($payers as $p):
                            $mid = md5($p['email']);
                            $h = $history[$p['email']] ?? null;
                            $isSent = !empty($h['sent_at']);
                            $isRead = !empty($h['read_at']);
                        ?>
                            <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between gap-3 text-[10px] border border-transparent transition <?= $isSent ? 'opacity-60' : '' ?>" id="row-<?= $mid ?>">
                                <div class="truncate">
                                    <p class="font-black text-slate-700 truncate"><?= htmlspecialchars(strtoupper($p['lastName'] . ' ' . $p['firstName'])) ?></p>
                                    <p class="text-slate-400 truncate"><?= htmlspecialchars($p['email']) ?></p>
                                </div>
                                <div class="flex gap-1 shrink-0">
                                    <button onclick="openMailingHistory('<?= htmlspecialchars($p['email']) ?>', '<?= htmlspecialchars($p['firstName'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['lastName'], ENT_QUOTES) ?>')" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-100 text-slate-500 hover:bg-blue-600 hover:text-white transition" title="Détails du suivi">
                                        <i class="fa-solid fa-info-circle text-[10px]"></i>
                                    </button>
                                    <?php if ($isSent): ?>
                                        <button onclick="resendOne('<?= htmlspecialchars($p['email']) ?>', '<?= htmlspecialchars($p['firstName'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['lastName'], ENT_QUOTES) ?>')" class="status-sent w-6 h-6 flex items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 hover:bg-emerald-500 hover:text-white transition" title="Dernier envoi le <?= $h['sent_at'] ?>. Cliquez pour renvoyer.">
                                            <i class="fa-solid fa-sync-alt text-[8px]"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="status-sent w-6 h-6 flex items-center justify-center rounded-lg bg-slate-200 text-slate-400" title="Non envoyé">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </span>
                                    <?php endif; ?>
                                    <span class="status-read w-6 h-6 flex items-center justify-center rounded-lg <?= $isRead ? 'bg-blue-100 text-blue-600' : 'bg-slate-200 text-slate-400' ?>" title="<?= $isRead ? 'Lu le ' . $h['read_at'] : 'Non lu' ?>">
                                        <i class="fa-solid fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ENVOI MANUEL (UNITAIRE) -->
                <div class="admin-card p-8 border-emerald-100 bg-emerald-50/20">
                    <h3 class="text-xs font-black uppercase text-emerald-600 mb-6 italic tracking-widest border-b border-emerald-50 pb-4">Envoi manuel unitaire</h3>
                    <div class="space-y-3">
                        <input type="text" id="manual-firstname" class="input-soft !bg-white !text-[10px] !py-3" placeholder="Prénom">
                        <input type="text" id="manual-lastname" class="input-soft !bg-white !text-[10px] !py-3" placeholder="Nom">
                        <input type="email" id="manual-email" class="input-soft !bg-white !text-[10px] !py-3" placeholder="<?= htmlspecialchars($mailingWording['contactEmailPlaceholder']) ?>">
                        <button onclick="sendManual()" id="btn-send-manual" class="w-full bg-emerald-600 text-white py-4 rounded-2xl font-black uppercase text-[10px] shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition flex items-center justify-center gap-2">
                            <i class="fa-solid fa-user-plus"></i> Envoyer ce rappel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal History -->
    <div id="modal-history" class="modal-overlay p-4" onclick="if(event.target === this) closeHistory()">
        <div class="modal-content overflow-hidden flex flex-col max-h-[90vh]">
            <div class="p-8 border-b border-slate-50 flex justify-between items-center">
                <div>
                    <h3 id="modal-title" class="text-xl font-black text-slate-900 uppercase italic">Suivi Envoi</h3>
                    <p id="modal-subtitle" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Détails et historique des tentatives</p>
                </div>
                <button onclick="closeHistory()" class="w-10 h-10 flex items-center justify-center rounded-full bg-slate-50 text-slate-400 hover:bg-slate-100 transition">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>

            <div class="p-8 overflow-y-auto flex-1 space-y-8">
                <!-- Workflow Visuel -->
                <div class="flex items-center gap-2">
                    <div class="flex flex-col items-center gap-2">
                        <div id="step-sent-icon" class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-black shadow-sm">1</div>
                        <span class="text-[8px] font-black uppercase text-slate-400">Envoyé</span>
                    </div>
                    <div id="line-sent-read" class="step-line"></div>
                    <div class="flex flex-col items-center gap-2">
                        <div id="step-read-icon" class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-black shadow-sm">2</div>
                        <span class="text-[8px] font-black uppercase text-slate-400">Lu</span>
                    </div>
                </div>

                <!-- Liste des tentatives -->
                <div class="space-y-4">
                    <h4 class="text-[10px] font-black uppercase text-slate-400 italic tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-history"></i> Tentatives d'envoi
                    </h4>
                    <div id="attempts-list" class="space-y-2">
                        <!-- Rempli en JS -->
                    </div>
                </div>
            </div>

            <div class="p-8 bg-slate-50 border-t border-slate-100 flex gap-4">
                <button id="modal-resend-btn" class="flex-1 bg-emerald-600 text-white py-4 rounded-2xl font-black uppercase text-xs shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-sync-alt"></i> Renvoyer maintenant
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-md5/2.19.0/js/md5.min.js"></script>
    <script>
        const campaign = "<?= $slug ?>";
        const payers = <?= json_encode(array_values($payers)) ?>;

        function toggleAiPrompt() {
            const container = document.getElementById('ai-prompt-container');
            container.classList.toggle('hidden');
            if (!container.classList.contains('hidden')) {
                document.getElementById('ai-prompt-input').focus();
            }
        }

        async function generateWithAi() {
            const prompt = document.getElementById('ai-prompt-input').value;
            if (!prompt) return notify("Veuillez saisir une instruction pour l'IA.", "error");

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
                        context: 'Mailing'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('mail-body').value = data.body;
                    document.getElementById('ai-prompt-container').classList.add('hidden');
                    notify("Email généré avec succès !", "success");
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
                const res = await fetch('admin.php?action=mailing_send_one', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        campaign: campaign,
                        email: email,
                        firstName: firstName,
                        lastName: lastName,
                        subject: document.getElementById('mail-subject').value,
                        body: document.getElementById('mail-body').value
                    })
                });
                const data = await res.json();
                if (data.success) {
                    notify("Rappel envoyé !", "success");
                    document.getElementById('manual-email').value = "";
                    document.getElementById('manual-firstname').value = "";
                    document.getElementById('manual-lastname').value = "";
                    setTimeout(() => location.reload(), 1500);
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

        async function resendOne(email, firstName, lastName) {
            if (!confirm(`Renvoyer le rappel à ${email} ?`)) return;

            notify("Renvoi en cours...", "info");

            try {
                const res = await fetch('admin.php?action=mailing_send_one', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        campaign: campaign,
                        email: email,
                        firstName: firstName,
                        lastName: lastName,
                        subject: document.getElementById('mail-subject').value,
                        body: document.getElementById('mail-body').value,
                        force: '1'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    notify("Email renvoyé avec succès !", "success");
                    if (document.getElementById('modal-history').classList.contains('open')) {
                        openMailingHistory(email, firstName, lastName);
                    }
                } else {
                    notify("Erreur : " + data.error, "error");
                }
            } catch (e) {
                notify("Erreur technique.", "error");
            }
        }

        async function openMailingHistory(email, firstName, lastName) {
            const modal = document.getElementById('modal-history');
            const attemptsList = document.getElementById('attempts-list');
            const title = document.getElementById('modal-title');

            title.innerText = firstName + ' ' + lastName;
            attemptsList.innerHTML = '<div class="py-10 text-center animate-pulse text-slate-300 font-black uppercase text-[10px]">Chargement...</div>';

            modal.classList.add('open');

            const resBtn = document.getElementById('modal-resend-btn');
            resBtn.onclick = () => resendOne(email, firstName, lastName);

            try {
                const res = await fetch(`admin.php?action=get_recipient_history&campaign=${campaign}&email=${email}&type=mailing`);
                const data = await res.json();

                if (data.success) {
                    // Visual Workflow
                    const stepSent = document.getElementById('step-sent-icon');
                    const stepRead = document.getElementById('step-read-icon');
                    const line = document.getElementById('line-sent-read');

                    if (data.sent_at) {
                        stepSent.className = "w-8 h-8 rounded-full flex items-center justify-center text-xs font-black shadow-sm bg-emerald-500 text-white";
                        stepSent.innerHTML = '<i class="fa-solid fa-check"></i>';
                    } else {
                        stepSent.className = "w-8 h-8 rounded-full flex items-center justify-center text-xs font-black shadow-sm bg-slate-100 text-slate-300";
                        stepSent.innerHTML = '1';
                    }

                    if (data.read_at) {
                        stepRead.className = "w-8 h-8 rounded-full flex items-center justify-center text-xs font-black shadow-sm bg-blue-500 text-white";
                        stepRead.innerHTML = '<i class="fa-solid fa-eye"></i>';
                        line.className = "step-line done bg-emerald-500";
                    } else {
                        stepRead.className = "w-8 h-8 rounded-full flex items-center justify-center text-xs font-black shadow-sm bg-slate-100 text-slate-300";
                        stepRead.innerHTML = '2';
                        line.className = "step-line";
                    }

                    // Attempts
                    if (data.attempts && data.attempts.length > 0) {
                        attemptsList.innerHTML = data.attempts.map(a => `
                            <div class="p-4 rounded-2xl border ${a.status === 'success' ? 'bg-emerald-50 border-emerald-100' : 'bg-red-50 border-red-100'}">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-[10px] font-black uppercase ${a.status === 'success' ? 'text-emerald-600' : 'text-red-600'}">
                                        <i class="fa-solid ${a.status === 'success' ? 'fa-check-circle' : 'fa-times-circle'} mr-1"></i>
                                        ${a.status === 'success' ? 'Envoyé avec succès' : 'Échec de l\'envoi'}
                                    </span>
                                    <span class="text-[9px] font-bold text-slate-400">${new Date(a.date).toLocaleString('fr-FR')}</span>
                                </div>
                                ${a.error ? `<p class="text-[10px] font-medium text-red-400 mt-2 bg-white/50 p-2 rounded-lg border border-red-50">${a.error}</p>` : ''}
                            </div>
                        `).join('');
                    } else if (data.sent_at) {
                         // Fallback for old history format without explicit attempts
                         attemptsList.innerHTML = `
                            <div class="p-4 rounded-2xl border bg-emerald-50 border-emerald-100">
                                <div class="flex justify-between items-start">
                                    <span class="text-[10px] font-black uppercase text-emerald-600">
                                        <i class="fa-solid fa-check-circle mr-1"></i> Envoyé avec succès
                                    </span>
                                    <span class="text-[9px] font-bold text-slate-400">${new Date(data.sent_at).toLocaleString('fr-FR')}</span>
                                </div>
                            </div>
                         `;
                    } else {
                        attemptsList.innerHTML = '<p class="text-[10px] text-slate-400 text-center py-4 font-bold uppercase italic">Aucune tentative enregistrée.</p>';
                    }
                } else {
                    attemptsList.innerHTML = `<p class="text-red-500 text-center font-bold">${data.error}</p>`;
                }
            } catch (e) {
                console.error(e);
                attemptsList.innerHTML = '<p class="text-red-500 text-center">Erreur lors de la récupération.</p>';
            }
        }

        function closeHistory() {
            document.getElementById('modal-history').classList.remove('open');
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
        const history = <?= json_encode($history) ?>;

        async function saveDraft() {
            const btn = document.getElementById('btn-save-draft');
            const oldText = btn.innerText;
            btn.innerText = "Enregistrement...";
            btn.disabled = true;

            const formData = new FormData();
            formData.append('save_mailing_draft', '1');
            formData.append('campaign', campaign);
            formData.append('subject', document.getElementById('mail-subject').value);
            formData.append('body', document.getElementById('mail-body').value);

            const fileInput = document.getElementById('mail-attachment');
            if (fileInput.files[0]) {
                formData.append('attachment', fileInput.files[0]);
            }

            try {
                const res = await fetch('admin.php', {
                    method: 'POST',
                    body: formData
                });
                notify("Brouillon enregistré avec succès !", "success");
                if (fileInput.files[0]) {
                    setTimeout(() => location.reload(), 1000);
                }
            } catch (e) {
                notify("Erreur lors de l'enregistrement.", "error");
            } finally {
                btn.innerText = oldText;
                btn.disabled = false;
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
                const res = await fetch('admin.php?action=mailing_send_one', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        campaign: campaign,
                        email: email,
                        firstName: document.getElementById('test-firstname').value || 'Test',
                        lastName: document.getElementById('test-lastname').value || 'TestUser',
                        subject: document.getElementById('mail-subject').value,
                        body: document.getElementById('mail-body').value,
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
            if (!document.getElementById('mail-subject').value) return notify("Veuillez saisir un objet.", "error");

            document.getElementById('btn-send-all').disabled = true;
            const statusContainer = document.getElementById('sending-status');
            statusContainer.classList.remove('hidden');

            const subject = document.getElementById('mail-subject').value;
            const body = document.getElementById('mail-body').value;

            let sentCount = parseInt(document.getElementById('stat-sent').innerText);
            const totalCount = payers.length;
            let currentInBatch = 0;
            let batchNum = 1;

            for (const p of payers) {
                if (history[p.email] && history[p.email].sent_at) continue;

                const rowId = 'row-' + md5(p.email);
                const row = document.getElementById(rowId);
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('bg-blue-50', 'border-blue-200');
                }

                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000);

                try {
                    currentInBatch++;
                    statusContainer.innerHTML = `<p class="text-[10px] font-black uppercase text-slate-400 text-center animate-pulse">Envoi du paquet ${batchNum} (${currentInBatch}/10)...</p>`;

                    const res = await fetch('admin.php?action=mailing_send_one', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        signal: controller.signal,
                        body: new URLSearchParams({
                            campaign: campaign,
                            email: p.email,
                            firstName: p.firstName,
                            lastName: p.lastName,
                            subject: subject,
                            body: body
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        sentCount++;
                        document.getElementById('stat-sent').innerText = sentCount;
                        document.getElementById('progress-bar').style.width = (sentCount / totalCount * 100) + '%';
                        if (row) {
                            row.classList.remove('bg-blue-50', 'border-blue-200');
                            row.classList.add('opacity-60', 'bg-emerald-50/30');
                            row.querySelector('.status-sent').classList.replace('bg-slate-200', 'bg-emerald-100');
                            row.querySelector('.status-sent').classList.replace('text-slate-400', 'text-emerald-600');
                        }
                        history[p.email] = { sent_at: new Date().toISOString() };
                    } else {
                        console.error("Erreur pour " + p.email + ": " + data.error);
                        if (row) row.classList.replace('bg-blue-50', 'bg-red-50');
                    }
                } catch (e) {
                    console.error("Erreur technique pour " + p.email, e);
                    if (row) row.classList.replace('bg-blue-50', 'bg-red-50');
                } finally {
                    clearTimeout(timeoutId);
                }

                if (currentInBatch >= 10) {
                    batchNum++;
                    currentInBatch = 0;
                    statusContainer.innerHTML = `<p class="text-[10px] font-black uppercase text-blue-500 text-center">Temporisation entre deux paquets (5s)...</p>`;
                    await new Promise(r => setTimeout(r, 5000));
                } else {
                    await new Promise(r => setTimeout(r, 800));
                }
            }

            document.getElementById('sending-status').innerHTML = '<p class="text-[10px] font-black uppercase text-emerald-500 text-center">Envoi terminé !</p>';
            document.getElementById('btn-send-all').innerText = "Envoi terminé";
        }
    </script>
</body>
</html>
