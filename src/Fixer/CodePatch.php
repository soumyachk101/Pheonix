<?php

declare(strict_types=1);

namespace Phoenix\Fixer;

/**
 * Value object representing a single code patch.
 */
final class CodePatch
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $oldCode,
        public readonly string $newCode,
    ) {}

    /**
     * Create from an associative array (e.g., parsed LLM response).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            file: $data['file'] ?? '',
            line: (int) ($data['line'] ?? 0),
            oldCode: $data['old'] ?? '',
            newCode: $data['new'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'line' => $this->line,
            'old' => $this->oldCode,
            'new' => $this->newCode,
        ];
    }

    /**
     * Validate that the patch has required fields.
     */
    public function isValid(): bool
    {
        return $this->file !== ''
            && $this->line > 0
            && $this->oldCode !== ''
            && $this->newCode !== ''
            && $this->oldCode !== $this->newCode;
    }
}
