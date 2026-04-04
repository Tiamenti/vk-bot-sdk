<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Exceptions;

use RuntimeException;

/**
 * Исключение при несовпадении секретного ключа Callback API.
 */
class InvalidSecretException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid or missing VK Callback API secret key.');
    }
}
