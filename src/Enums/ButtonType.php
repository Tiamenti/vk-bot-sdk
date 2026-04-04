<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Enums;

/**
 * Типы кнопок клавиатуры VK.
 *
 * @see https://dev.vk.com/ru/api/bots/development/keyboard
 */
enum ButtonType: string
{
    /** Обычная текстовая кнопка. */
    case Text = 'text';

    /** Кнопка-ссылка. */
    case OpenLink = 'open_link';

    /** Кнопка оплаты через VK Pay. */
    case VkPay = 'vkpay';

    /** Кнопка открытия мини-приложения. */
    case OpenApp = 'open_app';

    /** Кнопка отправки геолокации. */
    case Location = 'location';

    /** Callback-кнопка (event). */
    case Callback = 'callback';
}
