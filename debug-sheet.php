<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!SHEET_CSV_URL) {
    http_response_code(500);
    echo json_encode(['error' => 'SHEET_CSV_URL is not set']);
    exit;
}

$ch = curl_init(SHEET_CSV_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$body         = curl_exec($ch);
$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$contentType  = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlError    = curl_error($ch);
curl_close($ch);

if ($body === false || $curlError) {
    http_response_code(500);
    echo json_encode(['error' => $curlError]);
    exit;
}

echo json_encode([
    'status'      => $httpCode,
    'ok'          => $httpCode >= 200 && $httpCode < 300,
    'url'         => $effectiveUrl,
    'contentType' => $contentType,
    'bodyPreview' => mb_substr((string) $body, 0, 500),
    'bodyLength'  => strlen((string) $body),
], JSON_UNESCAPED_UNICODE);
