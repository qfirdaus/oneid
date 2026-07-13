<?php
/**
 * CLI entry point for scheduled daily sync.
 * Usage: php cron/run_sync.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$projectRoot = dirname(__DIR__);
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/sync_cron.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function cron_log($message){
    global $logFile;
    $line = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

require_once $projectRoot . '/lib/config.php';
require_once $projectRoot . '/lib/external_data_source_API.php';
require_once $projectRoot . '/lib/sync_user_runner.php';

$triggered_by = defined('SYNC_CRON_TRIGGERED_BY') ? SYNC_CRON_TRIGGERED_BY : 'cron';

cron_log('START sync triggered_by=' . $triggered_by);

try {
    $header_info = run_admin_sync_user($operation, $triggered_by);
    $operation->syslog_record(
        22,
        'User: ' . $triggered_by . ' ADMIN_SYNC_USER_CRON session=' . $header_info['ext_head_id']
            . ' new=' . $header_info['New']
            . ' updated=' . $header_info['Update']
            . ' deactivated=' . $header_info['Deactivate']
            . ' reactivated=' . $header_info['Reactivate'],
        '127.0.0.1'
    );
    cron_log('OK session=' . $header_info['ext_head_id']
        . ' status=' . ($header_info['ext_head_status'] ?? '?')
        . ' new=' . $header_info['New']
        . ' updated=' . $header_info['Update']
        . ' deactivated=' . $header_info['Deactivate']
        . ' reactivated=' . $header_info['Reactivate']);
    exit(0);
} catch (Exception $e) {
    cron_log('ERROR ' . $e->getMessage());
    exit(1);
}
