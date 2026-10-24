<?php

declare(strict_types=1);

require_once __DIR__ . '/Redactor.php';
require_once __DIR__ . '/TargetAllowlist.php';
require_once __DIR__ . '/Logger.php';

final class HttpClient
{
    private static ?TargetAllowlist $allowlist = null;

    public static function setAllowlist(TargetAllowlist $allowlist): void
    {
        self::$allowlist = $allowlist;
    }

    public static function getAllowlist(): TargetAllowlist
    {
        if (self::$allowlist === null) {
            self::$allowlist = TargetAllowlist::fromConfig();
        }
        return self::$allowlist;
    }

    /**
     * Kiểm tra tính hợp lệ của host và cổng theo whitelist (Yêu cầu an toàn mục 5).
     */
    public static function isHostAllowed(string $url): bool
    {
        return self::getAllowlist()->isAllowed($url);
    }

    /**
     * Phân loại kết quả kiểm thử (Cốt lõi Phép thử 3 & Yêu cầu 1).
     *
     * @param string $expected 'ALLOW' hoặc 'DENY'
     * @param int|null $httpStatus Mã HTTP thực tế
     * @param string|null $body Nội dung phản hồi HTTP
     * @param string|null $mustContain Chuỗi bắt buộc phải có trong body
     * @param string|null $mustNotContain Chuỗi cấm xuất hiện trong body (Leak check)
     * @param int|null $expectedStatus Mã HTTP cụ thể mong đợi (nếu có)
     * @param string|null $errorCode Mã lỗi mạng/hệ thống nếu có
     * @return array{outcome: string, detail: string, leak_detected: bool}
     */
    public static function classify(
        string $expected,
        ?int $httpStatus,
        ?string $body = null,
        ?string $mustContain = null,
        ?string $mustNotContain = null,
        ?int $expectedStatus = null,
        ?string $errorCode = null
    ): array {
        // Phép thử 3: Lỗi kết nối, timeout, host cấm -> luôn là ERROR, không bao giờ coi là từ chối đúng
        if ($errorCode !== null && $errorCode !== '') {
            return [
                'outcome' => 'ERROR',
                'detail' => 'Lỗi kết nối / hệ thống: ' . $errorCode,
                'leak_detected' => false,
            ];
        }

        if ($httpStatus === null || $httpStatus <= 0) {
            return [
                'outcome' => 'ERROR',
                'detail' => 'Không nhận được mã phản hồi HTTP',
                'leak_detected' => false,
            ];
        }

        // Lỗi máy chủ phía đối tượng kiểm thử (5xx)
        if ($httpStatus >= 500) {
            return [
                'outcome' => 'ERROR',
                'detail' => 'Máy chủ đối tượng trả về lỗi HTTP ' . $httpStatus,
                'leak_detected' => false,
            ];
        }

        $expected = strtoupper(trim($expected));
        $body = $body ?? '';

        // 1. Kiểm tra rò rỉ dữ liệu (Leak check - Phép thử 1)
        $leakDetected = false;
        if ($mustNotContain !== null && $mustNotContain !== '') {
            if (str_contains($body, $mustNotContain)) {
                $leakDetected = true;
                return [
                    'outcome' => 'FAIL',
                    'detail' => "Phát hiện rò rỉ dữ liệu cấm: '$mustNotContain' xuất hiện trong phản hồi",
                    'leak_detected' => true,
                ];
            }
        }

        // 2. Kiểm tra chuỗi bắt buộc phải có
        if ($mustContain !== null && $mustContain !== '') {
            if (!str_contains($body, $mustContain)) {
                return [
                    'outcome' => 'FAIL',
                    'detail' => "Nội dung phản hồi thiếu chuỗi bắt buộc: '$mustContain'",
                    'leak_detected' => false,
                ];
            }
        }

        // 3. Kiểm tra mã trạng thái cụ thể nếu được chỉ định
        if ($expectedStatus !== null && $expectedStatus > 0) {
            if ($httpStatus !== $expectedStatus) {
                return [
                    'outcome' => 'FAIL',
                    'detail' => "Mã HTTP thực tế $httpStatus khác mã mong đợi $expectedStatus",
                    'leak_detected' => false,
                ];
            }
        }

        // 4. Đối chiếu quyền ALLOW / DENY
        if ($expected === 'DENY') {
            // Mong đợi từ chối: HTTP 401, 403, 404
            if (in_array($httpStatus, [401, 403, 404], true)) {
                return [
                    'outcome' => 'PASS',
                    'detail' => "Được từ chối đúng quy định (HTTP $httpStatus)",
                    'leak_detected' => false,
                ];
            }
            return [
                'outcome' => 'FAIL',
                'detail' => "Kỳ vọng DENY (401/403/404) nhưng máy chủ trả HTTP $httpStatus",
                'leak_detected' => false,
            ];
        }

        if ($expected === 'ALLOW') {
            // Mong đợi cho phép: HTTP 2xx
            if ($httpStatus >= 200 && $httpStatus < 300) {
                return [
                    'outcome' => 'PASS',
                    'detail' => "Được cấp quyền đúng quy định (HTTP $httpStatus)",
                    'leak_detected' => false,
                ];
            }
            return [
                'outcome' => 'FAIL',
                'detail' => "Kỳ vọng ALLOW (2xx) nhưng máy chủ trả HTTP $httpStatus",
                'leak_detected' => false,
            ];
        }

        return [
            'outcome' => 'ERROR',
            'detail' => 'Kỳ vọng không hợp lệ: ' . $expected,
            'leak_detected' => false,
        ];
    }

    /**
     * Gửi yêu cầu HTTP thật độc lập (không lưu cookie hay session chung).
     *
     * @param string $method GET, POST, PUT, DELETE
     * @param string $url URL đầy đủ (ví dụ: http://127.0.0.1:8205/api/notes/1)
     * @param string|null $token Bearer token (nếu có)
     * @param array|string|null $payload Dữ liệu gửi kèm
     * @param int $timeoutSeconds Timeout kết nối và phản hồi
     * @return array{status: int|null, body: string, duration_ms: int, error_code: string|null, bytes: int}
     */
    public static function request(
        string $method,
        string $url,
        ?string $token = null,
        array|string|null $payload = null,
        int $timeoutSeconds = 4
    ): array {
        $startTime = hrtime(true);

        // Kiểm tra whitelist đích
        $allowCheck = self::getAllowlist()->check($url);
        if ($allowCheck !== null) {
            $durationMs = (int)round((hrtime(true) - $startTime) / 1e6);
            Logger::warning("Truy cập đích bị chặn: $url [$allowCheck]");
            return [
                'status' => null,
                'body' => '',
                'duration_ms' => $durationMs,
                'error_code' => $allowCheck,
                'bytes' => 0,
            ];
        }

        $headers = [
            'Accept: application/json',
            'User-Agent: AccessTester/2.0 (FIT-DNU; SecLab)',
        ];

        if ($token !== null && trim($token) !== '') {
            $headers[] = 'Authorization: Bearer ' . trim($token);
            Redactor::registerSecret($token);
        }

        $bodyContent = '';
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $bodyContent = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        if (extension_loaded('curl')) {
            return self::requestViaCurl($method, $url, $headers, $bodyContent, $timeoutSeconds, $startTime);
        }

        return self::requestViaStream($method, $url, $headers, $bodyContent, $timeoutSeconds, $startTime);
    }

    private static function requestViaCurl(
        string $method,
        string $url,
        array $headers,
        string $bodyContent,
        int $timeoutSeconds,
        int|float $startTime
    ): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Bắt buộc: không tự động follow redirect
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds + 2);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $bodyContent !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyContent);
        }

        $response = curl_exec($ch);
        $durationMs = (int)round((hrtime(true) - $startTime) / 1e6);

        if ($response === false) {
            $errno = curl_errno($ch);
            curl_close($ch);
            $errorCode = ($errno === CURLE_OPERATION_TIMEDOUT) ? 'TIMEOUT' : 'CONNECT_FAILED';
            return [
                'status' => null,
                'body' => '',
                'duration_ms' => $durationMs,
                'error_code' => $errorCode,
                'bytes' => 0,
            ];
        }

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $bodyStr = is_string($response) ? $response : '';
        return [
            'status' => $status,
            'body' => $bodyStr,
            'duration_ms' => $durationMs,
            'error_code' => null,
            'bytes' => strlen($bodyStr),
        ];
    }

    private static function requestViaStream(
        string $method,
        string $url,
        array $headers,
        string $bodyContent,
        int $timeoutSeconds,
        int|float $startTime
    ): array {
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $bodyContent,
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'follow_location' => 0,
            ]
        ]);

        $stream = @fopen($url, 'r', false, $context);
        $durationMs = (int)round((hrtime(true) - $startTime) / 1e6);

        if ($stream === false) {
            return [
                'status' => null,
                'body' => '',
                'duration_ms' => $durationMs,
                'error_code' => 'CONNECT_FAILED',
                'bytes' => 0,
            ];
        }

        $meta = stream_get_meta_data($stream);
        $body = (string)stream_get_contents($stream);
        fclose($stream);

        $status = null;
        if (isset($meta['wrapper_data']) && is_array($meta['wrapper_data'])) {
            foreach ($meta['wrapper_data'] as $headerLine) {
                if (preg_match('#^HTTP/\d\.\d\s+(\d+)#i', $headerLine, $matches)) {
                    $status = (int)$matches[1];
                    break;
                }
            }
        }

        if ($meta['timed_out'] ?? false) {
            return [
                'status' => null,
                'body' => '',
                'duration_ms' => $durationMs,
                'error_code' => 'TIMEOUT',
                'bytes' => 0,
            ];
        }

        return [
            'status' => $status,
            'body' => $body,
            'duration_ms' => $durationMs,
            'error_code' => ($status === null) ? 'CONNECT_FAILED' : null,
            'bytes' => strlen($body),
        ];
    }
}
