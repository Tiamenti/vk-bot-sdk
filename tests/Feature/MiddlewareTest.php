<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Facades\Vk;
use Tiamenti\VkBotSdk\Http\Controllers\CallbackController;
use Tiamenti\VkBotSdk\Middleware\VkMiddleware;

// ---------------------------------------------------------------------------
// Тестовые middleware
// ---------------------------------------------------------------------------

class PassThroughMiddleware implements VkMiddleware
{
    public static int $callCount = 0;

    public function handle(MessageContext $ctx, callable $next): void
    {
        self::$callCount++;
        $next($ctx);
    }
}

class BlockingMiddleware implements VkMiddleware
{
    public static bool $blocked = false;

    public function handle(MessageContext $ctx, callable $next): void
    {
        self::$blocked = true;
        // Намеренно не вызываем $next — блокируем цепочку
    }
}

class OrderTrackingMiddleware implements VkMiddleware
{
    public static array $order = [];

    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function handle(MessageContext $ctx, callable $next): void
    {
        self::$order[] = $this->name.':before';
        $next($ctx);
        self::$order[] = $this->name.':after';
    }
}

// ---------------------------------------------------------------------------
// Хелпер
// ---------------------------------------------------------------------------

function postVkEvent(mixed $test, string $text = 'test'): void
{
    Route::post('/vk/webhook', CallbackController::class);

    $test->postJson('/vk/webhook', [
        'type' => 'message_new',
        'secret' => 'test_secret',
        'group_id' => 123456,
        'object' => [
            'message' => [
                'id' => 1,
                'peer_id' => 100,
                'from_id' => 1,
                'text' => $text,
            ],
        ],
    ]);
}

// ---------------------------------------------------------------------------
// Тесты
// ---------------------------------------------------------------------------

describe('Middleware pipeline', function (): void {

    beforeEach(function (): void {
        PassThroughMiddleware::$callCount = 0;
        BlockingMiddleware::$blocked = false;
        OrderTrackingMiddleware::$order = [];
    });

    it('вызывает глобальный middleware', function (): void {
        Vk::middleware(PassThroughMiddleware::class);
        Vk::hears('test', fn () => null);

        postVkEvent($this);

        expect(PassThroughMiddleware::$callCount)->toBe(1);
    });

    it('middleware может заблокировать обработчик', function (): void {
        $handlerCalled = false;

        Vk::middleware(BlockingMiddleware::class);
        Vk::hears('test', function () use (&$handlerCalled): void {
            $handlerCalled = true;
        });

        postVkEvent($this);

        expect(BlockingMiddleware::$blocked)->toBeTrue();
        expect($handlerCalled)->toBeFalse();
    });

    it('callable middleware работает корректно', function (): void {
        $middlewareCalled = false;

        Vk::middleware(function (MessageContext $ctx, callable $next) use (&$middlewareCalled): void {
            $middlewareCalled = true;
            $next($ctx);
        });

        Vk::hears('test', fn () => null);

        postVkEvent($this);

        expect($middlewareCalled)->toBeTrue();
    });

    it('группа middleware применяется только к своим обработчикам', function (): void {
        $outsideCalled = false;
        $insideCalled = false;

        Vk::hears('outside', function () use (&$outsideCalled): void {
            $outsideCalled = true;
        });

        Vk::group(function () use (&$insideCalled): void {
            Vk::middleware(PassThroughMiddleware::class);
            Vk::hears('inside', function () use (&$insideCalled): void {
                $insideCalled = true;
            });
        });

        postVkEvent($this, 'outside');
        expect(PassThroughMiddleware::$callCount)->toBe(0);
        expect($outsideCalled)->toBeTrue();
    });

    it('middleware вызываются в правильном порядке', function (): void {
        // Используем callable middleware с именем
        Vk::middleware(function (MessageContext $ctx, callable $next): void {
            OrderTrackingMiddleware::$order[] = 'global:before';
            $next($ctx);
            OrderTrackingMiddleware::$order[] = 'global:after';
        });

        Vk::hears('test', fn () => null);

        postVkEvent($this);

        expect(OrderTrackingMiddleware::$order)->toBe(['global:before', 'global:after']);
    });

});
