<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Psr\Log\LoggerInterface;
use Tiamenti\VkBotSdk\Exceptions\InvalidSecretException;
use Tiamenti\VkBotSdk\VkBot;

/**
 * Контроллер Callback API.
 *
 * Принимает POST-запросы от VK, проверяет секрет и делегирует обработку VkBot.
 *
 * Подключите маршрут вручную в routes/web.php (или routes/api.php):
 * ```php
 * Route::post('/vk/webhook', \Tiamenti\VkBotSdk\Http\Controllers\CallbackController::class)
 *     ->name('vk.webhook');
 * ```
 */
final class CallbackController extends Controller
{
    public function __construct(
        private readonly VkBot $bot,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Обработать входящий запрос от VK Callback API.
     */
    public function __invoke(Request $request): Response
    {
        $payload = $request->json()->all();

        if (empty($payload)) {
            return response('Bad Request', 400);
        }

        // Проверка секретного ключа
        $incomingSecret = (string) ($payload['secret'] ?? '');

        if (! $this->bot->validateSecret($incomingSecret)) {
            $this->logger->warning('VK Callback: invalid secret', [
                'ip' => $request->ip(),
            ]);

            return response('Forbidden', 403);
        }

        try {
            $result = $this->bot->handle($payload);
        } catch (\Throwable $e) {
            $this->logger->error('VK Callback: handler error', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            // VK требует ответ 'ok', иначе будет повторять попытки
            return response('ok');
        }

        return response($result);
    }
}
