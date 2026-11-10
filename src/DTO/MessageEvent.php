<?php

declare(strict_types=1);

namespace Vivalto\Sms\DTO;

/**
 * One status event in the life of a message.
 */
final readonly class MessageEvent
{
    public function __construct(
        public string $id,
        public string $type,
        public string $status,
        public string $occurredAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            occurredAt: (string) ($data['occurred_at'] ?? ''),
        );
    }
}
