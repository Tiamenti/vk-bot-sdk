<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Upload\Concerns;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use VK\Client\VKApiClient;

/**
 * Трейт: потоковая передача файлов на сервер загрузки VK.
 *
 * Файл НИКОГДА не читается в память целиком — `fromPath()` открывает
 * дескриптор через `fopen()` и передаёт ресурс прямо в HTTP-клиент,
 * который читает файл чанками. Это принципиально важно для видео и
 * документов, которые могут весить несколько гигабайт.
 *
 * @property VKApiClient $api
 * @property string $token
 */
trait UploadsFile
{
    /**
     * Открыть файл по пути и загрузить его потоком.
     *
     * @param  string  $uploadUrl  URL сервера загрузки от VK API
     * @param  string  $fieldName  Имя поля в multipart-форме
     * @param  string  $path  Путь к файлу
     * @return array<string, mixed> Сырой ответ сервера загрузки
     *
     * @throws RuntimeException Если файл не существует или недоступен для чтения
     */
    private function uploadFromPath(string $uploadUrl, string $fieldName, string $path): array
    {
        if (! file_exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        if (! is_readable($path)) {
            throw new RuntimeException("File is not readable: {$path}");
        }

        $stream = fopen($path, 'r');

        if ($stream === false) {
            throw new RuntimeException("Cannot open file for reading: {$path}");
        }

        try {
            return $this->uploadFromStream($uploadUrl, $fieldName, $stream, basename($path));
        } finally {
            // Закрываем дескриптор независимо от результата
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Загрузить файл из уже открытого потока.
     *
     * Подходит для файлов из S3, curl-потоков, in-memory буферов и т.д.:
     * ```php
     * $stream = Storage::disk('s3')->readStream('videos/big.mp4');
     * $pending->fromStream($stream, 'big.mp4');
     * ```
     *
     * @param  resource|mixed  $stream  Открытый поток
     * @param  string  $fieldName  Имя поля в multipart-форме
     * @param  string  $filename  Имя файла для Content-Disposition
     * @return array<string, mixed> Сырой ответ сервера загрузки
     */
    private function uploadFromStream(string $uploadUrl, string $fieldName, mixed $stream, string $filename): array
    {
        $response = Http::attach($fieldName, $stream, $filename)
            ->post($uploadUrl);

        if ($response->failed()) {
            throw new RuntimeException(
                "VK upload server returned HTTP {$response->status()} for URL: {$uploadUrl}",
            );
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException(
                "Unexpected response from VK upload server: {$response->body()}",
            );
        }

        return $body;
    }
}
