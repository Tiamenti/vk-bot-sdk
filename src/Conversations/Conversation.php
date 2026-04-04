<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Conversations;

use Illuminate\Container\Container;
use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Exceptions\ConversationException;

/**
 * Базовый класс для сценариев диалога (Conversations).
 *
 * Пример:
 * ```php
 * class RegistrationConversation extends Conversation
 * {
 *     protected ?string $step = 'askName';
 *
 *     public function askName(MessageContext $ctx): void
 *     {
 *         $ctx->reply('Как тебя зовут?');
 *         $this->next('askAge');
 *     }
 *
 *     public function askAge(MessageContext $ctx): void
 *     {
 *         $this->set('name', $ctx->text());
 *         $ctx->reply('Сколько тебе лет?');
 *         $this->next('finish');
 *     }
 *
 *     public function finish(MessageContext $ctx): void
 *     {
 *         $ctx->reply("Привет, {$this->get('name')}!");
 *         $this->end();
 *     }
 * }
 * ```
 */
abstract class Conversation
{
    /**
     * Начальный шаг диалога.
     * Если не задан, используется метод 'start'.
     */
    protected ?string $step = null;

    /** @var array<string, mixed> Данные диалога */
    private array $data = [];

    private bool $skipMiddlewares = false;

    public function __construct(
        private readonly ConversationManager $manager,
        private readonly int $peerId,
        array $initialData = [],
    ) {
        $this->data = $initialData;
    }

    // -------------------------------------------------------------------------
    // Публичный API для шагов
    // -------------------------------------------------------------------------

    /**
     * Перейти к следующему шагу диалога.
     */
    public function next(string $step): void
    {
        // Сохраняем изменённые данные перед сменой шага
        $this->manager->updateData($this->peerId, $this->data);
        $this->manager->nextStep($this->peerId, $step);
    }

    /**
     * Завершить диалог.
     */
    public function end(): void
    {
        $this->manager->end($this->peerId);
    }

    /**
     * Сохранить значение в данных диалога.
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Получить значение из данных диалога.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Получить все данные диалога.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Установить флаг пропуска middleware для следующего шага.
     */
    public function setSkipMiddlewares(bool $skip): void
    {
        $this->skipMiddlewares = $skip;
    }

    public function shouldSkipMiddlewares(): bool
    {
        return $this->skipMiddlewares;
    }

    // -------------------------------------------------------------------------
    // Статический метод запуска
    // -------------------------------------------------------------------------

    /**
     * Запустить диалог.
     *
     * @param  MessageContext  $ctx  Контекст события
     * @param  array<string, mixed>  $args  Дополнительные данные
     *
     * @throws ConversationException
     */
    public static function begin(MessageContext $ctx, array $args = []): void
    {
        /** @var ConversationManager $manager */
        $manager = Container::getInstance()->make(ConversationManager::class);

        $instance = new static($manager, $ctx->getPeerId(), $args);

        $startStep = $instance->resolveStartStep();

        if (! method_exists($instance, $startStep)) {
            throw ConversationException::stepNotFound(static::class, $startStep);
        }

        // Регистрируем диалог ДО вызова шага, чтобы next() мог обновить его
        $manager->start($ctx->getPeerId(), static::class, $startStep, $args);

        $instance->{$startStep}($ctx);
    }

    // -------------------------------------------------------------------------
    // Внутренние методы
    // -------------------------------------------------------------------------

    /**
     * Определить начальный шаг диалога.
     *
     * @throws ConversationException
     */
    protected function resolveStartStep(): string
    {
        if ($this->step !== null) {
            return $this->step;
        }

        if (method_exists($this, 'start')) {
            return 'start';
        }

        throw ConversationException::missingStartStep(static::class);
    }
}
