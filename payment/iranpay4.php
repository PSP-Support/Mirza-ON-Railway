<?php
declare(strict_types=1);

ini_set('error_log', 'error_log');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels/Api/Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../vendor/autoload.php';

$ManagePanel = new ManagePanel();

function nm_aban_reply(int $httpCode, string $state, string $message, bool $webhook): never
{
    http_response_code($httpCode);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');

    if ($webhook) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $httpCode >= 200 && $httpCode < 300,
            'state' => $state,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    $title = match ($state) {
        'success', 'already' => 'پرداخت تأیید شد',
        'waiting' => 'در انتظار تأیید پرداخت',
        'expired' => 'مهلت پرداخت تمام شده است',
        default => 'وضعیت پرداخت مشخص نشد',
    };
    $colour = match ($state) {
        'success', 'already' => '#16a34a',
        'waiting' => '#d97706',
        'expired' => '#dc2626',
        default => '#dc2626',
    };
    $botHandle = ltrim((string)($GLOBALS['usernamebot'] ?? ''), '@');
    $botLink = $botHandle !== ''
        ? '<a href="https://t.me/' . htmlspecialchars($botHandle, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;margin-top:12px;padding:11px 22px;border-radius:10px;background:#2563eb;color:white;text-decoration:none">بازگشت به ربات</a>'
        : '';

    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
        . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#101418;font-family:Tahoma,Arial,sans-serif;padding:22px">'
        . '<main style="box-sizing:border-box;width:min(440px,100%);padding:30px 24px;border-radius:18px;background:#1b2229;color:#eef2f7;text-align:center;border:1px solid #303a44">'
        . '<div style="font-size:38px;color:' . $colour . '">' . (($state === 'success' || $state === 'already') ? '&#10003;' : '&#8987;') . '</div>'
        . '<h2 style="font-size:19px">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>'
        . '<p style="color:#b8c1ca;line-height:2;font-size:14px">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        . $botLink
        . '</main></body></html>';
    exit;
}

/**
 * The webhook payload is only a notification. Always query and verify the
 * invoice with the merchant API key before marking the local order as paid.
 */
function nm_aban_api(string $method, string $path, ?array $body = null): array
{
    $apiKey = trim((string)getPaySettingValue('apiiranpay4', ''));
    $apiBase = abangatewayEndpoint();
    if ($apiKey === '' || $apiKey === '0' || $apiBase === null) {
        return ['http_code' => 0, 'data' => [], 'error' => 'API key or endpoint is not configured'];
    }

    $method = strtoupper($method);
    $options = [
        CURLOPT_URL => $apiBase . $path,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 18,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
        ],
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        $options[CURLOPT_POSTFIELDS] = json_encode(
            $body ?? new stdClass(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    } else {
        $options[CURLOPT_HTTPGET] = true;
    }

    $curl = curl_init();
    curl_setopt_array($curl, $options);
    $raw = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($raw === false) {
        error_log('AbanGateway callback API transport error: ' . $error);
        return ['http_code' => $status, 'data' => [], 'error' => 'gateway connection failed'];
    }

    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        error_log('AbanGateway callback API returned non-JSON; HTTP ' . $status);
        return ['http_code' => $status, 'data' => [], 'error' => 'invalid gateway response'];
    }

    return ['http_code' => $status, 'data' => $decoded, 'error' => null];
}

$webhook = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
$rawBody = $webhook ? (string)file_get_contents('php://input') : '';
$eventData = $webhook ? json_decode($rawBody, true) : [];
$eventData = is_array($eventData) ? $eventData : [];

$orderId = trim((string)($eventData['order_id'] ?? $_GET['order_id'] ?? $_GET['order'] ?? ''));
$incomingInvoiceId = trim((string)($eventData['invoice_id'] ?? $_GET['invoice_id'] ?? ''));
$eventName = trim((string)($eventData['event'] ?? ''));

if ($orderId === '' && $incomingInvoiceId !== '') {
    $findByInvoice = $pdo->prepare(
        "SELECT id_order FROM payment_report WHERE dec_not_confirmed = ? AND Payment_Method = 'AbanGateway' LIMIT 1"
    );
    $findByInvoice->execute([$incomingInvoiceId]);
    $orderId = (string)($findByInvoice->fetchColumn() ?: '');
}

if ($orderId === '') {
    nm_aban_reply(400, 'failed', 'شناسه سفارش در درخواست بازگشت وجود ندارد.', $webhook);
}

$stmt = $pdo->prepare("SELECT * FROM payment_report WHERE id_order = ? AND Payment_Method = 'AbanGateway' LIMIT 1");
$stmt->execute([$orderId]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    nm_aban_reply(404, 'failed', 'سفارش آبان‌گیت‌وی پیدا نشد.', $webhook);
}

if (($payment['payment_Status'] ?? '') === 'paid') {
    nm_aban_reply(200, 'already', 'این سفارش قبلاً تأیید شده است.', $webhook);
}

if (in_array(($payment['payment_Status'] ?? ''), ['expire', 'expired', 'cancelled'], true)) {
    nm_aban_reply(200, 'expired', 'این سفارش منقضی یا لغو شده است.', $webhook);
}

$storedInvoiceId = trim((string)($payment['dec_not_confirmed'] ?? ''));
if ($storedInvoiceId === '') {
    error_log('AbanGateway callback: local order has no provider invoice id; order ' . $orderId);
    nm_aban_reply(409, 'failed', 'شناسه فاکتور درگاه برای این سفارش ذخیره نشده است.', $webhook);
}

if ($incomingInvoiceId !== '' && !hash_equals($storedInvoiceId, $incomingInvoiceId)) {
    error_log('AbanGateway callback: invoice id mismatch for order ' . $orderId);
    nm_aban_reply(409, 'failed', 'شناسه فاکتور با سفارش ثبت‌شده مطابقت ندارد.', $webhook);
}

if ($eventName !== '' && !in_array($eventName, ['invoice.paid', 'invoice.expired', 'invoice.cancelled'], true)) {
    nm_aban_reply(200, 'ignored', 'این رویداد نیاز به تغییری در سفارش ندارد.', $webhook);
}

if ($eventName === 'invoice.expired' || $eventName === 'invoice.cancelled') {
    $expire = $pdo->prepare(
        "UPDATE payment_report SET payment_Status = 'expire' WHERE id_order = ? AND Payment_Method = 'AbanGateway' AND payment_Status <> 'paid'"
    );
    $expire->execute([$orderId]);
    clearSelectCache('payment_report');
    nm_aban_reply(200, 'expired', 'مهلت فاکتور تمام شده یا فاکتور لغو شده است.', $webhook);
}

$remote = nm_aban_api('GET', '/invoices/' . rawurlencode($storedInvoiceId));
if ($remote['http_code'] < 200 || $remote['http_code'] >= 300) {
    error_log('AbanGateway invoice lookup failed for local order ' . $orderId . '; HTTP ' . $remote['http_code']);
    nm_aban_reply(503, 'failed', 'استعلام فاکتور از آبان‌گیت‌وی ناموفق بود. دوباره تلاش کنید.', $webhook);
}

$remoteInvoice = $remote['data'];
$remoteOrderId = (string)($remoteInvoice['order_id'] ?? '');
$remoteStatus = strtolower((string)($remoteInvoice['status'] ?? ''));
$localAmountRial = (int)round((float)($payment['price'] ?? 0) * 10);
$remoteAmountRial = (int)($remoteInvoice['amount_rial'] ?? 0);

if ($remoteOrderId === '' || !hash_equals((string)$orderId, $remoteOrderId) || $remoteAmountRial !== $localAmountRial) {
    error_log('AbanGateway invoice/order/amount mismatch for local order ' . $orderId);
    nm_aban_reply(409, 'failed', 'اطلاعات فاکتور درگاه با سفارش ثبت‌شده مطابقت ندارد.', $webhook);
}

if ($remoteStatus === 'expired' || $remoteStatus === 'cancelled') {
    $expire = $pdo->prepare(
        "UPDATE payment_report SET payment_Status = 'expire' WHERE id_order = ? AND Payment_Method = 'AbanGateway' AND payment_Status <> 'paid'"
    );
    $expire->execute([$orderId]);
    clearSelectCache('payment_report');
    nm_aban_reply(200, 'expired', 'مهلت فاکتور تمام شده یا فاکتور لغو شده است.', $webhook);
}

if ($remoteStatus !== 'paid') {
    if ($webhook && $eventName === 'invoice.paid') {
        nm_aban_reply(503, 'waiting', 'وضعیت فاکتور هنوز در آبان‌گیت‌وی نهایی نشده است.', true);
    }
    nm_aban_reply(200, 'waiting', 'پرداخت هنوز از طرف آبان‌گیت‌وی تأیید نشده است.', $webhook);
}

// The API's one-time verify call is authoritative. On a duplicate webhook,
// already_verified is accepted only after the invoice GET above returned paid.
$verified = nm_aban_api('POST', '/invoices/' . rawurlencode($storedInvoiceId) . '/verify');
$verifiedOk = $verified['http_code'] === 200 && !empty($verified['data']['verified']);
$alreadyVerified = $verified['http_code'] === 409
    && (string)($verified['data']['error']['code'] ?? '') === 'already_verified';

if (!$verifiedOk && !$alreadyVerified) {
    $errorCode = (string)($verified['data']['error']['code'] ?? 'verify_failed');
    error_log('AbanGateway verify failed for order ' . $orderId . '; HTTP ' . $verified['http_code'] . ', code ' . $errorCode);
    if ($errorCode === 'not_yet_paid' || $verified['http_code'] === 402) {
        nm_aban_reply(503, 'waiting', 'آبان‌گیت‌وی هنوز پرداخت را نهایی نکرده است.', $webhook);
    }
    nm_aban_reply(503, 'failed', 'تأیید نهایی پرداخت توسط آبان‌گیت‌وی ناموفق بود.', $webhook);
}

$verifiedOrderId = (string)($verified['data']['order_id'] ?? $remoteOrderId);
$verifiedAmountRial = (int)($verified['data']['amount_rial'] ?? $remoteAmountRial);
if (!hash_equals((string)$orderId, $verifiedOrderId) || $verifiedAmountRial !== $localAmountRial) {
    error_log('AbanGateway verify result mismatch for order ' . $orderId);
    nm_aban_reply(409, 'failed', 'پاسخ تأیید درگاه با سفارش ثبت‌شده مطابقت ندارد.', $webhook);
}

if (!claimPaymentPaid($orderId)) {
    $fresh = $pdo->prepare("SELECT payment_Status FROM payment_report WHERE id_order = ? LIMIT 1");
    $fresh->execute([$orderId]);
    if ((string)$fresh->fetchColumn() === 'paid') {
        nm_aban_reply(200, 'already', 'این سفارش قبلاً تأیید شده است.', $webhook);
    }
    nm_aban_reply(503, 'failed', 'ثبت وضعیت پرداخت در ربات ناموفق بود.', $webhook);
}

clearSelectCache('payment_report');
$textbotlang = languagechange();
try {
    DirectPayment($orderId, '../images.jpg');
} catch (Throwable $directPaymentError) {
    error_log('AbanGateway DirectPayment failed for order ' . $orderId . ': ' . $directPaymentError->getMessage());
    nm_aban_reply(500, 'failed', 'پرداخت تأیید شد، اما فعال‌سازی سفارش نیاز به بررسی مدیر دارد.', $webhook);
}

$cashbackPercent = (float)config_get('pay', 'chashbackiranpay4');
$balanceUser = select('user', '*', 'id', $payment['id_user'], 'select');
if ($cashbackPercent > 0 && is_array($balanceUser)) {
    $cashback = ((float)$payment['price'] * $cashbackPercent) / 100;
    addBalance($balanceUser['id'], $cashback);
    $textReport = sprintf($textbotlang['paymentGateway']['giftReport'], $cashback);
    sendmessage($balanceUser['id'], $textReport, null, 'HTML');
}

$setting = config_all('setting');
$paymentTopic = config_get('topic', 'paymentreport');
if (strlen((string)($setting['Channel_Report'] ?? '')) > 0 && is_array($balanceUser)) {
    $reportText = sprintf(
        $textbotlang['paymentGateway']['reportAbanGateway'] ?? 'AbanGateway payment confirmed: %s / %s / %s',
        $payment['id_user'],
        $balanceUser['username'] ?? '',
        number_format((int)$payment['price'])
    );
    telegram('sendmessage', [
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $paymentTopic,
        'text' => $reportText,
        'parse_mode' => 'HTML',
    ]);
}

nm_aban_reply(200, 'success', 'پرداخت شما با موفقیت تأیید شد و سفارش ثبت شد.', $webhook);
