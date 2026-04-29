<?php

declare(strict_types=1);

use Tiamenti\VkBotSdk\Enums\AttachmentType;
use Tiamenti\VkBotSdk\Upload\ValueObjects\Attachment;

describe('Attachment::__toString()', function (): void {

    it('формирует строку без accessKey', function (): void {
        $attachment = new Attachment(
            type: AttachmentType::Photo,
            ownerId: -12345,
            id: 67890,
        );

        expect((string) $attachment)->toBe('photo-12345_67890');
    });

    it('формирует строку с accessKey', function (): void {
        $attachment = new Attachment(
            type: AttachmentType::Photo,
            ownerId: -12345,
            id: 67890,
            accessKey: 'abc123xyz',
        );

        expect((string) $attachment)->toBe('photo-12345_67890_abc123xyz');
    });

    it('корректно обрабатывает положительный ownerId (личное фото)', function (): void {
        $attachment = new Attachment(
            type: AttachmentType::Photo,
            ownerId: 123456,
            id: 789,
        );

        expect((string) $attachment)->toBe('photo123456_789');
    });

    it('работает для типа doc', function (): void {
        $attachment = new Attachment(
            type: AttachmentType::Document,
            ownerId: 123456,
            id: 111,
            accessKey: 'key',
        );

        expect((string) $attachment)->toBe('doc123456_111_key');
    });

    it('работает для типа video', function (): void {
        $attachment = new Attachment(
            type: AttachmentType::Video,
            ownerId: -9999,
            id: 42,
        );

        expect((string) $attachment)->toBe('video-9999_42');
    });

    it('работает для типа audio_message', function (): void {
        $attachment = new Attachment(
            type: AttachmentType::AudioMessage,
            ownerId: 100,
            id: 200,
        );

        expect((string) $attachment)->toBe('audio_message100_200');
    });

});

describe('Attachment::fromPhotoResponse()', function (): void {

    it('парсит одиночный объект фото', function (): void {
        $response = ['owner_id' => -123, 'id' => 456, 'access_key' => 'key123'];
        $attachment = Attachment::fromPhotoResponse($response);

        expect($attachment->type)->toBe(AttachmentType::Photo);
        expect($attachment->ownerId)->toBe(-123);
        expect($attachment->id)->toBe(456);
        expect($attachment->accessKey)->toBe('key123');
    });

    it('парсит массив фотографий (photos.save)', function (): void {
        $response = [['owner_id' => -123, 'id' => 456]];
        $attachment = Attachment::fromPhotoResponse($response);

        expect($attachment->ownerId)->toBe(-123);
        expect($attachment->id)->toBe(456);
        expect($attachment->accessKey)->toBeNull();
    });

});

describe('Attachment::fromDocResponse()', function (): void {

    it('парсит обычный документ (type=doc)', function (): void {
        $saved = [
            'type' => 'doc',
            'doc' => ['owner_id' => 111, 'id' => 222, 'access_key' => 'dockey'],
        ];
        $attachment = Attachment::fromDocResponse($saved);

        expect($attachment->type)->toBe(AttachmentType::Document);
        expect($attachment->ownerId)->toBe(111);
        expect($attachment->id)->toBe(222);
        expect($attachment->accessKey)->toBe('dockey');
        expect((string) $attachment)->toBe('doc111_222_dockey');
    });

    it('парсит голосовое сообщение (type=audio_message)', function (): void {
        $saved = [
            'type' => 'audio_message',
            'audio_message' => ['owner_id' => 333, 'id' => 444],
        ];
        $attachment = Attachment::fromDocResponse($saved);

        expect($attachment->type)->toBe(AttachmentType::AudioMessage);
        expect($attachment->ownerId)->toBe(333);
        expect($attachment->id)->toBe(444);
        expect((string) $attachment)->toBe('audio_message333_444');
    });

    it('парсит граффити (type=graffiti)', function (): void {
        $saved = [
            'type' => 'graffiti',
            'graffiti' => ['owner_id' => 555, 'id' => 666],
        ];
        $attachment = Attachment::fromDocResponse($saved);

        expect($attachment->type)->toBe(AttachmentType::Graffiti);
        expect((string) $attachment)->toBe('graffiti555_666');
    });

    it('fallback на doc при отсутствии type', function (): void {
        $saved = [
            'doc' => ['owner_id' => 1, 'id' => 2],
        ];
        $attachment = Attachment::fromDocResponse($saved);

        expect($attachment->type)->toBe(AttachmentType::Document);
    });

});

describe('Attachment::fromVideoResponse()', function (): void {

    it('парсит ответ video.save', function (): void {
        $saved = [
            'owner_id' => -999,
            'video_id' => 12345,
            'upload_url' => 'https://upload.vk.com/...',
        ];
        $attachment = Attachment::fromVideoResponse($saved);

        expect($attachment->type)->toBe(AttachmentType::Video);
        expect($attachment->ownerId)->toBe(-999);
        expect($attachment->id)->toBe(12345);
        expect((string) $attachment)->toBe('video-999_12345');
    });

});

describe('Attachment::fromStoryResponse()', function (): void {

    it('парсит ответ stories.save с items', function (): void {
        $saved = ['items' => [['owner_id' => 777, 'id' => 888]]];
        $attachment = Attachment::fromStoryResponse($saved);

        expect($attachment->type)->toBe(AttachmentType::Story);
        expect($attachment->ownerId)->toBe(777);
        expect($attachment->id)->toBe(888);
    });

    it('парсит плоский ответ без items', function (): void {
        $saved = ['owner_id' => 10, 'id' => 20];
        $attachment = Attachment::fromStoryResponse($saved);

        expect($attachment->ownerId)->toBe(10);
        expect($attachment->id)->toBe(20);
    });

});
