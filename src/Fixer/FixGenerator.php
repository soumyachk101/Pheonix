<?php

declare(strict_types=1);

namespace Phoenix\Fixer;

use Phoenix\ErrorHandler\ErrorReport;
use Phoenix\LLM\LLMInterface;
use Phoenix\LLM\PromptBuilder;

/**
 * Orchestrates the fix generation pipeline:
 * ErrorReport → PromptBuilder → LLM → PatchParser → CodePatch[]
 */
final class FixGenerator
{
    public function __construct(
        private readonly LLMInterface $llm,
    ) {}

    /**
     * Generate a fix for the given error report.
     *
     * @return array{root_cause: string, patches: CodePatch[], confidence: float, backend: string, prompt: string, raw_response: string}
     */
    public function generateFix(ErrorReport $report): array
    {
        // 1. Build the prompt
        $prompt = PromptBuilder::build($report);

        // 2. Call the LLM
        $llmResult = $this->llm->generateFix($prompt);

        // 3. Parse the response
        $parsed = PatchParser::parse($llmResult['raw_response']);

        // 4. Return complete result
        return [
            'root_cause' => $parsed['root_cause'],
            'patches' => $parsed['patches'],
            'confidence' => $parsed['confidence'],
            'backend' => $llmResult['backend'],
            'prompt' => $prompt,
            'raw_response' => $llmResult['raw_response'],
        ];
    }
}
