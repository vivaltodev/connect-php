<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * The search state of the project, built from cached tables.
 *
 * status is one of active, degraded, pending, failed or disabled. The object
 * keeps answering while Meilisearch itself is down, so healthy can be false
 * while status is active.
 */
final readonly class SearchStatus
{
    /**
     * @param  list<SearchIndex>  $indexes
     * @param  list<SearchCredential>  $credentials
     */
    public function __construct(
        public string $projectId,
        public string $projectName,
        public string $host,
        public string $namespace,
        public bool $sandbox,
        public string $status,
        public ?string $asOf,
        public ?bool $healthy,
        public int $documents,
        public ?int $documentLimit,
        public array $indexes,
        public array $credentials,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $project = Payload::assoc($data, 'project');
        $usage = Payload::assoc($data, 'usage');

        $indexes = [];

        foreach (Payload::list($data, 'indexes') as $index) {
            if (is_array($index)) {
                $indexes[] = SearchIndex::fromArray($index);
            }
        }

        $credentials = [];

        foreach (Payload::list($data, 'credentials') as $credential) {
            if (is_array($credential)) {
                $credentials[] = SearchCredential::fromArray($credential);
            }
        }

        return new self(
            projectId: Payload::string($project, 'id'),
            projectName: (string) ($project['name'] ?? ''),
            host: (string) ($data['host'] ?? ''),
            namespace: (string) ($data['namespace'] ?? ''),
            sandbox: (bool) ($data['sandbox'] ?? false),
            status: Payload::string($data, 'status'),
            asOf: isset($data['as_of']) ? (string) $data['as_of'] : null,
            healthy: isset($data['healthy']) ? (bool) $data['healthy'] : null,
            documents: (int) ($usage['documents'] ?? 0),
            documentLimit: isset($usage['document_limit']) ? (int) $usage['document_limit'] : null,
            indexes: $indexes,
            credentials: $credentials,
        );
    }

    public function isReady(): bool
    {
        return $this->status === 'active';
    }

    public function index(string $name): ?SearchIndex
    {
        foreach ($this->indexes as $index) {
            if ($index->name === $name) {
                return $index;
            }
        }

        return null;
    }
}
