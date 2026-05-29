<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;
use Phoenix\ErrorHandler\ErrorContext;

final class ErrorContextTest extends TestCase
{
    public function testExtractCodeContext(): void
    {
        // Create a temp file with known content
        $tmpFile = tempnam(sys_get_temp_dir(), 'phoenix_test');
        $lines = [];
        for ($i = 1; $i <= 30; $i++) {
            $lines[] = "// Line $i";
        }
        file_put_contents($tmpFile, implode("\n", $lines));

        $context = ErrorContext::extractCodeContext($tmpFile, 15, 5);

        // Should have lines 10-20 (centered on 15, radius 5)
        $this->assertArrayHasKey(10, $context);
        $this->assertArrayHasKey(15, $context);
        $this->assertArrayHasKey(20, $context);
        $this->assertArrayNotHasKey(9, $context);
        $this->assertArrayNotHasKey(21, $context);

        // Verify content
        $this->assertSame('// Line 10', $context[10]);
        $this->assertSame('// Line 15', $context[15]);

        unlink($tmpFile);
    }

    public function testFromException(): void
    {
        $exception = new \RuntimeException('Test error', 42);

        $report = ErrorContext::fromException($exception);

        $this->assertSame('RuntimeException', $report->type);
        $this->assertSame('Test error', $report->message);
        $this->assertNotEmpty($report->file);
        $this->assertGreaterThan(0, $report->line);
        $this->assertNotEmpty($report->stackTrace);
        $this->assertGreaterThan(0, $report->timestamp);
    }

    public function testToPromptString(): void
    {
        $exception = new \RuntimeException('Test error');

        $report = ErrorContext::fromException($exception);
        $prompt = $report->toPromptString();

        $this->assertStringContainsString('RuntimeException', $prompt);
        $this->assertStringContainsString('Test error', $prompt);
        $this->assertStringContainsString('File:', $prompt);
        $this->assertStringContainsString('Stack Trace:', $prompt);
    }
}
