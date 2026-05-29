<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;
use Phoenix\Fixer\PatchParser;

final class PatchParserTest extends TestCase
{
    public function testParsesValidJsonResponse(): void
    {
        $response = json_encode([
            'root_cause' => 'Undefined variable $foo',
            'patches' => [
                [
                    'file' => '/app/src/Example.php',
                    'line' => 10,
                    'old' => 'echo $foo;',
                    'new' => 'echo $foo ?? "";',
                ],
            ],
            'confidence' => 0.9,
        ]);

        $result = PatchParser::parse($response);

        $this->assertSame('Undefined variable $foo', $result['root_cause']);
        $this->assertCount(1, $result['patches']);
        $this->assertSame(0.9, $result['confidence']);
        $this->assertSame('/app/src/Example.php', $result['patches'][0]->file);
    }

    public function testParsesJsonWrappedInMarkdown(): void
    {
        $response = '```json
{
    "root_cause": "Type error",
    "patches": [],
    "confidence": 0.5
}
```';

        $result = PatchParser::parse($response);

        $this->assertSame('Type error', $result['root_cause']);
        $this->assertSame(0.5, $result['confidence']);
    }

    public function testHandlesInvalidJson(): void
    {
        $result = PatchParser::parse('not json at all');

        $this->assertSame('Failed to parse LLM response as JSON', $result['root_cause']);
        $this->assertEmpty($result['patches']);
        $this->assertSame(0.0, $result['confidence']);
    }

    public function testClampsConfidence(): void
    {
        $response = json_encode([
            'root_cause' => 'test',
            'patches' => [],
            'confidence' => 1.5,
        ]);

        $result = PatchParser::parse($response);
        $this->assertSame(1.0, $result['confidence']);

        $response = json_encode([
            'root_cause' => 'test',
            'patches' => [],
            'confidence' => -0.5,
        ]);

        $result = PatchParser::parse($response);
        $this->assertSame(0.0, $result['confidence']);
    }

    public function testFiltersInvalidPatches(): void
    {
        $response = json_encode([
            'root_cause' => 'test',
            'patches' => [
                ['file' => '', 'line' => 0, 'old' => '', 'new' => ''],  // invalid
                ['file' => '/app/test.php', 'line' => 5, 'old' => 'a', 'new' => 'b'],  // valid
            ],
            'confidence' => 0.7,
        ]);

        $result = PatchParser::parse($response);
        $this->assertCount(1, $result['patches']);
    }
}
