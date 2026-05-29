<?php

declare(strict_types=1);

namespace Phoenix\Fixer;

/**
 * Parses LLM JSON responses into CodePatch objects.
 */
final class PatchParser
{
    /**
     * Parse an LLM response into a structured fix result.
     *
     * @return array{root_cause: string, patches: CodePatch[], confidence: float}
     */
    public static function parse(string $rawResponse): array
    {
        // Try to extract JSON from the response (LLMs sometimes wrap in markdown)
        $json = self::extractJson($rawResponse);

        if ($json === null) {
            return self::emptyResult('Failed to parse LLM response as JSON');
        }

        $patches = [];
        foreach ($json['patches'] ?? [] as $patchData) {
            $patch = CodePatch::fromArray($patchData);
            if ($patch->isValid()) {
                $patches[] = $patch;
            }
        }

        return [
            'root_cause' => $json['root_cause'] ?? 'Unknown',
            'patches' => $patches,
            'confidence' => self::clampConfidence($json['confidence'] ?? 0.0),
        ];
    }

    /**
     * Extract JSON from an LLM response that may contain markdown or extra text.
     */
    private static function extractJson(string $response): ?array
    {
        // First try: direct JSON parse
        $decoded = json_decode($response, true);
        if ($decoded !== null && is_array($decoded)) {
            return $decoded;
        }

        // Second try: extract JSON from markdown code blocks
        if (preg_match('/```(?:json)?\s*\n?(\{[\s\S]*?\})\s*\n?```/', $response, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded !== null && is_array($decoded)) {
                return $decoded;
            }
        }

        // Third try: find first { ... } block
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded !== null && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Clamp confidence to 0.0-1.0 range.
     */
    private static function clampConfidence(float $confidence): float
    {
        return max(0.0, min(1.0, $confidence));
    }

    /**
     * Return an empty result structure.
     */
    private static function emptyResult(string $reason): array
    {
        return [
            'root_cause' => $reason,
            'patches' => [],
            'confidence' => 0.0,
        ];
    }
}
