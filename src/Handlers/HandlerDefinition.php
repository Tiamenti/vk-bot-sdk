<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Handlers;

use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Enums\EventType;

/**
 * Определение одного обработчика: условие + callable + middleware.
 */
final class HandlerDefinition
{
    public const TYPE_ON       = 'on';
    public const TYPE_HEARS    = 'hears';
    public const TYPE_COMMAND  = 'command';
    public const TYPE_PAYLOAD  = 'payload';
    public const TYPE_FALLBACK = 'fallback';

    /**
     * @param string                        $type        Тип обработчика
     * @param callable                      $handler     Обработчик
     * @param EventType|null                $event       Тип события (для on())
     * @param string|array<int,string>|null $pattern     Паттерн текста (для hears/command)
     * @param string|array<mixed>|null      $payload     Payload (для onPayload)
     * @param array<int, callable|string>   $middlewares Middleware для этого обработчика
     */
    public function __construct(
        private readonly string $type,
        private readonly mixed $handler,
        private readonly ?EventType $event = null,
        private readonly string|array|null $pattern = null,
        private readonly string|array|null $payload = null,
        private readonly array $middlewares = [],
    ) {}

    /**
     * Проверить, подходит ли обработчик для данного контекста.
     */
    public function matches(MessageContext $ctx): bool
    {
        return match ($this->type) {
            self::TYPE_ON       => $this->matchesEvent($ctx),
            self::TYPE_HEARS    => $this->matchesText($ctx),
            self::TYPE_COMMAND  => $this->matchesCommand($ctx),
            self::TYPE_PAYLOAD  => $this->matchesPayload($ctx),
            self::TYPE_FALLBACK => true,
            default             => false,
        };
    }

    /**
     * Получить callable обработчика.
     */
    public function getHandler(): mixed
    {
        return $this->handler;
    }

    /**
     * Получить middleware для этого обработчика.
     *
     * @return array<int, callable|string>
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * Создать новое определение с добавленными middleware.
     *
     * @param array<int, callable|string> $middlewares
     */
    public function withMiddlewares(array $middlewares): self
    {
        return new self(
            type: $this->type,
            handler: $this->handler,
            event: $this->event,
            pattern: $this->pattern,
            payload: $this->payload,
            middlewares: array_merge($middlewares, $this->middlewares),
        );
    }

    // -------------------------------------------------------------------------
    // Приватные методы проверки
    // -------------------------------------------------------------------------

    private function matchesEvent(MessageContext $ctx): bool
    {
        return $this->event !== null && $ctx->getEvent() === $this->event;
    }

    private function matchesText(MessageContext $ctx): bool
    {
        $text = $ctx->getText();

        if ($text === null) {
            return false;
        }

        $patterns = (array) $this->pattern;

        foreach ($patterns as $pattern) {
            // Regexp-паттерн
            if (str_starts_with($pattern, '/')) {
                if (preg_match($pattern, $text)) {
                    return true;
                }
                continue;
            }

            // Точное совпадение (без учёта регистра)
            if (mb_strtolower($text) === mb_strtolower($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesCommand(MessageContext $ctx): bool
    {
        $text = $ctx->getText();

        if ($text === null) {
            return false;
        }

        $commands = (array) $this->pattern;
        $normalized = ltrim(mb_strtolower($text), '/');

        foreach ($commands as $command) {
            $normalizedCommand = ltrim(mb_strtolower($command), '/');

            if ($normalized === $normalizedCommand) {
                return true;
            }
        }

        return false;
    }

    private function matchesPayload(MessageContext $ctx): bool
    {
        $payload = $ctx->getPayload();

        if ($payload === null) {
            return false;
        }

        $patterns = (array) $this->payload;

        foreach ($patterns as $pattern) {
            if (is_string($pattern)) {
                // Проверяем конкретное значение ключа 'button' или всего payload
                if (isset($payload['button']) && $payload['button'] === $pattern) {
                    return true;
                }
                if (isset($payload['action']) && $payload['action'] === $pattern) {
                    return true;
                }
                // Сравнение JSON-строки
                if (json_encode($payload, JSON_UNESCAPED_UNICODE) === $pattern) {
                    return true;
                }
                continue;
            }

            // Сравнение массива: все ключи из паттерна должны совпасть
            if (is_array($pattern)) {
                $match = true;
                foreach ($pattern as $key => $value) {
                    if (! isset($payload[$key]) || $payload[$key] !== $value) {
                        $match = false;
                        break;
                    }
                }
                if ($match) {
                    return true;
                }
            }
        }

        return false;
    }
}
