<?php

declare(strict_types=1);

use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Enums\EventType;
use VK\Client\VKApiClient;

function ctx(array $message = [], EventType $event = EventType::MessageNew): MessageContext
{
    $api = Mockery::mock(VKApiClient::class);

    return new MessageContext(
        api: $api,
        token: 'token',
        event: $event,
        eventObject: ['message' => array_merge([
            'id' => 1,
            'peer_id' => 100,
            'from_id' => 42,
            'text' => 'Привет',
        ], $message)],
    );
}

describe('MessageContext геттеры', function (): void {

    it('getEvent() возвращает тип события', function (): void {
        expect(ctx()->getEvent())->toBe(EventType::MessageNew);
    });

    it('getPeerId() возвращает peer_id', function (): void {
        expect(ctx()->getPeerId())->toBe(100);
    });

    it('getFromId() возвращает from_id', function (): void {
        expect(ctx()->getFromId())->toBe(42);
    });

    it('getText() возвращает текст сообщения', function (): void {
        expect(ctx()->getText())->toBe('Привет');
    });

    it('getText() возвращает null для пустого текста', function (): void {
        expect(ctx(['text' => ''])->getText())->toBeNull();
    });

    it('text() — псевдоним getText()', function (): void {
        expect(ctx()->text())->toBe('Привет');
    });

    it('getPayload() декодирует JSON payload', function (): void {
        $payload = json_encode(['action' => 'test']);
        $context = ctx(['payload' => $payload]);

        expect($context->getPayload())->toBe(['action' => 'test']);
    });

    it('getPayload() возвращает null при отсутствии payload', function (): void {
        expect(ctx()->getPayload())->toBeNull();
    });

    it('getPayload() принимает уже декодированный массив', function (): void {
        $context = ctx(['payload' => ['action' => 'test']]);

        expect($context->getPayload())->toBe(['action' => 'test']);
    });

    it('getMessageId() возвращает ID сообщения', function (): void {
        expect(ctx(['id' => 999])->getMessageId())->toBe(999);
    });

    it('getEventObject() возвращает сырой объект', function (): void {
        $raw = ['message' => ['id' => 1, 'peer_id' => 100, 'from_id' => 42, 'text' => 'Привет']];
        $api = Mockery::mock(VKApiClient::class);
        $context = new MessageContext($api, 'token', EventType::MessageNew, $raw);

        expect($context->getEventObject())->toBe($raw);
    });

    it('getEventId() возвращает null для не-event событий', function (): void {
        expect(ctx()->getEventId())->toBeNull();
    });

    it('getMessageEvent() возвращает null для не-event событий', function (): void {
        expect(ctx()->getMessageEvent())->toBeNull();
    });

    it('getMessageEvent() возвращает объект для MessageEvent', function (): void {
        $api = Mockery::mock(VKApiClient::class);
        $obj = ['event_id' => 'abc', 'user_id' => 1, 'peer_id' => 100, 'payload' => ['event' => 'test']];
        $ctx = new MessageContext($api, 'token', EventType::MessageEvent, $obj);

        expect($ctx->getMessageEvent())->toBe($obj);
        expect($ctx->getEventId())->toBe('abc');
    });

});
