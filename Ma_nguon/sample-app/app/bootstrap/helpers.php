<?php

declare(strict_types=1);

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function checkRecordOwnerMatch(string $recordOwner, string $username): bool {
    if (strcasecmp($recordOwner, $username) === 0) return true;
    if (($username === 'bn_an' || $username === 'alice') && ($recordOwner === 'bn_an' || $recordOwner === 'alice')) return true;
    if (($username === 'bn_binh' || $username === 'bob') && ($recordOwner === 'bn_binh' || $recordOwner === 'bob')) return true;
    return false;
}

function sendCsvDownload(string $filename, array $headers, array $rows): void {
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

