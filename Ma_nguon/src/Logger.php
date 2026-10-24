<?php

declare(strict_types=1);

require_once __DIR__ . '/Redactor.php';

final class Logger
{
    private static string $logFile = __DIR__ . '/../.local/runner.log';

    public static function setLogFile(string $path): void
    {
        self::$logFile = $path;
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $cleanMessage = Redactor::redactText($message);
        $cleanContext = Redactor::redactArray($context);

        $contextStr = !empty($cleanContext) ? ' ' . json_encode($cleanContext, JSON_UNESCAPED_UNICODE) : '';
        $line = sprintf("[%s] [%s] %s%s\n", $timestamp, strtoupper($level), $cleanMessage, $contextStr);

        @file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }
}
