<?php

declare(strict_types=1);

use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Enums\EventType;
use Tiamenti\VkBotSdk\Handlers\HandlerDefinition;
use VK\Client\VKApiClient;

/**
 * Вспомогательная функция — создать MessageContext с текстом и/или payload.
 */
function makeCtx(string $text = '', ?array $payload = null, EventType $event = EventType::MessageNew): MessageContext
{
    $message = ['text' => $text, 'peer_id' => 100, 'from_id' => 1];

    if ($payload !== null) {
        $message['payload'] = json_encode($payload);
    }

    $api = Mockery::mock(VKApiClient::class);

    return new MessageContext(
        api: $api,
        token: 'token',
        event: $event,
        eventObject: ['message' => $message],
    );
}

describe('HandlerDefinition::matches() — события', function (): void {

    it('совпадает по EventType', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_ON,
            handler: fn () => null,
            event: EventType::GroupJoin,
        );

        expect($def->matches(makeCtx(event: EventType::GroupJoin)))->toBeTrue();
        expect($def->matches(makeCtx(event: EventType::GroupLeave)))->toBeFalse();
    });

});

describe('HandlerDefinition::matches() — текст', function (): void {

    it('совпадает по точному тексту (без учёта регистра)', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_HEARS,
            handler: fn () => null,
            pattern: 'привет',
        );

        expect($def->matches(makeCtx('ПРИВЕТ')))->toBeTrue();
        expect($def->matches(makeCtx('пока')))->toBeFalse();
    });

    it('совпадает по массиву паттернов', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_HEARS,
            handler: fn () => null,
            pattern: ['да', 'нет'],
        );

        expect($def->matches(makeCtx('да')))->toBeTrue();
        expect($def->matches(makeCtx('нет')))->toBeTrue();
        expect($def->matches(makeCtx('может')))->toBeFalse();
    });

    it('совпадает по регулярному выражению', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_HEARS,
            handler: fn () => null,
            pattern: '/^привет/ui',
        );

        expect($def->matches(makeCtx('Привет, мир!')))->toBeTrue();
        expect($def->matches(makeCtx('пока')))->toBeFalse();
    });

    it('не совпадает на пустой текст', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_HEARS,
            handler: fn () => null,
            pattern: 'привет',
        );

        expect($def->matches(makeCtx('')))->toBeFalse();
    });

});

describe('HandlerDefinition::matches() — команды', function (): void {

    it('совпадает на команду со слешем и без', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_COMMAND,
            handler: fn () => null,
            pattern: '/start',
        );

        expect($def->matches(makeCtx('/start')))->toBeTrue();
        expect($def->matches(makeCtx('start')))->toBeTrue();
        expect($def->matches(makeCtx('/help')))->toBeFalse();
    });

});

describe('HandlerDefinition::matches() — payload', function (): void {

    // -----------------------------------------------------------------------
    // Частичное совпадение (ключевой сценарий)
    // -----------------------------------------------------------------------

    it("частичное совпадение: паттерн ['action'=>'buy'], payload содержит доп. ключи", function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_PAYLOAD,
            handler: fn () => null,
            payload: ['action' => 'buy'],
        );

        // payload кнопки содержит лишний ключ product_id — должно совпасть
        expect($def->matches(makeCtx(payload: ['action' => 'buy', 'product_id' => 123])))->toBeTrue();
        // Точное совпадение без лишних ключей — тоже должно совпасть
        expect($def->matches(makeCtx(payload: ['action' => 'buy'])))->toBeTrue();
        // Другое action — не совпадает
        expect($def->matches(makeCtx(payload: ['action' => 'cancel', 'product_id' => 123])))->toBeFalse();
    });

    it('частичное совпадение по нескольким ключам паттерна', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_PAYLOAD,
            handler: fn () => null,
            payload: ['action' => 'buy', 'type' => 'premium'],
        );

        expect($def->matches(makeCtx(payload: ['action' => 'buy', 'type' => 'premium', 'id' => 5])))->toBeTrue();
        expect($def->matches(makeCtx(payload: ['action' => 'buy', 'type' => 'basic', 'id' => 5])))->toBeFalse();
        expect($def->matches(makeCtx(payload: ['action' => 'buy'])))->toBeFalse(); // нет ключа 'type'
    });

    // -----------------------------------------------------------------------
    // Список паттернов (list-массив)
    // -----------------------------------------------------------------------

    it('список паттернов: срабатывает на любой из них', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_PAYLOAD,
            handler: fn () => null,
            payload: [
                ['action' => 'yes'],
                ['action' => 'confirm'],
            ],
        );

        expect($def->matches(makeCtx(payload: ['action' => 'yes'])))->toBeTrue();
        expect($def->matches(makeCtx(payload: ['action' => 'confirm', 'extra' => 1])))->toBeTrue();
        expect($def->matches(makeCtx(payload: ['action' => 'no'])))->toBeFalse();
    });

    // -----------------------------------------------------------------------
    // Строковый паттерн
    // -----------------------------------------------------------------------

    it('строковый паттерн совпадает по ключу action', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_PAYLOAD,
            handler: fn () => null,
            payload: 'buy',
        );

        expect($def->matches(makeCtx(payload: ['action' => 'buy'])))->toBeTrue();
        expect($def->matches(makeCtx(payload: ['action' => 'cancel'])))->toBeFalse();
    });

    it('строковый паттерн совпадает по ключу button', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_PAYLOAD,
            handler: fn () => null,
            payload: 'yes',
        );

        expect($def->matches(makeCtx(payload: ['button' => 'yes'])))->toBeTrue();
        expect($def->matches(makeCtx(payload: ['button' => 'no'])))->toBeFalse();
    });

    // -----------------------------------------------------------------------
    // Граничные случаи
    // -----------------------------------------------------------------------

    it('не совпадает при отсутствии payload', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_PAYLOAD,
            handler: fn () => null,
            payload: ['action' => 'buy'],
        );

        expect($def->matches(makeCtx('без payload')))->toBeFalse();
    });

    it('использует array_key_exists, а не isset (ключ с null-значением)', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_PAYLOAD,
            handler: fn () => null,
            payload: ['action' => null],
        );

        expect($def->matches(makeCtx(payload: ['action' => null])))->toBeTrue();
    });

    // -----------------------------------------------------------------------
    // Fallback
    // -----------------------------------------------------------------------

    it('fallback всегда совпадает', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_FALLBACK,
            handler: fn () => null,
        );

        expect($def->matches(makeCtx('любой текст')))->toBeTrue();
    });

});
