<?php

declare(strict_types=1);

namespace Vivalto\Connect\Laravel\Commands;

use Illuminate\Support\Carbon;
use Vivalto\Connect\DTO\DocumentResult;
use Vivalto\Connect\Exceptions\ApiException;

/**
 * Render a real document through the Vivalto Connect API.
 *
 * The command renders synchronously, checks that the returned bytes match the
 * requested type and writes the file where the caller can open it. It is the
 * document counterpart of connect:test-sms.
 */
final class TestPdfCommand extends ConnectCommand
{
    /**
     * The MIME type each document type must return.
     *
     * @var array<string, string>
     */
    private const MIME_TYPES = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpeg' => 'image/jpeg',
    ];

    /**
     * @var array<string, string>
     */
    private const HINTS = [
        'forbidden' => 'The API key needs the documents:render scope. Edit the key in the portal: Projects, the project, API keys.',
        'too_many_renders' => 'All render slots stayed busy. Retry with a backoff, or render with async so Connect can queue the document.',
        'document_not_ready' => 'The render did not finish. Retry, and check the system check page if it repeats.',
    ];

    protected $signature = 'connect:test-pdf
                            {--type=pdf : Document type: pdf, png or jpeg}
                            {--path= : Where to write the file. Defaults to storage/app/connect-test-<time>.<ext>}
                            {--html-file= : Render this HTML file instead of the built-in sample}
                            {--format=A4 : Paper size passed to the renderer}
                            {--landscape : Render the page in landscape orientation}';

    protected $description = 'Render a test PDF or image through the Vivalto Connect API';

    public function handle(): int
    {
        $client = $this->resolveClient();

        if ($client === null) {
            return self::FAILURE;
        }

        $type = strtolower($this->stringOption('type'));

        if ($type === '') {
            $type = 'pdf';
        }

        if (! isset(self::MIME_TYPES[$type])) {
            $this->error(sprintf(
                'Unknown document type "%s". Use one of: %s.',
                $type,
                implode(', ', array_keys(self::MIME_TYPES)),
            ));

            return self::FAILURE;
        }

        $html = $this->resolveHtml();

        if ($html === null) {
            return self::FAILURE;
        }

        $options = [
            'type' => $type,
            'format' => $this->resolveFormat(),
            'orientation' => (bool) $this->option('landscape') ? 'landscape' : 'portrait',
        ];

        try {
            $result = $client->documents()->render($html, $options);
        } catch (ApiException $exception) {
            $this->error('The document was not rendered.');
            $this->reportApiException($exception);

            $hint = $exception->errorCode === null ? null : (self::HINTS[$exception->errorCode] ?? null);

            if ($hint !== null) {
                $this->line('  '.$hint);
            }

            return self::FAILURE;
        }

        $expected = self::MIME_TYPES[$type];
        $problem = $this->verify($result, $type, $expected);

        $path = $this->resolvePath($type);
        $written = @file_put_contents($path, $result->contents);

        $this->report($result, $type, $path, $written !== false);

        if ($problem !== null) {
            $this->newLine();
            $this->error($problem);

            return self::FAILURE;
        }

        if ($written === false) {
            $this->newLine();
            $this->error('The document was rendered but could not be written to '.$path.'.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf('The %s was rendered and saved.', strtoupper($type)));

        return self::SUCCESS;
    }

    /**
     * Return null and explain the problem when the file cannot be read.
     */
    private function resolveHtml(): ?string
    {
        $file = $this->stringOption('html-file');

        if ($file === '') {
            return $this->sampleHtml();
        }

        if (! is_file($file) || ! is_readable($file)) {
            $this->error('The HTML file does not exist or cannot be read: '.$file);

            return null;
        }

        $html = file_get_contents($file);

        if ($html === false || trim($html) === '') {
            $this->error('The HTML file is empty: '.$file);

            return null;
        }

        return $html;
    }

    private function resolveFormat(): string
    {
        $format = $this->stringOption('format');

        return $format === '' ? 'A4' : strtoupper($format);
    }

    private function sampleHtml(): string
    {
        $time = Carbon::now()->format('d.m.Y H:i:s');

        return <<<HTML
            <!doctype html>
            <html lang="no">
            <head>
                <meta charset="utf-8">
                <style>
                    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #1f2937; margin: 32px; }
                    h1 { font-size: 22px; margin: 0 0 4px; }
                    p { font-size: 12px; line-height: 1.5; margin: 0 0 12px; }
                    table { border-collapse: collapse; width: 100%; margin-top: 16px; font-size: 12px; }
                    th, td { border: 1px solid #d1d5db; padding: 8px 10px; text-align: left; }
                    th { background: #f3f4f6; }
                    .muted { color: #6b7280; }
                </style>
            </head>
            <body>
                <h1>Vivalto Connect document test</h1>
                <p class="muted">Generated {$time}</p>
                <p>If you can read this page, the API key authenticated, the documents scope was accepted and the renderer produced a real file.</p>
                <table>
                    <thead>
                        <tr><th>Check</th><th>Result</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Authentication</td><td>Accepted</td></tr>
                        <tr><td>Documents scope</td><td>Accepted</td></tr>
                        <tr><td>Rendered at</td><td>{$time}</td></tr>
                    </tbody>
                </table>
            </body>
            </html>
            HTML;
    }

    /**
     * Return an explanation when the response is not the requested type.
     */
    private function verify(DocumentResult $result, string $type, string $expected): ?string
    {
        if ($result->mimeType !== $expected) {
            return sprintf(
                'The API returned %s, but %s was requested. Check the type option and the project render settings.',
                $result->mimeType,
                $type,
            );
        }

        $valid = match ($type) {
            'pdf' => str_starts_with(ltrim($result->contents), '%PDF-'),
            'png' => str_starts_with($result->contents, "\x89PNG\r\n\x1a\n"),
            'jpeg' => str_starts_with($result->contents, "\xFF\xD8\xFF"),
            default => false,
        };

        if (! $valid) {
            return sprintf('The API returned %s bytes that are not a valid %s file.', $result->mimeType, $type);
        }

        return null;
    }

    private function resolvePath(string $type): string
    {
        $path = $this->stringOption('path');

        if ($path !== '') {
            return $path;
        }

        $extension = $type === 'jpeg' ? 'jpg' : $type;
        $name = 'connect-test-'.Carbon::now()->format('Ymd-His').'.'.$extension;

        return $this->defaultDirectory().DIRECTORY_SEPARATOR.$name;
    }

    private function defaultDirectory(): string
    {
        $storage = $this->laravel->storagePath();
        $app = $storage.DIRECTORY_SEPARATOR.'app';

        return is_dir($app) ? $app : $storage;
    }

    private function report(DocumentResult $result, string $type, string $path, bool $written): void
    {
        $this->newLine();
        $this->info('The API returned a document.');

        $rows = [
            ['Type', $type],
            ['MIME type', $result->mimeType],
            ['Bytes', number_format(strlen($result->contents), 0, ',', ' ')],
        ];

        if ($result->documentId !== null) {
            $rows[] = ['Document id', $result->documentId];
        }

        if ($result->durationMs !== null) {
            $rows[] = ['Duration', $result->durationMs.' ms'];
        }

        $rows[] = ['Path', $written ? $path : $path.' (not written)'];

        $this->table(['Field', 'Value'], $rows);
    }
}
