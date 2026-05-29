<?php

declare(strict_types=1);

namespace Phoenix\ErrorHandler;

use Phoenix\Fixer\FixGenerator;
use Phoenix\Storage\ErrorStore;
use Phoenix\Patcher\HotPatcher;

/**
 * Main error handler that hooks into PHP's error/exception system.
 * Captures errors and triggers the self-healing pipeline.
 */
final class PhoenixHandler
{
    private ?FixGenerator $fixGenerator = null;
    private ?ErrorStore $errorStore = null;
    private ?HotPatcher $hotPatcher = null;
    private array $config;
    private bool $handling = false; // Prevent recursive error handling

    public function __construct(
        ?FixGenerator $fixGenerator = null,
        ?ErrorStore $errorStore = null,
        ?HotPatcher $hotPatcher = null,
        array $config = [],
    ) {
        $this->fixGenerator = $fixGenerator;
        $this->errorStore = $errorStore;
        $this->hotPatcher = $hotPatcher;
        $this->config = $config ?: require __DIR__ . '/../../config/phoenix.php';
    }

    /**
     * Register all PHP error/exception handlers.
     */
    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Custom error handler — catches warnings, notices, etc.
     *
     * @return bool true to prevent PHP's default error handler
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // Don't handle suppressed errors
        if (!(error_reporting() & $errno)) {
            return true;
        }

        $report = ErrorContext::fromError($errno, $errstr, $errfile, $errline);
        $this->processReport($report);

        return true; // Prevent default handler
    }

    /**
     * Exception handler — catches uncaught exceptions.
     */
    public function handleException(\Throwable $e): void
    {
        $report = ErrorContext::fromException($e);
        $this->processReport($report);
    }

    /**
     * Shutdown handler — catches fatal errors.
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE];
        if (in_array($error['type'], $fatalTypes, true)) {
            $report = ErrorContext::fromError(
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );
            $this->processReport($report);
        }
    }

    /**
     * Process an error report through the self-healing pipeline.
     */
    private function processReport(ErrorReport $report): void
    {
        // Prevent recursive handling
        if ($this->handling) {
            return;
        }
        $this->handling = true;

        try {
            // 1. Store the error
            $errorId = null;
            if ($this->errorStore) {
                $errorId = $this->errorStore->save($report);
            }

            // 2. Generate fix via LLM
            if ($this->fixGenerator && $errorId) {
                $fixResult = $this->fixGenerator->generateFix($report);

                // 3. Store fix result
                $this->errorStore->saveFix($errorId, $fixResult);

                // 4. Auto-apply if confidence is high enough
                if (
                    $this->config['patcher']['auto_apply']
                    && $fixResult['confidence'] >= $this->config['patcher']['min_confidence']
                    && !empty($fixResult['patches'])
                    && $this->hotPatcher
                ) {
                    foreach ($fixResult['patches'] as $patch) {
                        $this->hotPatcher->applyPatch($patch);
                    }
                    $this->errorStore->markFixApplied($errorId);
                }
            }
        } catch (\Throwable $e) {
            // Log but don't recurse
            error_log("[Phoenix] Failed to process error report: " . $e->getMessage());
        } finally {
            $this->handling = false;
        }
    }
}
