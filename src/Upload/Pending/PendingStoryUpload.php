<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Upload\Pending;

use Tiamenti\VkBotSdk\Upload\Concerns\UploadsFile;
use Tiamenti\VkBotSdk\Upload\Contracts\PendingUpload;
use Tiamenti\VkBotSdk\Upload\ValueObjects\Attachment;
use VK\Client\VKApiClient;

/**
 * Загрузка историй (Stories) через VK API.
 *
 * Фото и видео истории используют разные серверы загрузки и поля формы,
 * но одинаковый метод сохранения — stories.save.
 *
 * Использование:
 * ```php
 * // Фото-история для пользователя
 * $attachment = Vk::upload()->story()->asPhoto()->forUser()->fromPath('/tmp/story.jpg');
 *
 * // Видео-история для группы
 * $attachment = Vk::upload()->story()->asVideo()->forGroup(groupId: 123)->fromPath('/tmp/story.mp4');
 *
 * // Из потока
 * $stream = Storage::disk('s3')->readStream('stories/today.mp4');
 * $attachment = Vk::upload()->story()->asVideo()->fromStream($stream, 'today.mp4');
 * ```
 */
final class PendingStoryUpload implements PendingUpload
{
    use UploadsFile;

    private bool $isVideo = false;

    private bool $forGroup = false;

    private ?int $groupId = null;

    /** @var bool Разрешить ответы на историю */
    private bool $replyAllowed = true;

    /** @var string|null Ссылка-стикер */
    private ?string $linkText = null;

    private ?string $linkUrl = null;

    public function __construct(
        private readonly VKApiClient $api,
        private readonly string $token,
    ) {}

    // -------------------------------------------------------------------------
    // Fluent-методы: тип медиа
    // -------------------------------------------------------------------------

    /**
     * История из фотографии.
     */
    public function asPhoto(): self
    {
        $this->isVideo = false;

        return $this;
    }

    /**
     * История из видео.
     */
    public function asVideo(): self
    {
        $this->isVideo = true;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Fluent-методы: место назначения
    // -------------------------------------------------------------------------

    /**
     * Загрузить историю для пользователя.
     */
    public function forUser(): self
    {
        $this->forGroup = false;
        $this->groupId = null;

        return $this;
    }

    /**
     * Загрузить историю для сообщества.
     */
    public function forGroup(int $groupId): self
    {
        $this->forGroup = true;
        $this->groupId = $groupId;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Fluent-методы: параметры истории
    // -------------------------------------------------------------------------

    /**
     * Разрешить или запретить ответы на историю.
     */
    public function allowReply(bool $allow = true): self
    {
        $this->replyAllowed = $allow;

        return $this;
    }

    /**
     * Добавить стикер-ссылку на историю.
     */
    public function withLink(string $text, string $url): self
    {
        $this->linkText = $text;
        $this->linkUrl = $url;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Загрузка
    // -------------------------------------------------------------------------

    public function fromPath(string $path): Attachment
    {
        [$uploadUrl, $field] = $this->getUploadServer();
        $uploaded = $this->uploadFromPath($uploadUrl, $field, $path);

        return $this->save($uploaded);
    }

    public function fromStream(mixed $stream, string $filename): Attachment
    {
        [$uploadUrl, $field] = $this->getUploadServer();
        $uploaded = $this->uploadFromStream($uploadUrl, $field, $stream, $filename);

        return $this->save($uploaded);
    }

    // -------------------------------------------------------------------------
    // Приватные методы
    // -------------------------------------------------------------------------

    /**
     * @return array{string, string} [uploadUrl, fieldName]
     */
    private function getUploadServer(): array
    {
        $params = $this->buildServerParams();

        if ($this->isVideo) {
            $server = $this->api->stories()->getVideoUploadServer($this->token, $params);
            $field = 'video_file';
        } else {
            $server = $this->api->stories()->getPhotoUploadServer($this->token, $params);
            $field = 'file';
        }

        return [(string) $server['upload_result'], $field];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildServerParams(): array
    {
        $params = [
            'add_to_news' => 1,
            'reply_to_story' => $this->replyAllowed ? 'all' : 'none',
        ];

        if ($this->forGroup && $this->groupId !== null) {
            $params['group_id'] = $this->groupId;
        }

        if ($this->linkText !== null && $this->linkUrl !== null) {
            $params['link_text'] = $this->linkText;
            $params['link_url'] = $this->linkUrl;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $uploaded
     */
    private function save(array $uploaded): Attachment
    {
        // stories.save принимает upload_results — строку из ответа загрузки
        $uploadResults = $uploaded['upload_result'] ?? ($uploaded['response'] ?? '');

        $saved = (array) $this->api->stories()->save($this->token, [
            'upload_results' => $uploadResults,
        ]);

        return Attachment::fromStoryResponse($saved);
    }
}
