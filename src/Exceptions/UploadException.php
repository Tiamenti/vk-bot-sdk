<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Исключение при ошибке загрузки файла на сервер VK.
 *
 * Содержит HTTP-статус ответа сервера загрузки и флаг,
 * указывающий можно ли повторить попытку.
 */
final class UploadException extends RuntimeException
{
    /**
     * HTTP-статусы, при которых повторная попытка имеет смысл.
     *
     * 413 — сервер загрузки перегружен или квота конкретного экземпляра исчерпана;
     *       помогает получение свежего upload_url через новый getUploadServer().
     * 429 — rate limit; нужно подождать.
     * 500/502/503/504 — временные проблемы инфраструктуры VK.
     *
     * @var array<int, int>
     */
    private const RETRYABLE_STATUSES = [413, 429, 500, 502, 503, 504];

    public function __construct(
        private readonly int $httpStatus,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    /**
     * Создать из HTTP-статуса и URL.
     */
    public static function fromHttpStatus(int $status, string $uploadUrl): self
    {
        return new self(
            httpStatus: $status,
            message: "VK upload server returned HTTP {$status} for URL: {$uploadUrl}",
        );
    }

    /**
     * HTTP-статус ответа сервера загрузки.
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Стоит ли повторить попытку загрузки.
     *
     * При 413 обязательно нужен свежий upload_url — повтор с тем же URL бессмысленен.
     * Логику обновления URL берёт на себя Pending*Upload через withRetry().
     */
    public function isRetryable(): bool
    {
        return in_array($this->httpStatus, self::RETRYABLE_STATUSES, strict: true);
    }
}
