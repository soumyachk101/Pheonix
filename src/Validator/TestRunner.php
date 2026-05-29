<?php

declare(strict_types=1);

namespace Phoenix\Validator;

/**
 * Runs test suites inside a Docker container.
 */
final class TestRunner
{
    /**
     * Run PHPUnit tests in a Docker container.
     *
     * @return array{passed: bool, output: string, tests_run: int, failures: int}
     */
    public static function runPhpUnit(string $projectDir, string $dockerImage = 'php:8.3-cli'): array
    {
        $cmd = sprintf(
            'docker run --rm -v %s:/app -w /app %s vendor/bin/phpunit --no-coverage 2>&1',
            escapeshellarg($projectDir),
            escapeshellarg($dockerImage)
        );

        $output = '';
        $exitCode = 0;

        exec($cmd, $outputLines, $exitCode);
        $output = implode("\n", $outputLines);

        // Parse PHPUnit output for test counts
        $testsRun = 0;
        $failures = 0;

        if (preg_match('/(\d+) test/', $output, $m)) {
            $testsRun = (int) $m[1];
        }
        if (preg_match('/(\d+) failure/', $output, $m)) {
            $failures = (int) $m[1];
        }

        return [
            'passed' => $exitCode === 0,
            'output' => $output,
            'tests_run' => $testsRun,
            'failures' => $failures,
        ];
    }

    /**
     * Run a PHP syntax check on a file.
     *
     * @return array{valid: bool, output: string}
     */
    public static function checkSyntax(string $filePath, string $dockerImage = 'php:8.3-cli'): array
    {
        $dir = dirname($filePath);
        $file = basename($filePath);

        $cmd = sprintf(
            'docker run --rm -v %s:/app -w /app %s php -l %s 2>&1',
            escapeshellarg($dir),
            escapeshellarg($dockerImage),
            escapeshellarg($file)
        );

        $output = '';
        $exitCode = 0;

        exec($cmd, $outputLines, $exitCode);
        $output = implode("\n", $outputLines);

        return [
            'valid' => $exitCode === 0,
            'output' => $output,
        ];
    }
}
