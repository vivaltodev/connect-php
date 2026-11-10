<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * One webhook delivery attempt from the delivery log.
 */
final readonly class WebhookDelivery
{
    public function __construct(
        public string $id,
        public string $eventId,
        public string $eventType,
        public string $status,
        public int $attempts,
        public ?int $responseStatus,
        public ?string $nextAttemptAt,
        public ?string $deliveredAt,
        public ?string $lastError,
        public ?string $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            eventId: (string) ($data['event_id'] ?? ''),
            eventType: Payload::string($data, 'event_type'),
            status: Payload::string($data, 'status'),
            attempts: (int) ($data['attempts'] ?? 0),
            responseStatus: isset($data['response_status']) ? (int) $data['response_status'] : null,
            nextAttemptAt: isset($data['next_attempt_at']) ? (string) $data['next_attempt_at'] : null,
            deliveredAt: isset($data['delivered_at']) ? (string) $data['delivered_at'] : null,
            lastError: isset($data['last_error']) ? (string) $data['last_error'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
        );
    }
}
