<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Исключение при ошибке вызова VK API.
 */
class VkApiException extends RuntimeException
{
    /**
     * @param int             $errorCode    Код ошибки VK API
     * @param string          $errorMessage Сообщение об ошибке VK API
     * @param array<string, mixed> $requestParams Параметры запроса
     */
    public function __construct(
        private readonly int $errorCode,
        private readonly string $errorMessage,
        private readonly array $requestParams = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: "VK API Error #{$errorCode}: {$errorMessage}",
            code: $code,
            previous: $previous,
        );
    }

    /**
     * Создать исключение из ответа VK API.
     *
     * @param array<string, mixed> $error
     */
    public static function fromApiResponse(array $error): self
    {
        return new self(
            errorCode: (int) ($error['error_code'] ?? 0),
            errorMessage: (string) ($error['error_msg'] ?? 'Unknown error'),
            requestParams: $error['request_params'] ?? [],
        );
    }

    /**
     * Код ошибки VK API.
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * Сообщение об ошибке VK API.
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * Параметры запроса, вызвавшего ошибку.
     *
     * @return array<string, mixed>
     */
    public function getRequestParams(): array
    {
        return $this->requestParams;
    }
}
