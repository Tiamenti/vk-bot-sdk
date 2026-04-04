<?php

declare(strict_types=1);

use Tiamenti\VkBotSdk\Enums\ButtonColor;
use Tiamenti\VkBotSdk\Keyboard\Button;
use Tiamenti\VkBotSdk\Keyboard\Keyboard;

describe('Keyboard::make()', function (): void {

    it('создаёт пустую клавиатуру', function (): void {
        $array = Keyboard::make()->toArray();

        expect($array)->toMatchArray([
            'one_time' => false,
            'inline' => false,
            'buttons' => [],
        ]);
    });

    it('добавляет кнопку в первую строку', function (): void {
        $keyboard = Keyboard::make()
            ->button('Нажми', ButtonColor::Primary);

        $array = $keyboard->toArray();

        expect($array['buttons'])->toHaveCount(1);
        expect($array['buttons'][0])->toHaveCount(1);
        expect($array['buttons'][0][0]['action']['label'])->toBe('Нажми');
        expect($array['buttons'][0][0]['color'])->toBe('primary');
    });

    it('добавляет кнопки в несколько строк', function (): void {
        $keyboard = Keyboard::make()
            ->button('Один')
            ->button('Два')
            ->row()
            ->button('Три');

        $array = $keyboard->toArray();

        expect($array['buttons'])->toHaveCount(2);
        expect($array['buttons'][0])->toHaveCount(2);
        expect($array['buttons'][1])->toHaveCount(1);
    });

    it('устанавливает флаг one_time', function (): void {
        $array = Keyboard::make()->button('OK')->oneTime()->toArray();

        expect($array['one_time'])->toBeTrue();
    });

    it('устанавливает флаг inline', function (): void {
        $array = Keyboard::make()->button('OK')->inline()->toArray();

        expect($array['inline'])->toBeTrue();
    });

    it('добавляет payload к кнопке', function (): void {
        $array = Keyboard::make()
            ->button('Кнопка', ButtonColor::Secondary, ['action' => 'test'])
            ->toArray();

        $payloadRaw = $array['buttons'][0][0]['action']['payload'];
        $payload = json_decode($payloadRaw, true);

        expect($payload)->toBe(['action' => 'test']);
    });

    it('создаёт кнопку-ссылку', function (): void {
        $array = Keyboard::make()
            ->openLink('VK', 'https://vk.com')
            ->toArray();

        expect($array['buttons'][0][0]['action'])->toMatchArray([
            'type' => 'open_link',
            'label' => 'VK',
            'link' => 'https://vk.com',
        ]);
        // Нет поля color для open_link
        expect($array['buttons'][0][0])->not->toHaveKey('color');
    });

    it('создаёт callback-кнопку', function (): void {
        $array = Keyboard::make()
            ->callback('Событие', ['event' => 'test'], ButtonColor::Positive)
            ->toArray();

        expect($array['buttons'][0][0])->toMatchArray([
            'color' => 'positive',
        ]);
        expect($array['buttons'][0][0]['action']['type'])->toBe('callback');
    });

    it('сериализует в JSON', function (): void {
        $json = Keyboard::make()->button('OK')->toJson();

        expect(json_decode($json, true))->toBeArray();
    });

    it('не создаёт пустые строки при повторном вызове row()', function (): void {
        $keyboard = Keyboard::make()
            ->row() // пустая строка в начале — не должна создаться
            ->button('Один')
            ->row()
            ->button('Два');

        $array = $keyboard->toArray();

        expect($array['buttons'])->toHaveCount(2);
    });

});

describe('Button', function (): void {

    it('создаёт текстовую кнопку', function (): void {
        $button = Button::text('Привет', ButtonColor::Primary);
        $array = $button->toArray();

        expect($array['action']['type'])->toBe('text');
        expect($array['action']['label'])->toBe('Привет');
        expect($array['color'])->toBe('primary');
    });

    it('создаёт кнопку геолокации без цвета', function (): void {
        $button = Button::location();
        $array = $button->toArray();

        expect($array['action']['type'])->toBe('location');
        expect($array)->not->toHaveKey('color');
    });

});
