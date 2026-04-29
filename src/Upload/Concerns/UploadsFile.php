<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Upload\Concerns;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tiamenti\VkBotSdk\Exceptions\UploadException;
use VK\Client\VKApiClient;

/**
 * Трейт: потоковая передача файлов на сервер загрузки VK.
 *
 * Файл НИКОГДА не читается в память целиком — `uploadFromPath()` открывает
 * дескриптор через `fopen()` и передаёт ресурс прямо в Guzzle, который
 * читает файл чанками. Это принципиально важно для видео и документов,
 * которые могут весить несколько гигабайт.
 *
 * ### Таймауты
 * `timeout => 0` означает «ждать получения данных сколько угодно». Без этого
 * Guzzle рвёт соединение по таймауту чтения (обычно 30 сек), что для файла
 * в 1+ ГБ неизбежно приводит к HTTP 504 и деградации скорости: Guzzle
 * начинает считать прошедшее время на каждый чанк, создавая оверхед.
 *
 * `connect_timeout => 30` при этом остаётся — установка соединения всё равно
 * должна завершаться за разумное время.
 *
 * `expect => false` отключает заголовок `Expect: 100-Continue`. По умолчанию
 * Guzzle отправляет его для больших тел запроса, ожидая подтверждения от
 * сервера перед передачей данных. VK-серверы иногда не возвращают 100,
 * что добавляет лишний round-trip и может вызвать задержку в 1–2 секунды.
 *
 * @property VKApiClient $api
 * @property string $token
 */
trait UploadsFile
{
    /**
     * Открыть файл по пути и загрузить его потоком.
     *
     * Дескриптор закрывается в блоке `finally` независимо от результата.
     *
     * @param  string  $uploadUrl  URL сервера загрузки от VK API
     * @param  string  $fieldName  Имя поля в multipart-форме
     * @param  string  $path  Абсолютный или относительный путь к файлу
     * @return array<string, mixed> Сырой ответ сервера загрузки
     *
     * @throws RuntimeException Если файл не существует или недоступен для чтения
     * @throws UploadException При ошибке HTTP от сервера загрузки
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
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Загрузить файл из уже открытого потока.
     *
     * Принимает любой ресурс, совместимый с Guzzle:
     * открытый файловый дескриптор, поток из S3 (`Storage::readStream()`),
     * in-memory буфер (`fopen('php://memory', 'r+')`) и т.д.
     *
     * @param  resource|mixed  $stream  Открытый поток
     * @param  string  $fieldName  Имя поля в multipart-форме
     * @param  string  $filename  Имя файла для Content-Disposition
     * @return array<string, mixed> Сырой ответ сервера загрузки
     *
     * @throws UploadException При ошибке HTTP (со статусом для retry-логики)
     * @throws RuntimeException При невалидном теле ответа
     */
    private function uploadFromStream(string $uploadUrl, string $fieldName, mixed $stream, string $filename): array
    {
        $response = Http::withOptions([
            // Без лимита на время передачи данных.
            // Критично для больших файлов: без этого Guzzle рвёт
            // соединение по дефолтному таймауту (~30с), что для 1+ ГБ
            // гарантированно даёт 504 и деградацию скорости в htop.
            'timeout' => 0,

            // Соединение с сервером всё равно должно устанавливаться быстро.
            'connect_timeout' => 30,

            // Отключает Expect: 100-Continue. Guzzle по умолчанию добавляет
            // этот заголовок для больших тел запроса и ждёт подтверждения сервера.
            // VK-серверы загрузки иногда не отвечают на него корректно,
            // добавляя задержку перед началом передачи данных.
            'expect' => false,

            // Критично: сохранять метод POST при редиректах (301/302/303).
            //
            // По умолчанию Guzzle конвертирует POST → GET при 302-редиректе
            // (поведение браузеров, закреплённое в HTTP/1.1 §15.4.3).
            // Серверы VK (pu.vk.com) используют 302 для балансировки нагрузки
            // между инстансами. После редиректа Guzzle отправляет GET —
            // сервер загрузки его не принимает (405), а тело multipart-запроса
            // теряется. Именно это приводит к интермиттентным ошибкам:
            //   - HTTP 405 Method Not Allowed
            //   - "file is undefined" в docs.save (пустое тело → нет ключа file)
            //
            // 'strict' => true заставляет Guzzle сохранять POST для всех
            // типов редиректов, включая 301 и 302.
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'referer' => false,
                'protocols' => ['https', 'http'],
            ],
        ])
            ->attach($fieldName, $stream, $filename)
            ->post($uploadUrl);

        if ($response->failed()) {
            throw UploadException::fromHttpStatus($response->status(), $uploadUrl);
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException(
                "Unexpected response from VK upload server: {$response->body()}",
            );
        }

        return $body;
    }

    /**
     * Выполнить загрузку с автоматическими повторными попытками.
     *
     * Каждая попытка вызывает $attempt заново — это означает, что внутри
     * замыкания нужно получать свежий upload_url через getUploadServer(),
     * иначе повтор с тем же 413-сервером ничего не даст.
     *
     * Задержки между попытками: 1с, 2с, 4с (экспоненциальный backoff).
     *
     * Если поток (`fromStream`) не перематывается между попытками — это
     * ответственность вызывающего кода. Для файловых потоков используйте
     * `rewindStreamIfSeekable()`.
     *
     * @template T
     *
     * @param  callable(): T  $attempt  Замыкание с логикой загрузки
     * @param  int  $maxAttempts  Максимальное число попыток (по умолчанию 3)
     * @return T
     *
     * @throws UploadException После исчерпания всех попыток
     * @throws RuntimeException При нетипизированных ошибках
     */
    private function withRetry(callable $attempt, int $maxAttempts = 3): mixed
    {
        $lastException = null;

        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                return $attempt();
            } catch (UploadException $e) {
                $lastException = $e;

                if (! $e->isRetryable() || $i === $maxAttempts - 1) {
                    throw $e;
                }

                // Экспоненциальный backoff: 1с → 2с → 4с
                sleep(2 ** $i);
            }
        }

        // Сюда попасть невозможно (петля выбрасывает или возвращает),
        // но PHP требует return/throw после цикла
        throw $lastException ?? new RuntimeException('Upload failed: unknown error');
    }

    /**
     * Проверить, что ответ сервера загрузки содержит ожидаемый ключ.
     *
     * Если ключ отсутствует или пустой — значит multipart-тело не дошло до
     * сервера (чаще всего из-за редиректа POST→GET). Это позволяет получить
     * внятную ошибку в SDK вместо загадочного "file is undefined" от VK API.
     *
     * @param  array<string, mixed>  $uploaded
     *
     * @throws UploadException
     */
    private function assertUploadedKey(array $uploaded, string $key): void
    {
        $value = $uploaded[$key] ?? null;

        if ($value === null || $value === '') {
            $receivedKeys = implode(', ', array_keys($uploaded)) ?: '(empty response)';

            throw new UploadException(
                httpStatus: 0,
                message: "Upload server response is missing required key '{$key}'. "
                    ."Received keys: [{$receivedKeys}]. "
                    .'This usually means the multipart body was lost during an HTTP redirect (POST→GET). '
                    .'Ensure allow_redirects.strict=true is set in Guzzle options.',
            );
        }
    }

    /**
     * Перемотать поток в начало, если он это поддерживает.
     *
     * Вызывается перед каждой повторной попыткой в fromStream().
     * Нативно-сетевые потоки (S3 presigned URL, curl) не перематываются —
     * для них повтор невозможен и выбрасывается исключение после первой ошибки.
     *
     * @param  resource|mixed  $stream
     *
     * @throws RuntimeException Если поток не перематывается (не seekable)
     */
    private function rewindStreamIfSeekable(mixed $stream): void
    {
        if (! is_resource($stream)) {
            return;
        }

        $meta = stream_get_meta_data($stream);

        if (! $meta['seekable']) {
            throw new RuntimeException(
                'Cannot retry upload: the stream is not seekable. '
                .'Use fromPath() instead of fromStream() for large files with retry support, '
                .'or pre-buffer the stream content.',
            );
        }

        rewind($stream);
    }
}
