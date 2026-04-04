<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Context\Concerns;

use Tiamenti\VkBotSdk\Exceptions\VkApiException;
use VK\Client\VKApiClient;

/**
 * Трейт: пересылка сообщений и управление активностью.
 *
 * @property VKApiClient $api
 * @property string      $token
 * @property int         $peerId
 */
trait CanForward
{
    /**
     * Переслать сообщение.
     *
     * @param int      $messageId   ID пересылаемого сообщения
     * @param int|null $toPeerId    Назначение (по умолчанию — текущий чат)
     * @param bool     $asForward   Отображать как пересланное сообщение
     * @throws VkApiException
     */
    public function forwardMessage(
        int $messageId,
        ?int $toPeerId = null,
        bool $asForward = true,
    ): void {
        $forwardData = json_encode([
            'peer_id'     => $this->peerId,
            'message_ids' => [$messageId],
            'is_reply'    => ! $asForward,
        ], JSON_THROW_ON_ERROR);

        $this->api->messages()->send($this->token, [
            'peer_id'   => $toPeerId ?? $this->peerId,
            'random_id' => 0,
            'forward'   => $forwardData,
        ]);
    }

    /**
     * Установить статус активности (печатает, записывает голосовое и т.д.).
     *
     * @param string $type Тип активности: 'typing', 'audiomessage'
     * @throws VkApiException
     */
    public function setActivity(string $type = 'typing'): void
    {
        $this->api->messages()->setActivity($this->token, [
            'peer_id' => $this->peerId,
            'type'    => $type,
        ]);
    }

    /**
     * Ответить на событие callback-кнопки (event button).
     *
     * @param string               $eventId   ID события из объекта message_event
     * @param int                  $userId    ID пользователя
     * @param array<string, mixed> $eventData Данные ответа
     * @throws VkApiException
     */
    public function sendMessageEventAnswer(
        string $eventId,
        int $userId,
        array $eventData = [],
    ): void {
        $params = [
            'event_id'  => $eventId,
            'user_id'   => $userId,
            'peer_id'   => $this->peerId,
        ];

        if (! empty($eventData)) {
            $params['event_data'] = json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $this->api->messages()->sendMessageEventAnswer($this->token, $params);
    }
}
