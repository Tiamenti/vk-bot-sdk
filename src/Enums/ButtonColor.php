<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Enums;

/**
 * Цвета кнопок клавиатуры VK.
 *
 * @see https://dev.vk.com/ru/api/bots/development/keyboard
 */
enum ButtonColor: string
{
    /** Синяя кнопка (основное действие). */
    case Primary   = 'primary';

    /** Белая кнопка (нейтральное действие). */
    case Secondary = 'secondary';

    /** Красная кнопка (деструктивное действие). */
    case Negative  = 'negative';

    /** Зелёная кнопка (подтверждение). */
    case Positive  = 'positive';
}
