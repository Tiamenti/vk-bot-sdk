<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Upload;

use Tiamenti\VkBotSdk\Upload\Pending\PendingDocumentUpload;
use Tiamenti\VkBotSdk\Upload\Pending\PendingPhotoUpload;
use Tiamenti\VkBotSdk\Upload\Pending\PendingStoryUpload;
use Tiamenti\VkBotSdk\Upload\Pending\PendingVideoUpload;
use Tiamenti\VkBotSdk\VkBot;

/**
 * Точка входа в модуль загрузки вложений.
 *
 * Доступен через фасад:
 * ```php
 * Vk::upload()->photo()->toMessages($peerId)->fromPath('/tmp/photo.jpg');
 * Vk::upload()->video()->withName('Ролик')->fromPath('/tmp/video.mp4');
 * Vk::upload()->document()->asVoiceMessage($peerId)->fromPath('/tmp/voice.ogg');
 * Vk::upload()->story()->asVideo()->forGroup(123)->fromPath('/tmp/story.mp4');
 * ```
 */
final class Uploader
{
    public function __construct(
        private readonly VkBot $bot,
        private ?string $token = null,
    ) {
        if (! $this->token) {
            $this->token = $this->bot->getToken();
        }
    }

    public function useToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Загрузить фотографию.
     *
     * Поддерживаемые места назначения:
     * - `toMessages($peerId)` — для отправки в сообщении
     * - `toWall($groupId)` — на стену
     * - `toAlbum($albumId, $groupId)` — в альбом
     * - `asOwnerPhoto()` — фото профиля
     * - `asChatPhoto($chatId)` — фото чата
     * - `asCover($groupId)` — обложка сообщества
     * - `asMarketPhoto($groupId)` — фото товара
     * - `asMarketAlbumPhoto($groupId)` — фото подборки товаров
     * - `asPollPhoto($ownerId)` — фото опроса
     */
    public function photo(): PendingPhotoUpload
    {
        return new PendingPhotoUpload(
            api: $this->bot->getApi(),
            token: $this->token,
        );
    }

    /**
     * Загрузить видео.
     *
     * Доступные параметры:
     * - `withName($name)` — заголовок
     * - `withDescription($desc)` — описание
     * - `toGroup($groupId)` — загрузить в сообщество
     * - `asPrivate()` — приватное видео
     * - `withWallpost()` — опубликовать на стене
     * - `toAlbum($albumId)` — добавить в альбом
     */
    public function video(): PendingVideoUpload
    {
        return new PendingVideoUpload(
            api: $this->bot->getApi(),
            token: $this->token,
        );
    }

    /**
     * Загрузить документ, голосовое сообщение или граффити.
     *
     * Типы:
     * - `toMessages($peerId)` — документ в сообщение
     * - `toWall($groupId)` — документ на стену
     * - `asVoiceMessage($peerId)` — голосовое сообщение (OGG/Opus)
     * - `asGraffiti($groupId)` — граффити (PNG с прозрачностью)
     */
    public function document(): PendingDocumentUpload
    {
        return new PendingDocumentUpload(
            api: $this->bot->getApi(),
            token: $this->token,
        );
    }

    /**
     * Загрузить историю (Story).
     *
     * Типы медиа:
     * - `asPhoto()` — история из фотографии
     * - `asVideo()` — история из видео
     *
     * Места назначения:
     * - `forUser()` — история пользователя
     * - `forGroup($groupId)` — история сообщества
     */
    public function story(): PendingStoryUpload
    {
        return new PendingStoryUpload(
            api: $this->bot->getApi(),
            token: $this->token,
        );
    }
}
