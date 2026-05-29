<?php

declare(strict_types=1);

namespace Phoenix\Storage;

use Phoenix\ErrorHandler\ErrorReport;
use PDO;
use PDOException;

/**
 * SQLite storage for errors and fix results.
 */
final class ErrorStore
{
    private PDO $db;

    public function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->db = new PDO("sqlite:$dbPath");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initializeSchema();
    }

    private function initializeSchema(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS errors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp REAL NOT NULL,
                type TEXT NOT NULL,
                message TEXT NOT NULL,
                file TEXT NOT NULL,
                line INTEGER NOT NULL,
                stack_trace TEXT,
                code_context TEXT,
                fix_applied INTEGER DEFAULT 0,
                created_at TEXT DEFAULT (datetime('now'))
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS fixes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                error_id INTEGER NOT NULL,
                llm_backend TEXT,
                prompt TEXT,
                response TEXT,
                patches_json TEXT,
                confidence REAL,
                root_cause TEXT,
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (error_id) REFERENCES errors(id)
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS patches_applied (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                fix_id INTEGER NOT NULL,
                file TEXT NOT NULL,
                backup_path TEXT,
                applied_at TEXT DEFAULT (datetime('now')),
                status TEXT DEFAULT 'applied',
                FOREIGN KEY (fix_id) REFERENCES fixes(id)
            )
        ");
    }

    /**
     * Save an error report and return its ID.
     */
    public function save(ErrorReport $report): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO errors (timestamp, type, message, file, line, stack_trace, code_context)
            VALUES (:timestamp, :type, :message, :file, :line, :stack_trace, :code_context)
        ");

        $stmt->execute([
            ':timestamp' => $report->timestamp,
            ':type' => $report->type,
            ':message' => $report->message,
            ':file' => $report->file,
            ':line' => $report->line,
            ':stack_trace' => $report->stackTrace,
            ':code_context' => json_encode($report->codeContext),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Save a fix result for an error.
     */
    public function saveFix(int $errorId, array $fixResult): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO fixes (error_id, llm_backend, prompt, response, patches_json, confidence, root_cause)
            VALUES (:error_id, :llm_backend, :prompt, :response, :patches_json, :confidence, :root_cause)
        ");

        $stmt->execute([
            ':error_id' => $errorId,
            ':llm_backend' => $fixResult['backend'] ?? 'unknown',
            ':prompt' => $fixResult['prompt'] ?? '',
            ':response' => $fixResult['raw_response'] ?? '',
            ':patches_json' => json_encode($fixResult['patches'] ?? []),
            ':confidence' => $fixResult['confidence'] ?? 0.0,
            ':root_cause' => $fixResult['root_cause'] ?? '',
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Mark an error as having a fix applied.
     */
    public function markFixApplied(int $errorId): void
    {
        $stmt = $this->db->prepare("UPDATE errors SET fix_applied = 1 WHERE id = :id");
        $stmt->execute([':id' => $errorId]);
    }

    /**
     * Get recent errors with their fixes.
     *
     * @return array<int, array>
     */
    public function getRecentErrors(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, f.confidence, f.root_cause, f.patches_json, f.llm_backend
            FROM errors e
            LEFT JOIN fixes f ON f.error_id = e.id
            ORDER BY e.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get error statistics.
     */
    public function getStats(): array
    {
        $total = $this->db->query("SELECT COUNT(*) FROM errors")->fetchColumn();
        $fixed = $this->db->query("SELECT COUNT(*) FROM errors WHERE fix_applied = 1")->fetchColumn();

        return [
            'total_errors' => (int) $total,
            'fixes_applied' => (int) $fixed,
            'fix_rate' => $total > 0 ? round($fixed / $total * 100, 1) : 0,
        ];
    }
}
