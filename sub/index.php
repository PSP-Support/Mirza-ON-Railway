<?php
declare(strict_types=1);

ini_set('error_log', 'error_log');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../panels/Api/Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';

/**
 * v2rayNG accepts subscription responses containing one supported share URI per line.
 * Keep this endpoint plain-text only: no HTML, warnings, debug output, or extra whitespace.
 */
function nm_sub_response(int $status, string $body): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    echo $body;
    exit;
}

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!preg_match('~(?:^|/)sub/([a-f0-9]{4,64})/?$~i', $path, $matches)) {
    nm_sub_response(404, "اشتراک معتبر نیست.\n");
}
$token = strtolower($matches[1]);

try {
    $invoice = select('invoice', '*', 'id_invoice', $token, 'select');

    $allowedStatuses = ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'];
    if (!is_array($invoice) || !in_array($invoice['Status'] ?? '', $allowedStatuses, true)) {
        nm_sub_response(404, "اشتراک پیدا نشد یا غیرفعال است.\n");
    }

    $manager = new ManagePanel();
    $userData = $manager->DataUser(
        $invoice['Service_location'] ?? '',
        $invoice['username'] ?? ''
    );

    if (!is_array($userData) || ($userData['status'] ?? '') === 'Unsuccessful') {
        error_log('NM subscription endpoint: DataUser returned no usable data for invoice ' . $token);
        nm_sub_response(502, "دریافت اطلاعات اشتراک از سرور ناموفق بود.\n");
    }

    $rawLinks = $userData['links'] ?? [];
    if (!is_array($rawLinks)) {
        error_log('NM subscription endpoint: links field is not an array for invoice ' . $token);
        nm_sub_response(502, "ساختار کانفیگ‌های اشتراک معتبر نیست.\n");
    }

    $supportedSchemes = [
        'vless://', 'vmess://', 'trojan://', 'ss://', 'shadowsocks://',
        'socks://', 'hysteria://', 'hysteria2://', 'hy2://', 'tuic://',
        'wireguard://', 'anytls://'
    ];

    $configs = [];
    foreach ($rawLinks as $link) {
        if (!is_string($link)) {
            continue;
        }

        $link = trim($link);
        if ($link === '' || preg_match('/[\r\n\x00-\x08\x0B\x0C\x0E-\x1F]/', $link)) {
            continue;
        }

        $lowerLink = strtolower($link);
        $validScheme = false;
        foreach ($supportedSchemes as $scheme) {
            if (str_starts_with($lowerLink, $scheme)) {
                $validScheme = true;
                break;
            }
        }

        if ($validScheme) {
            $configs[$link] = $link;
        }
    }

    if (!$configs) {
        error_log('NM subscription endpoint: no supported config URI found for invoice ' . $token);
        nm_sub_response(502, "هیچ کانفیگ قابل پشتیبانی برای این اشتراک دریافت نشد.\n");
    }

    // Standard newline-separated URI list. v2rayNG parses both raw URI lists and Base64 lists.
    $body = implode("\n", array_values($configs)) . "\n";
    nm_sub_response(200, $body);

} catch (Throwable $e) {
    error_log('NM subscription endpoint error: ' . $e->getMessage());
    nm_sub_response(500, "خطای داخلی هنگام ساخت لینک اشتراک.\n");
}
