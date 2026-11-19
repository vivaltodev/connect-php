<?php

use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Symfony\Component\Console\Tester\CommandTester;
use Vivalto\Connect\Client;
use Vivalto\Connect\Laravel\Commands\TestPdfCommand;
use Vivalto\Connect\Laravel\VivaltoConnectServiceProvider;
use Vivalto\Connect\Tests\Support\FakeHttpClient;
use Vivalto\Connect\Tests\Support\TestApplication;

/**
 * Run connect:test-pdf against a container wired to the fake transport.
 *
 * @param  array<string, mixed>  $arguments
 * @param  array<string, mixed>  $config
 * @return array{status: int, output: string}
 */
function runTestPdfCommand(array $arguments, FakeHttpClient $http, array $config = []): array
{
    $container = new TestApplication;

    $repository = new Repository([
        'connect' => array_merge([
            'api_key' => 'test-key',
            'base_url' => 'https://api.example.test',
            'timeout' => 5,
            'retries' => 0,
        ], $config),
    ]);

    $container->instance('config', $repository);
    $container->instance(ConfigRepository::class, $repository);
    $container->instance(Client::class, sdkClient($http));

    $command = new TestPdfCommand;
    $command->setLaravel($container);

    $tester = new CommandTester($command);
    $status = $tester->execute($arguments, ['interactive' => false]);

    return ['status' => $status, 'output' => $tester->getDisplay()];
}

/**
 * A temporary path for a rendered document.
 */
function tempDocumentPath(string $extension = 'pdf'): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.'connect-test-'.bin2hex(random_bytes(6)).'.'.$extension;
}

it('renders a pdf and writes the file', function (): void {
    $path = tempDocumentPath();
    $http = new FakeHttpClient(binaryResponse(
        200,
        '%PDF-1.4 fake',
        'application/pdf',
        ['X-Document-Id' => 'doc_abc123', 'X-Document-Duration-Ms' => '120'],
    ));

    $result = runTestPdfCommand(['--path' => $path], $http);

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('The PDF was rendered and saved.')
        ->and($result['output'])->toContain('doc_abc123')
        ->and($result['output'])->toContain('120 ms')
        ->and(file_get_contents($path))->toBe('%PDF-1.4 fake');

    expect($http->requests)->toHaveCount(1);

    $request = $http->requests[0];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://api.example.test/api/v1/documents')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer test-key')
        ->and($request->getHeaderLine('Accept'))->toBe('application/pdf, image/png, image/jpeg');

    $body = json_decode((string) $request->getBody(), true);

    expect($body['type'])->toBe('pdf')
        ->and($body['format'])->toBe('A4')
        ->and($body['orientation'])->toBe('portrait')
        ->and($body['html'])->toContain('Vivalto Connect document test');

    unlink($path);
});

it('renders a png in landscape', function (): void {
    $path = tempDocumentPath('png');
    $http = new FakeHttpClient(binaryResponse(200, "\x89PNG\r\n\x1a\nfake", 'image/png'));

    $result = runTestPdfCommand([
        '--type' => 'png',
        '--path' => $path,
        '--landscape' => true,
    ], $http);

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('The PNG was rendered and saved.');

    $body = json_decode((string) $http->requests[0]->getBody(), true);

    expect($body['type'])->toBe('png')
        ->and($body['orientation'])->toBe('landscape');

    unlink($path);
});

it('renders the html from --html-file', function (): void {
    $htmlPath = tempDocumentPath('html');
    $pdfPath = tempDocumentPath();
    file_put_contents($htmlPath, '<h1>Invoice 1042</h1>');

    $http = new FakeHttpClient(binaryResponse(200, '%PDF-1.4 fake', 'application/pdf'));

    $result = runTestPdfCommand([
        '--path' => $pdfPath,
        '--html-file' => $htmlPath,
    ], $http);

    expect($result['status'])->toBe(0);

    $body = json_decode((string) $http->requests[0]->getBody(), true);

    expect($body['html'])->toBe('<h1>Invoice 1042</h1>');

    unlink($htmlPath);
    unlink($pdfPath);
});

it('fails before the request when the type is unknown', function (): void {
    $http = new FakeHttpClient(binaryResponse(200, '%PDF-1.4 fake', 'application/pdf'));

    $result = runTestPdfCommand(['--type' => 'gif'], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('Unknown document type "gif"');

    expect($http->requests)->toBe([]);
});

it('fails when the returned bytes are not the requested type', function (): void {
    $path = tempDocumentPath();
    $http = new FakeHttpClient(binaryResponse(200, 'not a pdf', 'application/pdf'));

    $result = runTestPdfCommand(['--path' => $path], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('that are not a valid pdf file');

    unlink($path);
});

it('fails when the mime type does not match the requested type', function (): void {
    $path = tempDocumentPath();
    $http = new FakeHttpClient(binaryResponse(200, '%PDF-1.4 fake', 'application/pdf'));

    $result = runTestPdfCommand(['--type' => 'png', '--path' => $path], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('The API returned application/pdf, but png was requested.');

    unlink($path);
});

it('explains a missing render scope', function (): void {
    $http = new FakeHttpClient(jsonResponse(403, errorPayload('forbidden', 'Forbidden.')));

    $result = runTestPdfCommand(['--path' => tempDocumentPath()], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('The document was not rendered.')
        ->and($result['output'])->toContain('documents:render');
});

it('fails when the html file cannot be read', function (): void {
    $http = new FakeHttpClient(binaryResponse(200, '%PDF-1.4 fake', 'application/pdf'));

    $result = runTestPdfCommand(['--html-file' => '/tmp/does-not-exist-'.bin2hex(random_bytes(4)).'.html'], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('The HTML file does not exist or cannot be read');

    expect($http->requests)->toBe([]);
});

it('fails when the reader rejects the render options', function (): void {
    $http = new FakeHttpClient(jsonResponse(422, errorPayload(
        'invalid_options',
        'The format is not supported.',
        ['field' => 'format'],
    )));

    $result = runTestPdfCommand(['--format' => 'A4', '--path' => tempDocumentPath()], $http);

    expect($result['status'])->toBe(1)
        ->and($result['output'])->toContain('Code: invalid_options')
        ->and($result['output'])->toContain('The format is not supported.');
});

it('registers the command through the service provider', function (): void {
    $commands = VivaltoConnectServiceProvider::artisanCommands();

    expect($commands)->toContain(TestPdfCommand::class);

    $command = new TestPdfCommand;

    expect($command->getName())->toBe('connect:test-pdf')
        ->and($command->getDefinition()->hasOption('type'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('path'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('html-file'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('format'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('landscape'))->toBeTrue();
});
