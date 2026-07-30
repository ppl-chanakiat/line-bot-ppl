<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function getFaqCsv(): string {
    $cacheFile = sys_get_temp_dir() . '/line_bot_faq_' . md5(SHEET_CSV_URL) . '.json';
    $now = time();

    if (file_exists($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if ($cached && ($now - $cached['fetchedAt']) < SHEET_CACHE_TTL) {
            return $cached['csv'];
        }
    }

    $ch = curl_init(SHEET_CSV_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $csv       = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($csv === false || $curlError || $httpCode < 200 || $httpCode >= 300) {
        $errMsg = $curlError ?: "HTTP {$httpCode}";
        error_log("[sheet] fetch error: {$errMsg}");
        if (file_exists($cacheFile)) {
            $stale = json_decode((string) file_get_contents($cacheFile), true);
            error_log('[sheet] using stale cache');
            return $stale['csv'];
        }
        throw new Exception("Sheet fetch failed: {$errMsg}");
    }

    file_put_contents($cacheFile, json_encode(['csv' => $csv, 'fetchedAt' => $now]));
    return $csv;
}
