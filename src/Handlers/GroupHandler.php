<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Handlers;

use Tiamenti\VkBotSdk\Enums\EventType;

/**
 * Группа обработчиков с общими middleware.
 *
 * Используется внутри Vk::group():
 * ```php
 * Vk::group(function () {
 *     Vk::middleware(AdminMiddleware::class);
 *     Vk::hears('admin', [AdminHandler::class, 'index']);
 * });
 * ```
 */
final class GroupHandler
{
    /** @var array<int, callable|string> Middleware группы */
    private array $middlewares = [];

    /** @var array<int, HandlerDefinition> Обработчики, собранные в группе */
    private array $collected = [];

    /**
     * @param HandlerCollection $collection Основная коллекция обработчиков
     */
    public function __construct(
        private readonly HandlerCollection $collection,
    ) {}

    /**
     * Добавить middleware для всей группы.
     *
     * @param callable|string $middleware
     */
    public function addMiddleware(callable|string $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Добавить обработчик в группу (применяет middleware группы).
     */
    public function addDefinition(HandlerDefinition $definition): void
    {
        $decorated = $definition->withMiddlewares($this->middlewares);
        $this->collection->add($decorated);
    }

    /**
     * Получить текущие middleware группы.
     *
     * @return array<int, callable|string>
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}
