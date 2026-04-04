<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Keyboard;

use Tiamenti\VkBotSdk\Enums\ButtonColor;
use Tiamenti\VkBotSdk\Enums\ButtonType;

/**
 * Кнопка клавиатуры VK.
 *
 * @see https://dev.vk.com/ru/api/bots/development/keyboard
 */
final class Button
{
    /**
     * @param  ButtonType  $type  Тип кнопки
     * @param  ButtonColor|null  $color  Цвет кнопки (только для text и callback)
     * @param  array<string, mixed>  $action  Параметры действия кнопки
     */
    public function __construct(
        private readonly ButtonType $type,
        private readonly ?ButtonColor $color,
        private readonly array $action,
    ) {}

    /**
     * Создать текстовую кнопку.
     *
     * @param  array<string, mixed>|null  $payload  JSON-payload
     */
    public static function text(
        string $label,
        ButtonColor $color = ButtonColor::Secondary,
        ?array $payload = null,
    ): self {
        $action = ['type' => ButtonType::Text->value, 'label' => $label];

        if ($payload !== null) {
            $action['payload'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        return new self(ButtonType::Text, $color, $action);
    }

    /**
     * Создать кнопку-ссылку.
     */
    public static function openLink(string $label, string $link, ?string $hash = null): self
    {
        $action = [
            'type' => ButtonType::OpenLink->value,
            'label' => $label,
            'link' => $link,
        ];

        if ($hash !== null) {
            $action['hash'] = $hash;
        }

        return new self(ButtonType::OpenLink, null, $action);
    }

    /**
     * Создать кнопку VK Pay.
     *
     * @param  array<string, mixed>  $hash  Параметры VK Pay
     */
    public static function vkPay(array $hash): self
    {
        return new self(
            ButtonType::VkPay,
            null,
            ['type' => ButtonType::VkPay->value, 'hash' => json_encode($hash, JSON_UNESCAPED_UNICODE)],
        );
    }

    /**
     * Создать кнопку открытия мини-приложения.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public static function openApp(
        string $label,
        int $appId,
        int $ownerId,
        ?string $hash = null,
        ?array $payload = null,
    ): self {
        $action = [
            'type' => ButtonType::OpenApp->value,
            'label' => $label,
            'app_id' => $appId,
            'owner_id' => $ownerId,
        ];

        if ($hash !== null) {
            $action['hash'] = $hash;
        }

        if ($payload !== null) {
            $action['payload'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        return new self(ButtonType::OpenApp, null, $action);
    }

    /**
     * Создать кнопку геолокации.
     */
    public static function location(): self
    {
        return new self(ButtonType::Location, null, ['type' => ButtonType::Location->value]);
    }

    /**
     * Создать callback-кнопку (event button).
     *
     * @param  array<string, mixed>  $payload  JSON-payload события
     */
    public static function callback(
        string $label,
        array $payload,
        ButtonColor $color = ButtonColor::Secondary,
    ): self {
        return new self(
            ButtonType::Callback,
            $color,
            [
                'type' => ButtonType::Callback->value,
                'label' => $label,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ],
        );
    }

    /**
     * Сериализовать кнопку в массив для VK API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $button = ['action' => $this->action];

        if ($this->color !== null && in_array($this->type, [ButtonType::Text, ButtonType::Callback], strict: true)) {
            $button['color'] = $this->color->value;
        }

        return $button;
    }
}
