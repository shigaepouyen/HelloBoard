<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$rootDir = dirname(__DIR__);
$defaultDbPath = $rootDir . '/config/satisfaction.db';
$defaultSince = '2026-03-26 17:30:19';
$defaultMaxReadDelay = 5;

$options = getopt('', [
    'db::',
    'since::',
    'campaign::',
    'max-read-delay::',
    'apply',
    'help',
]);

if (isset($options['help'])) {
    echo <<<TXT
Usage:
  php scripts/repair_public_satisfaction_tokens.php [options]

Options:
  --db=PATH                SQLite file path. Default: config/satisfaction.db
  --since="YYYY-MM-DD HH:MM:SS"
                           Ignore tokens created before this timestamp.
                           Default: {$defaultSince}
  --campaign=SLUG          Restrict the repair to one campaign slug.
  --max-read-delay=SEC     Max delay between sent_at and read_at to consider
                           a token as polluted by the public access flow.
                           Default: {$defaultMaxReadDelay}
  --apply                  Apply the repair. Without this flag, the script only
                           prints a dry-run report.
  --help                   Show this message.

Repair rule:
  - sent_at is not null
  - read_at is not null
  - sent_at >= --since
  - read_at happened within --max-read-delay seconds after sent_at
  - no successful send attempt is attached when survey_attempts exists

Applied change:
  sent_at = NULL, read_at = NULL, status = 'pending'

TXT;
    exit(0);
}

$dbPath = $options['db'] ?? $defaultDbPath;
$since = $options['since'] ?? $defaultSince;
$campaign = $options['campaign'] ?? null;
$maxReadDelay = isset($options['max-read-delay']) ? (int) $options['max-read-delay'] : $defaultMaxReadDelay;
$apply = isset($options['apply']);

if ($maxReadDelay < 0) {
    fwrite(STDERR, "--max-read-delay must be >= 0.\n");
    exit(1);
}

if (!is_file($dbPath)) {
    fwrite(STDERR, "Database not found: {$dbPath}\n");
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    fwrite(STDERR, "Connection error: {$e->getMessage()}\n");
    exit(1);
}

$attemptsTableExists = (bool) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'survey_attempts'")->fetchColumn();

$sql = <<<SQL
SELECT
    t.token,
    t.campaign_slug,
    t.order_id,
    t.email,
    t.sent_at,
    t.read_at,
    t.status,
    CASE
        WHEN t.read_at IS NOT NULL
        THEN CAST(strftime('%s', t.read_at) AS INTEGER) - CAST(strftime('%s', t.sent_at) AS INTEGER)
        ELSE NULL
    END AS read_delay_seconds,
    EXISTS(SELECT 1 FROM survey_responses r WHERE r.token = t.token) AS has_response
FROM survey_tokens t
WHERE t.sent_at IS NOT NULL
  AND t.read_at IS NOT NULL
  AND t.sent_at >= :since
  AND ABS(CAST(strftime('%s', t.read_at) AS INTEGER) - CAST(strftime('%s', t.sent_at) AS INTEGER)) <= :maxReadDelay
SQL;

if ($campaign !== null && $campaign !== '') {
    $sql .= "\n  AND t.campaign_slug = :campaign";
}

if ($attemptsTableExists) {
    $sql .= "\n  AND NOT EXISTS (
        SELECT 1
        FROM survey_attempts a
        WHERE a.token = t.token
          AND a.status = 'success'
    )";
}

$sql .= "\nORDER BY t.sent_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':since', $since);
$stmt->bindValue(':maxReadDelay', $maxReadDelay, PDO::PARAM_INT);
if ($campaign !== null && $campaign !== '') {
    $stmt->bindValue(':campaign', $campaign);
}
$stmt->execute();
$candidates = $stmt->fetchAll();

echo "Database: {$dbPath}\n";
echo "Mode: " . ($apply ? 'apply' : 'dry-run') . "\n";
echo "Cutoff: {$since}\n";
echo "Max read delay: {$maxReadDelay}s\n";
echo "Campaign filter: " . (($campaign !== null && $campaign !== '') ? $campaign : 'none') . "\n";
echo "survey_attempts table: " . ($attemptsTableExists ? 'yes' : 'no') . "\n";
echo "Candidates: " . count($candidates) . "\n\n";

if (!$candidates) {
    echo "No polluted tokens matched the repair rule.\n";
    exit(0);
}

$responseCount = 0;
foreach ($candidates as $candidate) {
    if (!empty($candidate['has_response'])) {
        $responseCount++;
    }
}

echo "Candidates with responses: {$responseCount}\n\n";
echo str_pad('sent_at', 20)
    . str_pad('read_at', 20)
    . str_pad('delay', 8)
    . str_pad('campaign', 28)
    . str_pad('email', 36)
    . "response\n";
echo str_repeat('-', 119) . "\n";

foreach ($candidates as $candidate) {
    echo str_pad((string) $candidate['sent_at'], 20)
        . str_pad((string) $candidate['read_at'], 20)
        . str_pad((string) $candidate['read_delay_seconds'], 8)
        . str_pad((string) $candidate['campaign_slug'], 28)
        . str_pad((string) $candidate['email'], 36)
        . (!empty($candidate['has_response']) ? 'yes' : 'no')
        . "\n";
}

if (!$apply) {
    echo "\nDry-run only. Re-run with --apply to update sent_at/read_at/status.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $update = $pdo->prepare("UPDATE survey_tokens SET sent_at = NULL, read_at = NULL, status = 'pending' WHERE token = ?");
    foreach ($candidates as $candidate) {
        $update->execute([$candidate['token']]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Repair failed: {$e->getMessage()}\n");
    exit(1);
}

echo "\nApplied repair to " . count($candidates) . " token(s).\n";
