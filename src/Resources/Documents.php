<?php

declare(strict_types=1);

namespace Vivalto\Connect\Resources;

use Vivalto\Connect\Client;
use Vivalto\Connect\DTO\Document;
use Vivalto\Connect\DTO\DocumentPage;
use Vivalto\Connect\DTO\DocumentResult;

/**
 * Render HTML to PDF or an image, and read the document history.
 *
 * Synchronous rendering returns the binary file. Asynchronous rendering
 * returns a document record that can be polled with get().
 */
final class Documents
{
    private const ACCEPT = 'application/pdf, image/png, image/jpeg';

    public function __construct(private readonly Client $client) {}

    /**
     * Render HTML and return the binary document.
     *
     * Supported options are type, format, paper_size, orientation, margins,
     * transparent_background, scale, window_size, image_quality, full_page
     * and device_scale_factor.
     *
     * @param  array<string, mixed>  $options
     */
    public function render(string $html, array $options = []): DocumentResult
    {
        $response = $this->client->requestRaw(
            'POST',
            '/api/v1/documents',
            array_merge(['html' => $html], $options),
            ['Accept' => self::ACCEPT],
        );

        return DocumentResult::fromRaw($response);
    }

    /**
     * Render HTML directly to a file and return the result metadata.
     *
     * @param  array<string, mixed>  $options
     */
    public function renderToFile(string $path, string $html, array $options = []): DocumentResult
    {
        $result = $this->render($html, $options);

        if (file_put_contents($path, $result->contents) === false) {
            throw new \RuntimeException('Could not write the rendered document to '.$path.'.');
        }

        return $result;
    }

    /**
     * Start an asynchronous render and return the queued document.
     *
     * @param  array<string, mixed>  $options
     */
    public function create(string $html, array $options = []): Document
    {
        $response = $this->client->request(
            'POST',
            '/api/v1/documents',
            array_merge(['html' => $html, 'async' => true], $options),
        );

        $data = $response['data'] ?? $response;

        return Document::fromArray(is_array($data) ? $data : []);
    }

    /**
     * Get one document with its metadata and download link.
     */
    public function get(string $id): Document
    {
        $response = $this->client->request('GET', '/api/v1/documents/'.rawurlencode($id));
        $data = $response['data'] ?? $response;

        return Document::fromArray(is_array($data) ? $data : []);
    }

    /**
     * List documents with optional filters and pagination.
     *
     * Supported filters are type, status, from, to, per_page and page.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): DocumentPage
    {
        return DocumentPage::fromArray(
            $this->client->request('GET', '/api/v1/documents', query: $filters),
        );
    }

    /**
     * Download a completed document from a signed URL.
     *
     * The configured base URL must match the host in the signed URL, because
     * the signature covers the full URL.
     */
    public function download(string $url): DocumentResult
    {
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        $path = is_string($path) ? $path : '';

        if (is_string($query) && $query !== '') {
            $path .= '?'.$query;
        }

        return DocumentResult::fromRaw(
            $this->client->requestRaw('GET', $path, headers: ['Accept' => self::ACCEPT]),
        );
    }
}
