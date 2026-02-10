<?php
$srcPath = __DIR__ . '/../src/Services/';
require_once $srcPath . 'SatisfactionService.php';
require_once $srcPath . 'Storage.php';

$satService = new SatisfactionService();
$token = $_GET['t'] ?? null;

if (!$token) {
    die("Token manquant.");
}

$info = $satService->getTokenInfo($token);
if (!$info) {
    die("Token invalide ou expiré.");
}

// Check if already responded
$responses = $satService->getResponsesByCampaign($info['campaign_slug']);
$alreadyResponded = false;
foreach ($responses as $r) {
    if ($r['token'] === $token) {
        $alreadyResponded = true;
        break;
    }
}

$questions = $satService->getQuestions($info['campaign_slug']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyResponded) {
    $ratings = [
        (int)($_POST['q1'] ?? 0),
        (int)($_POST['q2'] ?? 0),
        (int)($_POST['q3'] ?? 0),
        (int)($_POST['q4'] ?? 0),
        (int)($_POST['q5'] ?? 0)
    ];
    $comment = $_POST['comment'] ?? '';

    $satService->saveResponse($token, $ratings, $comment);
    $alreadyResponded = true;
    $success = true;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre avis — <?= htmlspecialchars($info['item_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #1e293b; }
        .rating-btn { width: 3.5rem; height: 3.5rem; border-radius: 1rem; border: 2px solid #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 800; cursor: pointer; transition: 0.2s; background: white; }
        .rating-btn:hover { border-color: #2563eb; background: #eff6ff; }
        .rating-btn.active { background: #2563eb; border-color: #2563eb; color: white; transform: scale(1.1); }
        .card { background: white; border-radius: 2.5rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-xl">
        <div class="text-center mb-10">
            <img src="assets/img/logo.svg" alt="HelloBoard" class="w-12 h-12 mx-auto mb-6">
            <h1 class="text-2xl font-black uppercase italic tracking-tight">Votre avis nous intéresse</h1>
            <p class="text-slate-400 font-bold text-sm uppercase mt-2 italic"><?= htmlspecialchars($info['item_name']) ?></p>
        </div>

        <div class="card p-8 md:p-12">
            <?php if (isset($success)): ?>
                <div class="text-center py-10 animate-fade-in">
                    <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fa-solid fa-check text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-black mb-4">Merci beaucoup !</h2>
                    <p class="text-slate-500 font-medium">Votre précieux feedback a été enregistré. Vos réponses nous aident à nous améliorer au quotidien.</p>
                </div>
            <?php elseif ($alreadyResponded): ?>
                <div class="text-center py-10">
                    <div class="w-20 h-20 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fa-solid fa-info text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-black mb-4">Déjà répondu</h2>
                    <p class="text-slate-500 font-medium">Vous avez déjà complété ce questionnaire pour cette transaction. Merci pour votre temps !</p>
                </div>
            <?php else: ?>
                <form method="POST" id="survey-form">
                    <div class="space-y-10">
                        <?php foreach($questions as $i => $q): $idx = $i + 1; ?>
                            <div class="question-block" data-index="<?= $idx ?>">
                                <p class="font-black text-slate-800 leading-tight mb-6">
                                    <span class="text-blue-600 mr-2"><?= $idx ?>.</span> <?= htmlspecialchars($q['label']) ?>
                                </p>
                                <div class="flex justify-between gap-2">
                                    <?php for($val=1;$val<=5;$val++): ?>
                                        <div class="rating-btn" onclick="setRating(<?= $idx ?>, <?= $val ?>, this)" data-val="<?= $val ?>">
                                            <?= $val ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="q<?= $idx ?>" id="q<?= $idx ?>-val" required>
                            </div>
                        <?php endforeach; ?>

                        <div class="pt-6 border-t border-slate-100">
                            <label class="text-[10px] font-black text-slate-400 uppercase block mb-3 tracking-widest italic">Avez-vous des suggestions d'amélioration ?</label>
                            <textarea name="comment" rows="4" class="w-full bg-slate-50 border-2 border-transparent focus:border-blue-600 rounded-2xl p-5 text-slate-700 outline-none transition" placeholder="Votre message libre ici..."></textarea>
                        </div>

                        <button type="submit" id="submit-btn" class="w-full bg-slate-900 text-white py-6 rounded-[2rem] font-black uppercase text-xs tracking-widest shadow-xl hover:bg-blue-600 transition disabled:opacity-50" disabled>
                            Envoyer mes réponses
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="text-center mt-12">
            <p class="text-[10px] font-black text-slate-300 uppercase italic tracking-widest">Propulsé par HelloBoard — Module Satisfaction</p>
        </div>
    </div>

    <script>
        const ratings = { q1: null, q2: null, q3: null, q4: null, q5: null };

        function setRating(qIdx, val, el) {
            // Remove active class from siblings
            const parent = el.parentElement;
            parent.querySelectorAll('.rating-btn').forEach(btn => btn.classList.remove('active'));

            // Set active
            el.classList.add('active');

            // Set hidden value
            document.getElementById(`q${qIdx}-val`).value = val;
            ratings[`q${qIdx}`] = val;

            // Check if all are answered
            checkCompletion();
        }

        function checkCompletion() {
            const allSet = Object.values(ratings).every(v => v !== null);
            document.getElementById('submit-btn').disabled = !allSet;
        }
    </script>
</body>
</html>
