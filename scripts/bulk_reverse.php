<?php
/**
 * Bulk reversal of failed withdrawals
 *
 * Run from CLI: php scripts/bulk_reverse.php [options]
 *
 * Options:
 *   --dry-run          Preview only, no API calls or DB changes
 *   --network=stellar  Restrict to one network (stellar|binance|ethereum)
 *   --limit=50         Max withdrawals to process (default 20)
 *   --delay=2          Seconds between each reversal (default 2)
 *   --batch-pause=30   Seconds to pause between batches (default 30)
 *   --batch-size=10    Refunds per batch before pause (default 10)
 *   --no-usdd-fee      Only credit DBV; do not refund USDD withdrawal fees
 *
 * Examples:
 *   php scripts/bulk_reverse.php --dry-run
 *   php scripts/bulk_reverse.php --network=stellar --limit=50
 *   php scripts/bulk_reverse.php --limit=100 --delay=2 --batch-size=20
 */

$scriptDir = dirname(__DIR__);
require_once $scriptDir . '/app/Support/Database.php';
require_once $scriptDir . '/app/Support/ReverseService.php';
require_once $scriptDir . '/app/Support/Logger.php';
require_once $scriptDir . '/app/Support/AuditService.php';

$config = require $scriptDir . '/app/Config/config.php';

// Parse options
$opts = getopt('', ['dry-run', 'network:', 'limit:', 'delay:', 'batch-pause:', 'batch-size:', 'no-usdd-fee']);
$dryRun = isset($opts['dry-run']);
$refundUsddFee = !isset($opts['no-usdd-fee']);
$networkFilter = $opts['network'] ?? null;
$limit = (int)($opts['limit'] ?? 20);
$delay = (int)($opts['delay'] ?? 2);
$batchPause = (int)($opts['batch-pause'] ?? 30);
$batchSize = (int)($opts['batch-size'] ?? 10);

// Admin UID for audit (CLI runs as system)
$adminUid = 1290033;

echo "=== Bulk Reversal Script ===\n";
echo "Mode: " . ($dryRun ? "DRY-RUN (no changes)" : "LIVE") . "\n";
echo "USDD fee refund: " . ($refundUsddFee ? "yes" : "no (DBV only)") . "\n";
echo "Limit: $limit | Delay: {$delay}s | Batch: $batchSize | Pause: {$batchPause}s\n";
if ($networkFilter) echo "Network: $networkFilter only\n";
echo str_repeat('-', 50) . "\n";

$pdo = Database::pdo($config['db']);
$reverseService = new ReverseService($pdo, $config);
$audit = new AuditService($pdo);

$networks = $networkFilter ? [$networkFilter] : ['stellar', 'binance', 'ethereum'];
if ($networkFilter && !in_array($networkFilter, $networks)) {
    die("Invalid network. Use: stellar, binance, or ethereum\n");
}

// Collect failed withdrawals not already reversed
$toReverse = [];
foreach ($networks as $net) {
    $table = $net . '_withdraw';
    $stmt = $pdo->prepare("
        SELECT w.id, w.uid, w.amount, w.fee_usdd, w.address, w.created_at
        FROM {$table} w
        WHERE w.status = 2
        AND NOT EXISTS (SELECT 1 FROM reversals r WHERE r.network = ? AND r.withdrawal_id = w.id)
        ORDER BY w.created_at ASC
        LIMIT ?
    ");
    $stmt->execute([$net, $limit]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['network'] = $net;
        $toReverse[] = $row;
    }
}

$toReverse = array_slice($toReverse, 0, $limit);

if (empty($toReverse)) {
    echo "No failed withdrawals to reverse.\n";
    exit(0);
}

echo "Found " . count($toReverse) . " withdrawal(s) to reverse:\n";
$totalDbv = 0;
$totalUsdd = 0;
foreach ($toReverse as $i => $w) {
    $fee = (float)($w['fee_usdd'] ?? 0);
    $fee = $fee > 25 ? 0 : $fee;
    $totalDbv += (float)$w['amount'];
    $totalUsdd += $fee;
    echo sprintf("  %2d. %s #%d | UID %d | %.2f DBV | %.2f USDD\n",
        $i + 1, $w['network'], $w['id'], $w['uid'], $w['amount'], $fee);
}
echo "Totals: " . number_format($totalDbv, 2) . " DBV, " . number_format($totalUsdd, 2) . " USDD\n";
echo str_repeat('-', 50) . "\n";

if ($dryRun) {
    echo "Dry-run complete. Run without --dry-run to execute.\n";
    exit(0);
}

echo "Starting in 5 seconds... (Ctrl+C to cancel)\n";
sleep(5);

// Process
$done = 0;
$failed = 0;
$batchCount = 0;

foreach ($toReverse as $i => $w) {
    $batchCount++;
    echo "[" . ($i + 1) . "/" . count($toReverse) . "] Reversing {$w['network']} #{$w['id']} (UID {$w['uid']})... ";

    $result = $reverseService->reverseFailedWithdrawal($w['network'], (int)$w['id'], $refundUsddFee);

    if ($result['success']) {
        $done++;
        echo "OK\n";
        $audit->log($adminUid, 'reversal', 'withdrawal', $w['id'], [
            'network' => $w['network'],
            'refund_usdd_fee' => $refundUsddFee,
            'dbv_amount' => $result['dbv_amount'] ?? 0,
            'usdd_amount' => $result['usdd_amount'] ?? 0,
        ]);
    } else {
        $failed++;
        echo "FAILED: " . ($result['message'] ?? 'Unknown') . "\n";
        $audit->log($adminUid, 'reversal_failed', 'withdrawal', $w['id'], [
            'network' => $w['network'],
            'message' => $result['message'] ?? 'Unknown',
        ]);
    }

    // Delay between reversals
    if ($i < count($toReverse) - 1 && $delay > 0) {
        sleep($delay);
    }

    // Pause between batches
    if ($batchCount >= $batchSize && $i < count($toReverse) - 1 && $batchPause > 0) {
        echo "  --- Pausing {$batchPause}s before next batch ---\n";
        sleep($batchPause);
        $batchCount = 0;
    }
}

echo str_repeat('-', 50) . "\n";
echo "Done. Success: $done, Failed: $failed\n";
