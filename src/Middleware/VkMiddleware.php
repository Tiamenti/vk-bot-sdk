<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Middleware;

use Tiamenti\VkBotSdk\Context\MessageContext;

/**
 * Контракт middleware для обработки VK-событий.
 *
 * Пример реализации:
 * ```php
 * class AdminMiddleware implements VkMiddleware
 * {
 *     public function handle(MessageContext $ctx, callable $next): void
 *     {
 *         if ($ctx->getFromId() !== config('vk-bot.admin_id')) {
 *             $ctx->reply('Доступ запрещён.');
 *             return;
 *         }
 *         $next($ctx);
 *     }
 * }
 * ```
 */
interface VkMiddleware
{
    /**
     * Обработать входящее событие.
     *
     * @param MessageContext $ctx  Контекст текущего события
     * @param callable       $next Следующий обработчик в цепочке
     */
    public function handle(MessageContext $ctx, callable $next): void;
}
