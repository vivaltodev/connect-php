<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * Daily search usage for the project, oldest row first.
 */
final readonly class SearchUsage
{
    /**
     * @param  list<SearchUsageDay>  $rows
     */
    public function __construct(
        public array $rows,
        public string $from,
        public string $to,
        public int $days,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rows = [];

        foreach (Payload::list($data, 'data') as $row) {
            if (is_array($row)) {
                $rows[] = SearchUsageDay::fromArray($row);
            }
        }

        $meta = isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : [];

        return new self(
            rows: $rows,
            from: (string) ($meta['from'] ?? ''),
            to: (string) ($meta['to'] ?? ''),
            days: (int) ($meta['days'] ?? 0),
        );
    }

    public function latest(): ?SearchUsageDay
    {
        return $this->rows === [] ? null : $this->rows[array_key_last($this->rows)];
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }
}
