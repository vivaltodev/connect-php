<?php

declare(strict_types=1);

namespace Vivalto\Sms\DTO;

/**
 * A webhook endpoint registered on the project.
 */
final readonly class WebhookEndpoint
{
    /**
     * @param  list<string>  $events
     */
    public function __construct(
        public string $id,
        public string $url,
        public array $events,
        public bool $isActive,
        public ?string $description,
        public ?string $apiKeyId,
        public ?string $secret,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $events = Payload::list($data, 'events');

        return new self(
            id: Payload::string($data, 'id'),
            url: Payload::string($data, 'url'),
            events: array_values(array_map(
                static fn (mixed $event): string => (string) $event,
                $events,
            )),
            isActive: (bool) ($data['is_active'] ?? true),
            description: isset($data['description']) ? (string) $data['description'] : null,
            apiKeyId: isset($data['api_key_id']) ? (string) $data['api_key_id'] : null,
            secret: isset($data['secret']) ? (string) $data['secret'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }
}
