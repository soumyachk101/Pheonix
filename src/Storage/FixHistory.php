<?php

declare(strict_types=1);

namespace Phoenix\Storage;

use Phoenix\Fixer\CodePatch;
use PDO;

/**
 * Audit trail for all patches applied and rolled back.
 */
final class FixHistory
{
    private PDO $db;

    public function __construct(string $dbPath)
    {
        $this->db = new PDO("sqlite:$dbPath");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initializeSchema();
    }

    private function initializeSchema(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS patch_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                file TEXT NOT NULL,
                line INTEGER,
                old_code TEXT,
                new_code TEXT,
                backup_path TEXT,
                action TEXT NOT NULL,
                created_at TEXT DEFAULT (datetime('now'))
            )
        ");
    }

    /**
     * Log a patch application.
     */
    public function log(CodePatch $patch, string $backupPath, string $action): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO patch_history (file, line, old_code, new_code, backup_path, action)
            VALUES (:file, :line, :old_code, :new_code, :backup_path, :action)
        ");

        $stmt->execute([
            ':file' => $patch->file,
            ':line' => $patch->line,
            ':old_code' => $patch->oldCode,
            ':new_code' => $patch->newCode,
            ':backup_path' => $backupPath,
            ':action' => $action,
        ]);
    }

    /**
     * Log a rollback action.
     */
    public function logRollback(string $file, string $backupPath): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO patch_history (file, backup_path, action)
            VALUES (:file, :backup_path, 'rolled_back')
        ");

        $stmt->execute([
            ':file' => $file,
            ':backup_path' => $backupPath,
        ]);
    }

    /**
     * Get recent history entries.
     *
     * @return array<int, array>
     */
    public function getRecent(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM patch_history
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
