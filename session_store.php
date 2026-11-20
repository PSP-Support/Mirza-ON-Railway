<?php
declare(strict_types=1);

/**
 * Shared admin-panel sessions for browser pages and API endpoints.
 * Railway may route consecutive requests to different containers, so the
 * session state must live in the shared MySQL database, not local temp files.
 */

if (session_status() === PHP_SESSION_NONE) {
    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $isHttpsRequest = (
        (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ||
        $forwardedProto === 'https'
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttpsRequest,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', '28800');
}

if (!class_exists('MirzaPanelPdoSessionHandler', false)) {
    final class MirzaPanelPdoSessionHandler implements SessionHandlerInterface
    {
        private PDO $pdo;
        private ?string $lockName = null;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        public function open(string $path, string $name): bool
        {
            return true;
        }

        public function close(): bool
        {
            if ($this->lockName !== null) {
                try {
                    $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $stmt->execute([$this->lockName]);
                } catch (Throwable $e) {
                    error_log('Panel session lock release failed: ' . $e->getMessage());
                }
                $this->lockName = null;
            }
            return true;
        }

        public function read(string $id): string|false
        {
            $lockName = 'nm_panel_' . substr(hash('sha256', $id), 0, 48);
            $lock = $this->pdo->prepare('SELECT GET_LOCK(?, 8)');
            $lock->execute([$lockName]);
            if ((int)$lock->fetchColumn() !== 1) {
                error_log('Panel session lock could not be acquired.');
                return false;
            }
            $this->lockName = $lockName;

            $stmt = $this->pdo->prepare(
                'SELECT session_data, last_activity FROM panel_sessions WHERE session_id = ? LIMIT 1'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return '';
            }

            if ((int)$row['last_activity'] < time() - 28800) {
                $delete = $this->pdo->prepare('DELETE FROM panel_sessions WHERE session_id = ?');
                $delete->execute([$id]);
                return '';
            }

            return (string)$row['session_data'];
        }

        public function write(string $id, string $data): bool
        {
            $stmt = $this->pdo->prepare(
                'INSERT INTO panel_sessions (session_id, session_data, last_activity) VALUES (?, ?, ?) ' .
                'ON DUPLICATE KEY UPDATE session_data = VALUES(session_data), last_activity = VALUES(last_activity)'
            );
            return $stmt->execute([$id, $data, time()]);
        }

        public function destroy(string $id): bool
        {
            $stmt = $this->pdo->prepare('DELETE FROM panel_sessions WHERE session_id = ?');
            return $stmt->execute([$id]);
        }

        public function gc(int $max_lifetime): int|false
        {
            $stmt = $this->pdo->prepare('DELETE FROM panel_sessions WHERE last_activity < ?');
            $stmt->execute([time() - max(60, $max_lifetime)]);
            return $stmt->rowCount();
        }
    }
}

if (session_status() === PHP_SESSION_NONE && isset($pdo) && $pdo instanceof PDO) {
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS panel_sessions (' .
            'session_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,' .
            'session_data MEDIUMBLOB NOT NULL,' .
            'last_activity INT UNSIGNED NOT NULL,' .
            'INDEX idx_panel_sessions_activity (last_activity)' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        if (!session_set_save_handler(new MirzaPanelPdoSessionHandler($pdo), true)) {
            throw new RuntimeException('PHP refused to register the shared panel session handler.');
        }
    } catch (Throwable $e) {
        error_log('Panel shared-session handler unavailable; using PHP file sessions: ' . $e->getMessage());
    }
}
