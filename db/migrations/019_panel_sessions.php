<?php
return static function (PDO $pdo): void {
    error_log('[Mirza db] migration 019_panel_sessions: starting');

    $pdo->exec("CREATE TABLE IF NOT EXISTS panel_sessions (
        session_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
        session_data MEDIUMBLOB NOT NULL,
        last_activity INT UNSIGNED NOT NULL,
        INDEX idx_panel_sessions_activity (last_activity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $check = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'panel_sessions'"
    );
    $check->execute();
    if ((int)$check->fetchColumn() !== 1) {
        throw new RuntimeException('Migration 019 could not verify panel_sessions table.');
    }

    error_log('[Mirza db] migration 019_panel_sessions: verified panel_sessions table');
};
