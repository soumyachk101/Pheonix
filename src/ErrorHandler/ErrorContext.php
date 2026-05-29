<?php

declare(strict_types=1);

namespace Phoenix\ErrorHandler;

/**
 * Captures rich error context including surrounding source code lines.
 */
final class ErrorContext
{
    private const CONTEXT_LINES = 10;

    /**
     * Build an ErrorReport from PHP error parameters.
     */
    public static function fromError(
        int $errno,
        string $errstr,
        string $errfile,
        int $errline,
    ): ErrorReport {
        return new ErrorReport(
            type: self::errorTypeToString($errno),
            message: $errstr,
            file: $errfile,
            line: $errline,
            stackTrace: self::captureStackTrace(),
            codeContext: self::extractCodeContext($errfile, $errline),
            timestamp: microtime(true),
        );
    }

    /**
     * Build an ErrorReport from an exception.
     */
    public static function fromException(\Throwable $e): ErrorReport
    {
        return new ErrorReport(
            type: get_class($e),
            message: $e->getMessage(),
            file: $e->getFile(),
            line: $e->getLine(),
            stackTrace: $e->getTraceAsString(),
            codeContext: self::extractCodeContext($e->getFile(), $e->getLine()),
            timestamp: microtime(true),
        );
    }

    /**
     * Extract surrounding source code lines around the error.
     *
     * @return array<int, string> Line number => code content
     */
    public static function extractCodeContext(string $file, int $centerLine, int $radius = self::CONTEXT_LINES): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $start = max(0, $centerLine - $radius - 1);
        $end = min(count($lines), $centerLine + $radius);

        $context = [];
        for ($i = $start; $i < $end; $i++) {
            $context[$i + 1] = $lines[$i]; // 1-indexed line numbers
        }

        return $context;
    }

    /**
     * Capture the current stack trace, skipping Phoenix internal frames.
     */
    private static function captureStackTrace(): string
    {
        $e = new \Exception();
        $trace = $e->getTraceAsString();

        // Remove the first frame (this method) and the ErrorContext frames
        $lines = explode("\n", $trace);
        $filtered = array_filter($lines, function (string $line) {
            return !str_contains($line, 'Phoenix\\ErrorHandler\\ErrorContext');
        });

        return implode("\n", $filtered);
    }

    /**
     * Convert PHP error constant to readable string.
     */
    private static function errorTypeToString(int $errno): string
    {
        return match ($errno) {
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => "E_UNKNOWN($errno)",
        };
    }
}
