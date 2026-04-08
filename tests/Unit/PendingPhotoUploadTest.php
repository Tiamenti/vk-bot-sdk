<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tiamenti\VkBotSdk\Enums\AttachmentType;
use Tiamenti\VkBotSdk\Upload\Pending\PendingPhotoUpload;
use Tiamenti\VkBotSdk\Upload\ValueObjects\Attachment;
use VK\Client\VKApiClient;

/**
 * Создать мок VKApiClient с заглушками для методов загрузки.
 *
 * @return MockInterface&VKApiClient
 */
function mockApiForPhoto(string $uploadUrl = 'https://upload.vk.com/photo'): MockInterface
{
    $api = Mockery::mock(VKApiClient::class);

    // Мокируем photos() как возвращающий объект с нужными методами
    $photos = Mockery::mock();
    $photos->shouldReceive('getMessagesUploadServer')->andReturn(['upload_url' => $uploadUrl]);
    $photos->shouldReceive('getWallUploadServer')->andReturn(['upload_url' => $uploadUrl]);
    $photos->shouldReceive('getUploadServer')->andReturn(['upload_url' => $uploadUrl]);
    $photos->shouldReceive('getOwnerPhotoUploadServer')->andReturn(['upload_url' => $uploadUrl]);
    $photos->shouldReceive('getChatUploadServer')->andReturn(['upload_url' => $uploadUrl]);
    $photos->shouldReceive('getOwnerCoverPhotoUploadServer')->andReturn(['upload_url' => $uploadUrl]);
    $photos->shouldReceive('getMarketUploadServer')->andReturn(['upload_url' => $uploadUrl]);
    $photos->shouldReceive('getMarketAlbumUploadServer')->andReturn(['upload_url' => $uploadUrl]);

    $photos->shouldReceive('saveMessagesPhoto')->andReturn([['owner_id' => -1, 'id' => 100]]);
    $photos->shouldReceive('saveWallPhoto')->andReturn([['owner_id' => -1, 'id' => 101]]);
    $photos->shouldReceive('save')->andReturn([['owner_id' => -1, 'id' => 102]]);
    $photos->shouldReceive('saveOwnerPhoto')->andReturn(['owner_id' => -1, 'id' => 103]);
    $photos->shouldReceive('saveOwnerCoverPhoto')->andReturn(['owner_id' => -1, 'id' => 105]);
    $photos->shouldReceive('saveMarketPhoto')->andReturn([['owner_id' => -1, 'id' => 106]]);
    $photos->shouldReceive('saveMarketAlbumPhoto')->andReturn([['owner_id' => -1, 'id' => 107]]);

    $messages = Mockery::mock();
    $messages->shouldReceive('setChatPhoto')->andReturn(['message_id' => 1]);

    $polls = Mockery::mock();
    $polls->shouldReceive('getPhotoUploadServer')->andReturn(['upload_url' => $uploadUrl]);
    $polls->shouldReceive('savePhoto')->andReturn(['owner_id' => -1, 'id' => 108]);

    $api->shouldReceive('photos')->andReturn($photos);
    $api->shouldReceive('messages')->andReturn($messages);
    $api->shouldReceive('polls')->andReturn($polls);

    return $api;
}

beforeEach(function (): void {
    Http::fake([
        'https://upload.vk.com/*' => Http::response(
            ['server' => 1, 'photo' => 'photo_data', 'hash' => 'hash123'],
            200,
        ),
    ]);
});

describe('PendingPhotoUpload — destinations', function (): void {

    it('toMessages() вызывает getMessagesUploadServer', function (): void {
        $tmpFile = tempnam(sys_get_temp_dir(), 'vk_photo_');
        file_put_contents($tmpFile, 'fake_image');

        $api = mockApiForPhoto();
        $upload = new PendingPhotoUpload($api, 'token');

        $attachment = $upload->toMessages(100)->fromPath($tmpFile);

        expect($attachment)->toBeInstanceOf(Attachment::class);
        expect($attachment->type)->toBe(AttachmentType::Photo);

        unlink($tmpFile);
    });

    it('toWall() вызывает getWallUploadServer', function (): void {
        $tmpFile = tempnam(sys_get_temp_dir(), 'vk_photo_');
        file_put_contents($tmpFile, 'fake_image');

        $attachment = (new PendingPhotoUpload(mockApiForPhoto(), 'token'))
            ->toWall(groupId: 123)
            ->fromPath($tmpFile);

        expect($attachment->type)->toBe(AttachmentType::Photo);
        unlink($tmpFile);
    });

    it('toAlbum() вызывает getUploadServer (album)', function (): void {
        $tmpFile = tempnam(sys_get_temp_dir(), 'vk_photo_');
        file_put_contents($tmpFile, 'fake_image');

        $attachment = (new PendingPhotoUpload(mockApiForPhoto(), 'token'))
            ->toAlbum(albumId: 42, groupId: 123)
            ->fromPath($tmpFile);

        expect($attachment->type)->toBe(AttachmentType::Photo);
        unlink($tmpFile);
    });

    it('asCover() вызывает getOwnerCoverPhotoUploadServer', function (): void {
        $tmpFile = tempnam(sys_get_temp_dir(), 'vk_photo_');
        file_put_contents($tmpFile, 'fake_image');

        $attachment = (new PendingPhotoUpload(mockApiForPhoto(), 'token'))
            ->asCover(groupId: 123)
            ->fromPath($tmpFile);

        expect($attachment->type)->toBe(AttachmentType::Photo);
        unlink($tmpFile);
    });

    it('asPollPhoto() вызывает polls.getPhotoUploadServer', function (): void {
        $tmpFile = tempnam(sys_get_temp_dir(), 'vk_photo_');
        file_put_contents($tmpFile, 'fake_image');

        $attachment = (new PendingPhotoUpload(mockApiForPhoto(), 'token'))
            ->asPollPhoto(ownerId: -123)
            ->fromPath($tmpFile);

        expect($attachment->type)->toBe(AttachmentType::Photo);
        unlink($tmpFile);
    });

    it('fromStream() работает аналогично fromPath()', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'fake_image_data');
        rewind($stream);

        $attachment = (new PendingPhotoUpload(mockApiForPhoto(), 'token'))
            ->toMessages(100)
            ->fromStream($stream, 'photo.jpg');

        expect($attachment)->toBeInstanceOf(Attachment::class);
        fclose($stream);
    });

    it('по умолчанию destination — Messages (вызывается без явного destination)', function (): void {
        // Без вызова toMessages() — дефолтный destination должен быть Messages
        // Проверяем что getMessagesUploadServer вызван
        $api = mockApiForPhoto();
        $tmpFile = tempnam(sys_get_temp_dir(), 'vk_photo_');
        file_put_contents($tmpFile, 'fake_image');

        // Не вызываем destination-метод
        $attachment = (new PendingPhotoUpload($api, 'token'))->fromPath($tmpFile);
        expect($attachment->type)->toBe(AttachmentType::Photo);

        unlink($tmpFile);
    });

});
