<?php

declare(strict_types=1);

namespace Phoenix\Patcher;

use Phoenix\Fixer\CodePatch;
use Phoenix\Validator\DockerValidator;
use Phoenix\Storage\FixHistory;

/**
 * Applies validated patches to production code with zero downtime.
 *
 * Flow:
 * 1. Backup the original file
 * 2. Apply the patch atomically (write to temp, then rename)
 * 3. Clear OPcache
 * 4. Log the change
 */
final class HotPatcher
{
    public function __construct(
        private readonly BackupManager $backupManager,
        private readonly DockerValidator $validator,
        private readonly ?FixHistory $history = null,
    ) {}

    /**
     * Apply a validated patch to production code.
     *
     * @return array{success: bool, backup_path: string, message: string}
     */
    public function applyPatch(CodePatch $patch): array
    {
        // 1. Validate the patch exists
        if (!is_file($patch->file)) {
            return [
                'success' => false,
                'backup_path' => '',
                'message' => "Target file not found: {$patch->file}",
            ];
        }

        // 2. Create backup
        $backupPath = $this->backupManager->backup($patch->file);

        // 3. Validate in Docker before applying
        $validation = $this->validator->validate($patch);

        if (!$validation['passed']) {
            return [
                'success' => false,
                'backup_path' => $backupPath,
                'message' => "Validation failed:\n" . $validation['output'],
            ];
        }

        // 4. Apply the patch atomically
        try {
            $this->applyAtomicPatch($patch);

            // 5. Clear OPcache if available
            $this->clearOpCache($patch->file);

            // 6. Log to history
            if ($this->history) {
                $this->history->log($patch, $backupPath, 'applied');
            }

            return [
                'success' => true,
                'backup_path' => $backupPath,
                'message' => "Patch applied successfully to {$patch->file}:{$patch->line}",
            ];
        } catch (\Throwable $e) {
            // Rollback on failure
            $this->backupManager->restore($backupPath, $patch->file);

            return [
                'success' => false,
                'backup_path' => $backupPath,
                'message' => "Patch failed, rolled back: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Apply a patch atomically using temp file + rename.
     */
    private function applyAtomicPatch(CodePatch $patch): void
    {
        $content = file_get_contents($patch->file);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file: {$patch->file}");
        }

        // Try exact string replacement
        if (str_contains($content, $patch->oldCode)) {
            $patched = str_replace($patch->oldCode, $patch->newCode, $content, $count);
            if ($count === 0) {
                throw new \RuntimeException("String replacement produced no changes");
            }
        } else {
            // Fallback: line-based replacement
            $lines = explode("\n", $content);
            $targetLine = $patch->line - 1;
            if (!isset($lines[$targetLine])) {
                throw new \RuntimeException("Line {$patch->line} not found in file");
            }
            $lines[$targetLine] = $patch->newCode;
            $patched = implode("\n", $lines);
        }

        // Atomic write: write to temp file, then rename
        $tempFile = $patch->file . '.phoenix_tmp_' . uniqid();
        file_put_contents($tempFile, $patched, LOCK_EX);

        if (!rename($tempFile, $patch->file)) {
            @unlink($tempFile);
            throw new \RuntimeException("Failed to atomically replace file");
        }
    }

    /**
     * Clear PHP OPcache for a specific file.
     */
    private function clearOpCache(string $filePath): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($filePath, true);
        }
    }

    /**
     * Rollback a patch using the backup.
     */
    public function rollback(string $originalFile, string $backupPath): bool
    {
        $result = $this->backupManager->restore($backupPath, $originalFile);

        if ($result) {
            $this->clearOpCache($originalFile);

            if ($this->history) {
                $this->history->logRollback($originalFile, $backupPath);
            }
        }

        return $result;
    }
}
