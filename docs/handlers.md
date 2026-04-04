# Обработчики

Все обработчики регистрируются в файле `routes/vk.php` (путь настраивается в конфиге).

Файл подгружается автоматически при первом запросе к боту.

---

## Vk::hears() — текстовые паттерны

Срабатывает при точном совпадении текста входящего сообщения (без учёта регистра).

```php
use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Facades\Vk;

// Точное совпадение
Vk::hears('привет', function (MessageContext $ctx): void {
    $ctx->reply('Привет!');
});

// Несколько вариантов
Vk::hears(['да', 'ок', 'хорошо'], function (MessageContext $ctx): void {
    $ctx->reply('Отлично!');
});

// Регулярное выражение (должно начинаться с /)
Vk::hears('/^заказ\s#\d+/ui', function (MessageContext $ctx): void {
    $ctx->reply('Обрабатываю ваш заказ...');
});
```

---

## Vk::command() — команды

Работает так же, как `hears()`, но нечувствителен к наличию символа `/` в начале.

```php
// Сработает и на /start, и на start
Vk::command('start', function (MessageContext $ctx): void {
    $ctx->reply('Добро пожаловать!');
});

Vk::command('/help', function (MessageContext $ctx): void {
    $ctx->reply('Список команд: /start, /help');
});
```

---

## Vk::on() — события

Реагирует на конкретный тип события VK.

```php
use Tiamenti\VkBotSdk\Enums\EventType;

Vk::on(EventType::GroupJoin, function (MessageContext $ctx): void {
    $ctx->reply('Добро пожаловать в сообщество!');
});

Vk::on(EventType::MessageEvent, function (MessageContext $ctx): void {
    $eventId = $ctx->getEventId();
    $userId  = $ctx->getFromId();

    $ctx->sendMessageEventAnswer($eventId, $userId, [
        'type'    => 'show_snackbar',
        'text'    => 'Вы нажали кнопку!',
    ]);
});
```

Полный список событий — в [Enums/EventType](../src/Enums/EventType.php) или [документации VK](https://dev.vk.com/ru/api/community-events/json-schema).

---

## Vk::onPayload() — кнопки с payload

Срабатывает при нажатии кнопки с определённым payload.

```php
// Совпадение по массиву (все ключи должны совпасть)
Vk::onPayload(['action' => 'buy', 'item' => 'book'], function (MessageContext $ctx): void {
    $ctx->reply('Вы покупаете книгу!');
});

// Несколько вариантов payload
Vk::onPayload([
    ['action' => 'yes'],
    ['action' => 'confirm'],
], function (MessageContext $ctx): void {
    $ctx->reply('Подтверждено!');
});
```

---

## Vk::fallback() — обработчик по умолчанию

Вызывается, если ни один другой обработчик не совпал.

```php
Vk::fallback(function (MessageContext $ctx): void {
    $ctx->reply('Не понимаю вас. Напишите /help.');
});
```

> **⚠️ Важно**
> Должен быть зарегистрирован **последним** — иначе заблокирует все последующие обработчики.

---

## Vk::group() — группы

Объединяет обработчики с общими middleware.

```php
use App\VK\Middleware\AdminMiddleware;

Vk::group(function (): void {
    Vk::middleware(AdminMiddleware::class);

    Vk::hears('статистика', function (MessageContext $ctx): void {
        $ctx->reply('Статистика: ...');
    });

    Vk::command('ban', function (MessageContext $ctx): void {
        $ctx->reply('Пользователь забанен.');
    });
});
```

Группы можно вкладывать:

```php
Vk::group(function (): void {
    Vk::middleware(AuthMiddleware::class);

    Vk::group(function (): void {
        Vk::middleware(AdminMiddleware::class);
        Vk::hears('admin only', fn ($ctx) => $ctx->reply('OK'));
    });
});
```

---

## Обработчики в виде классов

Вместо замыканий можно использовать классы:

```php
// Invokable-класс
Vk::hears('привет', \App\VK\Handlers\GreetingHandler::class);

// Метод класса
Vk::command('start', [\App\VK\Handlers\StartHandler::class, 'handle']);
```

Классы создаются через Laravel Container, поэтому инъекция зависимостей работает автоматически.

Создать шаблон обработчика:

```bash
php artisan vk:make:handler GreetingHandler
```

---

## Порядок обработки

1. Если есть активный диалог (`Conversation`) — он обрабатывает событие
2. Обработчики проверяются в порядке регистрации
3. Первый совпавший обработчик выполняется, остальные пропускаются
4. Если ни один не совпал — вызывается `fallback()`
