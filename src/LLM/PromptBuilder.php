<?php

declare(strict_types=1);

namespace Phoenix\LLM;

use Phoenix\ErrorHandler\ErrorReport;

/**
 * Builds structured prompts for the LLM fix engine.
 *
 * Inspired by Ghost Coder's fixit/prompt_builder.py
 */
final class PromptBuilder
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are Phoenix, a self-healing PHP code engine. You analyze PHP errors and generate precise code patches.

Rules:
- Return ONLY a valid JSON object, no markdown, no explanation outside JSON
- Include the exact file path, line number, old code (must match exactly), and new code
- Confidence score 0.0-1.0 (be honest — if you're guessing, use < 0.5)
- Patches must be minimal — change only what's needed to fix the error
- Preserve existing code style and formatting
- Never suggest deleting entire functions or classes
- If the error is environmental (missing extension, config issue), set confidence to 0.0
PROMPT;

    /**
     * Build a complete fix prompt from an error report.
     */
    public static function build(ErrorReport $report): string
    {
        $contextBlock = self::formatCodeContext($report->codeContext);
        $systemPrompt = self::SYSTEM_PROMPT;

        return <<<PROMPT
        [SYSTEM]
        {$systemPrompt}

        [ERROR CONTEXT]
        {$report->toPromptString()}

        [SURROUNDING CODE]
        {$contextBlock}

        [TASK]
        Analyze this PHP error and return a JSON fix:

        {
            "root_cause": "One sentence explanation of why this error occurred",
            "patches": [
                {
                    "file": "/absolute/path/to/file.php",
                    "line": 42,
                    "old": "exact code that needs to change (include enough context to be unique)",
                    "new": "the corrected code"
                }
            ],
            "confidence": 0.85
        }

        Return ONLY the JSON object.
        PROMPT;
    }

    /**
     * Format code context lines for the prompt.
     */
    private static function formatCodeContext(array $codeContext): string
    {
        if (empty($codeContext)) {
            return '(No source code available)';
        }

        $lines = [];
        foreach ($codeContext as $lineNum => $code) {
            $lines[] = sprintf('%4d | %s', $lineNum, $code);
        }

        return implode("\n", $lines);
    }
}
