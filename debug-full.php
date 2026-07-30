<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/config.php';

header('Content-Type: application/json; charset=utf-8');

$question = $_GET['q'] ?? 'ตอนนี้มีห้องว่างมั้ย';
$results  = ['question' => $question];

// Step 1: fetch sheet
if (!SHEET_CSV_URL) {
    http_response_code(500);
    echo json_encode(['error' => 'SHEET_CSV_URL not set']);
    exit;
}

$ch = curl_init(SHEET_CSV_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$faqCsv     = curl_exec($ch);
$sheetCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$sheetError = curl_error($ch);
curl_close($ch);

if ($faqCsv === false || $sheetError) {
    http_response_code(500);
    echo json_encode(array_merge($results, ['sheetError' => $sheetError]), JSON_UNESCAPED_UNICODE);
    exit;
}

$results['sheetStatus'] = $sheetCode;
$results['faqPreview']  = mb_substr((string) $faqCsv, 0, 300);

// Step 2: call Gemini
if (!GEMINI_API_KEY) {
    http_response_code(500);
    echo json_encode(array_merge($results, ['error' => 'GEMINI_API_KEY not set']), JSON_UNESCAPED_UNICODE);
    exit;
}

$systemInstruction = "ดูข้อมูลใน <faq> แล้วตอบคำถาม ถ้าไม่มีข้อมูลตอบว่า \"ไม่มีข้อมูล\"\n<faq>\n{$faqCsv}\n</faq>";
$payload = json_encode([
    'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
    'contents'           => [['role' => 'user', 'parts' => [['text' => $question]]]],
    'generationConfig'   => ['temperature' => 1.0, 'maxOutputTokens' => 256],
], JSON_UNESCAPED_UNICODE);

$url   = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
$start = microtime(true);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$geminiResponse = curl_exec($ch);
$geminiCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$geminiError    = curl_error($ch);
curl_close($ch);

$elapsed = (int) ((microtime(true) - $start) * 1000);

if ($geminiResponse === false || $geminiError) {
    $results['geminiError'] = $geminiError;
} else {
    $json = json_decode((string) $geminiResponse, true);
    $results['geminiHttpStatus'] = $geminiCode;
    $results['elapsedMs']        = $elapsed;
    $results['finishReason']     = $json['candidates'][0]['finishReason'] ?? null;
    $results['answer']           = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
    $results['geminiError']      = $json['error'] ?? null;
    $results['usage']            = $json['usageMetadata'] ?? null;
}

echo json_encode($results, JSON_UNESCAPED_UNICODE);
