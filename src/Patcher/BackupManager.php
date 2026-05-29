<?php

declare(strict_types=1);

namespace Phoenix\Patcher;

/**
 * Manages file backups before patching.
 * Creates timestamped backups and supports rollback.
 */
final class BackupManager
{
    private string $backupDir;

    public function __construct(string $backupDir)
    {
        $this->backupDir = rtrim($backupDir, '/');

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Create a backup of a file before patching.
     *
     * @return string Path to the backup file
     */
    public function backup(string $filePath): string
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $timestamp = date('Y-m-d_H-i-s');
        $filename = basename($filePath);
        $hash = substr(md5($filePath), 0, 8);
        $backupName = "{$filename}.{$timestamp}.{$hash}.bak";
        $backupPath = $this->backupDir . '/' . $backupName;

        if (!copy($filePath, $backupPath)) {
            throw new \RuntimeException("Failed to create backup: {$backupPath}");
        }

        return $backupPath;
    }

    /**
     * Restore a file from its backup.
     */
    public function restore(string $backupPath, string $originalPath): bool
    {
        if (!is_file($backupPath)) {
            throw new \RuntimeException("Backup not found: {$backupPath}");
        }

        return copy($backupPath, $originalPath);
    }

    /**
     * List all backups for a specific file.
     *
     * @return array<int, array{path: string, timestamp: string}>
     */
    public function listBackups(string $filePath): array
    {
        $filename = basename($filePath);
        $backups = [];

        foreach (glob($this->backupDir . "/{$filename}.*.bak") as $backupFile) {
            // Extract timestamp from filename
            if (preg_match('/\.(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\./', $backupFile, $m)) {
                $backups[] = [
                    'path' => $backupFile,
                    'timestamp' => $m[1],
                ];
            }
        }

        // Sort newest first
        usort($backups, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        return $backups;
    }

    /**
     * Get the most recent backup for a file.
     */
    public function getLatestBackup(string $filePath): ?string
    {
        $backups = $this->listBackups($filePath);
        return $backups[0]['path'] ?? null;
    }
}
