<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Enums;

/**
 * Тип клавиатуры VK.
 */
enum KeyboardType: string
{
    /** Стандартная клавиатура (отображается под полем ввода). */
    case Default_ = 'default';

    /** Inline-клавиатура (отображается под сообщением). */
    case Inline   = 'inline';
}
