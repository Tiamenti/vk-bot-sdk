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
    public const TYPE_ON = 'on';

    public const TYPE_HEARS = 'hears';

    public const TYPE_COMMAND = 'command';

    public const TYPE_PAYLOAD = 'payload';

    public const TYPE_FALLBACK = 'fallback';

    /**
     * @param  string  $type  Тип обработчика
     * @param  callable  $handler  Обработчик
     * @param  EventType|null  $event  Тип события (для on())
     * @param  string|array<int,string>|null  $pattern  Паттерн текста (для hears/command)
     * @param  string|array<mixed>|null  $payload  Payload (для onPayload)
     * @param  array<int, callable|string>  $middlewares  Middleware для этого обработчика
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
            self::TYPE_ON => $this->matchesEvent($ctx),
            self::TYPE_HEARS => $this->matchesText($ctx),
            self::TYPE_COMMAND => $this->matchesCommand($ctx),
            self::TYPE_PAYLOAD => $this->matchesPayload($ctx),
            self::TYPE_FALLBACK => true,
            default => false,
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
     * @param  array<int, callable|string>  $middlewares
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

        // Нормализуем аргумент в список паттернов.
        //
        // Возможные форматы аргумента Vk::onPayload():
        //
        //   string → 'buy'
        //     Итог: ['buy']
        //
        //   assoc  → ['action' => 'buy']
        //     Это ОДИН паттерн-массив, не список. array_is_list() == false.
        //     Итог: [['action' => 'buy']]
        //
        //   list   → [['action' => 'buy'], ['action' => 'cancel']]
        //     Это список паттернов. array_is_list() == true.
        //     Итог: [['action' => 'buy'], ['action' => 'cancel']]
        //
        $patterns = $this->normalizePayloadPatterns($this->payload);

        foreach ($patterns as $pattern) {
            if (is_string($pattern) && $this->payloadMatchesString($payload, $pattern)) {
                return true;
            }

            if (is_array($pattern) && $this->payloadContains($payload, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Привести аргумент onPayload() к единому виду: плоский список паттернов.
     *
     * @param  string|array<mixed>|null  $raw
     * @return array<int, string|array<string, mixed>>
     */
    private function normalizePayloadPatterns(string|array|null $raw): array
    {
        if ($raw === null) {
            return [];
        }

        if (is_string($raw)) {
            return [$raw];
        }

        // Числово-индексированный массив — уже список паттернов
        // Пример: [['action' => 'buy'], ['action' => 'cancel']]
        if (array_is_list($raw)) {
            return $raw;
        }

        // Ассоциативный массив — один паттерн, оборачиваем в список
        // Пример: ['action' => 'buy', 'product_id' => 123]
        return [$raw];
    }

    /**
     * Сопоставить payload со строковым паттерном.
     *
     * @param  array<string, mixed>  $payload
     */
    private function payloadMatchesString(array $payload, string $pattern): bool
    {
        if (isset($payload['button']) && $payload['button'] === $pattern) {
            return true;
        }

        if (isset($payload['action']) && $payload['action'] === $pattern) {
            return true;
        }

        // Точное сравнение всего payload как JSON-строки
        if (json_encode($payload, JSON_UNESCAPED_UNICODE) === $pattern) {
            return true;
        }

        return false;
    }

    /**
     * Проверить, содержит ли payload все ключи паттерна с теми же значениями.
     * Лишние ключи в payload игнорируются — это намеренное поведение,
     * позволяющее регистрировать обработчик по частичному совпадению.
     *
     * Пример:
     *   payload  = ['action' => 'buy', 'product_id' => 123]
     *   pattern  = ['action' => 'buy']
     *   результат = true  ✓
     *
     * @param  array<string, mixed>  $payload  Реальный payload кнопки
     * @param  array<string, mixed>  $pattern  Паттерн для сравнения
     */
    private function payloadContains(array $payload, array $pattern): bool
    {
        foreach ($pattern as $key => $value) {
            if (! array_key_exists($key, $payload) || $payload[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
