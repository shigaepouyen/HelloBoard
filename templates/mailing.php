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
        .admin-card { background: white; border-radius: 2rem; border: 1px solid #edf2f7; }
        .input-soft { background: #f1f5f9; border: 2px solid transparent; border-radius: 1.25rem; padding: 12px 16px; font-weight: 700; width: 100%; outline: none; transition: 0.2s; }
        .input-soft:focus { border-color: #2563eb; background: white; }
    </style>
</head>
<body class="pb-32">
    <nav class="p-6 bg-white border-b border-slate-100 sticky top-0 z-50 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-4">
            <a href="admin.php" class="flex items-center gap-2">
                <img src="assets/img/logo.svg" alt="HelloBoard" class="w-8 h-8">
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
                            <label class="text-[10px] font-black text-slate-500 uppercase block mb-2 tracking-tighter">Corps du message (HTML supporté)</label>
                            <textarea id="mail-body" rows="12" class="input-soft font-mono text-sm" placeholder="Bonjour {{PRENOM}}, ..."><?= htmlspecialchars($mailingDraft['body']) ?></textarea>
                            <p class="text-[10px] text-slate-400 font-bold uppercase mt-3">Variables : {{NOM}}, {{PRENOM}}, {{NOM_CAMPAGNE}}</p>
                        </div>
                        <div class="flex gap-4">
                            <button onclick="saveDraft()" id="btn-save-draft" class="bg-slate-100 text-slate-600 px-6 py-4 rounded-2xl font-black uppercase text-xs hover:bg-slate-200 transition">
                                Enregistrer le brouillon
                            </button>
                        </div>
                    </div>
                </div>

                <div class="admin-card p-8 bg-blue-50/30 border-blue-100">
                    <h3 class="text-xs font-black uppercase text-blue-400 mb-6 italic tracking-widest border-b border-blue-50 pb-4">Mode Test (BAT)</h3>
                    <div class="flex gap-4">
                        <input type="email" id="test-email" class="input-soft !bg-white" placeholder="Votre email de test">
                        <button onclick="sendTest()" id="btn-send-test" class="bg-blue-600 text-white px-8 py-4 rounded-2xl font-black uppercase text-xs shadow-lg shadow-blue-200 hover:bg-blue-700 transition flex items-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i> Envoyer un test
                        </button>
                    </div>
                </div>
            </div>

            <!-- COLONNE DROITE : STATS & ENVOI -->
            <div class="space-y-8">
                <div class="admin-card p-8">
                    <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic tracking-widest border-b border-slate-50 pb-4">Progression de l'envoi</h3>

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
                    <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic tracking-widest border-b border-slate-50 pb-4">Liste des payeurs</h3>
                    <div class="max-h-[500px] overflow-y-auto pr-2 space-y-2" id="payers-list">
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
                                    <span class="status-sent w-6 h-6 flex items-center justify-center rounded-lg <?= $isSent ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-200 text-slate-400' ?>" title="Envoyé le <?= $h['sent_at'] ?? '?' ?>">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </span>
                                    <span class="status-read w-6 h-6 flex items-center justify-center rounded-lg <?= $isRead ? 'bg-blue-100 text-blue-600' : 'bg-slate-200 text-slate-400' ?>" title="Lu le <?= $h['read_at'] ?? '?' ?>">
                                        <i class="fa-solid fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-md5/2.19.0/js/md5.min.js"></script>
    <script>
        const campaign = "<?= $slug ?>";
        const payers = <?= json_encode(array_values($payers)) ?>;
        const history = <?= json_encode($history) ?>;

        async function saveDraft() {
            const btn = document.getElementById('btn-save-draft');
            const oldText = btn.innerText;
            btn.innerText = "Enregistrement...";

            await fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    save_mailing_draft: '1',
                    campaign: campaign,
                    subject: document.getElementById('mail-subject').value,
                    body: document.getElementById('mail-body').value
                })
            });

            btn.innerText = "Brouillon enregistré !";
            btn.classList.replace('bg-slate-100', 'bg-emerald-100');
            btn.classList.replace('text-slate-600', 'text-emerald-600');
            setTimeout(() => {
                btn.innerText = oldText;
                btn.classList.replace('bg-emerald-100', 'bg-slate-100');
                btn.classList.replace('text-emerald-600', 'text-slate-600');
            }, 2000);
        }

        async function sendTest() {
            const email = document.getElementById('test-email').value;
            if (!email) return alert("Veuillez saisir un email de test.");

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
                        firstName: 'Test',
                        lastName: 'TestUser',
                        subject: document.getElementById('mail-subject').value,
                        body: document.getElementById('mail-body').value,
                        is_test: '1'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    alert("Email de test envoyé avec succès !");
                } else {
                    alert("Erreur : " + data.error);
                }
            } catch (e) {
                alert("Erreur technique lors de l'envoi.");
            } finally {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        }

        async function startSending() {
            if (!confirm("Démarrer l'envoi aux payeurs restants ?")) return;

            document.getElementById('btn-send-all').disabled = true;
            document.getElementById('sending-status').classList.remove('hidden');

            const subject = document.getElementById('mail-subject').value;
            const body = document.getElementById('mail-body').value;

            let sentCount = parseInt(document.getElementById('stat-sent').innerText);
            const totalCount = payers.length;

            for (const p of payers) {
                if (history[p.email] && history[p.email].sent_at) continue;

                const rowId = 'row-' + md5(p.email);
                const row = document.getElementById(rowId);
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('bg-blue-50', 'border-blue-200');
                }

                try {
                    const res = await fetch('admin.php?action=mailing_send_one', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
                            row.classList.add('opacity-60');
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
                }

                await new Promise(r => setTimeout(r, 800));
            }

            document.getElementById('sending-status').innerHTML = '<p class="text-[10px] font-black uppercase text-emerald-500 text-center">Envoi terminé !</p>';
            document.getElementById('btn-send-all').innerText = "Envoi terminé";
        }
    </script>
</body>
</html>
