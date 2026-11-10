<?php

declare(strict_types=1);

namespace Vivalto\Connect\DTO;

/**
 * A rendered PDF or image returned by the API.
 */
final readonly class DocumentResult
{
    public function __construct(
        public string $contents,
        public string $mimeType,
        public int $status,
        public ?string $documentId = null,
        public ?int $durationMs = null,
    ) {}

    public static function fromRaw(RawResponse $response): self
    {
        $contentType = trim(explode(';', $response->contentType, 2)[0]);
        $duration = $response->header('X-Document-Duration-Ms');

        return new self(
            contents: $response->contents,
            mimeType: $contentType !== '' ? $contentType : 'application/octet-stream',
            status: $response->status,
            documentId: $response->header('X-Document-Id'),
            durationMs: is_numeric($duration) ? (int) $duration : null,
        );
    }

    public function isPdf(): bool
    {
        return str_starts_with(ltrim($this->contents), '%PDF');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function extension(): string
    {
        return match ($this->mimeType) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
