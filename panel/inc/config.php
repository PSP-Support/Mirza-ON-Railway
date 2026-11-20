<?php

// Railway terminates HTTPS at its proxy. Respect the forwarded scheme when
// deciding whether the browser session cookie must carry the Secure flag.
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
}

require __DIR__ . '/../../config.php';
require __DIR__ . '/../../function.php';

/**
 * Store admin-panel sessions in MySQL so a request routed to another Railway
 * replica, or a container restart, does not silently lose the login session.
 */
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
            $lock = $this->pdo->prepare('SELECT GET_LOCK(?, 5)');
            $lock->execute([$lockName]);
            if ((int)$lock->fetchColumn() !== 1) {
                error_log('Panel session lock could not be acquired.');
                return false;
            }
            $this->lockName = $lockName;

            $stmt = $this->pdo->prepare('SELECT session_data, last_activity FROM panel_sessions WHERE session_id = ? LIMIT 1');
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

// Register the shared handler before any panel page calls session_start().
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
        session_set_save_handler(new MirzaPanelPdoSessionHandler($pdo), true);
    } catch (Throwable $e) {
        // Keep the native PHP handler as a fallback if the database user cannot
        // create/use the session table; the reason will be visible in Railway logs.
        error_log('Panel shared-session handler unavailable; using PHP file sessions: ' . $e->getMessage());
    }
}

// Keep panel sessions alive for up to eight hours of inactivity.
ini_set('session.gc_maxlifetime', '28800');

date_default_timezone_set('Asia/Tehran');

// Panel UI language strings (loaded from lang/fa.php via languagechange())
$panelLanguages = ['fa' => 'فارسی', 'en' => 'English', 'ru' => 'Русский', 'zh' => '中文'];
$panelLang = $_GET['lang'] ?? $_COOKIE['panel_lang'] ?? 'fa';
if (!is_string($panelLang) || !isset($panelLanguages[$panelLang])) {
    $panelLang = 'fa';
}
if (isset($_GET['lang'])) {
    setcookie('panel_lang', $panelLang, ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
}
$textbotlang = languagechange(null, $panelLang);

function db_query(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_fetch(PDO $pdo, string $sql, array $params = []): ?array
{
    return db_query($pdo, $sql, $params)->fetch() ?: null;
}

function db_fetchAll(PDO $pdo, string $sql, array $params = []): array
{
    return db_query($pdo, $sql, $params)->fetchAll();
}

function db_count(PDO $pdo, string $sql, array $params = []): int
{
    return (int) db_query($pdo, $sql, $params)->fetchColumn();
}

function move_display_order(PDO $pdo, string $table, int $id, int $position): void
{
    $current = $pdo->query("SELECT id, display_order FROM `$table` ORDER BY display_order, id FOR UPDATE")->fetchAll(PDO::FETCH_KEY_PAIR);
    $ids = array_keys($current);
    $from = array_search($id, $ids, true);
    $to = max(0, min(count($ids) - 1, $position - 1));
    if ($from === false || $from === $to) {
        return;
    }
    array_splice($ids, $from, 1);
    array_splice($ids, $to, 0, [$id]);
    $stmt = $pdo->prepare("UPDATE `$table` SET display_order = ? WHERE id = ?");
    foreach ($ids as $index => $rowId) {
        if ((int) $current[$rowId] !== $index + 1) {
            $stmt->execute([$index + 1, $rowId]);
        }
    }
}
function require_auth(): void
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    global $pdo;
    if (empty($_SESSION['admin_user'])) {
        header('Location: login.php');
        exit;
    }
    try {
        $admin = db_fetch($pdo, "SELECT id_admin, rule FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
        if (!$admin || $admin['rule'] !== 'administrator') {
            session_destroy();
            header('Location: login.php');
            exit;
        }
    } catch (Throwable $e) {
        // A transient database error must not erase a valid admin session and
        // create a redirect loop back to the login page.
        error_log('Panel auth database check failed: ' . $e->getMessage());
        http_response_code(503);
        exit('ارتباط با پایگاه داده موقتاً برقرار نشد. صفحه را دوباره بارگذاری کنید.');
    }
}

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check_value(string $token): bool
{
    return hash_equals($_SESSION['csrf'] ?? '', $token);
}

function csrf_check_post(): void
{
    global $textbotlang;
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        die($textbotlang['panel']['configInvalidRequest']);
    }
}

function csrf_check_get(): void
{
    global $textbotlang;
    $token = $_GET['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        die($textbotlang['panel']['configInvalidRequest']);
    }
}

function flash(string $key, string $msg): void
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    $_SESSION["flash_{$key}"] = $msg;
}

function get_flash(string $key): ?string
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    $msg = $_SESSION["flash_{$key}"] ?? null;
    unset($_SESSION["flash_{$key}"]);
    return $msg;
}

function trunc(string $str, int $max = 30): string
{
    return mb_strlen($str, 'UTF-8') > $max
        ? mb_substr($str, 0, $max, 'UTF-8') . '…'
        : $str;
}

function safe_date($ts, string $fmt = 'Y/m/d'): string
{
    if (!$ts)
        return '—';
    if (!is_numeric($ts))
        return htmlspecialchars((string) $ts);
    return date($fmt, (int) $ts);
}
function check_login_rate(string $ip): bool
{
    $file = sys_get_temp_dir() . '/panel_login_' . md5($ip);
    $data = @json_decode(@file_get_contents($file) ?: '{}', true) ?: [];
    $now = time();
    $data = array_filter($data, fn($t) => ($now - $t) < 900);
    if (count($data) >= 10)
        return false;
    $data[] = $now;
    @file_put_contents($file, json_encode(array_values($data)), LOCK_EX);
    return true;
}

function clear_login_rate(string $ip): void
{
    @unlink(sys_get_temp_dir() . '/panel_login_' . md5($ip));
}

function user_role_label(string $agent): string
{
    global $textbotlang;
    return match ($agent) {
        'n' => $textbotlang['panel']['configRoleN'],
        'n2' => $textbotlang['panel']['configRoleN2'],
        'all' => $textbotlang['panel']['configRoleAll'],
        default => $textbotlang['panel']['configRoleDefault'],
    };
}

function user_role_tag(string $agent): string
{
    return match ($agent) {
        'f' => 'tag-info',
        'n' => 'tag-info',
        'n2' => 'tag-warn',
        'all' => 'tag-ok',
        default => 'tag-plain',
    };
}
