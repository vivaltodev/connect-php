<?php

declare(strict_types=1);

namespace Vivalto\Sms\DTO;

/**
 * An SMS message as returned by the API.
 */
final readonly class Message
{
    /**
     * @param  array{code: string, message: string|null}|null  $error
     * @param  list<MessageEvent>  $events
     */
    public function __construct(
        public string $id,
        public ?string $reference,
        public string $status,
        public string $to,
        public string $from,
        public string $class,
        public ?int $parts,
        public ?Price $price,
        public ?string $createdAt,
        public ?string $sentAt,
        public ?string $deliveredAt,
        public ?array $error,
        public array $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $price = $data['price'] ?? null;
        $error = $data['error'] ?? null;

        return new self(
            id: Payload::string($data, 'id'),
            reference: isset($data['reference']) ? (string) $data['reference'] : null,
            status: Payload::string($data, 'status'),
            to: Payload::string($data, 'to'),
            from: (string) ($data['from'] ?? ''),
            class: (string) ($data['class'] ?? 'standard'),
            parts: isset($data['parts']) ? (int) $data['parts'] : null,
            price: is_array($price) ? Price::fromArray($price) : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            sentAt: isset($data['sent_at']) ? (string) $data['sent_at'] : null,
            deliveredAt: isset($data['delivered_at']) ? (string) $data['delivered_at'] : null,
            error: self::errorFrom($error),
            events: self::eventsFrom($data['events'] ?? null),
        );
    }

    /**
     * @return array{code: string, message: string|null}|null
     */
    private static function errorFrom(mixed $error): ?array
    {
        if (! is_array($error)) {
            return null;
        }

        return [
            'code' => (string) ($error['code'] ?? ''),
            'message' => isset($error['message']) ? (string) $error['message'] : null,
        ];
    }

    /**
     * @return list<MessageEvent>
     */
    private static function eventsFrom(mixed $events): array
    {
        if (! is_array($events)) {
            return [];
        }

        $result = [];

        foreach ($events as $event) {
            if (is_array($event)) {
                $result[] = MessageEvent::fromArray($event);
            }
        }

        return $result;
    }
}
