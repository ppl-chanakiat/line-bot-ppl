<?php
declare(strict_types=1);

$_envFile = __DIR__ . '/../.env';
if (file_exists($_envFile)) {
    $lines = file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        if (getenv($k) === false) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
        }
    }
}

define('LINE_CHANNEL_ACCESS_TOKEN', getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: '');
define('LINE_CHANNEL_SECRET',       getenv('LINE_CHANNEL_SECRET')       ?: '');
define('GEMINI_API_KEY',            getenv('GEMINI_API_KEY')            ?: '');
define('SHEET_CSV_URL',             getenv('SHEET_CSV_URL')             ?: '');

define('DEFAULT_MESSAGE_TH', 'ขออภัยครับ เรื่องนี้ผมขอตรวจสอบข้อมูลเพิ่มเติมก่อนนะครับ เดี๋ยวเจ้าหน้าที่จะติดต่อกลับไปให้เร็วที่สุดครับ');
define('DEFAULT_MESSAGE_EN', "Sorry, I'll need to double-check this for you. Our staff will get back to you as soon as possible.");

define('SHEET_CACHE_TTL', 60);
define('GEMINI_TIMEOUT',  8);

function isThaiText(string $text): bool {
    return (bool) preg_match('/[\x{0E00}-\x{0E7F}]/u', $text);
}

function defaultMessage(string $userText): string {
    return isThaiText($userText) ? DEFAULT_MESSAGE_TH : DEFAULT_MESSAGE_EN;
}
