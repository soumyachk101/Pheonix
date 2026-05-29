<?php

declare(strict_types=1);

namespace Phoenix\Validator;

use Phoenix\Fixer\CodePatch;

/**
 * Validates code patches in an isolated Docker container.
 *
 * Flow:
 * 1. Copy project files to a temp directory
 * 2. Apply the patch to the temp copy
 * 3. Spin up a Docker container
 * 4. Run syntax check + tests
 * 5. Return pass/fail with output
 */
final class DockerValidator
{
    private string $dockerImage;
    private int $timeout;
    private string $testCommand;

    public function __construct(
        string $dockerImage = 'php:8.3-cli',
        int $timeout = 30,
        string $testCommand = 'vendor/bin/phpunit',
    ) {
        $this->dockerImage = $dockerImage;
        $this->timeout = $timeout;
        $this->testCommand = $testCommand;
    }

    /**
     * Validate a patch by testing it in an isolated Docker container.
     *
     * @return array{passed: bool, syntax_ok: bool, tests_ok: bool, output: string}
     */
    public function validate(CodePatch $patch): array
    {
        $tempDir = $this->createTempWorkspace();

        try {
            // Copy the target file to temp workspace
            $relativePath = basename($patch->file);
            $tempFile = $tempDir . '/' . $relativePath;
            copy($patch->file, $tempFile);

            // Apply the patch to the temp copy
            $this->applyPatchToTemp($tempFile, $patch);

            // Run syntax check
            $syntaxResult = $this->runInContainer($tempDir, "php -l /app/{$relativePath}");

            if (!$syntaxResult['success']) {
                return [
                    'passed' => false,
                    'syntax_ok' => false,
                    'tests_ok' => false,
                    'output' => "Syntax check failed:\n" . $syntaxResult['output'],
                ];
            }

            // Run tests if available
            $testsResult = ['success' => true, 'output' => 'No test suite configured'];
            if ($this->testCommand !== '') {
                $testsResult = $this->runInContainer($tempDir, $this->testCommand);
            }

            return [
                'passed' => $syntaxResult['success'] && $testsResult['success'],
                'syntax_ok' => $syntaxResult['success'],
                'tests_ok' => $testsResult['success'],
                'output' => "Syntax:\n{$syntaxResult['output']}\n\nTests:\n{$testsResult['output']}",
            ];
        } finally {
            // Cleanup temp directory
            $this->removeTempWorkspace($tempDir);
        }
    }

    /**
     * Apply a patch to a temporary file.
     */
    private function applyPatchToTemp(string $tempFile, CodePatch $patch): void
    {
        $content = file_get_contents($tempFile);
        if ($content === false) {
            throw new \RuntimeException("Cannot read temp file: {$tempFile}");
        }

        // Try exact string replacement first
        if (str_contains($content, $patch->oldCode)) {
            $patched = str_replace($patch->oldCode, $patch->newCode, $content, $count);
            if ($count > 0) {
                file_put_contents($tempFile, $patched);
                return;
            }
        }

        // Fallback: line-based replacement
        $lines = explode("\n", $content);
        $targetLine = $patch->line - 1; // 0-indexed
        if (isset($lines[$targetLine])) {
            $lines[$targetLine] = $patch->newCode;
            file_put_contents($tempFile, implode("\n", $lines));
        }
    }

    /**
     * Run a command inside a Docker container.
     *
     * @return array{success: bool, output: string}
     */
    private function runInContainer(string $mountDir, string $command): array
    {
        $cmd = sprintf(
            'docker run --rm -v %s:/app -w /app %s %s 2>&1',
            escapeshellarg($mountDir),
            escapeshellarg($this->dockerImage),
            $command
        );

        $output = '';
        $exitCode = 0;

        exec($cmd, $outputLines, $exitCode);
        $output = implode("\n", $outputLines);

        return [
            'success' => $exitCode === 0,
            'output' => $output,
        ];
    }

    /**
     * Create a temporary workspace directory.
     */
    private function createTempWorkspace(): string
    {
        $tempDir = sys_get_temp_dir() . '/phoenix_validate_' . uniqid();
        mkdir($tempDir, 0755, true);
        return $tempDir;
    }

    /**
     * Remove a temporary workspace directory.
     */
    private function removeTempWorkspace(string $dir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
