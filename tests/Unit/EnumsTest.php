<?php

declare(strict_types=1);

use Tiamenti\VkBotSdk\Enums\ButtonColor;
use Tiamenti\VkBotSdk\Enums\ButtonType;
use Tiamenti\VkBotSdk\Enums\EventType;
use Tiamenti\VkBotSdk\Enums\KeyboardType;

describe('EventType', function (): void {

    it('имеет корректные значения', function (): void {
        expect(EventType::MessageNew->value)->toBe('message_new');
        expect(EventType::MessageEvent->value)->toBe('message_event');
        expect(EventType::GroupJoin->value)->toBe('group_join');
        expect(EventType::Confirmation->value)->toBe('confirmation');
    });

    it('tryFrom() возвращает null для неизвестного типа', function (): void {
        expect(EventType::tryFrom('unknown_event'))->toBeNull();
    });

    it('isMessage() корректно работает', function (): void {
        expect(EventType::MessageNew->isMessage())->toBeTrue();
        expect(EventType::MessageReply->isMessage())->toBeTrue();
        expect(EventType::GroupJoin->isMessage())->toBeFalse();
    });

    it('label() возвращает строку', function (): void {
        expect(EventType::MessageNew->label())->toBe('Новое сообщение');
        expect(EventType::GroupJoin->label())->toBe('Вступление в сообщество');
    });

});

describe('ButtonColor', function (): void {

    it('имеет корректные значения', function (): void {
        expect(ButtonColor::Primary->value)->toBe('primary');
        expect(ButtonColor::Secondary->value)->toBe('secondary');
        expect(ButtonColor::Negative->value)->toBe('negative');
        expect(ButtonColor::Positive->value)->toBe('positive');
    });

});

describe('ButtonType', function (): void {

    it('имеет корректные значения', function (): void {
        expect(ButtonType::Text->value)->toBe('text');
        expect(ButtonType::OpenLink->value)->toBe('open_link');
        expect(ButtonType::VkPay->value)->toBe('vkpay');
        expect(ButtonType::OpenApp->value)->toBe('open_app');
        expect(ButtonType::Location->value)->toBe('location');
        expect(ButtonType::Callback->value)->toBe('callback');
    });

});

describe('KeyboardType', function (): void {

    it('имеет корректные значения', function (): void {
        expect(KeyboardType::Default_->value)->toBe('default');
        expect(KeyboardType::Inline->value)->toBe('inline');
    });

});
