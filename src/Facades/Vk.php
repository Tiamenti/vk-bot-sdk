<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Facades;

use Illuminate\Support\Facades\Facade;
use Tiamenti\VkBotSdk\Enums\EventType;
use Tiamenti\VkBotSdk\VkBot;

/**
 * Фасад для работы с VkBot.
 *
 * @method static void on(EventType $event, callable $handler)
 * @method static void hears(string|array $pattern, callable|string|array $handler)
 * @method static void command(string $command, callable|string|array $handler)
 * @method static void onPayload(string|array $payload, callable|string|array $handler)
 * @method static void fallback(callable|string|array $handler)
 * @method static void middleware(callable|string $middleware)
 * @method static void group(callable $callback)
 * @method static string handle(array $payload)
 * @method static bool validateSecret(string $incomingSecret)
 * @method static \VK\Client\VKApiClient getApi()
 * @method static string getToken()
 *
 * @see VkBot
 */
class Vk extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'vk-bot';
    }
}
