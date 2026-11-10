<?php

namespace Vivalto\Sms\Exceptions;

/**
 * The API returned a 5xx response after every retry was used.
 */
final class ServerException extends ApiException {}
