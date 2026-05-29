<?php

declare(strict_types=1);

namespace Phoenix\LLM;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Ollama local LLM client.
 * Connects to http://localhost:11434/api/generate
 *
 * Inspired by Ghost Coder's fixit/ollama_client.py
 */
final class OllamaClient implements LLMInterface
{
    private Client $client;
    private string $model;

    public function __construct(
        string $baseUrl = 'http://localhost:11434',
        string $model = 'deepseek-coder-v2',
    ) {
        $this->client = new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'timeout' => 120,
        ]);
        $this->model = $model;
    }

    public function generateFix(string $prompt): array
    {
        try {
            $response = $this->client->post('api/generate', [
                'json' => [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'format' => 'json',
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return [
                'raw_response' => $body['response'] ?? '',
                'backend' => 'ollama',
            ];
        } catch (GuzzleException $e) {
            return [
                'raw_response' => json_encode(['error' => $e->getMessage()]),
                'backend' => 'ollama',
            ];
        }
    }

    public function streamFix(string $prompt): \Generator
    {
        try {
            $response = $this->client->post('api/generate', [
                'json' => [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => true,
                ],
                'stream' => true,
            ]);

            $body = $response->getBody();
            while (!$body->eof()) {
                $line = $this->readLine($body);
                if ($line === '') {
                    continue;
                }

                $chunk = json_decode($line, true);
                if ($chunk === null) {
                    continue;
                }

                if (isset($chunk['response'])) {
                    yield $chunk['response'];
                }

                if (!empty($chunk['done'])) {
                    break;
                }
            }
        } catch (GuzzleException $e) {
            yield "[error] Ollama request failed: " . $e->getMessage();
        }
    }

    private function readLine($stream): string
    {
        $line = '';
        while (!$stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $line .= $char;
        }
        return trim($line);
    }
}
