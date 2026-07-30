<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function replyText(string $replyToken, string $text): void {
    $payload = json_encode([
        'replyToken' => $replyToken,
        'messages'   => [['type' => 'text', 'text' => $text]],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.line.me/v2/bot/message/reply');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN,
        ],
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $curlError = curl_error($ch);
    curl_exec($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('[line] replyMessage failed: ' . $curlError);
    }
}
