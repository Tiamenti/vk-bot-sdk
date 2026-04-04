<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Keyboard;

use Tiamenti\VkBotSdk\Enums\ButtonColor;

/**
 * Fluent-builder клавиатуры VK.
 *
 * Пример использования:
 * ```php
 * $keyboard = Keyboard::make()
 *     ->button('Кнопка 1', ButtonColor::Primary, ['action' => 'btn1'])
 *     ->button('Кнопка 2', ButtonColor::Secondary)
 *     ->row()
 *     ->button('Назад', ButtonColor::Negative, ['action' => 'back'])
 *     ->inline()
 *     ->oneTime();
 * ```
 *
 * @see https://dev.vk.com/ru/api/bots/development/keyboard
 */
final class Keyboard
{
    /** @var array<int, array<int, Button>> Строки кнопок */
    private array $rows = [[]];

    private bool $inline  = false;
    private bool $oneTime = false;

    private function __construct() {}

    /**
     * Создать новую клавиатуру.
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Создать пустую (скрытую) клавиатуру.
     */
    public static function remove(): self
    {
        return (new self())->empty();
    }

    // -------------------------------------------------------------------------
    // Кнопки
    // -------------------------------------------------------------------------

    /**
     * Добавить текстовую кнопку.
     *
     * @param array<string, mixed>|null $payload JSON-payload
     */
    public function button(
        string $label,
        ButtonColor $color = ButtonColor::Secondary,
        ?array $payload = null,
    ): self {
        $this->addButton(Button::text($label, $color, $payload));

        return $this;
    }

    /**
     * Добавить кнопку-ссылку.
     */
    public function openLink(string $label, string $link, ?string $hash = null): self
    {
        $this->addButton(Button::openLink($label, $link, $hash));

        return $this;
    }

    /**
     * Добавить кнопку VK Pay.
     *
     * @param array<string, mixed> $hash
     */
    public function vkPay(array $hash): self
    {
        $this->addButton(Button::vkPay($hash));

        return $this;
    }

    /**
     * Добавить кнопку мини-приложения.
     *
     * @param array<string, mixed>|null $payload
     */
    public function openApp(
        string $label,
        int $appId,
        int $ownerId,
        ?string $hash = null,
        ?array $payload = null,
    ): self {
        $this->addButton(Button::openApp($label, $appId, $ownerId, $hash, $payload));

        return $this;
    }

    /**
     * Добавить кнопку геолокации.
     */
    public function location(): self
    {
        $this->addButton(Button::location());

        return $this;
    }

    /**
     * Добавить callback-кнопку (event button).
     *
     * @param array<string, mixed> $payload
     */
    public function callback(
        string $label,
        array $payload,
        ButtonColor $color = ButtonColor::Secondary,
    ): self {
        $this->addButton(Button::callback($label, $payload, $color));

        return $this;
    }

    // -------------------------------------------------------------------------
    // Строки и параметры
    // -------------------------------------------------------------------------

    /**
     * Начать новую строку кнопок.
     */
    public function row(): self
    {
        // Не создавать пустую строку, если текущая уже пустая
        if (! empty($this->rows[array_key_last($this->rows)])) {
            $this->rows[] = [];
        }

        return $this;
    }

    /**
     * Сделать клавиатуру inline (отображается под сообщением).
     */
    public function inline(bool $value = true): self
    {
        $this->inline = $value;

        return $this;
    }

    /**
     * Скрыть клавиатуру после нажатия.
     */
    public function oneTime(bool $value = true): self
    {
        $this->oneTime = $value;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Сериализация
    // -------------------------------------------------------------------------

    /**
     * Сериализовать клавиатуру в массив для VK API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $buttons = array_values(
            array_map(
                fn (array $row): array => array_map(
                    fn (Button $button): array => $button->toArray(),
                    $row,
                ),
                array_filter(
                    $this->rows,
                    fn (array $row): bool => ! empty($row),
                ),
            ),
        );

        return [
            'one_time' => $this->oneTime,
            'inline'   => $this->inline,
            'buttons'  => $buttons,
        ];
    }

    /**
     * Сериализовать клавиатуру в JSON-строку.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    // -------------------------------------------------------------------------
    // Вспомогательные методы
    // -------------------------------------------------------------------------

    /**
     * Добавить кнопку в текущую строку.
     */
    private function addButton(Button $button): void
    {
        $lastKey = array_key_last($this->rows);
        $this->rows[$lastKey][] = $button;
    }

    /**
     * Обнулить кнопки (скрытая клавиатура).
     */
    private function empty(): self
    {
        $this->rows = [[]];

        return $this;
    }
}
