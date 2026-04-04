<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tiamenti\VkBotSdk\Enums\EventType;
use Tiamenti\VkBotSdk\Facades\Vk;
use Tiamenti\VkBotSdk\Http\Controllers\CallbackController;

beforeEach(function (): void {
    Route::post('/vk/webhook', CallbackController::class);
});

/**
 * Отправить тестовое событие message_new.
 */
function sendMessage(mixed $test, string $text, ?array $payload = null): void
{
    $message = [
        'id'      => rand(1, 9999),
        'peer_id' => 100,
        'from_id' => 1,
        'text'    => $text,
    ];

    if ($payload !== null) {
        $message['payload'] = json_encode($payload);
    }

    $test->postJson('/vk/webhook', [
        'type'     => 'message_new',
        'secret'   => 'test_secret',
        'group_id' => 123456,
        'object'   => ['message' => $message],
    ]);
}

function sendEvent(mixed $test, EventType $type, array $object = []): void
{
    $test->postJson('/vk/webhook', [
        'type'     => $type->value,
        'secret'   => 'test_secret',
        'group_id' => 123456,
        'object'   => $object,
    ]);
}

// ---------------------------------------------------------------------------
// Тесты hears()
// ---------------------------------------------------------------------------

describe('Vk::hears()', function (): void {

    it('срабатывает на точное совпадение', function (): void {
        $called = false;
        Vk::hears('привет', function () use (&$called): void { $called = true; });
        sendMessage($this, 'привет');
        expect($called)->toBeTrue();
    });

    it('нечувствителен к регистру', function (): void {
        $called = false;
        Vk::hears('привет', function () use (&$called): void { $called = true; });
        sendMessage($this, 'ПРИВЕТ');
        expect($called)->toBeTrue();
    });

    it('срабатывает по массиву паттернов', function (): void {
        $results = [];
        Vk::hears(['да', 'нет', 'может'], function () use (&$results): void {
            $results[] = true;
        });

        sendMessage($this, 'да');
        sendMessage($this, 'нет');
        sendMessage($this, 'может');

        expect($results)->toHaveCount(3);
    });

    it('срабатывает по регулярному выражению', function (): void {
        $called = false;
        Vk::hears('/^заказ\s#\d+/ui', function () use (&$called): void { $called = true; });
        sendMessage($this, 'Заказ #12345 оформлен');
        expect($called)->toBeTrue();
    });

    it('не срабатывает на частичное совпадение', function (): void {
        $called = false;
        Vk::hears('привет', function () use (&$called): void { $called = true; });
        sendMessage($this, 'приветствую');
        expect($called)->toBeFalse();
    });

});

// ---------------------------------------------------------------------------
// Тесты command()
// ---------------------------------------------------------------------------

describe('Vk::command()', function (): void {

    it('срабатывает на команду со слешем', function (): void {
        $called = false;
        Vk::command('start', function () use (&$called): void { $called = true; });
        sendMessage($this, '/start');
        expect($called)->toBeTrue();
    });

    it('срабатывает на команду без слеша', function (): void {
        $called = false;
        Vk::command('/help', function () use (&$called): void { $called = true; });
        sendMessage($this, 'help');
        expect($called)->toBeTrue();
    });

});

// ---------------------------------------------------------------------------
// Тесты onPayload()
// ---------------------------------------------------------------------------

describe('Vk::onPayload()', function (): void {

    it('срабатывает на совпадение payload', function (): void {
        $called = false;
        Vk::onPayload(['action' => 'buy'], function () use (&$called): void { $called = true; });
        sendMessage($this, '', ['action' => 'buy']);
        expect($called)->toBeTrue();
    });

    it('не срабатывает на несовпадающий payload', function (): void {
        $called = false;
        Vk::onPayload(['action' => 'buy'], function () use (&$called): void { $called = true; });
        sendMessage($this, '', ['action' => 'cancel']);
        expect($called)->toBeFalse();
    });

});

// ---------------------------------------------------------------------------
// Тесты on()
// ---------------------------------------------------------------------------

describe('Vk::on()', function (): void {

    it('срабатывает на EventType::GroupJoin', function (): void {
        $called = false;
        Vk::on(EventType::GroupJoin, function () use (&$called): void { $called = true; });

        sendEvent($this, EventType::GroupJoin, ['user_id' => 1, 'join_type' => 'join']);

        expect($called)->toBeTrue();
    });

    it('не срабатывает на другой тип события', function (): void {
        $called = false;
        Vk::on(EventType::GroupLeave, function () use (&$called): void { $called = true; });
        sendMessage($this, 'привет');
        expect($called)->toBeFalse();
    });

});

// ---------------------------------------------------------------------------
// Тесты fallback()
// ---------------------------------------------------------------------------

describe('Vk::fallback()', function (): void {

    it('срабатывает если ни один обработчик не совпал', function (): void {
        $called = false;
        Vk::hears('конкретный текст', fn () => null);
        Vk::fallback(function () use (&$called): void { $called = true; });
        sendMessage($this, 'что-то другое');
        expect($called)->toBeTrue();
    });

    it('не срабатывает если обработчик найден', function (): void {
        $fallbackCalled = false;
        $handlerCalled  = false;

        Vk::hears('точный текст', function () use (&$handlerCalled): void { $handlerCalled = true; });
        Vk::fallback(function () use (&$fallbackCalled): void { $fallbackCalled = true; });

        sendMessage($this, 'точный текст');

        expect($handlerCalled)->toBeTrue();
        expect($fallbackCalled)->toBeFalse();
    });

});

// ---------------------------------------------------------------------------
// Тесты group()
// ---------------------------------------------------------------------------

describe('Vk::group()', function (): void {

    it('обработчики в группе работают', function (): void {
        $called = false;

        Vk::group(function () use (&$called): void {
            Vk::hears('группа', function () use (&$called): void { $called = true; });
        });

        sendMessage($this, 'группа');

        expect($called)->toBeTrue();
    });

});
