<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Upload\Pending;

use Tiamenti\VkBotSdk\Upload\Concerns\UploadsFile;
use Tiamenti\VkBotSdk\Upload\Contracts\PendingUpload;
use Tiamenti\VkBotSdk\Upload\ValueObjects\Attachment;
use VK\Client\VKApiClient;

/**
 * Загрузка видео через VK API.
 *
 * Видео — единственный тип, где getUploadServer и save — один и тот же вызов:
 * `video.save` сразу возвращает `upload_url` + метаданные. После загрузки
 * файла на upload_url дополнительных сохраняющих вызовов не нужно.
 *
 * Использование:
 * ```php
 * $attachment = Vk::upload()->video()
 *     ->withName('Мой ролик')
 *     ->withDescription('Описание видео')
 *     ->fromPath('/tmp/video.mp4');
 *
 * // Загрузить в группу
 * $attachment = Vk::upload()->video()
 *     ->toGroup(groupId: 123)
 *     ->fromPath('/tmp/promo.mp4');
 *
 * // Из S3
 * $stream = Storage::disk('s3')->readStream('videos/promo.mp4');
 * $attachment = Vk::upload()->video()->fromStream($stream, 'promo.mp4');
 * ```
 */
final class PendingVideoUpload implements PendingUpload
{
    use UploadsFile;

    private ?string $name = null;

    private ?string $description = null;

    private ?int $groupId = null;

    private bool $isPrivate = false;

    private bool $wallpost = false;

    private ?string $link = null;

    private ?int $albumId = null;

    public function __construct(
        private readonly VKApiClient $api,
        private readonly string $token,
    ) {}

    // -------------------------------------------------------------------------
    // Fluent-параметры
    // -------------------------------------------------------------------------

    /**
     * Название видео.
     */
    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Описание видео.
     */
    public function withDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Загрузить в сообщество.
     */
    public function toGroup(int $groupId): self
    {
        $this->groupId = $groupId;

        return $this;
    }

    /**
     * Сделать видео приватным (только по прямой ссылке).
     */
    public function asPrivate(bool $value = true): self
    {
        $this->isPrivate = $value;

        return $this;
    }

    /**
     * Опубликовать на стене после загрузки.
     */
    public function withWallpost(bool $value = true): self
    {
        $this->wallpost = $value;

        return $this;
    }

    /**
     * Загрузить видео по внешней ссылке (без передачи файла).
     * При использовании этого метода fromPath/fromStream игнорируют путь
     * и VK сам скачивает видео по указанной ссылке.
     */
    public function fromLink(string $link): Attachment
    {
        $this->link = $link;

        $saved = $this->callVideoSave();

        return Attachment::fromVideoResponse($saved);
    }

    /**
     * Поместить в альбом.
     */
    public function toAlbum(int $albumId): self
    {
        $this->albumId = $albumId;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Загрузка
    // -------------------------------------------------------------------------

    public function fromPath(string $path): Attachment
    {
        return $this->withRetry(function () use ($path): Attachment {
            // video.save возвращает upload_url напрямую — нет отдельного getUploadServer.
            // Вызываем его заново на каждой попытке чтобы получить актуальный URL.
            $saved = $this->callVideoSave();
            $uploadUrl = (string) $saved['upload_url'];
            $this->uploadFromPath($uploadUrl, 'video_file', $path);

            return Attachment::fromVideoResponse($saved);
        });
    }

    public function fromStream(mixed $stream, string $filename): Attachment
    {
        return $this->withRetry(function () use ($stream, $filename): Attachment {
            $this->rewindStreamIfSeekable($stream);
            $saved = $this->callVideoSave();
            $uploadUrl = (string) $saved['upload_url'];
            $this->uploadFromStream($uploadUrl, 'video_file', $stream, $filename);

            return Attachment::fromVideoResponse($saved);
        });
    }

    // -------------------------------------------------------------------------
    // Приватные методы
    // -------------------------------------------------------------------------

    /**
     * Вызвать video.save и получить upload_url + метаданные.
     *
     * @return array<string, mixed>
     */
    private function callVideoSave(): array
    {
        $params = [];

        if ($this->name !== null) {
            $params['name'] = $this->name;
        }

        if ($this->description !== null) {
            $params['description'] = $this->description;
        }

        if ($this->groupId !== null) {
            $params['group_id'] = $this->groupId;
        }

        if ($this->isPrivate) {
            $params['privacy_view'] = ['nobody'];
            $params['is_private'] = 1;
        }

        if ($this->wallpost) {
            $params['wallpost'] = 1;
        }

        if ($this->link !== null) {
            $params['link'] = $this->link;
        }

        if ($this->albumId !== null) {
            $params['album_id'] = $this->albumId;
        }

        return (array) $this->api->video()->save($this->token, $params);
    }
}
