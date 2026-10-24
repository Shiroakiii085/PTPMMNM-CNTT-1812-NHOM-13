<?php

declare(strict_types=1);

/**
 * Lớp khử dữ liệu nhạy cảm (Phép thử 5 & Yêu cầu an toàn).
 * Đảm bảo mật khẩu, token, khóa bí mật không bao giờ lọt vào báo cáo, log hoặc CSDL kết quả.
 */
final class Redactor
{
    private const SENSITIVE_KEYS = [
        'authorization',
        'token',
        'password',
        'pgpassword',
        'secret',
        'credential',
        'cookie',
        'bearer',
        'api_key',
        'apikey',
        'access_token',
    ];

    /** @var list<string> Danh sách các giá trị bí mật động cần che mờ */
    private static array $registeredSecrets = [];

    /**
     * Đăng ký một giá trị bí mật (ví dụ token lấy từ môi trường) để luôn bị che mờ.
     */
    public static function registerSecret(?string $secret): void
    {
        if ($secret !== null) {
            $secret = trim($secret);
            if (strlen($secret) >= 4 && !in_array($secret, self::$registeredSecrets, true)) {
                self::$registeredSecrets[] = $secret;
            }
        }
    }

    /**
     * Đăng ký danh sách các biến môi trường nhạy cảm.
     */
    public static function registerEnvSecrets(array $envVarNames): void
    {
        foreach ($envVarNames as $name) {
            $val = getenv((string)$name);
            if ($val !== false && $val !== '') {
                self::registerSecret($val);
            }
        }
    }

    public static function clearRegisteredSecrets(): void
    {
        self::$registeredSecrets = [];
    }

    /**
     * Khử thông tin nhạy cảm trong chuỗi văn bản (log, URL, output, report).
     */
    public static function redactText(string $text): string
    {
        // 1. Che các chuỗi bí mật đã đăng ký trước
        foreach (self::$registeredSecrets as $secret) {
            $text = str_replace($secret, '[REDACTED]', $text);
        }

        // 2. Ẩn Bearer tokens (ví dụ: Bearer test_token_...)
        $text = (string)preg_replace('/(Bearer\s+)[A-Za-z0-9_\-\.\~]+/i', '$1[REDACTED]', $text);

        // 3. Ẩn Authorization: ...
        $text = (string)preg_replace('/(Authorization:\s*)[^\r\n]+/i', '$1[REDACTED]', $text);

        // 4. Ẩn thông tin nhạy cảm trong URL (ví dụ: http://user:pass@host/...)
        $text = (string)preg_replace('#([a-z]+://)([^:]+):([^@]+)@#i', '$1[REDACTED]:[REDACTED]@', $text);

        // 5. Ẩn các tham số dạng "password=...", "token=..." trong query string hoặc body
        $text = (string)preg_replace('/(?<=(?:password|token|secret|key|access_token|pgpassword)=)[^&\s"\']+/i', '[REDACTED]', $text);

        // 6. Ẩn JSON fields nhạy cảm: "token": "...", "password": "..."
        $text = (string)preg_replace('/"((?:password|token|secret|pgpassword|api_key|access_token))"\s*:\s*"[^"]*"/i', '"$1": "[REDACTED]"', $text);

        return $text;
    }

    /**
     * Khử thông tin nhạy cảm trong mảng headers.
     */
    public static function redactHeaders(array $headers): array
    {
        $clean = [];
        foreach ($headers as $k => $v) {
            $keyLower = strtolower((string)$k);
            if (in_array($keyLower, ['authorization', 'cookie', 'set-cookie'], true)) {
                $clean[$k] = '[REDACTED]';
            } elseif (is_string($v) && stripos($v, 'bearer ') !== false) {
                $clean[$k] = '[REDACTED]';
            } else {
                $clean[$k] = is_string($v) ? self::redactText($v) : $v;
            }
        }
        return $clean;
    }

    /**
     * Khử đệ quy trong mảng dữ liệu trước khi log hoặc xuất báo cáo.
     */
    public static function redactArray(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $keyLower = strtolower((string)$key);
            $isSensitive = false;
            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains($keyLower, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive && !is_array($value)) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $clean[$key] = self::redactArray($value);
            } elseif (is_string($value)) {
                $clean[$key] = self::redactText($value);
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }
}
