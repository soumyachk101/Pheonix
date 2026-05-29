<?php

declare(strict_types=1);

namespace Phoenix;

use Dotenv\Dotenv;
use Phoenix\ErrorHandler\PhoenixHandler;
use Phoenix\Fixer\FixGenerator;
use Phoenix\LLM\LLMInterface;
use Phoenix\LLM\OllamaClient;
use Phoenix\LLM\OpenRouterClient;
use Phoenix\Patcher\BackupManager;
use Phoenix\Patcher\HotPatcher;
use Phoenix\Storage\ErrorStore;
use Phoenix\Storage\FixHistory;
use Phoenix\Validator\DockerValidator;

/**
 * Initialize Phoenix with a single function call.
 *
 * Usage:
 *   require_once 'vendor/autoload.php';
 *   Phoenix\init();  // That's it — self-healing is active
 */
function init(array $overrides = []): PhoenixHandler
{
    // Load .env if present
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();

    $config = array_replace_recursive(
        require __DIR__ . '/../config/phoenix.php',
        $overrides
    );

    // LLM client
    $llm = createLLMClient($config['llm']);

    // Storage
    $errorStore = new ErrorStore($config['storage']['db_path']);
    $history = new FixHistory($config['storage']['db_path']);

    // Validator & Patcher
    $validator = new DockerValidator(
        dockerImage: $config['docker']['image'],
        timeout: $config['docker']['timeout'],
        testCommand: $config['docker']['test_command'],
    );
    $backupManager = new BackupManager($config['patcher']['backup_dir']);
    $hotPatcher = new HotPatcher($backupManager, $validator, $history);

    // Fix generator
    $fixGenerator = new FixGenerator($llm);

    // Register handler
    $handler = new PhoenixHandler($fixGenerator, $errorStore, $hotPatcher, $config);
    $handler->register();

    return $handler;
}

/**
 * Create the appropriate LLM client based on config.
 */
function createLLMClient(array $llmConfig): LLMInterface
{
    return match ($llmConfig['backend']) {
        'openrouter' => new OpenRouterClient(
            apiKey: $llmConfig['openrouter']['api_key'],
            model: $llmConfig['openrouter']['model'],
        ),
        default => new OllamaClient(
            baseUrl: $llmConfig['ollama']['base_url'],
            model: $llmConfig['ollama']['model'],
        ),
    };
}
