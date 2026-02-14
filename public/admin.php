<?php
session_start();
$srcPath = __DIR__ . '/../src/Services/';
require_once $srcPath . 'Storage.php';
require_once $srcPath . 'HelloAssoClient.php';
require_once $srcPath . 'SatisfactionService.php';
require_once $srcPath . 'AiService.php';

$globals = Storage::getGlobalSettings();
$adminPassword = $globals['adminPassword'] ?? null;

// --- 1. GESTION AUTHENTIFICATION ---
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
if (isset($_POST['login'])) {
    if ($_POST['password'] === $adminPassword) { $_SESSION['authenticated'] = true; } else { $loginError = "Mot de passe incorrect"; }
}

if ($adminPassword && !isset($_SESSION['authenticated'])) {
    ?>
    <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Admin</title><script src="https://cdn.tailwindcss.com"></script><style>@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&display=swap');body{font-family:'Plus Jakarta Sans',sans-serif;background:#0f172a;}</style></head><body class="min-h-screen flex items-center justify-center p-6"><div class="w-full max-w-md bg-white rounded-[3rem] p-10 text-center"><div class="w-20 h-20 mx-auto mb-8"><img src="assets/img/logo.svg" alt="HelloBoard" class="w-full h-full"></div><h2 class="text-3xl font-black mb-10 italic uppercase">Console Admin</h2><form method="POST" class="space-y-4"><input type="password" name="password" class="w-full bg-slate-50 border-2 border-transparent focus:border-blue-600 rounded-[1.5rem] p-5 text-2xl text-center outline-none" placeholder="••••••" required autofocus><button type="submit" name="login" class="w-full bg-blue-600 text-white py-5 rounded-[2rem] font-black uppercase text-xs">Accéder</button></form><?php if(isset($loginError)): ?><p class="text-red-500 font-bold mt-4"><?= $loginError ?></p><?php endif; ?></div></body></html>
    <?php exit;
}

$action = $_GET['action'] ?? 'list';
$localCampaigns = Storage::listCampaigns();
$client = new HelloAssoClient($globals['clientId']??'', $globals['clientSecret']??'', $globals['debugMode']??false);

// --- 2. TRAITEMENT DES ACTIONS ---

// Sauvegarde globale
if (isset($_POST['save_settings'])) {
    $newSettings = [
        'clientId' => trim($_POST['clientId']),
        'clientSecret' => trim($_POST['clientSecret']),
        'orgSlug' => trim($_POST['orgSlug']),
        'smtpHost' => trim($_POST['smtpHost'] ?? ''),
        'smtpPort' => trim($_POST['smtpPort'] ?? '587'),
        'smtpUser' => trim($_POST['smtpUser'] ?? ''),
        'smtpPass' => trim($_POST['smtpPass'] ?? ''),
        'smtpFromName' => trim($_POST['smtpFromName'] ?? ''),
        'mistralApiKey' => trim($_POST['mistralApiKey'] ?? ''),
        'adminPassword' => $adminPassword,
        'debugMode' => isset($_POST['debugMode'])
    ];
    Storage::saveGlobalSettings($newSettings);
    header('Location: admin.php?action=settings&saved=1'); exit;
}

// Scan des campagnes
$scanResults = null;
if (isset($_POST['run_scan'])) {
    $scanResults = $client->discoverCampaigns($globals['orgSlug'] ?? '');
    $action = 'scan';
}

// Toggle Archive
if ($action === 'toggle_archive' && isset($_GET['campaign'])) {
    $slug = $_GET['campaign'];
    $campaigns = Storage::listCampaigns();
    foreach($campaigns as $conf) {
        if ($conf['slug'] === $slug) {
            $conf['archived'] = !($conf['archived'] ?? false);
            Storage::saveCampaign($slug, $conf);
            break;
        }
    }
    header('Location: admin.php'); exit;
}

// Delete
if ($action === 'delete' && isset($_GET['campaign'])) {
    Storage::deleteCampaign($_GET['campaign']);
    header('Location: admin.php'); exit;
}

// Clear logs
if ($action === 'clear_log') {
    $type = $_GET['type'] ?? 'helloasso';
    $logFile = __DIR__ . '/../logs/' . ($type === 'ai' ? 'debug_ai.log' : 'debug_helloasso.log');
    if (file_exists($logFile)) unlink($logFile);
    header('Location: admin.php?action=settings'); exit;
}

// Download logs
if ($action === 'dl_log') {
    $type = $_GET['type'] ?? 'helloasso';
    $logFile = __DIR__ . '/../logs/' . ($type === 'ai' ? 'debug_ai.log' : 'debug_helloasso.log');
    if (file_exists($logFile)) {
        header('Content-Type: text/plain'); header('Content-Disposition: attachment; filename="' . basename($logFile) . '"');
        readfile($logFile); exit;
    }
}

// Save Satisfaction Mailing Draft
if (isset($_POST['save_satisfaction_mailing_draft'])) {
    $slug = $_POST['campaign'];
    $campaigns = Storage::listCampaigns();
    foreach($campaigns as $conf) {
        if ($conf['slug'] === $slug) {
            $conf['satisfactionMailingDraft'] = [
                'subject' => $_POST['subject'],
                'body' => $_POST['body']
            ];
            Storage::saveCampaign($slug, $conf);
            echo json_encode(['success' => true]);
            exit;
        }
    }
    exit;
}

// Save Board
if (isset($_POST['save_campaign'])) {
    $config = json_decode($_POST['config'], true);
    if ($config) {
        if (empty($config['shareToken'])) $config['shareToken'] = bin2hex(random_bytes(16));
        Storage::saveCampaign($config['slug'], $config);
    }
    echo json_encode(['success' => true]); exit;
}

// Save Mailing Draft
if (isset($_POST['save_mailing_draft'])) {
    $slug = $_POST['campaign'];
    $campaigns = Storage::listCampaigns();
    foreach($campaigns as $conf) {
        if ($conf['slug'] === $slug) {
            $conf['mailingDraft'] = [
                'subject' => $_POST['subject'],
                'body' => $_POST['body']
            ];
            Storage::saveCampaign($slug, $conf);

            if (!empty($_FILES['attachment']['name'])) {
                Storage::saveMailingAttachment($slug, $_FILES['attachment']);
            }

            echo json_encode(['success' => true]);
            exit;
        }
    }
    exit;
}

// Delete Mailing Attachment
if ($action === 'delete_attachment' && isset($_GET['campaign']) && isset($_GET['file'])) {
    Storage::deleteMailingAttachment($_GET['campaign'], $_GET['file']);
    header('Location: admin.php?action=mailing&campaign=' . $_GET['campaign']);
    exit;
}

// Send One Email
if ($action === 'mailing_send_one' && isset($_POST['campaign'])) {
    header('Content-Type: application/json');
    $slug = $_POST['campaign'];
    $targetEmail = $_POST['email'];
    $firstName = $_POST['firstName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $isTest = isset($_POST['is_test']) && $_POST['is_test'] == '1';

    $campaigns = Storage::listCampaigns();
    $currentCamp = null;
    foreach($campaigns as $c) { if($c['slug'] === $slug) $currentCamp = $c; }

    if ($currentCamp) {
        require_once $srcPath . 'MailService.php';
        $mailer = new MailService($globals);

        $history = Storage::getMailingHistory($slug);
        if (!$isTest && !empty($history[$targetEmail]['sent_at'])) {
            echo json_encode(['success' => false, 'error' => 'Déjà envoyé']);
            exit;
        }

        $token = bin2hex(random_bytes(16));
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
        $host = $_SERVER['HTTP_HOST'];
        $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $trackingUrl = $protocol . '://' . $host . $path . '/track.php?c=' . $slug . '&t=' . $token;

        $subject = $_POST['subject'];
        $body = $_POST['body'];

        try {
            $attachments = Storage::listMailingAttachments($slug);
            $attachmentPaths = array_column($attachments, 'path');

            $mailer->send($targetEmail, $subject, $body, [
                'NOM' => strtoupper($lastName),
                'PRENOM' => $firstName,
                'NOM_CAMPAGNE' => $currentCamp['title']
            ], $trackingUrl, $attachmentPaths);

            if (!$isTest) {
                $history[$targetEmail] = [
                    'sent_at' => date('Y-m-d H:i:s'),
                    'token' => $token,
                    'read_at' => null
                ];
                Storage::saveMailingHistory($slug, $history);
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    exit;
}

// Global Satisfaction Action (Reporting)
if ($action === 'satisfaction_global') {
    $satService = new SatisfactionService();
    $filterSlug = $_GET['campaign_filter'] ?? null;
    if (empty($filterSlug)) $filterSlug = null;

    if (isset($_GET['delete'])) {
        $satService->deleteParticipation($_GET['delete']);
        header('Location: admin.php?action=satisfaction_global' . ($filterSlug ? '&campaign_filter='.$filterSlug : ''));
        exit;
    }
    $stats = $satService->getStats($filterSlug);
    $statsBySource = $satService->getStatsBySource($filterSlug);
    $responses = $satService->getResponsesByCampaign($filterSlug);
    include __DIR__ . '/../templates/satisfaction_global.php';
    exit;
}

// Save Satisfaction Questions
if (isset($_POST['save_satisfaction_questions'])) {
    $satService = new SatisfactionService();
    $questions = json_decode($_POST['questions'], true);
    $satService->saveQuestions($_POST['campaign'], $questions);
    echo json_encode(['success' => true]);
    exit;
}

// Get Satisfaction Recipients
if ($action === 'satisfaction_recipients' && isset($_GET['campaign'])) {
    header('Content-Type: application/json');
    $slug = $_GET['campaign'];
    $typeFilter = $_GET['type_filter'] ?? null;
    $excludeSent = isset($_GET['exclude_sent']) && $_GET['exclude_sent'] === '1';
    $excludeEver = isset($_GET['exclude_ever']) && $_GET['exclude_ever'] === '1';

    $currentCamp = null;
    foreach($localCampaigns as $c) { if($c['slug'] === $slug) $currentCamp = $c; }

    if ($currentCamp) {
        // --- FILTRE ELIGIBILITE : ACTION TERMINEE ---
        $formDetails = $client->getFormDetails($currentCamp['orgSlug'], $currentCamp['formSlug'], $currentCamp['formType']);
        $isFinished = true;
        $reason = null;

        if ($currentCamp['formType'] === 'Event' && !empty($formDetails['endDate'])) {
            $endDate = new DateTime($formDetails['endDate']);
            $now = new DateTime();
            if ($endDate > $now) {
                $isFinished = false;
                $reason = "L'événement n'est pas encore terminé (fin prévue le " . $endDate->format('d/m/Y à H:i') . ").";
            }
        }
        // Pour les autres types (Shop, Donation, etc.), on considère l'action comme "terminée" dès que payée si pas de date de fin claire

        if (!$isFinished) {
            echo json_encode(['success' => true, 'isEligible' => false, 'reason' => $reason, 'recipients' => []]);
            exit;
        }

        $satService = new SatisfactionService();
        $orders = $client->fetchAllOrders($currentCamp['orgSlug'], $currentCamp['formSlug'], $currentCamp['formType']);
        $recipients = [];

        foreach ($orders as $o) {
            // Un order est éligible si au moins un item est 'Paid' ou 'Processed'
            $hasValidItem = false;
            foreach ($o['items'] ?? [] as $item) {
                if (in_array(($item['state'] ?? ''), ['Paid', 'Processed'])) {
                    $hasValidItem = true;
                    break;
                }
            }
            if (!$hasValidItem) continue;

            $email = trim(strtolower($o['payer']['email'] ?? ''));
            if (!$email) continue;

            if ($excludeSent && $satService->isAlreadySent($slug, $o['id'])) continue;
            if ($excludeEver && $satService->hasEverReceived($email)) continue;

            $recipients[$o['id']] = [
                'orderId' => $o['id'],
                'email' => $email,
                'firstName' => trim($o['payer']['firstName'] ?? ''),
                'lastName' => trim($o['payer']['lastName'] ?? ''),
                'itemName' => $currentCamp['title'],
                'date' => $o['date']
            ];
        }
        echo json_encode([
            'success' => true,
            'isEligible' => true,
            'recipients' => array_values($recipients)
        ]);
    }
    exit;
}

// AI Generation
if ($action === 'ai_generate') {
    ob_start();
    header('Content-Type: application/json');
    $prompt = $_POST['prompt'] ?? '';
    $context = $_POST['context'] ?? 'Mailing';
    $campaignSlug = $_POST['campaign'] ?? '';

    if (empty($prompt)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Prompt vide']);
        exit;
    }

    $apiKey = $globals['mistralApiKey'] ?? '';
    if (empty($apiKey)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Clé API Mistral non configurée']);
        exit;
    }

    $campaignTitle = "votre campagne";
    foreach($localCampaigns as $c) {
        if ($c['slug'] === $campaignSlug) {
            $campaignTitle = $c['title'];
            break;
        }
    }

    try {
        $ai = new AiService($apiKey, $globals['debugMode'] ?? false);
        $generatedBody = $ai->generateEmailBody($prompt, $context, $campaignTitle);
        ob_end_clean();
        echo json_encode(['success' => true, 'body' => $generatedBody]);
    } catch (Throwable $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Send Satisfaction Email (or Test)
if ($action === 'satisfaction_send_one' && isset($_POST['campaign'])) {
    header('Content-Type: application/json');
    $slug = $_POST['campaign'];
    $orderId = $_POST['orderId'] ?? ('TEST-' . bin2hex(random_bytes(4)));
    $email = $_POST['email'];
    $firstName = $_POST['firstName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $itemName = $_POST['itemName'] ?? '';
    $isTest = isset($_POST['is_test']) && $_POST['is_test'] == '1';

    $currentCamp = null;
    foreach($localCampaigns as $c) { if($c['slug'] === $slug) $currentCamp = $c; }

    if ($currentCamp) {
        require_once $srcPath . 'MailService.php';
        $mailer = new MailService($globals);
        $satService = new SatisfactionService();

        if (!$isTest && $satService->isAlreadySent($slug, $orderId)) {
            echo json_encode(['success' => false, 'error' => 'Déjà envoyé']);
            exit;
        }

        $token = $satService->generateToken($slug, $orderId, $email, trim($firstName . ' ' . $lastName), $itemName);

        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
        $host = $_SERVER['HTTP_HOST'];
        $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $baseUrl = $protocol . '://' . $host . $path;

        $surveyUrl = $baseUrl . '/satisfaction.php?t=' . $token;
        $trackingUrl = $baseUrl . '/track.php?c=' . $slug . '&t=' . $token;

        $subject = $_POST['subject'] ?? ("Votre avis nous intéresse : " . $currentCamp['title']);
        $body = $_POST['body'] ?? ("Bonjour {{PRENOM}},\n\nMerci pour votre récent achat/participation à \"" . $currentCamp['title'] . "\".\n\nNous aimerions recueillir votre avis via ce court questionnaire :\n" . $surveyUrl . "\n\nCordialement,\n" . ($globals['smtpFromName'] ?? 'L\'équipe'));

        try {
            $mailer->send($email, $subject, $body, [
                'NOM' => strtoupper($lastName),
                'PRENOM' => $firstName,
                'NOM_CAMPAGNE' => $currentCamp['title'],
                'SURVEY_URL' => $surveyUrl
            ], $trackingUrl, []);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    exit;
}

// Sync Checkins
if ($action === 'sync_checkins' && isset($_GET['campaign'])) {
    header('Content-Type: application/json');
    $slug = $_GET['campaign'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $checkins = json_decode(file_get_contents('php://input'), true);
        if ($checkins !== null) {
            Storage::saveCheckins($slug, $checkins);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
        }
    } else {
        $checkins = Storage::getCheckins($slug);
        echo json_encode($checkins ?: new stdClass(), JSON_FORCE_OBJECT);
    }
    exit;
}

// API Analyze (for configuration screen)
if ($action === 'analyze') {
    header('Content-Type: application/json');
    $form = $_GET['form']; $org = $_GET['org'];
    $orders = $client->fetchAllOrders($org, $form, $_GET['type'] ?? 'Event');
    $itemsFound = [];
    foreach(array_slice($orders, 0, 100) as $o) {
        foreach($o['items'] ?? [] as $i) {
            if(!empty($i['name'])) {
                $name = trim($i['name']);
                if (!isset($itemsFound[$name])) {
                    $isProbableMain = ($i['amount'] > 0);
                    $itemsFound[$name] = ['pattern' => $name, 'category' => 'item', 'isMain' => $isProbableMain];
                }
            }
            foreach($i['customFields'] ?? [] as $cf) {
                if(!empty($cf['name'])) {
                    $name = trim($cf['name']);
                    if (!isset($itemsFound[$name])) {
                        $itemsFound[$name] = ['pattern' => $name, 'category' => 'field', 'isMain' => false];
                    }
                }
            }
        }
    }

    $configFile = __DIR__ . "/../config/campaigns/$form.json";
    $existing = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    echo json_encode([
        'rules' => $existing['rules'] ?? [],
        'goals' => $existing['goals'] ?? ['revenue'=>0, 'tickets'=>0, 'n1'=>0],
        'markers' => $existing['markers'] ?? [],
        'guestlist' => $existing['guestlist'] ?? null,
        'shareToken' => $existing['shareToken'] ?? null,
        'formType' => $existing['formType'] ?? null,
        'title' => $existing['title'] ?? null,
        'apiItems' => array_values($itemsFound)
    ]); exit;
}

// Exports & Mailing View
if (($action === 'export_csv' || $action === 'guestlist' || $action === 'mailing' || $action === 'satisfaction') && isset($_GET['campaign'])) {
    $slug = $_GET['campaign'];
    $currentCamp = null;
    foreach($localCampaigns as $c) { if($c['slug'] === $slug) $currentCamp = $c; }

    if ($currentCamp) {
        $orders = $client->fetchAllOrders($currentCamp['orgSlug'], $currentCamp['formSlug'], $currentCamp['formType']);
        $rules = $currentCamp['rules'] ?? [];
        $matchRule = function($text) use ($rules) {
            $text = mb_strtolower($text, 'UTF-8');
            foreach ($rules as $r) {
                if (strpos($text, mb_strtolower($r['pattern'], 'UTF-8')) !== false) return $r;
            }
            return null;
        };

        $participants = [];
        $groupByOrder = $currentCamp['guestlist']['groupByOrder'] ?? false;
        // For Event forms, we always want individual rows per ticket (one line per participant)
        if ($currentCamp['formType'] === 'Event') $groupByOrder = false;

        foreach($orders as $order) {
            $hasDonation = false;
            foreach($order['items'] as $item) {
                if ($item['type'] === 'Donation') { $hasDonation = true; break; }
            }

            $orderItems = [];
            foreach($order['items'] as $item) {
                if (isset($item['state']) && $item['state'] === 'Canceled') continue;
                if ($item['type'] === 'Donation') continue;

                $rule = $matchRule($item['name']);
                $itemType = 'Option';
                if ($rule) { $itemType = $rule['type']; }
                else if (($item['amount'] ?? 0) > 0) { $itemType = 'Billet'; }

                $orderItems[] = array_merge($item, ['computedType' => $itemType]);
            }

            if (empty($orderItems)) continue;

            $mainItems = [];
            $secondaryItems = [];
            $orderPhone = '';

            foreach($orderItems as $item) {
                if ($item['computedType'] === 'Billet') {
                    $mainItems[] = $item;
                } else {
                    $secondaryItems[] = $item;
                }
                // Try to find a phone number in ANY item of the order
                foreach($item['customFields'] ?? [] as $f) {
                    if (empty($orderPhone) && (strpos(mb_strtolower($f['name']), 'téléphone') !== false || $f['type'] === 'Phone')) {
                        $orderPhone = $f['answer'];
                    }
                }
            }

            // If we have no main items (e.g. only options in a shop order), treat all options as main items for display if not grouped
            if (empty($mainItems) && !$groupByOrder) {
                $mainItems = $secondaryItems;
                $secondaryItems = [];
            }

            if ($groupByOrder && !empty($mainItems)) {
                // GROUPED MODE
                $mainQuantities = []; $secondaryQuantities = []; $fieldsMap = []; $pNames = [];
                $firstFN = ''; $firstLN = '';

                foreach($mainItems as $item) {
                    if(!isset($mainQuantities[$item['name']])) $mainQuantities[$item['name']] = 0;
                    $mainQuantities[$item['name']]++;

                    $fn = trim($item['user']['firstName'] ?? '');
                    $ln = trim($item['user']['lastName'] ?? '');
                    if (empty($fn) && empty($ln)) {
                        $fn = trim($order['payer']['firstName'] ?? '');
                        $ln = trim($order['payer']['lastName'] ?? '');
                    }

                    if (empty($firstFN) && empty($firstLN)) { $firstFN = $fn; $firstLN = $ln; }
                    $uName = trim($fn . ' ' . $ln);
                    if (!empty($uName)) $pNames[] = $uName;

                    foreach($item['customFields'] ?? [] as $f) {
                        if (!isset($fieldsMap[$f['name']])) $fieldsMap[$f['name']] = [];
                        $fieldsMap[$f['name']][] = $f['answer'];
                    }
                }

                foreach($secondaryItems as $item) {
                    if(!isset($secondaryQuantities[$item['name']])) $secondaryQuantities[$item['name']] = 0;
                    $secondaryQuantities[$item['name']]++;
                    foreach($item['customFields'] ?? [] as $f) {
                        if (!isset($fieldsMap[$f['name']])) $fieldsMap[$f['name']] = [];
                        $fieldsMap[$f['name']][] = $f['answer'];
                    }
                }

                $flatFields = [];
                foreach($fieldsMap as $label => $answers) {
                    $flatFields[$label] = implode(', ', array_unique($answers));
                }

                $participants[] = [
                    'date' => substr($order['date'], 0, 10),
                    'nom' => strtoupper($firstLN),
                    'prenom' => $firstFN,
                    'main_items' => $mainQuantities,
                    'secondary_items' => $secondaryQuantities,
                    'fields_map' => $flatFields,
                    'hasDonation' => $hasDonation,
                    'email' => $order['payer']['email'] ?? '',
                    'phone' => $orderPhone,
                    'ref' => $order['id'],
                    'payer_name' => trim(trim($order['payer']['firstName'] ?? '') . ' ' . trim($order['payer']['lastName'] ?? '')),
                    'participant_names' => array_values(array_unique($pNames))
                ];
            } else {
                // INDIVIDUAL MODE (or fallback for empty main items)
                $orderSecondaryQuantities = [];
                foreach($secondaryItems as $si) {
                    if(!isset($orderSecondaryQuantities[$si['name']])) $orderSecondaryQuantities[$si['name']] = 0;
                    $orderSecondaryQuantities[$si['name']]++;
                }

                foreach($mainItems as $item) {
                    $flatFields = [];
                    foreach($item['customFields'] ?? [] as $f) {
                        $flatFields[$f['name']] = $f['answer'];
                    }

                    $lastName = trim($item['user']['lastName'] ?? '');
                    $firstName = trim($item['user']['firstName'] ?? '');
                    if (empty($lastName) && empty($firstName)) {
                        $lastName = trim($order['payer']['lastName'] ?? '');
                        $firstName = trim($order['payer']['firstName'] ?? '');
                    }

                    $participants[] = [
                        'date' => substr($order['date'], 0, 10),
                        'nom' => strtoupper($lastName),
                        'prenom' => $firstName,
                        'main_items' => [$item['name'] => 1],
                        'secondary_items' => $orderSecondaryQuantities, // Attach all order options to each main item
                        'fields_map' => $flatFields,
                        'hasDonation' => $hasDonation,
                        'email' => $order['payer']['email'] ?? '',
                        'phone' => $orderPhone,
                        'ref' => $order['id'] . '-' . $item['id'],
                        'payer_name' => trim(trim($order['payer']['firstName'] ?? '') . ' ' . trim($order['payer']['lastName'] ?? ''))
                    ];
                }
            }
        }

        usort($participants, function($a, $b) {
            $cmp = strcmp($a['nom'], $b['nom']);
            if ($cmp === 0) $cmp = strcmp($a['prenom'], $b['prenom']);
            return $cmp;
        });

        if ($action === 'export_csv') {
            // Identify all unique columns
            $allMainItems = [];
            $allSecondaryItems = [];
            $allCustomFields = [];
            foreach ($participants as $p) {
                foreach ($p['main_items'] as $name => $qty) $allMainItems[$name] = true;
                foreach ($p['secondary_items'] as $name => $qty) $allSecondaryItems[$name] = true;
                foreach ($p['fields_map'] as $label => $val) $allCustomFields[$label] = true;
            }
            $mainItemCols = array_keys($allMainItems);
            $secondaryItemCols = array_keys($allSecondaryItems);
            $customFieldCols = array_keys($allCustomFields);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=inscrits_' . $slug . '_' . date('Y-m-d') . '.csv');
            $output = fopen('php://output', 'w');

            // Build Header
            $header = ['Date', 'Nom', 'Prenom'];
            foreach ($mainItemCols as $col) $header[] = $col;
            foreach ($secondaryItemCols as $col) $header[] = $col;
            foreach ($customFieldCols as $col) $header[] = $col;
            $header = array_merge($header, ['Acheteur', 'Email', 'Telephone', 'Donation', 'Ref']);

            fputcsv($output, $header, ',', '"', "\\");

            foreach ($participants as $p) {
                $row = [$p['date'], $p['nom'], $p['prenom']];
                // Fill main items
                foreach ($mainItemCols as $col) {
                    $row[] = $p['main_items'][$col] ?? 0;
                }
                // Fill secondary items
                foreach ($secondaryItemCols as $col) {
                    $row[] = $p['secondary_items'][$col] ?? 0;
                }
                // Fill custom fields
                foreach ($customFieldCols as $col) {
                    $row[] = $p['fields_map'][$col] ?? '';
                }

                $row = array_merge($row, [
                    $p['payer_name'] ?? '',
                    $p['email'],
                    $p['phone'],
                    ($p['hasDonation'] ? 'OUI' : 'NON'),
                    $p['ref']
                ]);

                fputcsv($output, $row, ',', '"', "\\");
            }
            exit;
        }
        if ($action === 'guestlist') {
            include __DIR__ . '/../templates/guestlist.php';
            exit;
        }
        if ($action === 'mailing') {
            $payers = [];
            foreach ($orders as $o) {
                $email = trim(strtolower($o['payer']['email'] ?? ''));
                if (!$email) continue;
                if (!isset($payers[$email])) {
                    $payers[$email] = [
                        'email' => $email,
                        'firstName' => trim($o['payer']['firstName'] ?? ''),
                        'lastName' => trim($o['payer']['lastName'] ?? ''),
                        'orderDate' => $o['date']
                    ];
                }
            }
            usort($payers, function($a, $b) {
                return strcmp($a['lastName'] ?? '', $b['lastName'] ?? '') ?: strcmp($a['firstName'] ?? '', $b['firstName'] ?? '');
            });
            $history = Storage::getMailingHistory($slug);
            $attachments = Storage::listMailingAttachments($slug);
            $mailingDraft = $currentCamp['mailingDraft'] ?? ['subject' => '', 'body' => "Bonjour {{PRENOM}},\n\nCeci est un rappel pour la campagne {{NOM_CAMPAGNE}}.\n\nCordialement."];
            include __DIR__ . '/../templates/mailing.php';
            exit;
        }
        if ($action === 'satisfaction') {
            $satService = new SatisfactionService();
            if (isset($_GET['delete'])) {
                $satService->deleteParticipation($_GET['delete']);
                header('Location: admin.php?action=satisfaction&campaign=' . $slug);
                exit;
            }
            $questions = $satService->getQuestions($slug, $currentCamp['formType']);
            $tokens = $satService->getTokensByCampaign($slug);

            $mailingDraft = $currentCamp['satisfactionMailingDraft'] ?? null;
            if (!$mailingDraft) {
                $fType = $currentCamp['formType'] ?? 'Event';
                if ($fType === 'Shop' || $fType === 'Checkout' || $fType === 'PaymentForm' || $fType === 'Product') {
                    $mailingDraft = [
                        'subject' => "📦 Votre commande HelloAsso : qu'en avez-vous pensé ?",
                        'body' => "Bonjour {{PRENOM}},\n\nVous avez récemment effectué un achat sur notre boutique HelloAsso pour \"{{NOM_CAMPAGNE}}\".\n\nNous espérons que vos articles vous apportent entière satisfaction ! Pourriez-vous prendre 1 minute pour nous donner votre avis sur votre expérience d'achat et la qualité des produits ?\n\nVotre feedback est précieux :\n{{SURVEY_URL}}\n\nÀ bientôt,\n" . ($globals['smtpFromName'] ?? 'L\'équipe')
                    ];
                } else if ($fType === 'Donation') {
                    $mailingDraft = [
                        'subject' => "❤️ Merci pour votre don : votre avis compte",
                        'body' => "Bonjour {{PRENOM}},\n\nNous vous remercions encore pour votre généreux soutien à notre association lors de la campagne \"{{NOM_CAMPAGNE}}\".\n\nNous aimerions savoir comment vous avez trouvé le processus de don et si vous vous sentez suffisamment informé de l'usage des fonds. Cela nous aide à mieux communiquer avec nos donateurs.\n\nDonnez-nous votre avis ici :\n{{SURVEY_URL}}\n\nMerci pour votre confiance,\n" . ($globals['smtpFromName'] ?? 'L\'équipe')
                    ];
                } else if ($fType === 'Membership') {
                    $mailingDraft = [
                        'subject' => "🆔 Bienvenue parmi nous ! Votre avis sur l'adhésion",
                        'body' => "Bonjour {{PRENOM}},\n\nBienvenue dans notre association ! Nous sommes ravis de vous compter parmi nos adhérents pour \"{{NOM_CAMPAGNE}}\".\n\nAfin de mieux accueillir nos membres, nous aimerions recueillir votre avis sur la simplicité du processus d'adhésion et vos premières impressions.\n\nRépondre au questionnaire :\n{{SURVEY_URL}}\n\nÀ très vite,\n" . ($globals['smtpFromName'] ?? 'L\'équipe')
                    ];
                } else {
                    $mailingDraft = [
                        'subject' => "🎟️ Votre avis sur l'événement : " . $currentCamp['title'],
                        'body' => "Bonjour {{PRENOM}},\n\nMerci d'avoir participé à notre événement \"{{NOM_CAMPAGNE}}\". Nous espérons que vous avez passé un excellent moment !\n\nNous cherchons constamment à nous améliorer. Pourriez-vous nous accorder quelques instants pour nous dire ce que vous avez pensé de l'organisation et de l'accueil ?\n\nLien du questionnaire :\n{{SURVEY_URL}}\n\nMerci et à bientôt,\n" . ($globals['smtpFromName'] ?? 'L\'équipe')
                    ];
                }
            }

            include __DIR__ . '/../templates/satisfaction.php';
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>HelloBoard — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
        .admin-card { background: white; border-radius: 2rem; border: 1px solid #edf2f7; }
        .input-soft { background: #f1f5f9; border: 2px solid transparent; border-radius: 1.25rem; padding: 12px 16px; font-weight: 700; width: 100%; outline: none; transition: 0.2s; }
        .input-soft:focus { border-color: #2563eb; background: white; }
        .toggle-btn { width: 44px; height: 24px; background: #cbd5e1; border-radius: 20px; position: relative; cursor: pointer; }
        .toggle-btn.active { background: #2563eb; }
        .toggle-btn::after { content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; background: white; border-radius: 50%; transition: 0.3s; }
        .toggle-btn.active::after { transform: translateX(20px); }
        .animate-fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="pb-32">

    <nav class="p-4 md:p-6 bg-white border-b border-slate-100 sticky top-0 z-50 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-4">
            <a href="admin.php" class="flex items-center gap-2">
                <img src="assets/img/logo.svg" alt="HelloBoard" class="w-8 h-8">
                <h1 class="font-black italic uppercase text-slate-900 hidden md:block">Console Admin</h1>
            </a>
        </div>
        <div class="flex items-center gap-6">
            <a href="admin.php" class="text-xs font-black uppercase tracking-widest <?= ($action === 'list' || $action === 'edit' || $action === 'scan') ? 'text-blue-600' : 'text-slate-400' ?>">Boards</a>
            <a href="admin.php?action=satisfaction_global" class="text-xs font-black uppercase tracking-widest <?= strpos($action, 'satisfaction') !== false ? 'text-blue-600' : 'text-slate-400' ?>">Satisfaction</a>
            <a href="admin.php?action=settings" class="text-xs font-black uppercase tracking-widest <?= $action === 'settings' ? 'text-blue-600' : 'text-slate-400' ?>">Réglages</a>
            <div class="h-6 w-px bg-slate-200"></div>
            <a href="index.php" class="text-xs font-black uppercase text-slate-400 hover:text-red-500 transition">Quitter</a>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-12">

        <?php if ($action === 'list'): ?>
            <!-- LISTE DES BOARDS -->
            <div class="animate-fade-in">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-10">
                    <div>
                        <h2 class="text-3xl font-black italic uppercase text-slate-900">Mes Tableaux</h2>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Gérez vos boards et visualisations</p>
                    </div>
                    <div class="flex gap-3">
                        <form method="POST" class="inline">
                            <button type="submit" name="run_scan" class="bg-white border border-slate-200 text-slate-600 px-6 py-4 rounded-2xl font-black uppercase text-xs hover:bg-slate-50 transition shadow-sm">
                                <i class="fa-solid fa-sync-alt mr-2"></i> Scanner HelloAsso
                            </button>
                        </form>
                    </div>
                </div>

                <div class="grid gap-4">
                    <?php if(empty($localCampaigns)): ?>
                        <div class="text-center py-20 bg-slate-50 rounded-[3rem] border-2 border-dashed border-slate-200">
                            <p class="text-slate-400 font-bold mb-4 italic">Aucun board n'a été créé pour le moment.</p>
                            <p class="text-slate-300 text-[10px] uppercase font-black">Utilisez le bouton "Scanner" pour importer vos formulaires</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach($localCampaigns as $c): $isArchived = !empty($c['archived']); ?>
                        <div class="admin-card p-4 md:p-6 flex flex-col md:flex-row justify-between items-center gap-4 transition group hover:shadow-xl hover:shadow-slate-200/50 hover:border-blue-200 <?= $isArchived ? 'opacity-60 bg-slate-50 grayscale' : '' ?>">
                            <div class="flex-1 w-full md:w-auto">
                                <div class="flex items-center gap-3">
                                    <h3 class="font-black text-xl text-slate-800"><?= htmlspecialchars($c['title']) ?></h3>
                                    <?php if($isArchived): ?><span class="bg-slate-200 text-slate-500 text-[9px] font-black px-2 py-0.5 rounded uppercase">Archivé</span><?php endif; ?>
                                </div>
                                <div class="flex items-center gap-4 mt-2">
                                    <span class="text-[10px] font-black bg-slate-100 text-slate-500 px-2 py-1 rounded uppercase"><?= $c['formType'] ?></span>
                                    <a href="index.php?campaign=<?= $c['slug'] ?>" target="_blank" class="text-[10px] text-blue-500 font-black uppercase hover:underline"><i class="fa-solid fa-external-link-alt mr-1"></i> Voir</a>
                                    <a href="admin.php?action=guestlist&campaign=<?= $c['slug'] ?>" onclick="showLoader()" class="text-[10px] text-emerald-600 font-black uppercase hover:underline"><i class="fa-solid fa-clipboard-list mr-1"></i> Inscrits</a>
                                    <a href="admin.php?action=mailing&campaign=<?= $c['slug'] ?>" class="text-[10px] text-purple-600 font-black uppercase hover:underline"><i class="fa-solid fa-envelope mr-1"></i> Rappel</a>
                                    <a href="admin.php?action=satisfaction&campaign=<?= $c['slug'] ?>" class="text-[10px] text-amber-600 font-black uppercase hover:underline"><i class="fa-solid fa-star mr-1"></i> Satisfaction</a>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center justify-center md:justify-end gap-2 md:gap-3 w-full md:w-auto mt-2 md:mt-0">
                                <?php
                                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                                    $shareUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . str_replace('admin.php', 'index.php', $_SERVER['SCRIPT_NAME']) . '?campaign=' . $c['slug'] . '&token=' . ($c['shareToken'] ?? '');
                                ?>
                                <div class="flex gap-2">
                                    <button onclick="copyToClipboard('<?= $shareUrl ?>', this)" class="w-10 h-10 md:w-12 md:h-12 flex items-center justify-center bg-slate-50 text-slate-400 rounded-xl hover:bg-slate-100 transition" title="Copier le lien public"><i class="fa-solid fa-link"></i></button>
                                    <a href="admin.php?action=export_csv&campaign=<?= $c['slug'] ?>" onclick="return btnDownloadLoading(this)" class="w-10 h-10 md:w-12 md:h-12 flex items-center justify-center bg-slate-50 text-slate-400 rounded-xl hover:bg-slate-100 transition" title="Export CSV"><i class="fa-solid fa-download"></i></a>
                                    <a href="admin.php?action=toggle_archive&campaign=<?= $c['slug'] ?>" class="w-10 h-10 md:w-12 md:h-12 flex items-center justify-center bg-slate-50 text-slate-400 rounded-xl hover:bg-slate-100 transition" title="<?= $isArchived ? 'Restaurer' : 'Archiver' ?>"><i class="fa-solid <?= $isArchived ? 'fa-box-open' : 'fa-box-archive' ?>"></i></a>
                                    <button onclick="confirmDelete('<?= $c['slug'] ?>', '<?= htmlspecialchars(addslashes($c['title']), ENT_QUOTES) ?>')" class="w-10 h-10 md:w-12 md:h-12 flex items-center justify-center bg-red-50 text-red-300 rounded-xl hover:bg-red-500 hover:text-white transition" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                                </div>
                                <a href="admin.php?action=edit&campaign=<?= $c['slug'] ?>" class="bg-blue-600 text-white px-5 py-3 md:px-8 md:py-4 rounded-2xl text-[10px] md:text-xs font-black uppercase shadow-lg shadow-blue-100 transition transform active:scale-95">Réglages</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php elseif ($action === 'settings'): ?>
            <!-- PARAMETRES GLOBAUX -->
            <div class="animate-fade-in max-w-2xl mx-auto">
                <div class="mb-10">
                    <h2 class="text-3xl font-black italic uppercase text-slate-900">Configuration</h2>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Paramètres de connexion API et sécurité</p>
                </div>

                <?php if(isset($_GET['saved'])): ?>
                    <div class="mb-8 p-4 bg-emerald-50 text-emerald-600 rounded-2xl font-black uppercase text-[10px] tracking-widest border border-emerald-100 flex items-center gap-3 animate-bounce">
                        <i class="fa-solid fa-check-circle text-lg"></i> Configuration enregistrée !
                    </div>
                <?php endif; ?>

                <div class="admin-card p-6 md:p-10 mb-8">
                    <form method="POST">
                        <div class="space-y-8">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase block mb-3 tracking-widest italic">Identifiants HelloAsso</label>
                                <div class="grid gap-4">
                                    <input type="text" name="clientId" placeholder="Client ID" value="<?= htmlspecialchars($globals['clientId']??'') ?>" class="input-soft" required>
                                    <input type="password" name="clientSecret" placeholder="Client Secret" value="<?= htmlspecialchars($globals['clientSecret']??'') ?>" class="input-soft" required>
                                    <input type="text" name="orgSlug" placeholder="Slug de l'organisation" value="<?= htmlspecialchars($globals['orgSlug']??'') ?>" class="input-soft" required>
                                </div>
                            </div>

                            <div class="pt-8 border-t border-slate-100">
                                <label class="text-[10px] font-black text-slate-400 uppercase block mb-3 tracking-widest italic">Configuration Email (SMTP Gmail)</label>
                                <div class="grid gap-4">
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <div class="md:col-span-3">
                                            <input type="text" name="smtpHost" placeholder="Serveur SMTP (ex: smtp.gmail.com)" value="<?= htmlspecialchars($globals['smtpHost']??'smtp.gmail.com') ?>" class="input-soft">
                                        </div>
                                        <div>
                                            <input type="text" name="smtpPort" placeholder="Port (587)" value="<?= htmlspecialchars($globals['smtpPort']??'587') ?>" class="input-soft">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <input type="text" name="smtpUser" placeholder="Email Gmail" value="<?= htmlspecialchars($globals['smtpUser']??'') ?>" class="input-soft">
                                        <input type="password" name="smtpPass" placeholder="Mot de passe d'application" value="<?= htmlspecialchars($globals['smtpPass']??'') ?>" class="input-soft">
                                    </div>
                                    <input type="text" name="smtpFromName" placeholder="Nom de l'expéditeur" value="<?= htmlspecialchars($globals['smtpFromName']??'HelloBoard') ?>" class="input-soft">
                                </div>
                            </div>

                            <div class="pt-8 border-t border-slate-100">
                                <label class="text-[10px] font-black text-slate-400 uppercase block mb-3 tracking-widest italic">Intelligence Artificielle (Mistral AI)</label>
                                <div class="grid gap-4">
                                    <input type="password" name="mistralApiKey" placeholder="Clé API Mistral (laissez vide pour désactiver)" value="<?= htmlspecialchars($globals['mistralApiKey']??'') ?>" class="input-soft">
                                    <p class="text-[10px] text-slate-400 font-bold uppercase">Obtenez une clé gratuite sur <a href="https://console.mistral.ai/" target="_blank" class="text-blue-500 underline">console.mistral.ai</a></p>
                                </div>
                            </div>

                            <div class="pt-8 border-t border-slate-100">
                                <label class="text-[10px] font-black text-slate-400 uppercase block mb-3 tracking-widest italic">Maintenance & Logs</label>
                                <div class="flex items-center justify-between bg-slate-50 p-6 rounded-2xl border border-slate-100">
                                    <div class="flex items-center gap-4">
                                        <input type="checkbox" name="debugMode" id="debugMode" class="w-6 h-6 accent-blue-600" <?= ($globals['debugMode']??false) ? 'checked' : '' ?>>
                                        <div>
                                            <label for="debugMode" class="text-xs font-black uppercase text-slate-700 cursor-pointer block">Mode Débug</label>
                                            <p class="text-[10px] text-slate-400 font-bold">Enregistre les échanges API pour le support</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <?php
                                        $logFileHA = __DIR__ . '/../logs/debug_helloasso.log';
                                        if(file_exists($logFileHA)):
                                        ?>
                                            <div class="flex items-center gap-2 justify-end">
                                                <span class="text-[9px] font-black text-slate-400 uppercase">HelloAsso :</span>
                                                <a href="admin.php?action=dl_log&type=helloasso" class="bg-white border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase hover:bg-slate-50 transition">Télécharger</a>
                                                <a href="admin.php?action=clear_log&type=helloasso" class="bg-red-50 text-red-500 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase hover:bg-red-500 hover:text-white transition">Effacer</a>
                                            </div>
                                        <?php endif; ?>

                                        <?php
                                        $logFileAI = __DIR__ . '/../logs/debug_ai.log';
                                        if(file_exists($logFileAI)):
                                        ?>
                                            <div class="flex items-center gap-2 justify-end">
                                                <span class="text-[9px] font-black text-slate-400 uppercase">Mistral AI :</span>
                                                <a href="admin.php?action=dl_log&type=ai" class="bg-white border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase hover:bg-slate-50 transition">Télécharger</a>
                                                <a href="admin.php?action=clear_log&type=ai" class="bg-red-50 text-red-500 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase hover:bg-red-500 hover:text-white transition">Effacer</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-12">
                            <button type="submit" name="save_settings" class="w-full bg-slate-900 text-white py-5 rounded-[2rem] font-black uppercase text-xs tracking-widest shadow-xl hover:bg-blue-600 transition">
                                Mettre à jour la configuration
                            </button>
                        </div>
                    </form>
                </div>
                <div class="text-center">
                    <a href="admin.php" class="text-xs font-black text-slate-300 uppercase hover:text-slate-500 transition">Annuler les modifications</a>
                </div>
            </div>

        <?php elseif ($action === 'scan'): ?>
            <!-- RESULTATS DU SCAN -->
            <div class="animate-fade-in">
                <div class="mb-10 flex justify-between items-end">
                    <div>
                        <h2 class="text-3xl font-black italic uppercase text-slate-900">Campagnes Trouvées</h2>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Sélectionnez un formulaire à configurer</p>
                    </div>
                    <a href="admin.php" class="text-xs font-black text-slate-400 uppercase hover:text-slate-900 transition">Retour</a>
                </div>

                <div class="grid gap-4">
                    <?php if(!isset($scanResults) || empty($scanResults['forms'])): ?>
                        <div class="p-10 bg-orange-50 text-orange-600 rounded-[2.5rem] text-center border-2 border-dashed border-orange-200">
                            <i class="fa-solid fa-exclamation-triangle text-3xl mb-4"></i>
                            <p class="font-black uppercase text-sm italic">Aucun formulaire n'a été détecté sur ce compte.</p>
                            <p class="text-xs mt-2 opacity-70">Vérifiez vos identifiants API et le slug de l'organisation.</p>
                        </div>
                    <?php else:
                        $existingSlugs = array_column($localCampaigns, 'slug');
                        foreach($scanResults['forms'] as $form):
                            $isConfigured = in_array($form['slug'], $existingSlugs);
                    ?>
                        <div class="admin-card p-6 flex justify-between items-center animate-fade-in <?= $isConfigured ? 'bg-emerald-50/30 border-emerald-100' : '' ?>">
                            <div>
                                <div class="flex items-center gap-3">
                                    <h4 class="font-black text-lg text-slate-800"><?= htmlspecialchars($form['name']) ?></h4>
                                    <?php if($isConfigured): ?>
                                        <span class="text-[9px] font-black bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded uppercase flex items-center gap-1"><i class="fa-solid fa-check"></i> Configuré</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-[10px] font-black bg-slate-100 text-slate-500 px-2 py-1 rounded uppercase mt-2 inline-block"><?= $form['type'] ?></span>
                            </div>
                            <a href="admin.php?action=edit&campaign=<?= $form['slug'] ?>&org=<?= $scanResults['orgSlug'] ?>&type=<?= $form['type'] ?>&name=<?= urlencode($form['name']) ?>"
                               class="<?= $isConfigured ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-600 text-white shadow-lg shadow-blue-100' ?> px-8 py-4 rounded-2xl text-xs font-black uppercase transition transform active:scale-95">
                                <?= $isConfigured ? 'Modifier' : 'Configurer' ?>
                            </a>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        <?php elseif ($action === 'edit'): ?>
            <!-- EDITION D'UN BOARD -->
            <div id="config-zone" class="animate-fade-in">
                <div class="py-20 text-center animate-pulse text-slate-400 font-black uppercase tracking-widest italic">Analyse des données en cours...</div>
            </div>

            <script>
            window.onload = function() {
                const urlParams = new URLSearchParams(window.location.search);
                const campaign = urlParams.get('campaign');
                const org = urlParams.get('org') || '<?= $globals['orgSlug'] ?? '' ?>';
                const type = urlParams.get('type') || '';
                const name = urlParams.get('name') || '';

                // Si on a les infos nécessaires, on lance l'analyse
                if (campaign) {
                    editCamp(org, campaign, type, name);
                }
            };
            </script>
        <?php endif; ?>

    </main>

    <script>
    function showLoader() {
        const loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.innerHTML = `
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] flex items-center justify-center">
                <div class="bg-white p-8 rounded-[2rem] text-center shadow-2xl animate-fade-in">
                    <div class="w-16 h-16 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                    <p class="font-black uppercase text-xs tracking-widest text-slate-900">Génération en cours...</p>
                    <p class="text-[10px] text-slate-400 font-bold uppercase mt-2">Ceci peut prendre quelques secondes</p>
                </div>
            </div>
        `;
        document.body.appendChild(loader);
    }

    function btnDownloadLoading(btn) {
        if (btn.dataset.loading === 'true') return false;
        btn.dataset.loading = 'true';
        const icon = btn.querySelector('i');
        if (icon) {
            const originalClass = icon.className;
            icon.className = 'fa-solid fa-circle-notch fa-spin';
            setTimeout(() => {
                icon.className = originalClass;
                delete btn.dataset.loading;
            }, 5000);
        }
        return true;
    }

    const labelsMap = {
        'Event': { main: '🎫 Billet', quota: 'Quota Billets' },
        'Shop': { main: '📦 Produit', quota: 'Quota Articles' },
        'Membership': { main: '🆔 Adhésion', quota: 'Quota Adhésions' },
        'Donation': { main: '❤️ Donateur', quota: 'Objectif Dons' },
        'Crowdfunding': { main: '🚀 Contributeur', quota: 'Objectif Contrib.' },
        'PaymentForm': { main: '💳 Article', quota: 'Quota Articles' },
        'Checkout': { main: '📦 Produit', quota: 'Quota Articles' },
        'Product': { main: '📦 Produit', quota: 'Quota Articles' }
    };

    function confirmDelete(slug, title) {
        if(confirm(`Voulez-vous vraiment supprimer définitivement le board "${title}" ?\nCette action est irréversible.`)) {
            window.location.href = `admin.php?action=delete&campaign=${slug}`;
        }
    }

    async function editCamp(org, slug, forceType = '', forceName = '') {
        const zone = document.getElementById('config-zone');
        if (!zone) return;

        try {
            const res = await fetch(`admin.php?action=analyze&org=${org}&form=${slug}${forceType ? '&type='+forceType : ''}`);
            const data = await res.json();

            // On retrouve le type et le nom soit depuis l'URL, soit depuis la config existante si dispo
            const currentType = forceType || data.formType || 'Event';
            const currentName = forceName || data.title || slug;
            const labels = labelsMap[currentType] || labelsMap['Event'];
            const isShop = (['Shop', 'Checkout', 'PaymentForm', 'Product', 'product'].includes(currentType));

            const goals = data.goals || { revenue: 0, tickets: 0, n1: 0 };
            const rules = data.rules || [];
            const guestlist = data.guestlist || { columns: ['nom', 'prenom', 'formule', 'options'], showCheckboxes: true, groupByOrder: false };
            const token = data.shareToken || '';

            // Fusionner les items trouvés dans l'API avec les règles existantes
            (data.apiItems || []).forEach(item => {
                if(!rules.find(r => r.pattern === item.pattern)) {
                    rules.push({
                        pattern: item.pattern,
                        displayLabel: item.pattern,
                        type: item.isMain ? 'Billet' : 'Option',
                        group: 'Divers',
                        chartType: 'pie',
                        transform: '',
                        hidden: false,
                        costPrice: 0,
                        sellingPrice: 0,
                        stock: 0
                    });
                }
            });

            zone.innerHTML = `
                <div class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div>
                        <a href="admin.php" class="text-[10px] font-black text-slate-400 uppercase hover:text-slate-900 transition flex items-center gap-2 mb-2">
                            <i class="fa-solid fa-arrow-left"></i> Retour aux boards
                        </a>
                        <h2 class="text-3xl font-black italic uppercase text-slate-900">${currentName}</h2>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Configuration du board et des indicateurs</p>
                    </div>
                    <div class="flex gap-3 w-full md:w-auto">
                        <button onclick="location.reload()" class="flex-1 md:flex-none text-slate-400 hover:text-slate-600 font-black uppercase text-xs tracking-widest transition px-6">Annuler</button>
                        <button id="save-main-btn" class="flex-1 md:flex-none bg-blue-600 text-white px-10 py-5 rounded-[1.5rem] font-black uppercase text-xs shadow-xl shadow-blue-100 transition transform active:scale-95">
                            Sauvegarder
                        </button>
                    </div>
                </div>

                <div class="admin-card p-8 mb-8 flex flex-col md:flex-row justify-between items-center gap-6 border-2 border-blue-50 bg-blue-50/20">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-600 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-blue-200">
                            <i class="fa-solid fa-share-nodes text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xs font-black uppercase text-slate-400 italic tracking-widest">Lien de partage public</h3>
                            <p class="text-sm font-bold text-slate-700 mt-1">Partagez ce board sans donner d'accès administrateur</p>
                        </div>
                    </div>
                    <div class="flex-1 w-full md:w-auto">
                        <div class="flex gap-2">
                            <input type="text" readonly id="share-url" class="input-soft !bg-white !py-4 !text-xs text-blue-600 font-mono" value="${window.location.origin + window.location.pathname.replace('admin.php', 'index.php') + '?campaign=' + slug + '&token=' + token}">
                            <button onclick="copyShareLink(this)" class="bg-slate-900 text-white px-6 py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-blue-600 transition shadow-lg">
                                Copier
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
                    <div class="admin-card p-8">
                        <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic tracking-widest border-b border-slate-50 pb-4">Objectifs & Quotas</h3>
                        <div class="grid gap-6">
                            <div>
                                <label class="text-[10px] font-black text-slate-500 uppercase block mb-2 tracking-tighter">Objectif de Recettes (€)</label>
                                <input type="number" id="goal-rev" class="input-soft" value="${goals.revenue}">
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-500 uppercase block mb-2 tracking-tighter">${labels.quota.toUpperCase()} (QTÉ)</label>
                                <input type="number" id="goal-tix" class="input-soft" value="${goals.tickets}">
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-500 uppercase block mb-2 tracking-tighter">Référence année précédente (N-1)</label>
                                <input type="number" id="goal-n1" class="input-soft" value="${goals.n1}">
                            </div>
                        </div>
                    </div>

                    <div class="admin-card p-8">
                        <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic tracking-widest border-b border-slate-50 pb-4">Marqueurs Temporels</h3>
                        <div id="markers-list" class="space-y-3 mb-6">
                            ${(data.markers || []).map(m => `
                                <div class="flex gap-3 marker-row animate-fade-in">
                                    <input type="text" placeholder="Événement (ex: Envoi Email)" class="marker-label input-soft !py-3 !text-xs" value="${m.label}">
                                    <input type="date" class="marker-date input-soft !py-3 !text-xs w-44" value="${m.date}">
                                    <button onclick="this.parentElement.remove()" class="text-slate-300 hover:text-red-500 transition px-2"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            `).join('')}
                        </div>
                        <button onclick="addMarkerRow()" class="w-full py-4 border-2 border-dashed border-slate-100 rounded-2xl text-[10px] font-black text-blue-600 uppercase hover:bg-blue-50 transition">
                            <i class="fa-solid fa-plus mr-2"></i> Ajouter un marqueur
                        </button>
                    </div>
                </div>

                <div class="admin-card p-8 mb-12">
                    <h3 class="text-xs font-black uppercase text-slate-400 mb-6 italic tracking-widest border-b border-slate-50 pb-4">Configuration Liste Inscrits / Émargement</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="text-[10px] font-black text-slate-500 uppercase block mb-4 tracking-tighter">Colonnes à afficher</label>
                            <div class="grid grid-cols-2 gap-3">
                                ${['date', 'nom', 'prenom', 'formule', 'options', 'email', 'phone'].map(col => `
                                    <label class="flex items-center gap-3 bg-slate-50 p-3 rounded-xl border border-slate-100 cursor-pointer hover:bg-white transition">
                                        <input type="checkbox" class="guestlist-col w-5 h-5 accent-blue-600" value="${col}" ${guestlist.columns.includes(col) ? 'checked' : ''}>
                                        <span class="text-[10px] font-black uppercase text-slate-600">${col}</span>
                                    </label>
                                `).join('')}
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="flex flex-col justify-center gap-4 bg-slate-50 p-6 rounded-[2rem] border border-slate-100">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-black uppercase text-slate-600">Afficher cases à cocher (Check-in)</span>
                                    <div class="toggle-btn guestlist-checkboxes ${guestlist.showCheckboxes ? 'active' : ''}" onclick="this.classList.toggle('active')"></div>
                                </div>
                                <p class="text-[9px] text-slate-400 font-bold uppercase leading-relaxed">Active le mode check-in interactif avec sauvegarde locale et barré des noms.</p>
                            </div>
                            <div class="flex flex-col justify-center gap-4 bg-slate-50 p-6 rounded-[2rem] border border-slate-100 ${currentType === 'Event' ? 'hidden' : ''}">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-black uppercase text-slate-600">Grouper par commande</span>
                                    <div class="toggle-btn guestlist-groupby ${guestlist.groupByOrder ? 'active' : ''}" onclick="this.classList.toggle('active')"></div>
                                </div>
                                <p class="text-[9px] text-slate-400 font-bold uppercase leading-relaxed">Affiche une seule ligne par acheteur avec tous ses articles (Recommandé pour les boutiques).</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-card overflow-hidden">
                    <div class="p-8 bg-slate-50/50 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-black uppercase text-slate-400 italic tracking-widest">Importation & Mapping</h3>
                            <p class="text-[10px] text-slate-300 font-bold uppercase mt-1">Organisez et renommez les articles de HelloAsso</p>
                        </div>
                        <span class="text-[10px] font-black text-slate-300 bg-white px-3 py-1 rounded-full shadow-sm"><i class="fa-solid fa-info-circle mr-1"></i> Glissez pour réorganiser</span>
                    </div>

                    <div class="p-4 lg:p-8">
                        <div class="hidden lg:flex items-center gap-4 px-6 mb-4 text-[9px] font-black uppercase text-slate-300 italic">
                            <div class="w-12 text-center">ORDRE / ACTIF</div>
                            <div class="flex-1 ml-4">ARTICLE SOURCE (HELLOASSO)</div>
                            <div class="w-48">NOM AFFICHÉ SUR LE BOARD</div>
                            <div class="w-32">BLOC / GROUPE</div>
                            <div class="w-24">TYPE</div>
                            <div class="w-24">CHART</div>
                            <div class="w-32">REGEX</div>
                    ${isShop ? '<div class="w-48 text-center">FINANCES / STOCK</div>' : ''}
                        </div>

                        <div id="rules-list" class="space-y-3">
                            ${rules.map(r => `
                                <div class="rule-tile bg-white border border-slate-100 rounded-2xl p-4 md:p-5 flex flex-col lg:flex-row items-center gap-4 group hover:border-blue-300 hover:shadow-lg hover:shadow-blue-50 transition-all" data-item="${r.pattern}">
                                    <div class="flex items-center justify-between lg:justify-start gap-4 w-full lg:w-auto">
                                        <div class="cursor-grab text-slate-200 group-hover:text-blue-400 transition-colors p-2"><i class="fa-solid fa-grip-vertical text-lg"></i></div>
                                        <div class="toggle-btn ${r.hidden ? '' : 'active'}" onclick="this.classList.toggle('active')" title="Activer/Désactiver l'importation"></div>
                                    </div>

                                    <div class="flex-1 w-full min-w-0">
                                        <div class="text-[9px] font-black text-slate-300 uppercase mb-1 truncate italic flex items-center gap-2">
                                            <i class="fa-solid fa-plug text-[8px]"></i> Source : ${r.pattern}
                                        </div>
                                        <input type="text" class="display-label input-soft !py-2 !text-sm border-transparent focus:border-blue-500 !bg-slate-50/50" value="${r.displayLabel}">
                                    </div>

                                    <div class="flex flex-wrap lg:flex-nowrap lg:items-center gap-2 md:gap-4 w-full lg:w-auto">
                                        <div class="w-full lg:w-32">
                                            <input type="text" class="rule-group input-soft !py-2 !px-3 !text-[10px] uppercase text-slate-600" value="${r.group || 'Divers'}" placeholder="BLOC">
                                        </div>
                                        <div class="w-[48%] lg:w-24">
                                            <select class="rule-type input-soft !py-2 !px-2 !text-[10px] uppercase font-black">
                                                <option value="Billet" ${r.type==='Billet'?'selected':''}>${labels.main}</option>
                                                <option value="Option" ${r.type==='Option'?'selected':''}>Option</option>
                                                <option value="Ignorer" ${r.type==='Ignorer'?'selected':''}>Masquer</option>
                                            </select>
                                        </div>
                                        <div class="w-[48%] lg:w-24">
                                            <select class="rule-chart input-soft !py-2 !px-2 !text-[10px] uppercase font-black">
                                                <option value="pie" ${r.chartType==='pie'?'selected':''}>Pie</option>
                                                <option value="bar" ${r.chartType==='bar'?'selected':''}>Bar</option>
                                                <option value="doughnut" ${r.chartType==='doughnut'?'selected':''}>Donut</option>
                                            </select>
                                        </div>
                                        <div class="w-full lg:w-32">
                                            <input type="text" class="rule-transform input-soft !py-2 !px-3 !text-[10px] uppercase text-slate-600" value="${r.transform || ''}" placeholder="REGEX:...">
                                        </div>

                                        ${isShop ? `
                                            <div class="flex gap-1 w-full lg:w-48 bg-slate-50 p-1 rounded-xl">
                                                <div class="flex-1 relative">
                                                    <span class="absolute -top-2 left-1/2 -translate-x-1/2 text-[6px] font-black text-slate-400">ACHAT</span>
                                                    <input type="number" step="0.01" class="rule-cost-price input-soft !p-1 !pt-3 !text-[10px] text-center !bg-transparent" value="${r.costPrice || 0}">
                                                </div>
                                                <div class="flex-1 relative">
                                                    <span class="absolute -top-2 left-1/2 -translate-x-1/2 text-[6px] font-black text-slate-400">VENTE</span>
                                                    <input type="number" step="0.01" class="rule-selling-price input-soft !p-1 !pt-3 !text-[10px] text-center !bg-transparent" value="${r.sellingPrice || 0}">
                                                </div>
                                                <div class="flex-1 relative">
                                                    <span class="absolute -top-2 left-1/2 -translate-x-1/2 text-[6px] font-black text-slate-400">STOCK</span>
                                                    <input type="number" class="rule-stock input-soft !p-1 !pt-3 !text-[10px] text-center !bg-transparent" value="${r.stock || 0}">
                                                </div>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('save-main-btn').onclick = () => save(org, slug, currentType, currentName, token);
            new Sortable(document.getElementById('rules-list'), {
                animation: 150,
                handle: '.cursor-grab',
                ghostClass: 'bg-blue-50',
                dragClass: 'shadow-2xl'
            });

        } catch (e) {
            console.error(e);
            zone.innerHTML = `<div class="p-10 bg-red-50 text-red-500 rounded-3xl text-center font-black uppercase text-sm">Erreur lors de l'analyse du formulaire.</div>`;
        }
    }

    function addMarkerRow() {
        const div = document.createElement('div');
        div.className = 'flex gap-3 marker-row mb-2 animate-fade-in';
        div.innerHTML = `
            <input type="text" placeholder="Action" class="marker-label input-soft !py-3 !text-xs">
            <input type="date" class="marker-date input-soft !py-3 !text-xs w-44">
            <button onclick="this.parentElement.remove()" class="text-slate-300 hover:text-red-500 transition px-2"><i class="fa-solid fa-trash-can"></i></button>
        `;
        document.getElementById('markers-list').appendChild(div);
    }

    async function save(org, slug, type, name, token) {
        const btn = document.getElementById('save-main-btn');
        const oldText = btn.innerText;
        btn.innerText = "Patientez...";
        btn.disabled = true;

        const rules = [];
        document.querySelectorAll('.rule-tile').forEach(row => {
            rules.push({
                pattern: row.dataset.item,
                displayLabel: row.querySelector('.display-label').value,
                type: row.querySelector('.rule-type').value,
                group: row.querySelector('.rule-group').value || 'Divers',
                chartType: row.querySelector('.rule-chart').value,
                transform: row.querySelector('.rule-transform') ? row.querySelector('.rule-transform').value : '',
                hidden: !row.querySelector('.toggle-btn').classList.contains('active'),
                costPrice: row.querySelector('.rule-cost-price') ? parseFloat(row.querySelector('.rule-cost-price').value) : 0,
                sellingPrice: row.querySelector('.rule-selling-price') ? parseFloat(row.querySelector('.rule-selling-price').value) : 0,
                stock: row.querySelector('.rule-stock') ? parseInt(row.querySelector('.rule-stock').value) : 0
            });
        });

        const markers = [];
        document.querySelectorAll('.marker-row').forEach(row => {
            const l = row.querySelector('.marker-label').value;
            const d = row.querySelector('.marker-date').value;
            if(l && d) markers.push({label: l, date: d});
        });

        const guestlistColumns = [];
        document.querySelectorAll('.guestlist-col:checked').forEach(cb => guestlistColumns.push(cb.value));

        const config = {
            slug,
            title: name,
            orgSlug: org,
            formSlug: slug,
            formType: type,
            shareToken: token,
            rules,
            markers,
            goals: {
                revenue: parseFloat(document.getElementById('goal-rev').value),
                tickets: parseInt(document.getElementById('goal-tix').value),
                n1: parseInt(document.getElementById('goal-n1').value)
            },
            guestlist: {
                columns: guestlistColumns,
                showCheckboxes: document.querySelector('.guestlist-checkboxes').classList.contains('active'),
                groupByOrder: type === 'Event' ? false : document.querySelector('.guestlist-groupby').classList.contains('active')
            }
        };

        try {
            await fetch('admin.php', {
                method: 'POST',
                body: new URLSearchParams({
                    save_campaign: '1',
                    config: JSON.stringify(config)
                })
            });
            window.location.href = 'index.php?campaign=' + slug;
        } catch (e) {
            alert("Erreur lors de la sauvegarde.");
            btn.innerText = oldText;
            btn.disabled = false;
        }
    }

    function copyShareLink(btn) {
        const input = document.getElementById('share-url');
        input.select();
        document.execCommand('copy');
        const oldText = btn.innerText;
        btn.innerText = "Copié !";
        btn.classList.replace('bg-slate-900', 'bg-emerald-500');
        setTimeout(() => {
            btn.innerText = oldText;
            btn.classList.replace('bg-emerald-500', 'bg-slate-900');
        }, 2000);
    }

    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text);
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i>';
        btn.classList.replace('text-slate-400', 'text-emerald-500');
        setTimeout(() => {
            btn.innerHTML = oldHtml;
            btn.classList.replace('text-emerald-500', 'text-slate-400');
        }, 2000);
    }
    </script>
</body>
</html>
