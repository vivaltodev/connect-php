<?php

declare(strict_types=1);

namespace Vivalto\Sms\Resources;

use Vivalto\Sms\Client;
use Vivalto\Sms\DTO\Message;
use Vivalto\Sms\DTO\MessagePage;

/**
 * Send and read SMS messages.
 */
final class Messages
{
    public function __construct(private readonly Client $client) {}

    /**
     * Send one SMS.
     *
     * An Idempotency-Key is generated when none is supplied and is reused for
     * every retry of this call.
     *
     * @param  array<string, string|int|float|bool>  $metadata
     */
    public function send(
        string $to,
        string $from,
        string $text,
        ?string $class = null,
        ?string $reference = null,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): Message {
        $payload = array_filter([
            'to' => $to,
            'from' => $from,
            'text' => $text,
            'class' => $class,
            'reference' => $reference,
            'metadata' => $metadata === [] ? null : $metadata,
        ], static fn (mixed $value): bool => $value !== null);

        $response = $this->client->request(
            'POST',
            '/api/v1/messages',
            $payload,
            ['Idempotency-Key' => $idempotencyKey ?? bin2hex(random_bytes(16))],
        );

        return Message::fromArray($response);
    }

    /**
     * Get one message with its status events.
     */
    public function get(string $id): Message
    {
        return Message::fromArray(
            $this->client->request('GET', '/api/v1/messages/'.rawurlencode($id)),
        );
    }

    /**
     * List messages with optional filters and pagination.
     *
     * Supported filters are status, reference, recipient, created_after,
     * created_before, per_page and page.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): MessagePage
    {
        return MessagePage::fromArray(
            $this->client->request('GET', '/api/v1/messages', query: $filters),
        );
    }
}
