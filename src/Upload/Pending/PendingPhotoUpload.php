<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Upload\Pending;

use Tiamenti\VkBotSdk\Enums\AttachmentType;
use Tiamenti\VkBotSdk\Upload\Concerns\UploadsFile;
use Tiamenti\VkBotSdk\Upload\Contracts\PendingUpload;
use Tiamenti\VkBotSdk\Upload\Enums\PhotoDestination;
use Tiamenti\VkBotSdk\Upload\ValueObjects\Attachment;
use VK\Client\VKApiClient;

/**
 * Загрузка фотографий во все поддерживаемые места VK API.
 *
 * Использование:
 * ```php
 * // Фото в сообщение
 * $attachment = Vk::upload()->photo()->toMessages($peerId)->fromPath('/tmp/photo.jpg');
 *
 * // Фото на стену группы
 * $attachment = Vk::upload()->photo()->toWall(groupId: 123)->fromPath('/tmp/banner.jpg');
 *
 * // Фото из S3-потока
 * $stream = Storage::disk('s3')->readStream('photos/cover.jpg');
 * $attachment = Vk::upload()->photo()->asCover(groupId: 123)->fromStream($stream, 'cover.jpg');
 * ```
 */
final class PendingPhotoUpload implements PendingUpload
{
    use UploadsFile;

    private PhotoDestination $destination = PhotoDestination::Messages;

    /** @var array<string, mixed> Параметры конкретного места назначения */
    private array $destinationParams = [];

    public function __construct(
        private readonly VKApiClient $api,
        private readonly string $token,
    ) {}

    // -------------------------------------------------------------------------
    // Fluent-методы выбора места назначения
    // -------------------------------------------------------------------------

    /**
     * Загрузить фото для отправки в сообщении.
     */
    public function toMessages(int $peerId): self
    {
        $this->destination = PhotoDestination::Messages;
        $this->destinationParams = ['peer_id' => $peerId];

        return $this;
    }

    /**
     * Загрузить фото на стену пользователя или группы.
     */
    public function toWall(?int $groupId = null): self
    {
        $this->destination = PhotoDestination::Wall;
        $this->destinationParams = $groupId !== null ? ['group_id' => $groupId] : [];

        return $this;
    }

    /**
     * Загрузить фото в альбом.
     */
    public function toAlbum(int $albumId, ?int $groupId = null): self
    {
        $this->destination = PhotoDestination::Album;
        $this->destinationParams = ['album_id' => $albumId];

        if ($groupId !== null) {
            $this->destinationParams['group_id'] = $groupId;
        }

        return $this;
    }

    /**
     * Загрузить как фото профиля (владельца).
     */
    public function asOwnerPhoto(): self
    {
        $this->destination = PhotoDestination::OwnerPhoto;
        $this->destinationParams = [];

        return $this;
    }

    /**
     * Загрузить как фото чата.
     */
    public function asChatPhoto(int $chatId): self
    {
        $this->destination = PhotoDestination::ChatPhoto;
        $this->destinationParams = ['chat_id' => $chatId];

        return $this;
    }

    /**
     * Загрузить как обложку сообщества.
     *
     * @param  int  $groupId  ID сообщества
     * @param  int  $cropX  Начало кропа по X (px)
     * @param  int  $cropY  Начало кропа по Y (px)
     * @param  int  $cropX2  Конец кропа по X (px)
     * @param  int  $cropY2  Конец кропа по Y (px)
     */
    public function asCover(
        int $groupId,
        int $cropX = 0,
        int $cropY = 0,
        int $cropX2 = 1590,
        int $cropY2 = 400,
    ): self {
        $this->destination = PhotoDestination::Cover;
        $this->destinationParams = [
            'group_id' => $groupId,
            'crop_x' => $cropX,
            'crop_y' => $cropY,
            'crop_x2' => $cropX2,
            'crop_y2' => $cropY2,
        ];

        return $this;
    }

    /**
     * Загрузить как фото товара в магазине.
     */
    public function asMarketPhoto(int $groupId, bool $mainPhoto = false): self
    {
        $this->destination = PhotoDestination::Market;
        $this->destinationParams = [
            'group_id' => $groupId,
            'main_photo' => $mainPhoto ? 1 : 0,
        ];

        return $this;
    }

    /**
     * Загрузить как фото подборки товаров.
     */
    public function asMarketAlbumPhoto(int $groupId): self
    {
        $this->destination = PhotoDestination::MarketAlbum;
        $this->destinationParams = ['group_id' => $groupId];

        return $this;
    }

    /**
     * Загрузить как фото опроса.
     */
    public function asPollPhoto(int $ownerId): self
    {
        $this->destination = PhotoDestination::Poll;
        $this->destinationParams = ['owner_id' => $ownerId];

        return $this;
    }

    // -------------------------------------------------------------------------
    // Загрузка
    // -------------------------------------------------------------------------

    public function fromPath(string $path): Attachment
    {
        [$uploadUrl, $field, $saveCallback] = $this->resolveDestination();

        $uploaded = $this->uploadFromPath($uploadUrl, $field, $path);

        return $saveCallback($uploaded);
    }

    public function fromStream(mixed $stream, string $filename): Attachment
    {
        [$uploadUrl, $field, $saveCallback] = $this->resolveDestination();

        $uploaded = $this->uploadFromStream($uploadUrl, $field, $stream, $filename);

        return $saveCallback($uploaded);
    }

    // -------------------------------------------------------------------------
    // Внутренняя диспетчеризация
    // -------------------------------------------------------------------------

    /**
     * Разрешить параметры для текущего места назначения.
     *
     * @return array{string, string, callable} [uploadUrl, fieldName, saveCallback]
     */
    private function resolveDestination(): array
    {
        return match ($this->destination) {
            PhotoDestination::Messages => $this->resolveMessages(),
            PhotoDestination::Wall => $this->resolveWall(),
            PhotoDestination::Album => $this->resolveAlbum(),
            PhotoDestination::OwnerPhoto => $this->resolveOwnerPhoto(),
            PhotoDestination::ChatPhoto => $this->resolveChatPhoto(),
            PhotoDestination::Cover => $this->resolveCover(),
            PhotoDestination::Market => $this->resolveMarket(),
            PhotoDestination::MarketAlbum => $this->resolveMarketAlbum(),
            PhotoDestination::Poll => $this->resolvePoll(),
        };
    }

    /** @return array{string, string, callable} */
    private function resolveMessages(): array
    {
        $server = $this->api->photos()->getMessagesUploadServer($this->token, $this->destinationParams);
        $uploadUrl = (string) $server['upload_url'];

        return [$uploadUrl, 'photo', function (array $uploaded): Attachment {
            $saved = $this->api->photos()->saveMessagesPhoto($this->token, $uploaded);

            return Attachment::fromPhotoResponse((array) $saved);
        }];
    }

    /** @return array{string, string, callable} */
    private function resolveWall(): array
    {
        $server = $this->api->photos()->getWallUploadServer($this->token, $this->destinationParams);
        $uploadUrl = (string) $server['upload_url'];

        return [$uploadUrl, 'photo', function (array $uploaded): Attachment {
            $saved = $this->api->photos()->saveWallPhoto($this->token, array_merge(
                $this->destinationParams,
                $uploaded,
            ));

            return Attachment::fromPhotoResponse((array) $saved);
        }];
    }

    /** @return array{string, string, callable} */
    private function resolveAlbum(): array
    {
        $server = $this->api->photos()->getUploadServer($this->token, $this->destinationParams);
        $uploadUrl = (string) $server['upload_url'];

        return [$uploadUrl, 'file1', function (array $uploaded): Attachment {
            $saved = $this->api->photos()->save($this->token, array_merge(
                $this->destinationParams,
                $uploaded,
            ));

            return Attachment::fromPhotoResponse((array) $saved);
        }];
    }

    /** @return array{string, string, callable} */
    private function resolveOwnerPhoto(): array
    {
        $server = $this->api->photos()->getOwnerPhotoUploadServer($this->token, []);
        $uploadUrl = (string) $server['upload_url'];

        return [$uploadUrl, 'photo', function (array $uploaded): Attachment {
            $saved = $this->api->photos()->saveOwnerPhoto($this->token, $uploaded);

            return Attachment::fromPhotoResponse((array) $saved);
        }];
    }

    /** @return array{string, string, callable} */
    private function resolveChatPhoto(): array
    {
        $server = $this->api->photos()->getChatUploadServer($this->token, $this->destinationParams);
        $uploadUrl = (string) $server['upload_url'];

        // Запоминаем параметры до вызова saveCallback
        $params = $this->destinationParams;

        return [$uploadUrl, 'file', function (array $uploaded) use ($params): Attachment {
            // messages.setChatPhoto не возвращает вложение — возвращает { message_id, chat }
            // Создаём Attachment из предыдущего шага (uploaded содержит server, photo, hash)
            $this->api->messages()->setChatPhoto($this->token, [
                'file' => $uploaded['response'] ?? ($uploaded['file'] ?? ''),
            ]);

            // Возвращаем временный Attachment на основе chat_id, так как
            // setChatPhoto не предоставляет photo_id в стандартном ответе
            return new Attachment(
                type: AttachmentType::Photo,
                ownerId: 0,
                id: (int) ($params['chat_id'] ?? 0),
            );
        }];
    }

    /** @return array{string, string, callable} */
    private function resolveCover(): array
    {
        $server = $this->api->photos()->getOwnerCoverPhotoUploadServer($this->token, $this->destinationParams);
        $uploadUrl = (string) $server['upload_url'];

        return [$uploadUrl, 'photo', function (array $uploaded): Attachment {
            $saved = $this->api->photos()->saveOwnerCoverPhoto($this->token, $uploaded);

            return Attachment::fromPhotoResponse((array) $saved);
        }];
    }

    /** @return array{string, string, callable} */
    private function resolveMarket(): array
    {
        $server = $this->api->photos()->getMarketUploadServer($this->token, $this->destinationParams);
        $uploadUrl = (string) $server['upload_url'];

        return [$uploadUrl, 'file', function (array $uploaded): Attachment {
            $saved = $this->api->photos()->saveMarketPhoto($this->token, array_merge(
                $this->destinationParams,
                $uploaded,
            ));

            return Attachment::fromPhotoResponse((array) $saved);
        }];
    }

    /** @return array{string, string, callable} */
    private function resolveMarketAlbum(): array
    {
        $server = $this->api->photos()->getMarketAlbumUploadServer($this->token, $this->destinationParams);
        $uploadUrl = (string) $server['upload_url'];

        return [$uploadUrl, 'file', function (array $uploaded): Attachment {
            $saved = $this->api->photos()->saveMarketAlbumPhoto($this->token, array_merge(
                $this->destinationParams,
                $uploaded,
            ));

            return Attachment::fromPhotoResponse((array) $saved);
        }];
    }

    /** @return array{string, string, callable} */
    private function resolvePoll(): array
    {
        $server = $this->api->polls()->getPhotoUploadServer($this->token, $this->destinationParams);
        $uploadUrl = (string) $server['upload_url'];

        return [$uploadUrl, 'file', function (array $uploaded): Attachment {
            $saved = $this->api->polls()->savePhoto($this->token, $uploaded);

            return Attachment::fromPhotoResponse((array) $saved);
        }];
    }
}
