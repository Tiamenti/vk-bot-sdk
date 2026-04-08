<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Tiamenti\VkBotSdk\Enums\AttachmentType;
use Tiamenti\VkBotSdk\Upload\Pending\PendingDocumentUpload;
use Tiamenti\VkBotSdk\Upload\ValueObjects\Attachment;
use VK\Client\VKApiClient;

function mockApiForDoc(array $saveResponse, string $uploadUrl = 'https://upload.vk.com/doc'): mixed
{
    $docs = Mockery::mock();
    $docs->shouldReceive('getMessagesUploadServer')->andReturn(['upload_url' => $uploadUrl]);
    $docs->shouldReceive('getWallUploadServer')->andReturn(['upload_url' => $uploadUrl]);
    $docs->shouldReceive('save')->andReturn($saveResponse);

    $api = Mockery::mock(VKApiClient::class);
    $api->shouldReceive('docs')->andReturn($docs);

    return $api;
}

beforeEach(function (): void {
    Http::fake([
        'https://upload.vk.com/*' => Http::response(['file' => 'encoded_doc_data'], 200),
    ]);
});

describe('PendingDocumentUpload — нормализация ответа', function (): void {

    it('парсит ответ docs.save для обычного документа (type=doc)', function (): void {
        $saveResponse = [
            'type' => 'doc',
            'doc' => ['owner_id' => 111, 'id' => 222, 'access_key' => 'key'],
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'vk_doc_');
        file_put_contents($tmpFile, 'fake document');

        $attachment = (new PendingDocumentUpload(mockApiForDoc($saveResponse), 'token'))
            ->toMessages(100)
            ->fromPath($tmpFile);

        expect($attachment->type)->toBe(AttachmentType::Document);
        expect($attachment->ownerId)->toBe(111);
        expect($attachment->id)->toBe(222);
        expect($attachment->accessKey)->toBe('key');
        expect((string) $attachment)->toBe('doc111_222_key');

        unlink($tmpFile);
    });

    it('парсит ответ docs.save для голосового сообщения (type=audio_message)', function (): void {
        $saveResponse = [
            'type' => 'audio_message',
            'audio_message' => ['owner_id' => 333, 'id' => 444],
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'vk_voice_');
        file_put_contents($tmpFile, 'fake ogg');

        $attachment = (new PendingDocumentUpload(mockApiForDoc($saveResponse), 'token'))
            ->asVoiceMessage(100)
            ->fromPath($tmpFile);

        expect($attachment->type)->toBe(AttachmentType::AudioMessage);
        expect($attachment->ownerId)->toBe(333);
        expect($attachment->id)->toBe(444);
        expect((string) $attachment)->toBe('audio_message333_444');

        unlink($tmpFile);
    });

    it('парсит ответ docs.save для граффити (type=graffiti)', function (): void {
        $saveResponse = [
            'type' => 'graffiti',
            'graffiti' => ['owner_id' => 555, 'id' => 666],
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'vk_graffiti_');
        file_put_contents($tmpFile, 'fake png');

        $attachment = (new PendingDocumentUpload(mockApiForDoc($saveResponse), 'token'))
            ->asGraffiti(groupId: 123)
            ->fromPath($tmpFile);

        expect($attachment->type)->toBe(AttachmentType::Graffiti);
        expect($attachment->ownerId)->toBe(555);
        expect($attachment->id)->toBe(666);
        expect((string) $attachment)->toBe('graffiti555_666');

        unlink($tmpFile);
    });

    it('fromStream() работает для документов', function (): void {
        $saveResponse = [
            'type' => 'doc',
            'doc' => ['owner_id' => 1, 'id' => 2],
        ];

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'fake content');
        rewind($stream);

        $attachment = (new PendingDocumentUpload(mockApiForDoc($saveResponse), 'token'))
            ->toWall()
            ->fromStream($stream, 'document.pdf');

        expect($attachment)->toBeInstanceOf(Attachment::class);
        expect($attachment->type)->toBe(AttachmentType::Document);

        fclose($stream);
    });

});
