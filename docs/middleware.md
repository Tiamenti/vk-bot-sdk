# Middleware

Middleware позволяет перехватывать запросы до вызова обработчика и после него.

---

## Создание middleware

```bash
php artisan vk:make:middleware AdminMiddleware
```

Файл создастся в `app/VK/Middleware/AdminMiddleware.php`.

### Реализация интерфейса

```php
<?php

namespace App\VK\Middleware;

use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Middleware\VkMiddleware;

final class AdminMiddleware implements VkMiddleware
{
    private const ADMIN_IDS = [123456, 789012];

    public function handle(MessageContext $ctx, callable $next): void
    {
        if (! in_array($ctx->getFromId(), self::ADMIN_IDS, strict: true)) {
            $ctx->reply('Доступ запрещён. Эта команда только для администраторов.');
            return; // Не вызываем $next — блокируем дальнейшую обработку
        }

        $next($ctx); // Передаём управление следующему middleware или обработчику
    }
}
```

---

## Регистрация

### Глобальный middleware

Применяется ко **всем** обработчикам.

```php
// routes/vk.php
use App\VK\Middleware\LoggingMiddleware;

Vk::middleware(LoggingMiddleware::class);

Vk::hears('привет', fn ($ctx) => $ctx->reply('Привет!')); // LoggingMiddleware сработает
Vk::command('start', fn ($ctx) => $ctx->reply('Старт'));  // LoggingMiddleware сработает
```

### Middleware группы

Применяется только к обработчикам внутри группы.

```php
Vk::group(function (): void {
    Vk::middleware(AdminMiddleware::class);

    Vk::hears('статистика', [StatsHandler::class, 'index']);
    Vk::command('ban', [BanHandler::class, 'handle']);
    // AdminMiddleware применяется только к этим двум обработчикам
});

Vk::hears('привет', fn ($ctx) => $ctx->reply('Привет!')); // AdminMiddleware НЕ применяется
```

### Callable middleware

Быстрый способ без создания отдельного класса:

```php
Vk::middleware(function (MessageContext $ctx, callable $next): void {
    logger()->info('VK event', ['peer_id' => $ctx->getPeerId()]);
    $next($ctx);
});
```

---

## Порядок выполнения

Middleware выполняются в порядке: глобальные → middleware группы → обработчик.

```
Запрос → Global MW 1 → Global MW 2 → Group MW → Handler → Group MW → Global MW 2 → Global MW 1 → Ответ
```

---

## Практические примеры

### Логирование

```php
final class LoggingMiddleware implements VkMiddleware
{
    public function handle(MessageContext $ctx, callable $next): void
    {
        $start = microtime(true);

        logger()->info('VK event received', [
            'event'   => $ctx->getEvent()->value,
            'peer_id' => $ctx->getPeerId(),
            'from_id' => $ctx->getFromId(),
            'text'    => $ctx->getText(),
        ]);

        $next($ctx);

        logger()->info('VK event handled', [
            'duration_ms' => round((microtime(true) - $start) * 1000),
        ]);
    }
}
```

### Ограничение частоты запросов (Rate Limiting)

```php
use Illuminate\Support\Facades\RateLimiter;

final class RateLimitMiddleware implements VkMiddleware
{
    public function handle(MessageContext $ctx, callable $next): void
    {
        $key      = 'vk_user:' . $ctx->getFromId();
        $maxAttempts = 10;
        $decaySeconds = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $ctx->reply('Слишком много запросов. Подождите немного.');
            return;
        }

        RateLimiter::hit($key, $decaySeconds);
        $next($ctx);
    }
}
```

### Проверка подписки

```php
final class SubscriberOnlyMiddleware implements VkMiddleware
{
    public function __construct(
        private readonly \Illuminate\Database\ConnectionInterface $db,
    ) {}

    public function handle(MessageContext $ctx, callable $next): void
    {
        $isSubscriber = $this->db
            ->table('subscribers')
            ->where('vk_id', $ctx->getFromId())
            ->exists();

        if (! $isSubscriber) {
            $ctx->reply('Эта функция доступна только подписчикам.');
            return;
        }

        $next($ctx);
    }
}
```
