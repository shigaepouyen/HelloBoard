<?php
/**
 * Template for the Guest List / Check-in view.
 *
 * Variables available:
 * - $currentCamp: array - Campaign configuration
 * - $participants: array - List of participants
 * - $slug: string - Campaign slug
 */

$type = $currentCamp['formType'] ?? 'Event';
$unit = (in_array($type, ['Shop', 'Checkout', 'PaymentForm'])) ? 'articles' : 'inscrits';
$guestlistConfig = $currentCamp['guestlist'] ?? [
    'columns' => ['nom', 'prenom', 'formule', 'options'],
    'showCheckboxes' => true
];

$title = htmlspecialchars($currentCamp['title']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in — <?= $title ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .print-container { width: 100% !important; max-width: none !important; box-shadow: none !important; border: none !important; padding: 0 !important; }
            table { border-collapse: collapse !important; width: 100% !important; }
            th, td { border: 1px solid #e2e8f0 !important; padding: 8px !important; }
            tr { page-break-inside: avoid !important; }
        }

        .checked-in {
            background-color: #f0fdf4 !important; /* emerald-50 */
            color: #94a3b8; /* slate-400 */
        }
        .checked-in .check-box {
            background-color: #10b981 !important; /* emerald-500 */
            border-color: transparent !important;
            color: white !important;
        }
        .checked-in .search-target span:first-child {
            text-decoration: line-through;
        }

        /* Responsive adjustments for mobile/tablet check-in */
        @media (max-width: 640px) {
            .hide-mobile { display: none; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">

    <div class="no-print sticky top-0 z-50 bg-slate-900 text-white p-4 shadow-lg">
        <div class="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <a href="admin.php" class="w-10 h-10 flex items-center justify-center bg-white/10 rounded-full hover:bg-white/20 transition">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-sm font-black uppercase tracking-tighter"><?= $title ?></h1>
                    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">
                        <span id="checked-count">0</span> / <span id="total-count"><?= count($participants) ?></span> <?= $unit ?> présents
                    </div>
                </div>
            </div>

            <div class="flex-1 w-full max-w-md">
                <div class="relative">
                    <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-500"></i>
                    <input type="text" id="search" placeholder="Rechercher un nom, email..."
                           class="w-full bg-white/10 border border-white/10 rounded-2xl py-3 pl-12 pr-4 outline-none focus:bg-white/20 focus:border-blue-500 transition font-bold text-sm"
                           onkeyup="filterList()">
                </div>
            </div>

            <div class="flex gap-2">
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-2xl font-black uppercase text-[10px] tracking-widest transition flex items-center gap-2">
                    <i class="fa-solid fa-print"></i> Imprimer
                </button>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto p-4 md:p-8 print-container">
        <div class="bg-white rounded-[2rem] shadow-sm overflow-hidden print:shadow-none">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 text-slate-400 uppercase text-[10px] font-black tracking-widest">
                    <tr>
                        <?php if($guestlistConfig['showCheckboxes']): ?>
                            <th class="p-4 w-16 text-center border-b border-slate-100">Check</th>
                        <?php endif; ?>

                        <?php if(in_array('nom', $guestlistConfig['columns']) || in_array('prenom', $guestlistConfig['columns'])): ?>
                            <th class="p-4 border-b border-slate-100">Participant / Payer</th>
                        <?php endif; ?>

                        <?php if(in_array('formule', $guestlistConfig['columns']) || in_array('options', $guestlistConfig['columns'])): ?>
                            <th class="p-4 border-b border-slate-100">Détails de l'achat</th>
                        <?php endif; ?>

                        <?php if(in_array('email', $guestlistConfig['columns']) || in_array('phone', $guestlistConfig['columns'])): ?>
                            <th class="p-4 border-b border-slate-100 hide-mobile">Contact</th>
                        <?php endif; ?>

                        <?php if(in_array('date', $guestlistConfig['columns'])): ?>
                            <th class="p-4 border-b border-slate-100 hide-mobile">Date</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="list-body">
                    <?php foreach($participants as $idx => $p):
                        $checkId = $p['ref_commande'] . '_' . $idx;
                    ?>
                        <tr class="border-b border-slate-50 hover:bg-slate-50/50 cursor-pointer transition group"
                            onclick="toggleCheck(this, '<?= $checkId ?>')"
                            data-check-id="<?= $checkId ?>">

                            <?php if($guestlistConfig['showCheckboxes']): ?>
                                <td class="p-4 w-16 text-center">
                                    <div class="w-8 h-8 border-2 border-slate-200 rounded-xl mx-auto check-box flex items-center justify-center text-transparent transition-all duration-300 group-hover:border-blue-300">
                                        <i class="fa-solid fa-check"></i>
                                    </div>
                                </td>
                            <?php endif; ?>

                            <?php if(in_array('nom', $guestlistConfig['columns']) || in_array('prenom', $guestlistConfig['columns'])): ?>
                                <td class="p-4 search-target">
                                    <?php if(in_array('nom', $guestlistConfig['columns'])): ?>
                                        <span class="block font-black uppercase text-slate-800"><?= htmlspecialchars($p['nom']) ?></span>
                                    <?php endif; ?>
                                    <?php if(in_array('prenom', $guestlistConfig['columns'])): ?>
                                        <span class="block text-sm text-slate-500 font-semibold"><?= htmlspecialchars($p['prenom']) ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <?php if(in_array('formule', $guestlistConfig['columns']) || in_array('options', $guestlistConfig['columns'])): ?>
                                <td class="p-4">
                                    <?php if(in_array('formule', $guestlistConfig['columns'])): ?>
                                        <span class="inline-block px-2 py-1 bg-blue-50 text-blue-600 rounded-lg text-[10px] font-black uppercase mb-1">
                                            <?= htmlspecialchars($p['formule']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if(in_array('options', $guestlistConfig['columns']) && !empty($p['options'])): ?>
                                        <div class="text-[10px] text-slate-400 italic leading-tight">
                                            <?= htmlspecialchars($p['options']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <?php if(in_array('email', $guestlistConfig['columns']) || in_array('phone', $guestlistConfig['columns'])): ?>
                                <td class="p-4 hide-mobile">
                                    <?php if(in_array('email', $guestlistConfig['columns'])): ?>
                                        <div class="text-[10px] font-bold text-slate-500"><?= htmlspecialchars($p['email'] ?: '-') ?></div>
                                    <?php endif; ?>
                                    <?php if(in_array('phone', $guestlistConfig['columns'])): ?>
                                        <div class="text-[10px] text-slate-400 mt-1"><?= htmlspecialchars($p['phone'] ?: '-') ?></div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <?php if(in_array('date', $guestlistConfig['columns'])): ?>
                                <td class="p-4 hide-mobile text-[10px] font-bold text-slate-300 italic text-right">
                                    <?= $p['date'] ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if(empty($participants)): ?>
                <div class="p-20 text-center">
                    <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300 text-3xl">
                        <i class="fa-solid fa-users-slash"></i>
                    </div>
                    <p class="text-slate-400 font-bold italic">Aucun participant trouvé pour cette campagne.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const storageKey = 'checkin_<?= $slug ?>';
        let checkedCount = 0;

        function filterList() {
            const query = document.getElementById('search').value.toLowerCase();
            const rows = document.querySelectorAll('#list-body tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }

        function toggleCheck(row, id) {
            const isChecked = row.classList.toggle('checked-in');

            let state = JSON.parse(localStorage.getItem(storageKey) || '{}');
            if (isChecked) {
                state[id] = 1;
            } else {
                delete state[id];
            }
            localStorage.setItem(storageKey, JSON.stringify(state));

            updateStats();
        }

        function updateStats() {
            const checked = document.querySelectorAll('.checked-in').length;
            document.getElementById('checked-count').innerText = checked;
        }

        // Initialize from storage
        window.onload = function() {
            const state = JSON.parse(localStorage.getItem(storageKey) || '{}');
            document.querySelectorAll('#list-body tr').forEach(row => {
                const id = row.getAttribute('data-check-id');
                if (state[id]) {
                    row.classList.add('checked-in');
                }
            });
            updateStats();
        };
    </script>

</body>
</html>
