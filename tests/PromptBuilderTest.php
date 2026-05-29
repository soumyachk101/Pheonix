<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;
use Phoenix\ErrorHandler\ErrorReport;
use Phoenix\LLM\PromptBuilder;

final class PromptBuilderTest extends TestCase
{
    public function testBuildContainsAllErrorInfo(): void
    {
        $report = new ErrorReport(
            type: 'TypeError',
            message: 'Argument #1 ($str) must be of type string, null given',
            file: '/app/src/Helper.php',
            line: 42,
            stackTrace: "#0 /app/src/Helper.php(42): strlen(NULL)\n#1 /app/index.php(10): Helper::process()",
            codeContext: [40 => 'function process($str) {', 41 => '  // process string', 42 => '  return strlen($str);', 43 => '}'],
            timestamp: microtime(true),
        );

        $prompt = PromptBuilder::build($report);

        $this->assertStringContainsString('TypeError', $prompt);
        $this->assertStringContainsString('Argument #1', $prompt);
        $this->assertStringContainsString('/app/src/Helper.php', $prompt);
        $this->assertStringContainsString('42', $prompt);
        $this->assertStringContainsString('strlen', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('confidence', $prompt);
    }

    public function testBuildIncludesCodeContext(): void
    {
        $report = new ErrorReport(
            type: 'Error',
            message: 'Test',
            file: '/test.php',
            line: 1,
            stackTrace: '',
            codeContext: [1 => '<?php', 2 => 'echo $undefined;'],
            timestamp: microtime(true),
        );

        $prompt = PromptBuilder::build($report);

        $this->assertStringContainsString('<?php', $prompt);
        $this->assertStringContainsString('echo $undefined', $prompt);
    }
}
