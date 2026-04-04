<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Events;

use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Enums\EventType;

/**
 * Базовое Laravel-событие для VK-событий.
 *
 * Диспатчится для каждого входящего события, позволяя
 * стандартным Laravel-листенерам реагировать на события VK.
 *
 * @example
 * Event::listen(VkEvent::class, function (VkEvent $event) {
 *     Log::info('VK event', ['type' => $event->getType()->value]);
 * });
 */
final class VkEvent
{
    public function __construct(
        private readonly MessageContext $context,
        private readonly EventType $type,
    ) {}

    public function getContext(): MessageContext
    {
        return $this->context;
    }

    public function getType(): EventType
    {
        return $this->type;
    }
}
