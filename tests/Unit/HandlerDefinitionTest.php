<?php

declare(strict_types=1);

use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Enums\EventType;
use Tiamenti\VkBotSdk\Handlers\HandlerDefinition;
use VK\Client\VKApiClient;

/**
 * Вспомогательная функция — создать MessageContext с текстом.
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

describe('HandlerDefinition::matches()', function (): void {

    it('совпадает по EventType', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_ON,
            handler: fn () => null,
            event: EventType::GroupJoin,
        );

        expect($def->matches(makeCtx(event: EventType::GroupJoin)))->toBeTrue();
        expect($def->matches(makeCtx(event: EventType::GroupLeave)))->toBeFalse();
    });

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

    it('совпадает по команде со слешем и без', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_COMMAND,
            handler: fn () => null,
            pattern: '/start',
        );

        expect($def->matches(makeCtx('/start')))->toBeTrue();
        expect($def->matches(makeCtx('start')))->toBeTrue();
        expect($def->matches(makeCtx('/help')))->toBeFalse();
    });

    it('совпадает по payload массиву', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_PAYLOAD,
            handler: fn () => null,
            payload: [['action' => 'buy']],
        );

        expect($def->matches(makeCtx(payload: ['action' => 'buy'])))->toBeTrue();
        expect($def->matches(makeCtx(payload: ['action' => 'sell'])))->toBeFalse();
    });

    it('fallback всегда совпадает', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_FALLBACK,
            handler: fn () => null,
        );

        expect($def->matches(makeCtx('любой текст')))->toBeTrue();
    });

    it('не совпадает с пустым текстом для hears', function (): void {
        $def = new HandlerDefinition(
            type: HandlerDefinition::TYPE_HEARS,
            handler: fn () => null,
            pattern: 'привет',
        );

        expect($def->matches(makeCtx('')))->toBeFalse();
    });

});
