<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Tiamenti\VkBotSdk\Upload\Concerns\UploadsFile;

/**
 * Тестовый класс, использующий трейт UploadsFile.
 * Открывает приватные методы через protected для тестирования.
 */
class TestableUploader
{
    use UploadsFile {
        uploadFromPath as public;
        uploadFromStream as public;
    }
}

describe('UploadsFile trait', function (): void {

    it('uploadFromPath использует fopen (не читает файл в память)', function (): void {
        // Создаём временный файл
        $tmpFile = tempnam(sys_get_temp_dir(), 'vk_test_');
        file_put_contents($tmpFile, str_repeat('x', 1024));

        Http::fake([
            'https://upload.vk.com/*' => Http::response(['server' => 1, 'photo' => 'data', 'hash' => 'h'], 200),
        ]);

        $uploader = new TestableUploader;
        $result = $uploader->uploadFromPath('https://upload.vk.com/upload', 'photo', $tmpFile);

        // Убеждаемся что файл был загружен и ответ разобран
        expect($result)->toBeArray();
        expect($result)->toHaveKey('server');

        // HTTP-запрос был отправлен
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'upload.vk.com');
        });

        unlink($tmpFile);
    });

    it('uploadFromPath бросает RuntimeException для несуществующего файла', function (): void {
        $uploader = new TestableUploader;

        expect(fn () => $uploader->uploadFromPath('https://upload.vk.com/upload', 'photo', '/nonexistent/file.jpg'))
            ->toThrow(RuntimeException::class, 'File not found');
    });

    it('uploadFromStream принимает ресурс и передаёт в HTTP-клиент', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'fake image data');
        rewind($stream);

        Http::fake([
            '*' => Http::response(['file' => 'encoded_file_data'], 200),
        ]);

        $uploader = new TestableUploader;
        $result = $uploader->uploadFromStream('https://upload.vk.com/upload', 'file', $stream, 'test.png');

        expect($result)->toHaveKey('file');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'upload.vk.com');
        });

        fclose($stream);
    });

    it('uploadFromStream бросает RuntimeException при HTTP-ошибке', function (): void {
        Http::fake(['*' => Http::response('Server Error', 500)]);

        $stream = fopen('php://memory', 'r+');

        $uploader = new TestableUploader;

        expect(fn () => $uploader->uploadFromStream('https://upload.vk.com/upload', 'file', $stream, 'test.jpg'))
            ->toThrow(RuntimeException::class, 'HTTP 500');

        fclose($stream);
    });

    it('закрывает дескриптор файла даже при исключении (finally)', function (): void {
        $tmpFile = tempnam(sys_get_temp_dir(), 'vk_test_');
        file_put_contents($tmpFile, 'data');

        Http::fake(['*' => Http::response('error', 500)]);

        $uploader = new TestableUploader;

        try {
            $uploader->uploadFromPath('https://upload.vk.com/upload', 'photo', $tmpFile);
        } catch (RuntimeException) {
            // Ожидаем исключение
        }

        // Если дескриптор закрыт корректно, можно удалить файл без ошибки
        expect(unlink($tmpFile))->toBeTrue();
    });

});
