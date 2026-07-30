<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/sheet.php';
require_once __DIR__ . '/lib/gemini.php';
require_once __DIR__ . '/lib/line.php';

header('Content-Type: application/json');

// LINE always expects HTTP 200 — only return non-200 for auth failure
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$rawBody  = (string) file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';

$expected = base64_encode(hash_hmac('sha256', $rawBody, LINE_CHANNEL_SECRET, true));
if (!hash_equals($expected, $signature)) {
    error_log('[webhook] invalid signature');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$body   = json_decode($rawBody, true);
$events = $body['events'] ?? [];

foreach ($events as $event) {
    if ($event['type'] !== 'message' || $event['message']['type'] !== 'text') {
        continue;
    }

    $userMessage = $event['message']['text'];
    $replyToken  = $event['replyToken'];

    try {
        $faqCsv   = getFaqCsv();
        $replyMsg = askGemini($faqCsv, $userMessage);
    } catch (Exception $e) {
        error_log('[webhook] processing error: ' . $e->getMessage());
        $replyMsg = defaultMessage($userMessage);
    }

    replyText($replyToken, $replyMsg);
}

echo json_encode(['ok' => true]);
