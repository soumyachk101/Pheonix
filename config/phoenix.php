<?php

declare(strict_types=1);

return [
    'llm' => [
        'backend' => $_ENV['PHOENIX_LLM_BACKEND'] ?? 'ollama',
        'ollama' => [
            'base_url' => $_ENV['PHOENIX_OLLAMA_BASE_URL'] ?? 'http://localhost:11434',
            'model' => $_ENV['PHOENIX_OLLAMA_MODEL'] ?? 'deepseek-coder-v2',
        ],
        'openrouter' => [
            'api_key' => $_ENV['OPENROUTER_API_KEY'] ?? '',
            'model' => $_ENV['PHOENIX_OPENROUTER_MODEL'] ?? 'deepseek/deepseek-coder',
        ],
    ],
    'docker' => [
        'image' => $_ENV['PHOENIX_DOCKER_IMAGE'] ?? 'php:8.3-cli',
        'timeout' => (int) ($_ENV['PHOENIX_DOCKER_TIMEOUT'] ?? 30),
        'test_command' => $_ENV['PHOENIX_TEST_COMMAND'] ?? 'vendor/bin/phpunit',
    ],
    'patcher' => [
        'backup_dir' => $_ENV['PHOENIX_BACKUP_DIR'] ?? __DIR__ . '/../backups',
        'auto_apply' => filter_var($_ENV['PHOENIX_AUTO_APPLY'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'min_confidence' => (float) ($_ENV['PHOENIX_MIN_CONFIDENCE'] ?? 0.8),
    ],
    'storage' => [
        'db_path' => $_ENV['PHOENIX_DB_PATH'] ?? __DIR__ . '/../data/phoenix.db',
    ],
];
