<?php

declare(strict_types=1);

namespace Phoenix\ErrorHandler;

/**
 * Value object representing a captured error with full context.
 */
final class ErrorReport
{
    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly string $file,
        public readonly int $line,
        public readonly string $stackTrace,
        public readonly array $codeContext,
        public readonly float $timestamp,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'stack_trace' => $this->stackTrace,
            'code_context' => $this->codeContext,
            'timestamp' => $this->timestamp,
        ];
    }

    public function toPromptString(): string
    {
        $contextLines = '';
        foreach ($this->codeContext as $lineNum => $code) {
            $marker = ((int)$lineNum === $this->line) ? '>>>' : '   ';
            $contextLines .= sprintf("%s %4d | %s\n", $marker, $lineNum, $code);
        }

        return <<<TEXT
        File: {$this->file}
        Line: {$this->line}
        Error Type: {$this->type}
        Message: {$this->message}

        Stack Trace:
        {$this->stackTrace}

        Surrounding Code:
        {$contextLines}
        TEXT;
    }
}
