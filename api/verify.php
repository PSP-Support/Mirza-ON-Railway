<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/utils.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');

function respond_json(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!function_exists('getallheaders')) {
    function getallheaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) !== 'HTTP_') {
                continue;
            }

            $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$headerName] = $value;
        }

        return $headers;
    }
}

function normalize_init_value($value): string
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if ($value === null) {
        return '';
    }

    return (string) $value;
}

function validate_telegram_init_data($rawData, string $botToken): array
{
    if (is_string($rawData)) {
        $rawData = trim($rawData);
        if ($rawData === '') {
            throw new InvalidArgumentException('Telegram init data is missing or invalid');
        }

        // Telegram signs the exact URL-decoded query values. Do not HTML-decode
        // the payload before verification, because that can mutate signed data.
        parse_str($rawData, $initData);
    } elseif (is_array($rawData)) {
        $initData = $rawData;
    } else {
        throw new InvalidArgumentException('Telegram init data is missing or invalid');
    }

    if (!is_array($initData) || $initData === []) {
        throw new InvalidArgumentException('Telegram init data payload is empty');
    }

    if (!isset($initData['hash'])) {
        throw new InvalidArgumentException('Telegram init data is missing required signature');
    }

    $receivedHash = (string) $initData['hash'];
    unset($initData['hash']);

    $dataCheckArray = [];
    foreach ($initData as $key => $value) {
        if ($value === null) {
            continue;
        }

        $stringValue = normalize_init_value($value);
        if ($stringValue === '') {
            continue;
        }

        $dataCheckArray[] = $key . '=' . $stringValue;
    }

    if ($dataCheckArray === []) {
        throw new InvalidArgumentException('Telegram init data payload is empty');
    }

    sort($dataCheckArray, SORT_STRING);
    $dataCheckString = implode("\n", $dataCheckArray);

    // Telegram WebApp: HMAC key is the bot token; message is the literal WebAppData.
    $secretKey = hash_hmac('sha256', 'WebAppData', $botToken, true);
    $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

    if (!hash_equals($calculatedHash, $receivedHash)) {
        // Safe diagnostics only: never log the bot token, initData, user ID, or hashes.
        $tokenFingerprint = substr(hash('sha256', $botToken), 0, 12);
        $botIdentity = 'unavailable';
        if ($botToken !== '' && function_exists('curl_init')) {
            $ch = curl_init('https://api.telegram.org/bot' . $botToken . '/getMe');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $identityResponse = curl_exec($ch);
            curl_close($ch);
            if (is_string($identityResponse)) {
                $identityData = json_decode($identityResponse, true);
                if (!empty($identityData['ok']) && !empty($identityData['result'])) {
                    $botIdentity = '@' . ($identityData['result']['username'] ?? 'unknown')
                        . ' (id ' . ($identityData['result']['id'] ?? 'unknown') . ')';
                } else {
                    $botIdentity = 'Telegram getMe rejected the configured token';
                }
            }
        }
        error_log('[Mirza verify] HMAC mismatch; token_fp=' . $tokenFingerprint
            . '; bot=' . $botIdentity
            . '; init_length=' . (is_string($rawData) ? strlen($rawData) : -1)
            . '; fields=' . implode(',', array_keys($initData))
            . '; received_hash_length=' . strlen($receivedHash));
        throw new RuntimeException('User verification failed');
    }

    $authDate = isset($initData['auth_date']) ? (int) $initData['auth_date'] : 0;
    if ($authDate <= 0) {
        throw new RuntimeException('Telegram init data is missing auth_date');
    }
    if (time() - $authDate > 86400) {
        throw new RuntimeException('Telegram init data has expired');
    }

    $userRaw = $initData['user'] ?? null;
    if (is_string($userRaw)) {
        $userData = json_decode($userRaw, true);
    } elseif (is_array($userRaw)) {
        $userData = $userRaw;
    } else {
        $userData = null;
    }

    if (!is_array($userData) || !isset($userData['id'])) {
        throw new RuntimeException('User data is missing or malformed in init data');
    }

    return $userData;
}

$rawInput = file_get_contents('php://input');

if ($rawInput === false) {
    respond_json(400, [
        'status' => false,
        'msg' => 'Failed to read request body',
        'token' => null,
    ]);
}

$rawInput = trim($rawInput);
$decodedJson = null;
if ($rawInput !== '') {
    $decodedJson = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $decodedJson = null;
    }
}

$headers = getallheaders();
$candidates = [];

$headerKeys = [
    'X-Telegram-Init-Data',
    'X-Telegram-Web-App-Init-Data',
    'Telegram-Init-Data',
];

foreach ($headerKeys as $headerKey) {
    $value = headerValue($headers, $headerKey);
    if ($value !== null && trim($value) !== '') {
        $candidates[] = trim($value);
    }
}

foreach ([
    $_POST['initData'] ?? null,
    $_POST['init_data'] ?? null,
    $_POST['initDataUnsafe'] ?? null,
    $_POST['init_data_unsafe'] ?? null,
] as $value) {
    if ($value === null) {
        continue;
    }

    if (is_string($value)) {
        $value = trim($value);
        if ($value === '') {
            continue;
        }
    }

    $candidates[] = $value;
}

if ($decodedJson !== null) {
    foreach ([
        $decodedJson,
        $decodedJson['initData'] ?? null,
        $decodedJson['init_data'] ?? null,
        $decodedJson['initDataUnsafe'] ?? null,
        $decodedJson['init_data_unsafe'] ?? null,
    ] as $value) {
        if ($value === null) {
            continue;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
        }

        $candidates[] = $value;
    }
} elseif ($rawInput !== '') {
    $candidates[] = $rawInput;
}

if ($candidates === []) {
    respond_json(400, [
        'status' => false,
        'msg' => 'Telegram init data is missing or invalid',
        'token' => null,
    ]);
}

$lastException = null;
$userData = null;

foreach ($candidates as $candidate) {
    try {
        $userData = validate_telegram_init_data($candidate, $APIKEY);
        break;
    } catch (InvalidArgumentException $exception) {
        $lastException = $exception;
        continue;
    } catch (RuntimeException $exception) {
        $lastException = $exception;
        continue;
    }
}

if ($userData === null) {
    $message = $lastException ? $lastException->getMessage() : 'Telegram init data is missing or invalid';
    $statusCode = $lastException instanceof RuntimeException ? 403 : 400;

    respond_json($statusCode, [
        'status' => false,
        'msg' => $message,
        'token' => null,
    ]);
}

$userId = $userData['id'];
$userRecord = select('user', '*', 'id', $userId, 'select');

if (!$userRecord) {
    respond_json(404, [
        'status' => false,
        'msg' => 'User not found',
        'token' => null,
    ]);
}

try {
    $randomString = bin2hex(random_bytes(20));
} catch (Exception $exception) {
    error_log('Failed to generate random token: ' . $exception->getMessage());
    respond_json(500, [
        'status' => false,
        'msg' => 'Failed to generate session token',
        'token' => null,
    ]);
}

update('user', 'token', $randomString, 'id', $userId);

respond_json(200, [
    'status' => true,
    'msg' => 'User verified',
    'token' => $randomString,
]);
