# Тестирование

Пакет поддерживает тестирование через Pest 3+ и Orchestra Testbench.

---

## Установка зависимостей

```bash
composer require --dev pestphp/pest pestphp/pest-plugin-laravel orchestra/testbench
```

---

## Базовый TestCase

```php
<?php

namespace Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Tiamenti\VkBotSdk\VkServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [VkServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('vk-bot.token', 'test_token');
        $app['config']->set('vk-bot.secret', 'test_secret');
        $app['config']->set('vk-bot.confirmation_token', 'confirm_abc');
        $app['config']->set('vk-bot.group_id', 123456);
        $app['config']->set('vk-bot.routes.path', 'routes/vk_test.php');
        $app['config']->set('vk-bot.conversations.driver', 'cache');
        $app['config']->set('cache.default', 'array');
    }
}
```

---

## Тестирование Callback API

```php
use Illuminate\Support\Facades\Route;
use Tiamenti\VkBotSdk\Facades\Vk;
use Tiamenti\VkBotSdk\Http\Controllers\CallbackController;

beforeEach(function (): void {
    Route::post('/vk/webhook', CallbackController::class);
});

it('возвращает confirmation_token', function (): void {
    $this->postJson('/vk/webhook', [
        'type'   => 'confirmation',
        'secret' => 'test_secret',
    ])->assertOk()->assertSeeText('confirm_abc');
});

it('вызывает обработчик на message_new', function (): void {
    $called = false;
    Vk::hears('тест', function () use (&$called): void { $called = true; });

    $this->postJson('/vk/webhook', [
        'type'   => 'message_new',
        'secret' => 'test_secret',
        'object' => ['message' => ['peer_id' => 100, 'from_id' => 1, 'text' => 'тест']],
    ])->assertOk();

    expect($called)->toBeTrue();
});
```

---

## Тестирование Keyboard

```php
use Tiamenti\VkBotSdk\Enums\ButtonColor;
use Tiamenti\VkBotSdk\Keyboard\Keyboard;

it('формирует корректную структуру клавиатуры', function (): void {
    $array = Keyboard::make()
        ->button('OK', ButtonColor::Primary, ['action' => 'ok'])
        ->row()
        ->button('Отмена', ButtonColor::Negative)
        ->oneTime()
        ->toArray();

    expect($array['one_time'])->toBeTrue();
    expect($array['buttons'])->toHaveCount(2);
    expect($array['buttons'][0][0]['color'])->toBe('primary');
    expect($array['buttons'][1][0]['color'])->toBe('negative');
});
```

---

## Тестирование Conversations

```php
use Illuminate\Support\Facades\Cache;
use Tiamenti\VkBotSdk\Conversations\ConversationManager;

beforeEach(fn () => Cache::flush());

it('сохраняет и восстанавливает состояние', function (): void {
    $manager = app(ConversationManager::class);
    $manager->start(100, MyConversation::class, 'step1', ['key' => 'value']);

    expect($manager->hasActive(100))->toBeTrue();
    expect($manager->getData(100)['key'])->toBe('value');
});

it('завершает диалог', function (): void {
    $manager = app(ConversationManager::class);
    $manager->start(100, MyConversation::class, 'step1');
    $manager->end(100);

    expect($manager->hasActive(100))->toBeFalse();
});
```

---

## Тестирование Middleware

```php
use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Middleware\VkMiddleware;

class TestMiddleware implements VkMiddleware
{
    public static bool $called = false;

    public function handle(MessageContext $ctx, callable $next): void
    {
        self::$called = true;
        $next($ctx);
    }
}

it('middleware вызывается перед обработчиком', function (): void {
    TestMiddleware::$called = false;

    Vk::middleware(TestMiddleware::class);
    Vk::hears('тест', fn () => null);

    $this->postJson('/vk/webhook', [/* ... */]);

    expect(TestMiddleware::$called)->toBeTrue();
});
```

---

## Мокирование VK API

Для тестирования без реальных запросов к VK API:

```php
use Mockery;
use VK\Client\VKApiClient;

it('отправляет сообщение через API', function (): void {
    $api = Mockery::mock(VKApiClient::class);
    $this->app->instance(VKApiClient::class, $api);

    $api->shouldReceive('messages->send')
        ->once()
        ->with('test_token', Mockery::subset(['peer_id' => 100]))
        ->andReturn(12345);

    // ... триггерим обработчик который вызовет $ctx->reply()
});
```

---

## Запуск тестов

```bash
# Все тесты
php artisan test
# или
./vendor/bin/pest

# Конкретный файл
./vendor/bin/pest tests/Feature/RouterTest.php

# С покрытием
./vendor/bin/pest --coverage
```
