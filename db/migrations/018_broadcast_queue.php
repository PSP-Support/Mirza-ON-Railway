<?php
return static function (PDO $pdo): void {
    error_log('[Mirza db] migration 018_broadcast_queue: starting');

    $pdo->exec("CREATE TABLE IF NOT EXISTS broadcast_queue (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        admin_id BIGINT NOT NULL,
        message_id BIGINT NOT NULL,
        type VARCHAR(32) NOT NULL,
        payload LONGTEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        total_count INT UNSIGNED NOT NULL DEFAULT 0,
        sent_count INT UNSIGNED NOT NULL DEFAULT 0,
        failed_count INT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_broadcast_status (status,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS broadcast_recipients (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        queue_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'pending',
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        last_error VARCHAR(500) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_broadcast_recipient (queue_id,user_id),
        INDEX idx_broadcast_pending (queue_id,status,id),
        CONSTRAINT fk_broadcast_recipient_queue FOREIGN KEY (queue_id) REFERENCES broadcast_queue(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $check = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('broadcast_queue', 'broadcast_recipients')");
    $tables = $check->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    if ($tables !== ['broadcast_queue', 'broadcast_recipients']) {
        throw new RuntimeException('Migration 018 did not create both broadcast tables in database ' . (string)$pdo->query('SELECT DATABASE()')->fetchColumn());
    }

    error_log('[Mirza db] migration 018_broadcast_queue: verified both tables');
};
