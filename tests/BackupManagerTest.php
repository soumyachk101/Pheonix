<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;
use Phoenix\Patcher\BackupManager;

final class BackupManagerTest extends TestCase
{
    private string $tempDir;
    private string $backupDir;
    private BackupManager $manager;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phoenix_test_' . uniqid();
        $this->backupDir = $this->tempDir . '/backups';
        mkdir($this->tempDir, 0755, true);

        $this->manager = new BackupManager($this->backupDir);
    }

    protected function tearDown(): void
    {
        // Cleanup
        $this->removeDir($this->tempDir);
    }

    public function testBackupCreatesFile(): void
    {
        $testFile = $this->tempDir . '/test.php';
        file_put_contents($testFile, '<?php echo "hello";');

        $backupPath = $this->manager->backup($testFile);

        $this->assertFileExists($backupPath);
        $this->assertSame('<?php echo "hello";', file_get_contents($backupPath));
    }

    public function testRestoreFromBackup(): void
    {
        $testFile = $this->tempDir . '/test.php';
        file_put_contents($testFile, '<?php echo "original";');

        $backupPath = $this->manager->backup($testFile);

        // Modify the file
        file_put_contents($testFile, '<?php echo "modified";');

        // Restore
        $result = $this->manager->restore($backupPath, $testFile);

        $this->assertTrue($result);
        $this->assertSame('<?php echo "original";', file_get_contents($testFile));
    }

    public function testListBackups(): void
    {
        $testFile = $this->tempDir . '/test.php';
        file_put_contents($testFile, '<?php echo "v1";');

        $this->manager->backup($testFile);

        file_put_contents($testFile, '<?php echo "v2";');
        $this->manager->backup($testFile);

        $backups = $this->manager->listBackups($testFile);
        $this->assertCount(2, $backups);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

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
