<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!GEMINI_API_KEY) {
    http_response_code(500);
    echo json_encode(['error' => 'GEMINI_API_KEY is not set']);
    exit;
}

$url     = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
$payload = json_encode([
    'contents'         => [['role' => 'user', 'parts' => [['text' => 'ตอบว่า OK']]]],
    'generationConfig' => ['maxOutputTokens' => 50],
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $curlError) {
    http_response_code(500);
    echo json_encode(['error' => $curlError]);
    exit;
}

echo json_encode([
    'httpStatus' => $httpCode,
    'body'       => json_decode((string) $response, true),
], JSON_UNESCAPED_UNICODE);
