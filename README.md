# tiamenti/vk-bot-sdk

Laravel SDK для разработки VK-ботов. Поддерживает Callback API и Long Poll, fluent-builder клавиатур, пошаговые диалоги (Conversations) и middleware-пайплайны.

Поддерживает Laravel 11, 12 и 13.

---

## Установка

```bash
composer require tiamenti/vk-bot-sdk
php artisan vendor:publish --tag=vk-bot-config
```

Добавьте в `.env`:

```dotenv
VK_BOT_TOKEN=your_token
VK_BOT_GROUP_ID=123456
VK_BOT_SECRET=your_secret
VK_BOT_CONFIRMATION_TOKEN=your_confirm_string
```

Подключите маршрут в `routes/web.php`:

```php
use Tiamenti\VkBotSdk\Http\Controllers\CallbackController;

Route::post('/vk/webhook', CallbackController::class);
```

---

## Примеры

### Обработка сообщений

```php
// routes/vk.php
use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Facades\Vk;

Vk::command('start', function (MessageContext $ctx): void {
    $ctx->reply('Добро пожаловать!');
});

Vk::hears(['привет', 'hi'], function (MessageContext $ctx): void {
    $ctx->reply("Привет, {$ctx->getFromId()}!");
});

Vk::fallback(function (MessageContext $ctx): void {
    $ctx->reply('Не понимаю вас. Напишите /start');
});
```

### Клавиатура

```php
use Tiamenti\VkBotSdk\Enums\ButtonColor;
use Tiamenti\VkBotSdk\Keyboard\Keyboard;

Vk::command('menu', function (MessageContext $ctx): void {
    $keyboard = Keyboard::make()
        ->button('📋 Заказы',  ButtonColor::Primary,   ['action' => 'orders'])
        ->button('⚙️ Настройки', ButtonColor::Secondary, ['action' => 'settings'])
        ->row()
        ->button('❌ Отмена', ButtonColor::Negative, ['action' => 'cancel'])
        ->oneTime();

    $ctx->reply('Главное меню:', keyboard: $keyboard);
});
```

### Пошаговый диалог

```php
use Tiamenti\VkBotSdk\Conversations\Conversation;

class FeedbackConversation extends Conversation
{
    public function start(MessageContext $ctx): void
    {
        $ctx->reply('Оставьте ваш отзыв:');
        $this->next('saveFeedback');
    }

    public function saveFeedback(MessageContext $ctx): void
    {
        // сохранить $ctx->getText() в БД...
        $ctx->reply('Спасибо за отзыв!');
        $this->end();
    }
}

Vk::hears('отзыв', FeedbackConversation::class);
```

### Middleware

```php
Vk::group(function (): void {
    Vk::middleware(AdminMiddleware::class);

    Vk::command('stats', [StatsHandler::class, 'handle']);
    Vk::command('ban',   [BanHandler::class,   'handle']);
});
```

---

## Artisan-команды

```bash
php artisan vk:make:handler    MyHandler
php artisan vk:make:middleware MyMiddleware
php artisan vk:make:conversation MyConversation
php artisan vk:bot:listen      # Long Poll режим
```

---

## Документация

Полная документация в папке [`docs/`](docs/):

- [Установка](docs/installation.md)
- [Конфигурация](docs/configuration.md)
- [Обработчики](docs/handlers.md)
- [Context API](docs/context-api.md)
- [Клавиатура](docs/keyboard.md)
- [Conversations](docs/conversations.md)
- [Middleware](docs/middleware.md)
- [Long Poll](docs/longpoll.md)
- [Тестирование](docs/testing.md)

---

## Лицензия

MIT © [Tiamenti](https://tiamenti.ru)
