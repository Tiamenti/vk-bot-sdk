<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Context\Concerns;

use Tiamenti\VkBotSdk\Exceptions\VkApiException;
use Tiamenti\VkBotSdk\Keyboard\Keyboard;
use Tiamenti\VkBotSdk\Upload\ValueObjects\Attachment;
use VK\Client\VKApiClient;

/**
 * Трейт: отправка и редактирование сообщений.
 *
 * @property VKApiClient $api
 * @property string $token
 * @property int $peerId
 */
trait CanReply
{
    /**
     * Отправить сообщение в текущий чат.
     *
     * @param  string|null  $message  Текст сообщения
     * @param  string|array|Attachment|null  $attachment  Вложение (строка, массив, или объект Attachment)
     * @param  Keyboard|array|null  $keyboard  Клавиатура
     * @param  int|null  $stickerId  ID стикера
     * @param  bool  $dontParseLinks  Не парсить ссылки
     * @param  bool  $disableMentions  Отключить упоминания
     * @param  string|null  $contentSource  Источник контента
     * @param  int|null  $randomId  Случайный ID (0 по умолчанию)
     * @param  int|null  $replyTo  ID сообщения для ответа
     * @param  string|null  $forwardMessages  ID пересылаемых сообщений
     * @param  string|null  $template  JSON-шаблон сообщения (карусель и т.д.)
     * @param  int|null  $expire  Время жизни сообщения (для бесед)
     * @return int ID отправленного сообщения
     *
     * @throws VkApiException
     */
    public function reply(
        ?string $message = null,
        string|array|Attachment|null $attachment = null,
        Keyboard|array|null $keyboard = null,
        ?int $stickerId = null,
        bool $dontParseLinks = false,
        bool $disableMentions = false,
        ?string $contentSource = null,
        ?int $randomId = null,
        ?int $replyTo = null,
        ?string $forwardMessages = null,
        ?string $template = null,
        ?int $expire = null,
    ): int {
        $params = [
            'peer_id' => $this->peerId,
            'random_id' => $randomId ?? 0,
        ];

        if ($message !== null) {
            $params['message'] = $message;
        }

        if ($attachment !== null) {
            $params['attachment'] = $this->normalizeAttachment($attachment);
        }

        if ($keyboard !== null) {
            $params['keyboard'] = $keyboard instanceof Keyboard
                ? $keyboard->toJson()
                : json_encode($keyboard, JSON_UNESCAPED_UNICODE);
        }

        if ($stickerId !== null) {
            $params['sticker_id'] = $stickerId;
        }

        if ($dontParseLinks) {
            $params['dont_parse_links'] = 1;
        }

        if ($disableMentions) {
            $params['disable_mentions'] = 1;
        }

        if ($contentSource !== null) {
            $params['content_source'] = $contentSource;
        }

        if ($replyTo !== null) {
            $params['reply_to'] = $replyTo;
        }

        if ($forwardMessages !== null) {
            $params['forward_messages'] = $forwardMessages;
        }

        if ($template !== null) {
            $params['template'] = $template;
        }

        if ($expire !== null) {
            $params['expire_ttl'] = $expire;
        }

        return (int) $this->api->messages()->send($this->token, $params);
    }

    /**
     * Отправить стикер в текущий чат.
     *
     * @throws VkApiException
     */
    public function sendSticker(int $stickerId): int
    {
        return $this->reply(stickerId: $stickerId);
    }

    /**
     * Отредактировать существующее сообщение.
     *
     * @param  int  $messageId  ID сообщения
     * @param  string  $message  Новый текст
     * @param  Keyboard|array|null  $keyboard  Новая клавиатура
     * @param  string|array|null  $attachment  Новые вложения
     *
     * @throws VkApiException
     */
    public function editMessage(
        int $messageId,
        string $message,
        Keyboard|array|null $keyboard = null,
        string|array|Attachment|null $attachment = null,
    ): void {
        $params = [
            'peer_id' => $this->peerId,
            'message_id' => $messageId,
            'message' => $message,
        ];

        if ($keyboard !== null) {
            $params['keyboard'] = $keyboard instanceof Keyboard
                ? $keyboard->toJson()
                : json_encode($keyboard, JSON_UNESCAPED_UNICODE);
        }

        if ($attachment !== null) {
            $params['attachment'] = $this->normalizeAttachment($attachment);
        }

        $this->api->messages()->edit($this->token, $params);
    }

    /**
     * Удалить сообщение(я).
     *
     * @param  int|int[]  $messageIds  ID сообщения или массив ID
     *
     * @throws VkApiException
     */
    public function deleteMessage(int|array $messageIds): void
    {
        $this->api->messages()->delete($this->token, [
            'message_ids' => implode(',', (array) $messageIds),
            'delete_for_all' => 1,
        ]);
    }

    /**
     * Нормализовать вложение в строку для VK API.
     *
     * Принимает:
     *   - Attachment — кастуется через (string)
     *   - string — используется как есть
     *   - array — каждый элемент кастуется к строке, затем join через запятую
     *
     * @param  string|array<int, string|Attachment>|Attachment  $attachment
     */
    private function normalizeAttachment(string|array|Attachment $attachment): string
    {
        if ($attachment instanceof Attachment) {
            return (string) $attachment;
        }

        if (is_string($attachment)) {
            return $attachment;
        }

        return implode(',', array_map(
            static fn (string|Attachment $item): string => (string) $item,
            $attachment,
        ));
    }
}
