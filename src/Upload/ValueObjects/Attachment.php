<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Upload\ValueObjects;

use Tiamenti\VkBotSdk\Enums\AttachmentType;

/**
 * Value object вложения VK.
 *
 * Представляет загруженный или существующий медиафайл и умеет
 * сериализоваться в строку формата, который принимает messages.send.
 *
 * Формат: {type}{ownerId}_{id}
 *          {type}{ownerId}_{id}_{accessKey}  — если есть ключ доступа
 *
 * Примеры:
 *   photo-12345_67890
 *   doc123456_789_abc123
 *
 * @see https://dev.vk.com/ru/reference/objects/attachments-message
 */
final readonly class Attachment
{
    public function __construct(
        public AttachmentType $type,
        public int $ownerId,
        public int $id,
        public ?string $accessKey = null,
    ) {}

    /**
     * Создать вложение из сырого ответа photos.save*.
     *
     * Если ответ — массив фотографий, берём первый элемент.
     *
     * @param  array<string, mixed>|array<int, array<string, mixed>>  $response
     */
    public static function fromPhotoResponse(array $response): self
    {
        // photos.save возвращает массив объектов, остальные — сам объект
        $photo = isset($response[0]) ? $response[0] : $response;

        return new self(
            type: AttachmentType::Photo,
            ownerId: (int) ($photo['owner_id'] ?? 0),
            id: (int) ($photo['id'] ?? 0),
            accessKey: isset($photo['access_key']) ? (string) $photo['access_key'] : null,
        );
    }

    /**
     * Создать вложение из сырого ответа docs.save.
     *
     * Ответ содержит поле 'type' ('doc', 'audio_message', 'graffiti'),
     * а сам объект лежит под этим же ключом.
     *
     * @param  array<string, mixed>  $saved
     */
    public static function fromDocResponse(array $saved): self
    {
        $typeStr = (string) ($saved['type'] ?? 'doc');
        $type = AttachmentType::tryFrom($typeStr) ?? AttachmentType::Document;
        $object = (array) ($saved[$typeStr] ?? []);

        return new self(
            type: $type,
            ownerId: (int) ($object['owner_id'] ?? 0),
            id: (int) ($object['id'] ?? 0),
            accessKey: isset($object['access_key']) ? (string) $object['access_key'] : null,
        );
    }

    /**
     * Создать вложение из ответа video.save.
     *
     * video.save возвращает upload_url + video_id + owner_id напрямую.
     *
     * @param  array<string, mixed>  $saved
     */
    public static function fromVideoResponse(array $saved): self
    {
        return new self(
            type: AttachmentType::Video,
            ownerId: (int) ($saved['owner_id'] ?? 0),
            id: (int) ($saved['video_id'] ?? 0),
            accessKey: isset($saved['access_key']) ? (string) $saved['access_key'] : null,
        );
    }

    /**
     * Создать вложение из ответа stories.save.
     *
     * @param  array<string, mixed>  $saved
     */
    public static function fromStoryResponse(array $saved): self
    {
        // stories.save возвращает { items: [{...}] }
        $story = (array) ($saved['items'][0] ?? $saved);

        return new self(
            type: AttachmentType::Story,
            ownerId: (int) ($story['owner_id'] ?? 0),
            id: (int) ($story['id'] ?? 0),
        );
    }

    /**
     * Строковое представление для передачи в attachment параметр VK API.
     */
    public function toString(): string
    {
        $base = "{$this->type->value}{$this->ownerId}_{$this->id}";

        return $this->accessKey !== null
            ? "{$base}_{$this->accessKey}"
            : $base;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
