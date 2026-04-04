<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Handlers;

use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Enums\EventType;

/**
 * Коллекция зарегистрированных обработчиков.
 *
 * Хранит все обработчики и предоставляет метод поиска совпадения
 * для входящего события.
 */
final class HandlerCollection
{
    /** @var array<int, HandlerDefinition> */
    private array $handlers = [];

    /**
     * Добавить определение обработчика в коллекцию.
     */
    public function add(HandlerDefinition $definition): void
    {
        $this->handlers[] = $definition;
    }

    /**
     * Найти первый подходящий обработчик для контекста.
     */
    public function match(MessageContext $ctx): ?HandlerDefinition
    {
        foreach ($this->handlers as $definition) {
            if ($definition->matches($ctx)) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Получить все обработчики.
     *
     * @return array<int, HandlerDefinition>
     */
    public function all(): array
    {
        return $this->handlers;
    }

    /**
     * Очистить все обработчики.
     */
    public function flush(): void
    {
        $this->handlers = [];
    }
}
