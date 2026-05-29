<?php

declare(strict_types=1);

namespace Phoenix\Dashboard;

use Phoenix\Storage\ErrorStore;
use Phoenix\Storage\FixHistory;
use Phoenix\Patcher\BackupManager;

/**
 * Simple web dashboard for viewing errors, fixes, and rollback.
 */
final class DashboardController
{
    public function __construct(
        private readonly ErrorStore $errorStore,
        private readonly FixHistory $history,
        private readonly BackupManager $backupManager,
    ) {}

    /**
     * Render the dashboard HTML.
     */
    public function render(): string
    {
        $stats = $this->errorStore->getStats();
        $recentErrors = $this->errorStore->getRecentErrors(20);
        $recentHistory = $this->history->getRecent(20);

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Phoenix Dashboard</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f0f23; color: #e0e0e0; padding: 2rem; }
                h1 { color: #ff6b35; margin-bottom: 1rem; font-size: 2rem; }
                h2 { color: #ff6b35; margin: 1.5rem 0 0.5rem; font-size: 1.2rem; }
                .stats { display: flex; gap: 1rem; margin-bottom: 2rem; }
                .stat-card { background: #1a1a2e; padding: 1.5rem; border-radius: 8px; flex: 1; text-align: center; }
                .stat-value { font-size: 2rem; font-weight: bold; color: #ff6b35; }
                .stat-label { color: #888; margin-top: 0.5rem; }
                table { width: 100%; border-collapse: collapse; background: #1a1a2e; border-radius: 8px; overflow: hidden; }
                th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #2a2a3e; }
                th { background: #16162a; color: #ff6b35; font-weight: 600; }
                tr:hover { background: #22223a; }
                .badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; }
                .badge-success { background: #1a472a; color: #4ade80; }
                .badge-fail { background: #471a1a; color: #f87171; }
                .badge-pending { background: #47441a; color: #fbbf24; }
                code { background: #2a2a3e; padding: 0.2rem 0.4rem; border-radius: 3px; font-size: 0.85rem; }
                .file-path { color: #8b8bff; word-break: break-all; }
            </style>
        </head>
        <body>
            <h1>Phoenix Dashboard</h1>

            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_errors'] ?></div>
                    <div class="stat-label">Total Errors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['fixes_applied'] ?></div>
                    <div class="stat-label">Fixes Applied</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['fix_rate'] ?>%</div>
                    <div class="stat-label">Fix Rate</div>
                </div>
            </div>

            <h2>Recent Errors</h2>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Message</th>
                        <th>File</th>
                        <th>Status</th>
                        <th>Confidence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentErrors as $error): ?>
                    <tr>
                        <td><?= htmlspecialchars($error['created_at'] ?? '') ?></td>
                        <td><code><?= htmlspecialchars($error['type'] ?? '') ?></code></td>
                        <td><?= htmlspecialchars(substr($error['message'] ?? '', 0, 80)) ?></td>
                        <td class="file-path"><?= htmlspecialchars(($error['file'] ?? '') . ':' . ($error['line'] ?? '')) ?></td>
                        <td>
                            <?php if (!empty($error['fix_applied'])): ?>
                                <span class="badge badge-success">Fixed</span>
                            <?php elseif (!empty($error['confidence'])): ?>
                                <span class="badge badge-pending">Suggested</span>
                            <?php else: ?>
                                <span class="badge badge-fail">Unresolved</span>
                            <?php endif; ?>
                        </td>
                        <td><?= isset($error['confidence']) ? round($error['confidence'] * 100) . '%' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentErrors)): ?>
                    <tr><td colspan="6" style="text-align:center; color:#888;">No errors recorded yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2>Patch History</h2>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Action</th>
                        <th>File</th>
                        <th>Line</th>
                        <th>Backup</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentHistory as $entry): ?>
                    <tr>
                        <td><?= htmlspecialchars($entry['created_at'] ?? '') ?></td>
                        <td>
                            <?php if ($entry['action'] === 'applied'): ?>
                                <span class="badge badge-success">Applied</span>
                            <?php else: ?>
                                <span class="badge badge-fail">Rolled Back</span>
                            <?php endif; ?>
                        </td>
                        <td class="file-path"><?= htmlspecialchars($entry['file'] ?? '') ?></td>
                        <td><?= $entry['line'] ?? '-' ?></td>
                        <td><?= htmlspecialchars(basename($entry['backup_path'] ?? '')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentHistory)): ?>
                    <tr><td colspan="5" style="text-align:center; color:#888;">No patches applied yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
