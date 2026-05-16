<?php
/**
 * Admin endpoint to download reports as CSV
 * GET: ?type=transactions|failed|fees|audit|reversals&format=csv
 */

session_start();

require_once __DIR__ . '/../../../app/Support/Database.php';
require_once __DIR__ . '/../../../app/Support/AdminHelper.php';
$config = require __DIR__ . '/../../../app/Config/config.php';
$pdo = Database::pdo($config['db']);
if (!isset($_SESSION['uid']) || !AdminHelper::isAdmin((int)$_SESSION['uid'], $pdo)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? '';
$format = strtolower($_GET['format'] ?? 'csv');

$validTypes = ['transactions', 'failed', 'fees', 'audit', 'reversals', 'commissions'];
if (!in_array($type, $validTypes) || $format !== 'csv') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid type or format. Use type=transactions|failed|fees|audit|reversals|commissions&format=csv']);
    exit;
}

require_once __DIR__ . '/../../../app/Support/AuditService.php';
$limit = 10000; // Max rows per report

$filename = $type . '_report_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$output = fopen('php://output', 'w');
// BOM for Excel UTF-8
fprintf($output, "\xEF\xBB\xBF");

try {
    switch ($type) {
        case 'transactions':
            $sql = "
                SELECT 'stellar' as network, 'deposit' as type, id, uid, amount, 0 as fee_usdd, status, NULL as address, txn_hash_stellar as txn_hash_network, txn_hash_yemchain, created_at FROM stellar_deposit
                UNION ALL
                SELECT 'stellar' as network, 'withdrawal' as type, id, uid, amount, COALESCE(fee_usdd,0) as fee_usdd, status, address, txn_hash_stellar, txn_hash_yemchain, created_at FROM stellar_withdraw
                UNION ALL
                SELECT 'binance' as network, 'deposit' as type, id, uid, amount, 0 as fee_usdd, status, NULL as address, txn_hash_bsc, txn_hash_yemchain, created_at FROM binance_deposit
                UNION ALL
                SELECT 'binance' as network, 'withdrawal' as type, id, uid, amount, COALESCE(fee_usdd,0) as fee_usdd, status, address, txn_hash_bsc, txn_hash_yemchain, created_at FROM binance_withdraw
                UNION ALL
                SELECT 'ethereum' as network, 'deposit' as type, id, uid, amount, 0 as fee_usdd, status, NULL as address, txn_hash_eth, txn_hash_yemchain, created_at FROM ethereum_deposit
                UNION ALL
                SELECT 'ethereum' as network, 'withdrawal' as type, id, uid, amount, COALESCE(fee_usdd,0) as fee_usdd, status, address, txn_hash_eth, txn_hash_yemchain, created_at FROM ethereum_withdraw
                ORDER BY created_at DESC LIMIT ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            fputcsv($output, ['Network', 'Type', 'ID', 'UID', 'Amount', 'Fee USDD', 'Status', 'Address', 'Txn Hash (Network)', 'Txn Hash (DigitalChain)', 'Created At']);
            foreach ($rows as $r) {
                fputcsv($output, [$r['network'], $r['type'], $r['id'], $r['uid'], $r['amount'], $r['fee_usdd'], $r['status'], $r['address'], $r['txn_hash_network'], $r['txn_hash_yemchain'], $r['created_at']]);
            }
            break;

        case 'failed':
            $sql = "
                SELECT 'stellar' as network, id, uid, address, amount, COALESCE(fee_usdd,0) as fee_usdd, txn_hash_stellar as txn_hash_network, txn_hash_yemchain, fee_hash_yemchain, COALESCE(error_message,'') as error_message, created_at FROM stellar_withdraw WHERE status = 2
                UNION ALL
                SELECT 'binance', id, uid, address, amount, COALESCE(fee_usdd,0), txn_hash_bsc, txn_hash_yemchain, fee_hash_yemchain, COALESCE(error_message,''), created_at FROM binance_withdraw WHERE status = 2
                UNION ALL
                SELECT 'ethereum', id, uid, address, amount, COALESCE(fee_usdd,0), txn_hash_eth, txn_hash_yemchain, fee_hash_yemchain, COALESCE(error_message,''), created_at FROM ethereum_withdraw WHERE status = 2
                ORDER BY created_at DESC LIMIT ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            fputcsv($output, ['Network', 'ID', 'UID', 'Address', 'Amount DBV', 'Fee USDD', 'Txn Hash (Network)', 'DBV Hash', 'Fee Hash', 'Failure Reason', 'Created At']);
            foreach ($rows as $r) {
                fputcsv($output, [$r['network'], $r['id'], $r['uid'], $r['address'], $r['amount'], $r['fee_usdd'], $r['txn_hash_network'], $r['txn_hash_yemchain'], $r['fee_hash_yemchain'] ?? '', $r['error_message'] ?? '', $r['created_at']]);
            }
            break;

        case 'fees':
            $sql = "
                SELECT 'stellar' as network, id, uid, amount, COALESCE(fee_usdd,0) as fee_usdd, address, created_at FROM stellar_withdraw WHERE status = 3 AND COALESCE(fee_usdd,0) <= 25
                UNION ALL
                SELECT 'binance', id, uid, amount, COALESCE(fee_usdd,0), address, created_at FROM binance_withdraw WHERE status = 3 AND COALESCE(fee_usdd,0) <= 25
                UNION ALL
                SELECT 'ethereum', id, uid, amount, COALESCE(fee_usdd,0), address, created_at FROM ethereum_withdraw WHERE status = 3 AND COALESCE(fee_usdd,0) <= 25
                ORDER BY created_at DESC LIMIT ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            fputcsv($output, ['Network', 'ID', 'UID', 'Amount DBV', 'Fee USDD', 'Address', 'Created At']);
            foreach ($rows as $r) {
                fputcsv($output, [$r['network'], $r['id'], $r['uid'], $r['amount'], $r['fee_usdd'], $r['address'], $r['created_at']]);
            }
            break;

        case 'audit':
            $audit = new AuditService($pdo);
            if (!$audit->tableExists()) {
                fputcsv($output, ['Message']);
                fputcsv($output, ['Audit table not created. Run setup_audit_log.php']);
            } else {
                $stmt = $pdo->query("SELECT id, admin_uid, action, entity_type, entity_id, details, ip, created_at FROM audit_log ORDER BY created_at DESC LIMIT $limit");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                fputcsv($output, ['ID', 'Admin UID', 'Action', 'Entity Type', 'Entity ID', 'Details', 'IP', 'Created At']);
                foreach ($rows as $r) {
                    fputcsv($output, [$r['id'], $r['admin_uid'], $r['action'], $r['entity_type'], $r['entity_id'], $r['details'], $r['ip'], $r['created_at']]);
                }
            }
            break;

        case 'reversals':
            try {
                $stmt = $pdo->query("SELECT id, network, withdrawal_id, uid, dbv_amount, usdd_amount, dbv_txn_hash, usdd_txn_hash, status, created_at FROM reversals ORDER BY created_at DESC LIMIT $limit");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                fputcsv($output, ['ID', 'Network', 'Withdrawal ID', 'UID', 'DBV Amount', 'USDD Amount', 'DBV Hash', 'USDD Hash', 'Status', 'Created At']);
                foreach ($rows as $r) {
                    fputcsv($output, [$r['id'], $r['network'], $r['withdrawal_id'], $r['uid'], $r['dbv_amount'], $r['usdd_amount'], $r['dbv_txn_hash'] ?? '', $r['usdd_txn_hash'] ?? '', $r['status'], $r['created_at']]);
                }
            } catch (Exception $e) {
                fputcsv($output, ['Message']);
                fputcsv($output, ['Reversals table not found or error']);
            }
            break;

        case 'commissions':
            try {
                $chk = $pdo->query("SHOW COLUMNS FROM stellar_withdraw LIKE 'referrer_uid'");
                if (!$chk || $chk->rowCount() === 0) {
                    fputcsv($output, ['Message']);
                    fputcsv($output, ['Referral columns not found. Run add_referral_columns migration.']);
                } else {
                    $sql = "
                        SELECT 'stellar' as network, id as withdrawal_id, referrer_uid, uid as referred_uid, referral_commission_usdd, referral_commission_hash, created_at FROM stellar_withdraw WHERE referrer_uid IS NOT NULL AND COALESCE(referral_commission_usdd, 0) > 0
                        UNION ALL
                        SELECT 'binance', id, referrer_uid, uid, referral_commission_usdd, referral_commission_hash, created_at FROM binance_withdraw WHERE referrer_uid IS NOT NULL AND COALESCE(referral_commission_usdd, 0) > 0
                        UNION ALL
                        SELECT 'ethereum', id, referrer_uid, uid, referral_commission_usdd, referral_commission_hash, created_at FROM ethereum_withdraw WHERE referrer_uid IS NOT NULL AND COALESCE(referral_commission_usdd, 0) > 0
                        ORDER BY created_at DESC LIMIT ?
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$limit]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    fputcsv($output, ['Network', 'Withdrawal ID', 'Referee UID', 'Referred UID', 'Amount USDD', 'Commission Hash', 'Created At']);
                    foreach ($rows as $r) {
                        fputcsv($output, [$r['network'], $r['withdrawal_id'], $r['referrer_uid'], $r['referred_uid'], $r['referral_commission_usdd'], $r['referral_commission_hash'] ?? '', $r['created_at']]);
                    }
                }
            } catch (Exception $e) {
                fputcsv($output, ['Message']);
                fputcsv($output, ['Error: ' . $e->getMessage()]);
            }
            break;
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    header('Content-Disposition: inline');
    error_log('Download report error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}

fclose($output);
