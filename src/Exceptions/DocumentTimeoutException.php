<?php

declare(strict_types=1);

namespace Vivalto\Connect\Exceptions;

/**
 * The document was still queued or rendering when the wait deadline passed.
 *
 * The document keeps rendering on the API side. Poll it again with
 * documents()->get(), or wait for the document webhook.
 */
final class DocumentTimeoutException extends ApiException
{
    public function __construct(
        public readonly string $documentId,
        public readonly int $timeoutSeconds,
        public readonly ?string $documentStatus = null,
    ) {
        parent::__construct(sprintf(
            'Document %s was still %s after %d seconds.',
            $documentId,
            $documentStatus !== null && $documentStatus !== '' ? $documentStatus : 'processing',
            $timeoutSeconds,
        ));
    }
}
