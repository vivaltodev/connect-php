<?php

declare(strict_types=1);

namespace Vivalto\Connect\Laravel\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Vivalto\Connect\Client;
use Vivalto\Connect\Exceptions\ApiException;
use Vivalto\Connect\Exceptions\RateLimitException;
use Vivalto\Connect\Exceptions\ValidationException;

/**
 * Shared plumbing for the connect:* Artisan commands.
 *
 * The commands all resolve the same client from the container and print API
 * errors the same way, so a failed check reads the same whichever command it
 * came from.
 */
abstract class ConnectCommand extends Command
{
    /**
     * Resolve the client from the container and explain a broken config.
     */
    protected function resolveClient(): ?Client
    {
        try {
            /** @var Client $client */
            $client = $this->laravel->make(Client::class);
        } catch (InvalidArgumentException $exception) {
            $this->error('The Vivalto Connect client is not configured.');
            $this->line('  '.$exception->getMessage());
            $this->line('Set VIVALTO_CONNECT_API_KEY in .env, then run config:clear.');

            return null;
        }

        return $client;
    }

    /**
     * Print the message, error code, field errors and retry delay of a failed
     * API call.
     */
    protected function reportApiException(ApiException $exception): void
    {
        $this->line('  '.$exception->getMessage());

        if ($exception->errorCode !== null) {
            $this->line('  Code: '.$exception->errorCode);
        }

        if ($exception instanceof ValidationException) {
            foreach ($exception->errors() as $field => $messages) {
                foreach (is_array($messages) ? $messages : [$messages] as $message) {
                    if (is_string($message)) {
                        $this->line(sprintf('  %s: %s', $field, $message));
                    }
                }
            }
        }

        if ($exception instanceof RateLimitException && $exception->retryAfter !== null) {
            $this->line(sprintf('  Retry after %d seconds.', $exception->retryAfter));
        }
    }

    protected function stringOption(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? trim($value) : '';
    }
}
