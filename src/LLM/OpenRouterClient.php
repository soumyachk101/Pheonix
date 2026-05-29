<?php

declare(strict_types=1);

namespace Phoenix\LLM;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * OpenRouter cloud LLM client.
 * Connects to https://openrouter.ai/api/v1/chat/completions
 */
final class OpenRouterClient implements LLMInterface
{
    private Client $client;
    private string $model;

    public function __construct(
        string $apiKey,
        string $model = 'deepseek/deepseek-coder',
    ) {
        $this->client = new Client([
            'base_uri' => 'https://openrouter.ai/api/v1/',
            'timeout' => 120,
            'headers' => [
                'Authorization' => "Bearer $apiKey",
                'HTTP-Referer' => 'https://phoenix-self-healing.local',
                'X-Title' => 'Phoenix Self-Healing',
            ],
        ]);
        $this->model = $model;
    }

    public function generateFix(string $prompt): array
    {
        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are Phoenix, a self-healing code engine. You analyze PHP errors and return structured JSON patches. Always respond with valid JSON only.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $content = $body['choices'][0]['message']['content'] ?? '';

            return [
                'raw_response' => $content,
                'backend' => 'openrouter',
            ];
        } catch (GuzzleException $e) {
            return [
                'raw_response' => json_encode(['error' => $e->getMessage()]),
                'backend' => 'openrouter',
            ];
        }
    }

    public function streamFix(string $prompt): \Generator
    {
        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are Phoenix, a self-healing code engine. You analyze PHP errors and return structured JSON patches. Always respond with valid JSON only.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.1,
                    'stream' => true,
                ],
                'stream' => true,
            ]);

            $body = $response->getBody();
            while (!$body->eof()) {
                $line = $this->readLine($body);
                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6);
                if ($data === '[DONE]') {
                    break;
                }

                $chunk = json_decode($data, true);
                $delta = $chunk['choices'][0]['delta']['content'] ?? '';
                if ($delta !== '') {
                    yield $delta;
                }
            }
        } catch (GuzzleException $e) {
            yield "[error] OpenRouter request failed: " . $e->getMessage();
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
