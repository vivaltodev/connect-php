<?php

declare(strict_types=1);

namespace Vivalto\Connect\Laravel\Commands;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Carbon;
use Vivalto\Connect\Client;
use Vivalto\Connect\DTO\Message;
use Vivalto\Connect\Exceptions\ApiException;

/**
 * Send a real test SMS through the Vivalto Connect API.
 *
 * The command uses the client that the service provider registers, so it
 * sends with the API key, base URL and retry settings from config/connect.php.
 */
final class SendTestSmsCommand extends ConnectCommand
{
    /**
     * Statuses that will not change again.
     *
     * @var list<string>
     */
    private const FINAL_STATUSES = ['delivered', 'failed', 'expired', 'rejected'];

    /**
     * Message classes accepted by the API.
     *
     * @var list<string>
     */
    private const CLASSES = ['standard', 'premium', 'secret'];

    /**
     * Hints for the error codes a caller is most likely to hit.
     *
     * @var array<string, string>
     */
    private const HINTS = [
        'invalid_sender' => 'The sender is not approved for the customer or the recipient country. Use a sender the customer already sends with, or ask Vivalto to approve a new one.',
        'invalid_recipient' => 'Use an E.164 number, for example +4790000000.',
        'invalid_class' => 'Use one of: standard, premium, secret.',
        'unauthenticated' => 'Check VIVALTO_CONNECT_API_KEY and whether it is a live or test key.',
        'forbidden' => 'The API key is missing a scope: messages:send to send SMS, messages:read to use --wait.',
    ];

    protected $signature = 'connect:test-sms
                            {to : Recipient in E.164 format, for example +4790000000}
                            {--from= : Approved sender, defaults to VIVALTO_CONNECT_SENDER}
                            {--text= : Message body, defaults to a timestamped test message}
                            {--class=standard : Message class: standard, premium or secret}
                            {--reference= : Optional reference stored on the message}
                            {--wait=0 : Seconds to poll for a final status (needs messages:read), 0 returns immediately}';

    protected $description = 'Send a test SMS through the Vivalto Connect API';

    public function handle(Repository $config): int
    {
        $client = $this->resolveClient();

        if ($client === null) {
            return self::FAILURE;
        }

        $argument = $this->argument('to');
        $to = is_string($argument) ? trim($argument) : '';

        if ($to === '') {
            $this->error('The recipient number is required.');

            return self::FAILURE;
        }

        $from = $this->resolveSender($config);

        if ($from === null) {
            return self::FAILURE;
        }

        $class = $this->resolveClass();

        if ($class === null) {
            return self::FAILURE;
        }

        $text = $this->stringOption('text');

        if ($text === '') {
            $text = 'Test-SMS fra Vivalto Connect '.Carbon::now()->format('d.m.Y H:i:s');
        }

        $reference = $this->stringOption('reference');

        try {
            $message = $client->messages()->send(
                to: $to,
                from: $from,
                text: $text,
                class: $class,
                reference: $reference === '' ? null : $reference,
            );
        } catch (ApiException $exception) {
            return $this->reportFailure($exception);
        }

        $this->reportMessage($message);

        $wait = max(0, (int) $this->option('wait'));

        if ($wait === 0) {
            $this->newLine();
            $this->line('Use --wait=30 to follow the delivery status.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line(sprintf('Waiting up to %d seconds for a final status.', $wait));

        $message = $this->waitForFinalStatus($client, $message, $wait);

        if (! in_array($message->status, self::FINAL_STATUSES, true)) {
            $this->warn(sprintf(
                'The message is still %s after %d seconds. The provider may still deliver it.',
                $message->status,
                $wait,
            ));

            return self::SUCCESS;
        }

        if ($message->status === 'delivered') {
            $this->info('The message was delivered.');

            return self::SUCCESS;
        }

        $this->error(sprintf('The message ended as %s.', $message->status));

        if ($message->error !== null) {
            $this->line('  '.$message->error['code'].': '.($message->error['message'] ?? 'No details.'));
        }

        return self::FAILURE;
    }

    /**
     * Use --from, then the configured sender.
     */
    private function resolveSender(Repository $config): ?string
    {
        $from = $this->stringOption('from');

        if ($from === '') {
            $configured = $config->get('connect.sender');
            $from = is_string($configured) ? trim($configured) : '';
        }

        if ($from === '') {
            $this->error('No sender is configured.');
            $this->line('Pass --from=VIVALTO or set VIVALTO_CONNECT_SENDER. The sender must be approved for the customer in the portal.');

            return null;
        }

        if (mb_strlen($from) > 11) {
            $this->error('The sender may not be longer than 11 characters.');

            return null;
        }

        return $from;
    }

    /**
     * Validate --class locally so a typo does not need a round trip.
     */
    private function resolveClass(): ?string
    {
        $class = strtolower($this->stringOption('class'));

        if ($class === '') {
            return 'standard';
        }

        if (! in_array($class, self::CLASSES, true)) {
            $this->error(sprintf(
                'Unknown message class "%s". Use one of: %s.',
                $class,
                implode(', ', self::CLASSES),
            ));

            return null;
        }

        return $class;
    }

    private function reportMessage(Message $message): void
    {
        $this->newLine();
        $this->info('The test SMS was accepted by the API.');

        $rows = [
            ['ID', $message->id],
            ['Status', $message->status],
            ['To', $message->to],
            ['From', $message->from],
            ['Class', $message->class],
            ['Parts', $message->parts === null ? '-' : (string) $message->parts],
            ['Price', $this->formatPrice($message)],
        ];

        if ($message->reference !== null) {
            $rows[] = ['Reference', $message->reference];
        }

        $this->table(['Field', 'Value'], $rows);
    }

    /**
     * Poll the message until it reaches a final status or the deadline passes.
     */
    private function waitForFinalStatus(Client $client, Message $message, int $seconds): Message
    {
        $status = $message->status;
        $deadline = microtime(true) + $seconds;

        while (! in_array($status, self::FINAL_STATUSES, true) && microtime(true) < $deadline) {
            try {
                $message = $client->messages()->get($message->id);
            } catch (ApiException $exception) {
                $this->warn('Could not read the message status: '.$exception->getMessage());

                $hint = $this->hintFor($exception->errorCode);

                if ($hint !== null) {
                    $this->line('  '.$hint);
                }

                return $message;
            }

            if ($message->status !== $status) {
                $this->line(sprintf('  %s -> %s', $status, $message->status));
                $status = $message->status;
            }

            if (in_array($status, self::FINAL_STATUSES, true)) {
                break;
            }

            usleep(500_000);
        }

        return $message;
    }

    private function reportFailure(ApiException $exception): int
    {
        $this->error('The test SMS was not sent.');
        $this->reportApiException($exception);

        $hint = $this->hintFor($exception->errorCode);

        if ($hint !== null) {
            $this->line('  '.$hint);
        }

        return self::FAILURE;
    }

    private function hintFor(?string $errorCode): ?string
    {
        return $errorCode === null ? null : (self::HINTS[$errorCode] ?? null);
    }

    private function formatPrice(Message $message): string
    {
        if ($message->price === null) {
            return 'not priced';
        }

        $price = number_format($message->price->amount, 2, ',', ' ')
            .' '.$message->price->currency;

        return $message->price->estimated ? $price.' (estimated)' : $price;
    }
}
