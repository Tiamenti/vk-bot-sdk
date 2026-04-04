<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Exceptions;

use RuntimeException;

/**
 * Исключение при ошибке в сценарии диалога (Conversation).
 */
class ConversationException extends RuntimeException
{
    /**
     * Создать исключение о несуществующем шаге.
     */
    public static function stepNotFound(string $conversationClass, string $step): self
    {
        return new self(
            "Step '{$step}' not found in conversation {$conversationClass}. "
            . "Make sure the public method '{$step}' exists.",
        );
    }

    /**
     * Создать исключение об отсутствии начального шага.
     */
    public static function missingStartStep(string $conversationClass): self
    {
        return new self(
            "Conversation {$conversationClass} has no start step. "
            . "Define the protected string \$step property or a public 'start' method.",
        );
    }
}
