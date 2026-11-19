<?php

declare(strict_types=1);

namespace Vivalto\Connect\Laravel\Commands;

use Vivalto\Connect\DTO\Account;
use Vivalto\Connect\DTO\Product;
use Vivalto\Connect\Exceptions\ApiException;

/**
 * Verify that the configured API key authenticates and carries the scopes the
 * application needs.
 *
 * The command is read only. It calls GET /api/v1/me, which the API answers
 * only when the key is valid, and compares the reported scopes with the ones
 * the caller asks for.
 */
final class VerifyCommand extends ConnectCommand
{
    /**
     * Platform groups the API does not list under products, with the scope
     * that grants access. The /me products list only covers billable products.
     *
     * @var array<string, string>
     */
    private const PLATFORM_PRODUCTS = [
        'webhooks' => 'webhooks:manage',
        'pricing' => 'pricing:read',
    ];

    /**
     * Scopes that are useful together, and what a key loses without the pair.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const PAIRS = [
        'messages:send' => ['messages:read', 'send SMS but cannot read the status back'],
        'documents:render' => ['documents:read', 'render documents but cannot list or read them'],
        'search:write' => ['search:read', 'index documents but cannot read search status or usage'],
    ];

    /**
     * @var array<string, string>
     */
    private const HINTS = [
        'unauthenticated' => 'Set VIVALTO_CONNECT_API_KEY to a key from the portal, then run config:clear. A key is shown only once, so create a new one if it is lost.',
        'forbidden' => 'The key is valid but lacks the scope. Edit the key in the portal: Projects, the project, API keys.',
    ];

    protected $signature = 'connect:verify
                            {--need=* : Scope the key must have, repeatable, for example --need=messages:send}
                            {--product=* : Product the key must have access to: sms, documents, search, webhooks or pricing}
                            {--json : Print the account and the checks as JSON}';

    protected $aliases = ['connect:test-auth'];

    protected $description = 'Verify the API key, its scopes and product access';

    public function handle(): int
    {
        $client = $this->resolveClient();

        if ($client === null) {
            return self::FAILURE;
        }

        try {
            $account = $client->me();
        } catch (ApiException $exception) {
            $this->error('The API key was not accepted.');
            $this->reportApiException($exception);

            $hint = $exception->errorCode === null ? null : (self::HINTS[$exception->errorCode] ?? null);

            if ($hint !== null) {
                $this->line('  '.$hint);
            }

            return self::FAILURE;
        }

        $verdict = $this->evaluate($account);

        if ((bool) $this->option('json')) {
            return $this->reportJson($account, $verdict);
        }

        $this->reportAccount($account);
        $this->reportScopes($account);
        $this->reportProducts($account);
        $this->reportVerdict($verdict);

        return $verdict['failed'] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Compare the account with the scopes and products the caller requires.
     *
     * @return array{missing_scopes: list<string>, missing_products: list<string>, warnings: list<string>, failed: bool}
     */
    private function evaluate(Account $account): array
    {
        $missingScopes = [];
        $required = $this->listOption('need');

        foreach ($required as $scope) {
            if (! $account->hasScope($scope)) {
                $missingScopes[] = $scope;
            }
        }

        $missingProducts = [];
        $wanted = $this->listOption('product');
        $available = $account->productKeys();

        foreach (self::PLATFORM_PRODUCTS as $product => $scope) {
            if ($account->hasScope($scope)) {
                $available[] = $product;
            }
        }

        foreach ($wanted as $product) {
            if (! in_array($product, $available, true)) {
                $missingProducts[] = $product;
            }
        }

        $warnings = [];

        if ($account->key->scopes === []) {
            $warnings[] = 'The key has no scopes, so every product call is rejected. Add scopes in the portal: Projects, the project, API keys.';
        }

        foreach (self::PAIRS as $scope => [$pair, $limitation]) {
            if ($account->hasScope($scope) && ! $account->hasScope($pair)) {
                $warnings[] = sprintf('%s is enabled without %s: the key can %s.', $scope, $pair, $limitation);
            }
        }

        if ($account->sandbox) {
            $warnings[] = 'This is a sandbox key. Messages are accepted but not delivered as real SMS.';
        }

        return [
            'missing_scopes' => $missingScopes,
            'missing_products' => $missingProducts,
            'warnings' => $warnings,
            'failed' => $missingScopes !== [] || $missingProducts !== [] || $account->key->scopes === [],
        ];
    }

    private function reportAccount(Account $account): void
    {
        $this->newLine();
        $this->info('The API key is valid.');

        $this->table(['Field', 'Value'], [
            ['Key', $account->key->name.' ('.$account->key->id.')'],
            ['Prefix', $account->key->prefix],
            ['Environment', $account->sandbox ? 'sandbox' : 'live'],
            ['Customer', $account->customer->name.' ('.$account->customer->id.')'],
            ['Project', $account->project->name.' ('.$account->project->id.')'],
            ['Default region', $account->defaultRegion === '' ? '-' : $account->defaultRegion],
            ['Rate limit', $account->rateLimitPerMinute.' requests per minute'],
        ]);
    }

    private function reportScopes(Account $account): void
    {
        $this->newLine();
        $this->line('Scopes');

        if ($account->key->scopes === []) {
            $this->line('  none');

            return;
        }

        foreach ($account->key->scopes as $scope) {
            $this->line('  '.$scope);
        }
    }

    private function reportProducts(Account $account): void
    {
        $this->newLine();
        $this->line('Products');

        if ($account->products === []) {
            $this->line('  none');

            return;
        }

        $rows = array_map(
            static fn (Product $product): array => [$product->key, $product->name, $product->unit],
            $account->products,
        );

        $this->table(['Key', 'Name', 'Unit'], $rows);
    }

    /**
     * @param  array{missing_scopes: list<string>, missing_products: list<string>, warnings: list<string>, failed: bool}  $verdict
     */
    private function reportVerdict(array $verdict): void
    {
        foreach ($verdict['warnings'] as $warning) {
            $this->newLine();
            $this->warn($warning);
        }

        if ($verdict['missing_scopes'] !== []) {
            $this->newLine();
            $this->error('Missing scopes: '.implode(', ', $verdict['missing_scopes']));
            $this->line('  Edit the key in the portal: Projects, the project, API keys.');
        } elseif ($this->listOption('need') !== []) {
            $required = $this->listOption('need');
            $count = count($required);

            $this->newLine();
            $this->info(sprintf(
                'All %d required scope%s %s present.',
                $count,
                $count === 1 ? '' : 's',
                $count === 1 ? 'is' : 'are',
            ));
        }

        if ($verdict['missing_products'] !== []) {
            $this->newLine();
            $this->error('The key has no access to: '.implode(', ', $verdict['missing_products']));
            $this->line('  Add a scope for the product in the portal: Projects, the project, API keys.');
        }

        if ($verdict['failed']) {
            return;
        }

        $this->newLine();
        $this->info('The key works and carries every scope this check requires.');
    }

    /**
     * @param  array{missing_scopes: list<string>, missing_products: list<string>, warnings: list<string>, failed: bool}  $verdict
     */
    private function reportJson(Account $account, array $verdict): int
    {
        $payload = [
            'ok' => ! $verdict['failed'],
            'account' => [
                'key' => [
                    'id' => $account->key->id,
                    'name' => $account->key->name,
                    'prefix' => $account->key->prefix,
                    'scopes' => $account->key->scopes,
                    'sandbox' => $account->key->sandbox,
                ],
                'customer' => [
                    'id' => $account->customer->id,
                    'name' => $account->customer->name,
                ],
                'project' => [
                    'id' => $account->project->id,
                    'name' => $account->project->name,
                    'default_region' => $account->project->defaultRegion,
                ],
                'products' => array_map(
                    static fn (Product $product): array => [
                        'key' => $product->key,
                        'name' => $product->name,
                        'unit' => $product->unit,
                    ],
                    $account->products,
                ),
                'rate_limit_per_minute' => $account->rateLimitPerMinute,
                'default_region' => $account->defaultRegion,
                'sandbox' => $account->sandbox,
            ],
            'missing_scopes' => $verdict['missing_scopes'],
            'missing_products' => $verdict['missing_products'],
            'warnings' => $verdict['warnings'],
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            $this->error('Could not encode the account as JSON.');

            return self::FAILURE;
        }

        $this->line($json);

        return $verdict['failed'] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Read a repeatable option as a trimmed list without empty entries.
     *
     * @return list<string>
     */
    private function listOption(string $name): array
    {
        $value = $this->option($name);

        if (! is_array($value)) {
            return [];
        }

        $values = [];

        foreach ($value as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $values[] = trim($entry);
            }
        }

        return array_values(array_unique($values));
    }
}
