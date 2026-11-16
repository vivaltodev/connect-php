<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * The result of GET /api/v1/health.
 *
 * A degraded API answers 503 with the same body, so health() returns this
 * object instead of throwing. The status is one of ok, degraded or failed.
 */
final readonly class Health
{
    /**
     * @param  array<string, HealthCheck>  $checks
     */
    public function __construct(
        public string $status,
        public ?string $checkedAt,
        public array $checks,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $checks = [];

        if (isset($data['checks']) && is_array($data['checks'])) {
            foreach ($data['checks'] as $name => $check) {
                if (is_array($check)) {
                    $checks[(string) $name] = HealthCheck::fromArray((string) $name, $check);
                }
            }
        }

        return new self(
            status: Payload::string($data, 'status'),
            checkedAt: isset($data['checked_at']) ? (string) $data['checked_at'] : null,
            checks: $checks,
        );
    }

    public function isHealthy(): bool
    {
        return $this->status === 'ok';
    }

    public function check(string $name): ?HealthCheck
    {
        return $this->checks[$name] ?? null;
    }

    /**
     * Every check that did not report ok.
     *
     * @return list<HealthCheck>
     */
    public function failedChecks(): array
    {
        return array_values(array_filter(
            $this->checks,
            static fn (HealthCheck $check): bool => ! $check->isOk(),
        ));
    }
}
