<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Upload\Pending;

use Tiamenti\VkBotSdk\Upload\Concerns\UploadsFile;
use Tiamenti\VkBotSdk\Upload\Contracts\PendingUpload;
use Tiamenti\VkBotSdk\Upload\ValueObjects\Attachment;
use VK\Client\VKApiClient;

/**
 * Загрузка документов, голосовых сообщений и граффити через VK API.
 *
 * Все три типа используют один и тот же метод docs.save, но разные
 * методы получения сервера загрузки и разные значения параметра type.
 *
 * Использование:
 * ```php
 * // Документ в сообщение
 * $attachment = Vk::upload()->document()->toMessages($peerId)->fromPath('/tmp/report.pdf');
 *
 * // Документ на стену группы
 * $attachment = Vk::upload()->document()->toWall(groupId: 123)->fromPath('/tmp/doc.docx');
 *
 * // Голосовое сообщение
 * $attachment = Vk::upload()->document()->asVoiceMessage($peerId)->fromPath('/tmp/voice.ogg');
 *
 * // Граффити
 * $attachment = Vk::upload()->document()->asGraffiti(groupId: 123)->fromPath('/tmp/art.png');
 * ```
 */
final class PendingDocumentUpload implements PendingUpload
{
    use UploadsFile;

    private const DOC_TYPE_DOC = 'doc';

    private const DOC_TYPE_AUDIO_MESSAGE = 'audio_message';

    private const DOC_TYPE_GRAFFITI = 'graffiti';

    private string $docType = self::DOC_TYPE_DOC;

    private string $serverMethod = 'messages'; // 'messages' | 'wall'

    private ?int $peerId = null;

    private ?int $groupId = null;

    private ?string $title = null;

    private ?string $tags = null;

    public function __construct(
        private readonly VKApiClient $api,
        private readonly string $token,
    ) {}

    // -------------------------------------------------------------------------
    // Fluent-методы
    // -------------------------------------------------------------------------

    /**
     * Загрузить документ для отправки в сообщении.
     */
    public function toMessages(int $peerId): self
    {
        $this->serverMethod = 'messages';
        $this->docType = self::DOC_TYPE_DOC;
        $this->peerId = $peerId;

        return $this;
    }

    /**
     * Загрузить документ на стену.
     */
    public function toWall(?int $groupId = null): self
    {
        $this->serverMethod = 'wall';
        $this->docType = self::DOC_TYPE_DOC;
        $this->groupId = $groupId;

        return $this;
    }

    /**
     * Загрузить как голосовое сообщение.
     * Файл должен быть в формате OGG с кодеком Opus.
     */
    public function asVoiceMessage(int $peerId): self
    {
        $this->serverMethod = 'messages';
        $this->docType = self::DOC_TYPE_AUDIO_MESSAGE;
        $this->peerId = $peerId;

        return $this;
    }

    /**
     * Загрузить как граффити.
     * Файл должен быть PNG с прозрачностью.
     */
    public function asGraffiti(?int $groupId = null): self
    {
        $this->serverMethod = 'wall';
        $this->docType = self::DOC_TYPE_GRAFFITI;
        $this->groupId = $groupId;

        return $this;
    }

    /**
     * Задать заголовок документа.
     */
    public function withTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Задать теги документа (через запятую).
     */
    public function withTags(string $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Загрузка
    // -------------------------------------------------------------------------

    public function fromPath(string $path): Attachment
    {
        $uploadUrl = $this->getUploadServer();
        $uploaded = $this->uploadFromPath($uploadUrl, 'file', $path);

        return $this->save($uploaded);
    }

    public function fromStream(mixed $stream, string $filename): Attachment
    {
        $uploadUrl = $this->getUploadServer();
        $uploaded = $this->uploadFromStream($uploadUrl, 'file', $stream, $filename);

        return $this->save($uploaded);
    }

    // -------------------------------------------------------------------------
    // Приватные методы
    // -------------------------------------------------------------------------

    /**
     * Получить URL сервера загрузки в зависимости от метода и типа.
     */
    private function getUploadServer(): string
    {
        $params = ['type' => $this->docType];

        if ($this->serverMethod === 'wall') {
            if ($this->groupId !== null) {
                $params['group_id'] = $this->groupId;
            }
            $server = $this->api->docs()->getWallUploadServer($this->token, $params);
        } else {
            if ($this->peerId !== null) {
                $params['peer_id'] = $this->peerId;
            }
            $server = $this->api->docs()->getMessagesUploadServer($this->token, $params);
        }

        return (string) $server['upload_url'];
    }

    /**
     * Сохранить загруженный документ через docs.save.
     *
     * @param  array<string, mixed>  $uploaded  Ответ сервера загрузки
     */
    private function save(array $uploaded): Attachment
    {
        $params = ['file' => $uploaded['file'] ?? ''];

        if ($this->title !== null) {
            $params['title'] = $this->title;
        }

        if ($this->tags !== null) {
            $params['tags'] = $this->tags;
        }

        $saved = (array) $this->api->docs()->save($this->token, $params);

        return Attachment::fromDocResponse($saved);
    }
}
