<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function buildSystemInstruction(string $faqCsv): string {
    return <<<EOT
<role>
คุณคือพนักงานต้อนรับ (เพศชาย) ของ UCE Place ซึ่งเป็นอพาร์ตเมนต์ให้เช่ารายเดือน (สัญญาขั้นต่ำ 6 เดือน) คุณเป็นพนักงานที่มีประสบการณ์ คอยตอบคำถามผู้ที่สนใจเช่าห้อง
</role>

<constraints>
- ตอบโดยอ้างอิงข้อมูลใน <faq> เท่านั้น
- ห้ามแต่ง ห้ามเดา ราคา เวลา ที่ตั้ง หรือเงื่อนไขใดๆ ที่ไม่มีใน <faq>
- ตอบเป็นภาษาเดียวกับที่ลูกค้าพิมพ์มาเสมอ: ลูกค้าพิมพ์ไทยตอบไทย ลูกค้าพิมพ์อังกฤษตอบอังกฤษ
- ถ้าคำถามไม่มีข้อมูลตรงกับ <faq> ให้ตอบด้วยข้อความ default แบบเป๊ะๆ คำต่อคำ ห้ามเพิ่มห้ามลด โดยเลือกตามภาษาของลูกค้า:
  - ถ้าลูกค้าพิมพ์ไทย: "กดเมนูด้านล่างเพื่อดูรูป และข้อมูลเพิ่มเติมได้เลยครับ"
  - ถ้าลูกค้าพิมพ์อังกฤษ: "Tap the menu below to view photos and additional information."
- หากลูกค้าพิมพ์มามีคำเหล่านี้ (สวัสดี ขอบคุณ) อนุญาติให้ตอบได้เองแบบสั้นๆ ไม่ต้องการ Detail และกระชับ
- โทน: สุภาพ กระชับ ใจดี พร้อมให้บริการ
  - ตอบไทย: พูดแบบพนักงานชาย ลงท้ายประโยคด้วย "ครับ"
  - ตอบอังกฤษ: สุภาพ มืออาชีพ ไม่ต้องมีคำลงท้ายพิเศษ
- ห้ามใช้ emoji
- ความยาว 1–2 ประโยค
</constraints>

<output_format>
ตอบเป็นภาษาเดียวกับลูกค้า (ไทยหรืออังกฤษ) ไม่ใช้ markdown ไม่ใช้สัญลักษณ์ตกแต่ง ขึ้นต้นตอบเนื้อหาได้เลย
</output_format>

<faq>
{$faqCsv}
</faq>
EOT;
}

function askGemini(string $faqCsv, string $userMessage): string {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;

    $payload = json_encode([
        'system_instruction' => ['parts' => [['text' => buildSystemInstruction($faqCsv)]]],
        'contents'           => [['role' => 'user', 'parts' => [['text' => "<question>\n{$userMessage}\n</question>"]]]],
        'generationConfig'   => ['temperature' => 1.0, 'maxOutputTokens' => 1024],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => GEMINI_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError) {
        $isTimeout = str_contains($curlError, 'timed out');
        error_log('[gemini] ' . ($isTimeout ? 'timeout' : 'error') . ': ' . $curlError);
        return defaultMessage($userMessage);
    }

    $json        = json_decode((string) $response, true);
    $finishReason = $json['candidates'][0]['finishReason'] ?? null;
    $usage        = $json['usageMetadata'] ?? [];

    error_log('[gemini] ' . json_encode([
        'finishReason'         => $finishReason,
        'thoughtsTokenCount'   => $usage['thoughtsTokenCount'] ?? 0,
        'candidatesTokenCount' => $usage['candidatesTokenCount'] ?? 0,
    ]));

    if ($finishReason === 'MAX_TOKENS') {
        error_log('[gemini] MAX_TOKENS — falling back to default message');
        return defaultMessage($userMessage);
    }

    $text = trim($json['candidates'][0]['content']['parts'][0]['text'] ?? '');
    if (!$text) {
        error_log('[gemini] empty response — falling back to default message');
        return defaultMessage($userMessage);
    }

    return $text;
}
