<?php
/**
 * Railway-compatible runtime configuration.
 * Secrets are injected through Railway Variables; never commit real credentials.
 */

$request_exec_timeout = getenv('REQUEST_EXEC_TIMEOUT_MS') !== false
    ? (int) getenv('REQUEST_EXEC_TIMEOUT_MS')
    : null;

if (!function_exists('mirza_env')) {
function mirza_env(array $names, ?string $default = null): ?string {
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && trim((string) $value) !== '') {
            return trim((string) $value);
        }
    }
    return $default;
}
}

$dbUrl = mirza_env(['MYSQL_URL', 'DATABASE_URL']);
$dbParts = $dbUrl ? parse_url($dbUrl) : false;
$dbhost = mirza_env(['DB_HOST', 'MYSQLHOST'], is_array($dbParts) ? ($dbParts['host'] ?? null) : null);
$dbport = mirza_env(['DB_PORT', 'MYSQLPORT'], is_array($dbParts) ? (string)($dbParts['port'] ?? '3306') : '3306');
$dbname = mirza_env(['DB_NAME', 'MYSQLDATABASE'], is_array($dbParts) ? ltrim((string)($dbParts['path'] ?? ''), '/') : null);
$usernamedb = mirza_env(['DB_USER', 'MYSQLUSER'], is_array($dbParts) ? (isset($dbParts['user']) ? urldecode($dbParts['user']) : null) : null);
$passworddb = mirza_env(['DB_PASSWORD', 'MYSQLPASSWORD'], is_array($dbParts) ? (isset($dbParts['pass']) ? urldecode($dbParts['pass']) : null) : null);

if (!$dbhost || !$dbname || !$usernamedb || $passworddb === null) {
    error_log('Mirza configuration error: set DB_HOST, DB_NAME, DB_USER and DB_PASSWORD (or Railway MySQL variables).');
    http_response_code(503);
    exit('Database configuration is incomplete. Check Railway Variables.');
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$dsn = "mysql:host={$dbhost};port={$dbport};dbname={$dbname};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $usernamedb, $passworddb, $options);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Throwable $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(503);
    exit('Database connection failed. Check Railway MySQL service and variables.');
}

$APIKEY = mirza_env(['TELEGRAM_BOT_TOKEN', 'BOT_TOKEN'], '');
$adminnumber = mirza_env(['TELEGRAM_ADMIN_ID', 'ADMIN_ID'], '');
$appUrl = mirza_env(['APP_URL'], null);
if (!$appUrl) {
    $railwayDomain = mirza_env(['RAILWAY_PUBLIC_DOMAIN']);
    $appUrl = $railwayDomain ? 'https://' . $railwayDomain : '';
}
$parsedAppUrl = $appUrl ? parse_url($appUrl) : false;
$domainhosts = is_array($parsedAppUrl) ? ($parsedAppUrl['host'] ?? '') : trim((string)$appUrl);
$domainhosts = preg_replace('/:\d+$/', '', (string)$domainhosts);
$usernamebot = ltrim((string) mirza_env(['TELEGRAM_BOT_USERNAME', 'BOT_USERNAME'], ''), '@');

if ($APIKEY === '') {
    error_log('Mirza configuration warning: TELEGRAM_BOT_TOKEN is not set.');
}
if ($adminnumber === '') {
    error_log('Mirza configuration warning: TELEGRAM_ADMIN_ID is not set.');
}
if ($domainhosts === '') {
    error_log('Mirza configuration warning: APP_URL or RAILWAY_PUBLIC_DOMAIN is not set.');
}
