<?php
$srcPath = __DIR__ . '/../src/Services/';
require_once $srcPath . 'SatisfactionService.php';
require_once $srcPath . 'Storage.php';

$satService = new SatisfactionService();
$globals = Storage::getGlobalSettings();
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

$campaign = Storage::getCampaign($info['campaign_slug']);
$formType = $campaign['formType'] ?? null;
$questions = $satService->getQuestions($info['campaign_slug'], $formType);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyResponded) {
    $ratings = [
        isset($_POST['q1']) ? (int)$_POST['q1'] : null,
        isset($_POST['q2']) ? (int)$_POST['q2'] : null,
        isset($_POST['q3']) ? (int)$_POST['q3'] : null,
        isset($_POST['q4']) ? (int)$_POST['q4'] : null,
        isset($_POST['q5']) ? (int)$_POST['q5'] : null
    ];
    $comment = $_POST['comment'] ?? '';

    $satService->saveResponse($token, $ratings, $comment);
    $alreadyResponded = true;
    $success = true;
}

$emojis = [
    1 => ['char' => '😠', 'color' => 'bg-red-50 text-red-500 border-red-100', 'active' => 'bg-red-500 text-white border-red-500'],
    2 => ['char' => '🙁', 'color' => 'bg-orange-50 text-orange-500 border-orange-100', 'active' => 'bg-orange-500 text-white border-orange-500'],
    3 => ['char' => '😐', 'color' => 'bg-amber-50 text-amber-500 border-amber-100', 'active' => 'bg-amber-500 text-white border-amber-500'],
    4 => ['char' => '🙂', 'color' => 'bg-lime-50 text-lime-500 border-lime-100', 'active' => 'bg-lime-500 text-white border-lime-500'],
    5 => ['char' => '😄', 'color' => 'bg-emerald-50 text-emerald-500 border-emerald-100', 'active' => 'bg-emerald-500 text-white border-emerald-500']
];

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
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #1e293b; overflow-x: hidden; }
        .rating-btn { width: 100%; aspect-ratio: 1/1; border-radius: 1.5rem; border: 2px solid transparent; display: flex; align-items: center; justify-content: center; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; }
        .rating-btn .emoji { font-size: 2rem; transition: 0.3s; }
        .rating-btn:hover { transform: translateY(-5px); }
        .rating-btn.active { transform: scale(1.05); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .rating-btn.active .emoji { transform: scale(1.2); }

        .card { background: white; border-radius: 2.5rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05); }

        .step-content { display: none; }
        .step-content.active { display: block; animation: slideIn 0.4s ease-out; }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .progress-bar { height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; background: #3b82f6; transition: width 0.4s ease-out; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-xl">
        <div class="text-center mb-8">
            <img src="<?= $globals['customLogo'] ?? 'assets/img/logo.svg' ?>" alt="Logo" class="max-h-16 mx-auto mb-6">
            <h1 class="text-2xl font-black uppercase italic tracking-tight">Votre avis nous intéresse</h1>
            <p class="text-slate-400 font-bold text-[10px] uppercase mt-2 italic tracking-widest"><?= htmlspecialchars($info['item_name']) ?></p>
        </div>

        <div class="card p-6 md:p-10 relative overflow-hidden">

            <?php if (!isset($success) && !$alreadyResponded): ?>
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-2">
                        <span id="step-indicator" class="text-[10px] font-black text-blue-600 uppercase tracking-widest italic">Question 1 / <?= count($questions) + 1 ?></span>
                        <span id="percent-indicator" class="text-[10px] font-black text-slate-300 uppercase tracking-widest italic">0%</span>
                    </div>
                    <div class="progress-bar">
                        <div id="progress-fill" class="progress-fill" style="width: 0%"></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="text-center py-10 animate-fade-in">
                    <div class="w-24 h-24 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-8">
                        <i class="fa-solid fa-check text-4xl"></i>
                    </div>
                    <h2 class="text-3xl font-black mb-4 italic uppercase">Merci beaucoup !</h2>
                    <p class="text-slate-500 font-medium leading-relaxed">Votre précieux feedback a été enregistré. Vos réponses nous aident à nous améliorer au quotidien.</p>
                    <div class="mt-10">
                        <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest mb-4 italic">Vous pouvez maintenant fermer cet onglet.</p>
                        <button onclick="window.close()" class="text-[10px] font-black text-blue-600 uppercase tracking-widest hover:underline transition">Fermer la fenêtre</button>
                    </div>
                </div>
            <?php elseif ($alreadyResponded): ?>
                <div class="text-center py-10">
                    <div class="w-24 h-24 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-8">
                        <i class="fa-solid fa-info text-4xl"></i>
                    </div>
                    <h2 class="text-3xl font-black mb-4 italic uppercase">Déjà répondu</h2>
                    <p class="text-slate-500 font-medium leading-relaxed">Vous avez déjà complété ce questionnaire pour cette transaction. Merci pour votre temps !</p>
                </div>
            <?php else: ?>
                <form method="POST" id="survey-form">

                    <!-- Questions Steps -->
                    <?php foreach($questions as $i => $q): $idx = $i + 1; ?>
                        <div class="step-content <?= $idx === 1 ? 'active' : '' ?>" data-step="<?= $idx ?>">
                            <h2 class="text-xl md:text-2xl font-black text-slate-800 leading-tight mb-8">
                                <span class="text-blue-600 mr-1"><?= $idx ?>.</span> <?= htmlspecialchars($q['label']) ?>
                            </h2>

                            <div class="grid grid-cols-5 gap-3 md:gap-4">
                                <?php foreach($emojis as $val => $e): ?>
                                    <div class="rating-btn <?= $e['color'] ?>" onclick="setRating(<?= $idx ?>, <?= $val ?>, this)" data-val="<?= $val ?>">
                                        <span class="emoji"><?= $e['char'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="q<?= $idx ?>" id="q<?= $idx ?>-val" required>
                        </div>
                    <?php endforeach; ?>

                    <!-- Final Comment Step -->
                    <div class="step-content" data-step="<?= count($questions) + 1 ?>">
                        <h2 class="text-xl md:text-2xl font-black text-slate-800 leading-tight mb-4">
                            Un dernier mot ?
                        </h2>
                        <p class="text-slate-400 text-sm font-medium mb-8">Avez-vous des suggestions d'amélioration ou un message à nous faire passer ?</p>

                        <textarea name="comment" rows="5" class="w-full bg-slate-50 border-2 border-transparent focus:border-blue-600 rounded-[2rem] p-6 text-slate-700 outline-none transition text-lg" placeholder="Votre message libre ici (optionnel)..."></textarea>

                        <button type="submit" id="submit-btn" class="w-full bg-slate-900 text-white py-6 rounded-[2rem] font-black uppercase text-xs tracking-widest shadow-xl hover:bg-blue-600 transition mt-8 flex items-center justify-center gap-3">
                            <span>Envoyer mes réponses</span>
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>

                    <!-- Navigation Footer -->
                    <div class="mt-12 pt-8 border-t border-slate-50 flex justify-between items-center">
                        <button type="button" id="prev-btn" onclick="prevStep()" class="text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-blue-600 transition disabled:opacity-0" disabled>
                            <i class="fa-solid fa-arrow-left mr-2"></i> Précédent
                        </button>
                        <div class="flex gap-1">
                            <?php for($s=1; $s<=count($questions)+1; $s++): ?>
                                <div class="w-1.5 h-1.5 rounded-full bg-slate-200 step-dot" data-step="<?= $s ?>"></div>
                            <?php endfor; ?>
                        </div>
                        <div class="w-20"></div> <!-- Spacer for balance -->
                    </div>

                </form>
            <?php endif; ?>
        </div>

        <div class="text-center mt-10">
            <p class="text-[10px] font-black text-slate-300 uppercase italic tracking-widest">Propulsé par HelloBoard — Module Satisfaction</p>
        </div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = <?= count($questions) + 1 ?>;
        const ratings = {};

        function updateUI() {
            // Update indicator
            document.getElementById('step-indicator').innerText = `Question ${currentStep} / ${totalSteps}`;

            // Update progress
            const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
            document.getElementById('progress-fill').style.width = `${progress}%`;
            document.getElementById('percent-indicator').innerText = `${Math.round(progress)}%`;

            // Update dots
            document.querySelectorAll('.step-dot').forEach(dot => {
                dot.className = 'w-1.5 h-1.5 rounded-full transition-all duration-300 ' +
                                (parseInt(dot.dataset.step) === currentStep ? 'bg-blue-600 w-4' : (parseInt(dot.dataset.step) < currentStep ? 'bg-blue-300' : 'bg-slate-200'));
            });

            // Update prev button
            document.getElementById('prev-btn').disabled = (currentStep === 1);
        }

        function setRating(qIdx, val, el) {
            // Set visual active state
            const parent = el.parentElement;
            parent.querySelectorAll('.rating-btn').forEach(btn => btn.classList.remove('active', 'ring-4', 'ring-blue-100'));
            el.classList.add('active', 'ring-4', 'ring-blue-100');

            // Set hidden value
            document.getElementById(`q${qIdx}-val`).value = val;
            ratings[`q${qIdx}`] = val;

            // Auto advance after small delay
            setTimeout(() => {
                nextStep();
            }, 400);
        }

        function nextStep() {
            if (currentStep < totalSteps) {
                // Check if current question is answered (if it's not the comment step)
                if (currentStep <= totalSteps - 1) {
                    if (!document.getElementById(`q${currentStep}-val`).value) {
                        return; // Force answer
                    }
                }

                document.querySelector(`.step-content[data-step="${currentStep}"]`).classList.remove('active');
                currentStep++;
                document.querySelector(`.step-content[data-step="${currentStep}"]`).classList.add('active');
                updateUI();
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                document.querySelector(`.step-content[data-step="${currentStep}"]`).classList.remove('active');
                currentStep--;
                document.querySelector(`.step-content[data-step="${currentStep}"]`).classList.add('active');
                updateUI();
            }
        }

        // Initialize UI
        if (document.getElementById('step-indicator')) {
            updateUI();
        }
    </script>
</body>
</html>
