<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Context;

use Tiamenti\VkBotSdk\Context\Concerns\CanForward;
use Tiamenti\VkBotSdk\Context\Concerns\CanReply;
use Tiamenti\VkBotSdk\Enums\EventType;
use VK\Client\VKApiClient;

/**
 * Контекст входящего VK-события.
 *
 * Единственный аргумент в каждом обработчике. Предоставляет методы
 * для чтения данных события и ответа пользователю.
 *
 * Пример:
 * ```php
 * Vk::hears('привет', function (MessageContext $ctx): void {
 *     $ctx->reply("Привет, {$ctx->getFromId()}!");
 * });
 * ```
 */
final class MessageContext
{
    use CanForward;
    use CanReply;

    /**
     * @param  VKApiClient  $api  Клиент VK API
     * @param  string  $token  Токен сообщества
     * @param  EventType  $event  Тип события
     * @param  array<string, mixed>  $eventObject  Сырой объект события
     */
    public function __construct(
        private readonly VKApiClient $api,
        private readonly string $token,
        private readonly EventType $event,
        private readonly array $eventObject,
    ) {
        $this->peerId = $this->resolvePeerId();
    }

    // -------------------------------------------------------------------------
    // Геттеры
    // -------------------------------------------------------------------------

    /**
     * Получить тип события.
     */
    public function getEvent(): EventType
    {
        return $this->event;
    }

    /**
     * Получить сырой объект события (поле 'object').
     *
     * @return array<string, mixed>
     */
    public function getEventObject(): array
    {
        return $this->eventObject;
    }

    /**
     * Получить объект сообщения (только для событий типа message_*).
     *
     * @return array<string, mixed>|null
     */
    public function getMessage(): ?array
    {
        return $this->eventObject['message'] ?? $this->eventObject ?? null;
    }

    /**
     * Получить peer_id (ID беседы или пользователя).
     */
    public function getPeerId(): int
    {
        return $this->peerId;
    }

    /**
     * Получить from_id (ID отправителя).
     */
    public function getFromId(): int
    {
        $message = $this->getMessage();

        return (int) (
            $message['from_id']
            ?? $this->eventObject['user_id']
            ?? 0
        );
    }

    /**
     * Получить текст сообщения.
     */
    public function getText(): ?string
    {
        $message = $this->getMessage();
        $text = $message['text'] ?? null;

        return ($text !== null && $text !== '') ? (string) $text : null;
    }

    /**
     * Псевдоним для getText() (для совместимости с Conversation).
     */
    public function text(): ?string
    {
        return $this->getText();
    }

    /**
     * Получить payload кнопки (декодированный JSON или null).
     *
     * @return array<string, mixed>|null
     */
    public function getPayload(): ?array
    {
        $message = $this->getMessage();
        $raw = $message['payload'] ?? $this->eventObject['payload'] ?? null;

        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, associative: true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Получить ID сообщения.
     */
    public function getMessageId(): ?int
    {
        $message = $this->getMessage();

        return isset($message['id']) ? (int) $message['id'] : null;
    }

    /**
     * Получить объект ответа на callback-кнопку (event_id, user_id, peer_id).
     *
     * @return array<string, mixed>|null
     */
    public function getMessageEvent(): ?array
    {
        if ($this->event !== EventType::MessageEvent) {
            return null;
        }

        return $this->eventObject;
    }

    /**
     * Получить event_id из события callback-кнопки.
     */
    public function getEventId(): ?string
    {
        return isset($this->eventObject['event_id'])
            ? (string) $this->eventObject['event_id']
            : null;
    }

    /**
     * Получить клиент VK API.
     */
    public function getApi(): VKApiClient
    {
        return $this->api;
    }

    // -------------------------------------------------------------------------
    // Внутренние методы
    // -------------------------------------------------------------------------

    /**
     * Определить peer_id из объекта события.
     */
    private function resolvePeerId(): int
    {
        $message = $this->eventObject['message'] ?? null;

        if ($message !== null) {
            return (int) ($message['peer_id'] ?? 0);
        }

        return (int) (
            $this->eventObject['peer_id']
            ?? $this->eventObject['user_id']
            ?? 0
        );
    }
}
