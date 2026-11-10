<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * A rendered document as returned by the metadata endpoints.
 */
final readonly class Document
{
    public function __construct(
        public string $id,
        public string $type,
        public string $status,
        public ?string $mimeType,
        public ?int $bytes,
        public ?int $durationMs,
        public ?string $createdAt,
        public ?string $completedAt,
        public ?string $expiresAt,
        public ?string $error,
        public ?string $downloadUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $links = $data['links'] ?? null;
        $download = is_array($links) && isset($links['download']) && is_string($links['download'])
            ? $links['download']
            : null;

        return new self(
            id: Payload::string($data, 'id'),
            type: Payload::string($data, 'type'),
            status: Payload::string($data, 'status'),
            mimeType: isset($data['mime_type']) ? (string) $data['mime_type'] : null,
            bytes: isset($data['bytes']) ? (int) $data['bytes'] : null,
            durationMs: isset($data['duration_ms']) ? (int) $data['duration_ms'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            completedAt: isset($data['completed_at']) ? (string) $data['completed_at'] : null,
            expiresAt: isset($data['expires_at']) ? (string) $data['expires_at'] : null,
            error: isset($data['error']) ? (string) $data['error'] : null,
            downloadUrl: $download,
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
