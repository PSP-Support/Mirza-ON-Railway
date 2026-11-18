<?php

require_once __DIR__ . '/db/bootstrap.php';

/* Railway startup diagnostic: confirm the broadcast tables on the exact app connection. */
try {
    global $pdo;
    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('broadcast_queue', 'broadcast_recipients')");
    $broadcastTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    sort($broadcastTables);
    echo "[Mirza db-check] database=" . $dbName . PHP_EOL;
    echo "[Mirza db-check] broadcast_tables=" . implode(',', $broadcastTables) . PHP_EOL;
    if ($broadcastTables !== ['broadcast_queue', 'broadcast_recipients']) {
        echo "[Mirza db-check] ERROR: one or more broadcast tables are missing." . PHP_EOL;
    } else {
        echo "[Mirza db-check] OK: both broadcast tables exist." . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "[Mirza db-check] ERROR: " . $e->getMessage() . PHP_EOL;
}


foreach (['Marzban.php', 'marzneshin.php', 'hiddify.php', 'ibsng.php', 'mikrotik.php', 'mirza_agent.php', 'nexora.php', 'Rebecca.php', 'WGDashboard.php', 'wg_mate.php', 'x-ui_single.php', 'alireza_single.php', 's_ui.php'] as $oldPanelFile) {
    if (is_file(__DIR__ . '/' . $oldPanelFile)) {
        @unlink(__DIR__ . '/' . $oldPanelFile);
    }
}

global $domainhosts;

$setting = config_all('setting');
if (intval($setting['Channel_Report'] ?? 0) != 0) {
    syncReportTopics($setting['Channel_Report'], languagechange());
}

$webhookSecret = ensureWebhookSecret();

telegram('setWebhook', [
    'url' => "https://$domainhosts/index.php?secret={$webhookSecret['secret']}",
]);
