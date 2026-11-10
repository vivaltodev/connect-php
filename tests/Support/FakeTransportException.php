<?php

namespace Vivalto\Connect\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * A PSR-18 transport failure used by the fake client.
 */
final class FakeTransportException extends RuntimeException implements ClientExceptionInterface {}
