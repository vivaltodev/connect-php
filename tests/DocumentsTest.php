<?php

use Vivalto\Connect\DTO\Document;
use Vivalto\Connect\DTO\DocumentPage;
use Vivalto\Connect\DTO\DocumentResult;
use Vivalto\Connect\Exceptions\ValidationException;
use Vivalto\Connect\Tests\Support\FakeHttpClient;

it('renders a pdf and returns the binary response', function (): void {
    $http = new FakeHttpClient(binaryResponse(
        200,
        '%PDF-1.4 fake',
        'application/pdf',
        [
            'X-Document-Id' => 'doc_abc123',
            'X-Document-Duration-Ms' => '120',
        ],
    ));
    $client = sdkClient($http);

    $result = $client->documents()->render(
        html: '<h1>Hei</h1>',
        options: [
            'format' => 'A4',
            'orientation' => 'portrait',
            'margins' => ['top' => 10, 'right' => 5, 'bottom' => 10, 'left' => 5],
        ],
    );

    expect($http->requests)->toHaveCount(1);

    $request = $http->requests[0];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://api.example.test/api/v1/documents')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer test-key')
        ->and($request->getHeaderLine('Accept'))->toBe('application/pdf, image/png, image/jpeg')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json');

    expect(json_decode((string) $request->getBody(), true))->toBe([
        'html' => '<h1>Hei</h1>',
        'format' => 'A4',
        'orientation' => 'portrait',
        'margins' => ['top' => 10, 'right' => 5, 'bottom' => 10, 'left' => 5],
    ]);

    expect($result)->toBeInstanceOf(DocumentResult::class)
        ->and($result->contents)->toBe('%PDF-1.4 fake')
        ->and($result->mimeType)->toBe('application/pdf')
        ->and($result->documentId)->toBe('doc_abc123')
        ->and($result->durationMs)->toBe(120)
        ->and($result->isPdf())->toBeTrue()
        ->and($result->isImage())->toBeFalse()
        ->and($result->extension())->toBe('pdf');
});

it('renders an image', function (): void {
    $http = new FakeHttpClient(binaryResponse(200, 'PNGDATA', 'image/png'));
    $client = sdkClient($http);

    $result = $client->documents()->render(
        html: '<h1>Hei</h1>',
        options: ['type' => 'png', 'full_page' => true],
    );

    expect($result->mimeType)->toBe('image/png')
        ->and($result->isPdf())->toBeFalse()
        ->and($result->isImage())->toBeTrue()
        ->and($result->extension())->toBe('png');
});

it('renders to a file', function (): void {
    $http = new FakeHttpClient(binaryResponse(200, '%PDF-1.4 fake', 'application/pdf'));
    $client = sdkClient($http);
    $path = sys_get_temp_dir().'/vivalto-connect-'.uniqid().'.pdf';

    try {
        $result = $client->documents()->renderToFile($path, '<h1>Hei</h1>');

        expect(file_get_contents($path))->toBe('%PDF-1.4 fake')
            ->and($result->isPdf())->toBeTrue();
    } finally {
        @unlink($path);
    }
});

it('creates an asynchronous render', function (): void {
    $http = new FakeHttpClient(jsonResponse(202, [
        'data' => documentPayload(['status' => 'queued', 'completed_at' => null, 'bytes' => null]),
    ]));
    $client = sdkClient($http);

    $document = $client->documents()->create(
        html: '<h1>Hei</h1>',
        options: ['format' => 'A5'],
    );

    $request = $http->requests[0];

    expect(json_decode((string) $request->getBody(), true))->toBe([
        'html' => '<h1>Hei</h1>',
        'async' => true,
        'format' => 'A5',
    ]);

    expect($document)->toBeInstanceOf(Document::class)
        ->and($document->id)->toBe('doc_abc123')
        ->and($document->status)->toBe('queued')
        ->and($document->isCompleted())->toBeFalse();
});

it('gets a document from the data envelope', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, ['data' => documentPayload()]));
    $client = sdkClient($http);

    $document = $client->documents()->get('doc_abc123');

    expect((string) $http->requests[0]->getUri())->toBe('https://api.example.test/api/v1/documents/doc_abc123')
        ->and($document->id)->toBe('doc_abc123')
        ->and($document->type)->toBe('pdf')
        ->and($document->status)->toBe('completed')
        ->and($document->bytes)->toBe(1234)
        ->and($document->downloadUrl)->toContain('signature=abc')
        ->and($document->isCompleted())->toBeTrue();
});

it('lists documents with filters', function (): void {
    $http = new FakeHttpClient(jsonResponse(200, pagePayload([
        documentPayload(),
        documentPayload(['id' => 'doc_def456', 'type' => 'png', 'mime_type' => 'image/png']),
    ], ['current_page' => 2, 'per_page' => 2, 'total' => 4, 'last_page' => 2])));
    $client = sdkClient($http);

    $page = $client->documents()->list([
        'type' => 'pdf',
        'status' => 'completed',
        'page' => 2,
        'per_page' => 2,
    ]);

    expect((string) $http->requests[0]->getUri())->toBe('https://api.example.test/api/v1/documents?type=pdf&status=completed&page=2&per_page=2')
        ->and($page)->toBeInstanceOf(DocumentPage::class)
        ->and($page->items)->toHaveCount(2)
        ->and($page->items[1]->id)->toBe('doc_def456')
        ->and($page->currentPage)->toBe(2)
        ->and($page->perPage)->toBe(2)
        ->and($page->total)->toBe(4)
        ->and($page->lastPage)->toBe(2);
});

it('downloads a signed document url', function (): void {
    $http = new FakeHttpClient(binaryResponse(200, '%PDF-1.4 signed', 'application/pdf'));
    $client = sdkClient($http);

    $result = $client->documents()->download(
        'https://api.example.test/api/v1/documents/doc_abc123/download?signature=abc',
    );

    expect((string) $http->requests[0]->getUri())->toBe('https://api.example.test/api/v1/documents/doc_abc123/download?signature=abc')
        ->and($result->contents)->toBe('%PDF-1.4 signed');
});

it('maps binary endpoint errors to the normal exceptions', function (): void {
    $http = new FakeHttpClient(jsonResponse(422, errorPayload('unsafe_html', 'HTML-en er ikke tillatt.')));
    $client = sdkClient($http);

    expect(fn () => $client->documents()->render('<h1>Hei</h1>'))
        ->toThrow(ValidationException::class);
});
