<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Handlers;

use Illuminate\Container\Container;
use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Conversations\ConversationManager;
use Tiamenti\VkBotSdk\Enums\EventType;
use Tiamenti\VkBotSdk\Middleware\VkMiddleware;

/**
 * Роутер VK-событий.
 *
 * Находит нужный обработчик из HandlerCollection и запускает
 * middleware-пайплайн перед его вызовом.
 */
final class Router
{
    /** @var array<int, callable|string> Глобальные middleware */
    private array $globalMiddlewares = [];

    /** @var GroupHandler|null Текущая активная группа */
    private ?GroupHandler $currentGroup = null;

    public function __construct(
        private readonly HandlerCollection $collection,
        private readonly Container $container,
    ) {}

    // -------------------------------------------------------------------------
    // Регистрация обработчиков
    // -------------------------------------------------------------------------

    /**
     * Зарегистрировать обработчик для типа события.
     */
    public function on(EventType $event, callable $handler): void
    {
        $this->register(new HandlerDefinition(
            type: HandlerDefinition::TYPE_ON,
            handler: $handler,
            event: $event,
        ));
    }

    /**
     * Зарегистрировать обработчик по тексту сообщения.
     *
     * @param string|array<int,string> $pattern
     */
    public function hears(string|array $pattern, callable|string|array $handler): void
    {
        $this->register(new HandlerDefinition(
            type: HandlerDefinition::TYPE_HEARS,
            handler: $this->resolveCallable($handler),
            pattern: $pattern,
        ));
    }

    /**
     * Зарегистрировать обработчик команды (например, /start).
     */
    public function command(string $command, callable|string|array $handler): void
    {
        $this->register(new HandlerDefinition(
            type: HandlerDefinition::TYPE_COMMAND,
            handler: $this->resolveCallable($handler),
            pattern: $command,
        ));
    }

    /**
     * Зарегистрировать обработчик по payload кнопки.
     *
     * @param string|array<mixed> $payload
     */
    public function onPayload(string|array $payload, callable|string|array $handler): void
    {
        $this->register(new HandlerDefinition(
            type: HandlerDefinition::TYPE_PAYLOAD,
            handler: $this->resolveCallable($handler),
            payload: $payload,
        ));
    }

    /**
     * Зарегистрировать fallback-обработчик (вызывается, если ничего не совпало).
     */
    public function fallback(callable|string|array $handler): void
    {
        $this->register(new HandlerDefinition(
            type: HandlerDefinition::TYPE_FALLBACK,
            handler: $this->resolveCallable($handler),
        ));
    }

    /**
     * Добавить глобальный middleware.
     *
     * @param callable|string $middleware
     */
    public function middleware(callable|string $middleware): void
    {
        if ($this->currentGroup !== null) {
            $this->currentGroup->addMiddleware($middleware);
        } else {
            $this->globalMiddlewares[] = $middleware;
        }
    }

    /**
     * Зарегистрировать группу обработчиков с общими middleware.
     */
    public function group(callable $callback): void
    {
        $group = new GroupHandler($this->collection);
        $previous = $this->currentGroup;
        $this->currentGroup = $group;

        $callback();

        $this->currentGroup = $previous;
    }

    // -------------------------------------------------------------------------
    // Диспетчеризация
    // -------------------------------------------------------------------------

    /**
     * Обработать входящее событие.
     */
    public function dispatch(MessageContext $ctx): void
    {
        // Проверяем активный диалог
        /** @var ConversationManager $conversationManager */
        $conversationManager = $this->container->make(ConversationManager::class);

        if ($conversationManager->hasActive($ctx->getPeerId())) {
            $conversationManager->resume($ctx);
            return;
        }

        $definition = $this->collection->match($ctx);

        if ($definition === null) {
            return;
        }

        $middlewares = array_merge($this->globalMiddlewares, $definition->getMiddlewares());

        $this->runPipeline($ctx, $definition->getHandler(), $middlewares);
    }

    // -------------------------------------------------------------------------
    // Приватные методы
    // -------------------------------------------------------------------------

    private function register(HandlerDefinition $definition): void
    {
        if ($this->currentGroup !== null) {
            $this->currentGroup->addDefinition($definition);
        } else {
            $this->collection->add($definition);
        }
    }

    /**
     * Запустить middleware-пайплайн и вызвать обработчик.
     *
     * @param array<int, callable|string> $middlewares
     */
    private function runPipeline(MessageContext $ctx, mixed $handler, array $middlewares): void
    {
        $core = function (MessageContext $ctx) use ($handler): void {
            $this->callHandler($handler, $ctx);
        };

        $pipeline = array_reduce(
            array_reverse($middlewares),
            function (callable $next, callable|string $middleware): callable {
                return function (MessageContext $ctx) use ($next, $middleware): void {
                    $instance = $this->resolveMiddleware($middleware);
                    $instance->handle($ctx, $next);
                };
            },
            $core,
        );

        $pipeline($ctx);
    }

    /**
     * Вызвать обработчик (callable, [Class, method], Conversation::class).
     */
    private function callHandler(mixed $handler, MessageContext $ctx): void
    {
        if (is_string($handler)) {
            // Может быть классом Conversation
            if (is_subclass_of($handler, \Tiamenti\VkBotSdk\Conversations\Conversation::class)) {
                $handler::begin($ctx);
                return;
            }
            // Или просто callable-строкой (функция)
            $handler($ctx);
            return;
        }

        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = is_object($class) ? $class : $this->container->make($class);
            $instance->{$method}($ctx);
            return;
        }

        if (is_callable($handler)) {
            $handler($ctx);
        }
    }

    /**
     * Разрешить middleware-экземпляр.
     */
    private function resolveMiddleware(callable|string $middleware): VkMiddleware
    {
        if (is_string($middleware)) {
            return $this->container->make($middleware);
        }

        // callable-обёртка
        return new class ($middleware) implements VkMiddleware {
            public function __construct(private readonly mixed $callable) {}

            public function handle(MessageContext $ctx, callable $next): void
            {
                ($this->callable)($ctx, $next);
            }
        };
    }

    /**
     * Привести handler к callable/string.
     */
    private function resolveCallable(callable|string|array $handler): mixed
    {
        return $handler;
    }
}
