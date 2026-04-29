<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Upload\Contracts;

use Tiamenti\VkBotSdk\Exceptions\VkApiException;
use Tiamenti\VkBotSdk\Upload\ValueObjects\Attachment;

/**
 * Контракт отложенной загрузки файла.
 *
 * Каждый реализующий класс описывает конкретный тип загрузки (фото, видео,
 * документ, история) и предоставляет fluent-методы для выбора места назначения.
 * Загрузка происходит ленивo — только при вызове fromPath() или fromStream().
 */
interface PendingUpload
{
    /**
     * Загрузить файл по пути на диске.
     *
     * Файл открывается через fopen() и передаётся в HTTP-клиент как ресурс,
     * никогда не читаясь в память целиком. Безопасно для файлов любого размера.
     *
     * @param  string  $path  Абсолютный или относительный путь к файлу
     * @return Attachment Нормализованный объект вложения
     *
     * @throws \RuntimeException Если файл не существует или недоступен
     * @throws VkApiException При ошибке VK API
     */
    public function fromPath(string $path): Attachment;

    /**
     * Загрузить файл из потока.
     *
     * Принимает любой совместимый с PSR-7 ресурс — открытый файловый дескриптор,
     * поток из S3 (Storage::readStream()), curl-поток и т.д.
     *
     * @param  resource|mixed  $stream  Открытый поток
     * @param  string  $filename  Имя файла для составления multipart-запроса
     * @return Attachment Нормализованный объект вложения
     *
     * @throws VkApiException
     */
    public function fromStream(mixed $stream, string $filename): Attachment;
}
