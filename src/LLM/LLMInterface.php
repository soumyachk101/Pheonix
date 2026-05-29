<?php

declare(strict_types=1);

namespace Phoenix\LLM;

/**
 * Contract for LLM backends (Ollama, OpenRouter, etc.)
 */
interface LLMInterface
{
    /**
     * Generate a fix suggestion from a prompt.
     *
     * @return array{raw_response: string, backend: string}
     */
    public function generateFix(string $prompt): array;

    /**
     * Stream fix tokens (for real-time dashboard updates).
     *
     * @return \Generator<string>
     */
    public function streamFix(string $prompt): \Generator;
}
