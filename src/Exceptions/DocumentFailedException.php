<?php

declare(strict_types=1);

namespace Vivalto\Connect\Exceptions;

/**
 * An asynchronous render finished with status "failed".
 *
 * The message is the reason stored by the API. For a busy renderer it says
 * how long the job waited for a free slot before it gave up.
 */
final class DocumentFailedException extends ApiException
{
    public function __construct(
        public readonly string $documentId,
        public readonly ?string $apiError = null,
    ) {
        parent::__construct(
            $apiError !== null && $apiError !== ''
                ? $apiError
                : 'The document could not be rendered.',
        );
    }
}
