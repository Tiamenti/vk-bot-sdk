# Conversations (диалоги)

Conversations — механизм пошаговых диалогов с пользователем. Вдохновлён Nutgram.

---

## Концепция

Каждый класс диалога — это набор **методов-шагов**. Бот запоминает, на каком шаге находится пользователь, и вызывает нужный метод при следующем сообщении.

```
Пользователь → "регистрация"
Бот: вызывает askName()  → "Как тебя зовут?"
Пользователь → "Иван"
Бот: вызывает askAge()   → "Сколько тебе лет?"
Пользователь → "25"
Бот: вызывает finish()   → "Привет, Иван! Тебе 25 лет."
```

---

## Создание диалога

```bash
php artisan vk:make:conversation RegistrationConversation
```

Файл создастся в `app/VK/Conversations/RegistrationConversation.php`.

---

## Пример

```php
<?php

namespace App\VK\Conversations;

use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Conversations\Conversation;

class RegistrationConversation extends Conversation
{
    // Первый шаг по умолчанию (если null — ищет метод start())
    protected ?string $step = 'askName';

    public function askName(MessageContext $ctx): void
    {
        $ctx->reply('Как тебя зовут?');
        $this->next('askAge');
    }

    public function askAge(MessageContext $ctx): void
    {
        $this->set('name', $ctx->getText());
        $ctx->reply('Сколько тебе лет?');
        $this->next('finish');
    }

    public function finish(MessageContext $ctx): void
    {
        $name = $this->get('name');
        $age  = $ctx->getText();

        $ctx->reply("Привет, {$name}! Тебе {$age} лет. Регистрация завершена!");
        $this->end();
    }
}
```

---

## Запуск диалога

### Из обработчика

```php
use App\VK\Conversations\RegistrationConversation;
use Tiamenti\VkBotSdk\Facades\Vk;

// Через замыкание
Vk::hears('регистрация', function (MessageContext $ctx): void {
    RegistrationConversation::begin($ctx);
});

// Через имя класса напрямую
Vk::hears('регистрация', RegistrationConversation::class);
```

### С начальными данными

```php
Vk::hears('купить', function (MessageContext $ctx): void {
    PurchaseConversation::begin($ctx, ['item_id' => 42]);
});

// В диалоге:
public function askConfirm(MessageContext $ctx): void
{
    $itemId = $this->get('item_id'); // 42
}
```

---

## Методы класса Conversation

| Метод | Описание |
|---|---|
| `$this->next(string $step)` | Перейти к следующему шагу |
| `$this->end()` | Завершить диалог |
| `$this->set(string $key, mixed $value)` | Сохранить данные |
| `$this->get(string $key, mixed $default = null)` | Получить данные |
| `$this->getData()` | Все данные диалога |
| `$this->setSkipMiddlewares(bool $skip)` | Пропустить middleware для следующего шага |
| `static::begin(MessageContext $ctx, array $args = [])` | Запустить диалог |

---

## Хранение состояния

### Cache (по умолчанию)

```dotenv
VK_BOT_CONVERSATIONS_DRIVER=cache
VK_BOT_CONVERSATIONS_TTL=60
```

Подходит для большинства случаев. Использует любой настроенный Laravel Cache-драйвер (Redis, Memcached, File, ...).

### Database

```dotenv
VK_BOT_CONVERSATIONS_DRIVER=database
```

Опубликуйте и запустите миграции:

```bash
php artisan vendor:publish --tag=vk-bot-migrations
php artisan migrate
```

---

## Прерывание диалога

Если пользователь отправляет сообщение, которое должно выйти из диалога (например, `/cancel`), зарегистрируйте глобальный middleware:

```php
use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Conversations\ConversationManager;
use Tiamenti\VkBotSdk\Middleware\VkMiddleware;

class CancelMiddleware implements VkMiddleware
{
    public function __construct(private readonly ConversationManager $manager) {}

    public function handle(MessageContext $ctx, callable $next): void
    {
        if ($ctx->getText() === '/cancel' && $this->manager->hasActive($ctx->getPeerId())) {
            $this->manager->end($ctx->getPeerId());
            $ctx->reply('Диалог отменён.');
            return;
        }

        $next($ctx);
    }
}
```

```php
// routes/vk.php
Vk::middleware(CancelMiddleware::class);
```

---

## Примечания

> **⚠️ Важно**
> Пока у пользователя активен диалог — все его сообщения перехватываются диалогом, даже если они совпадают с `hears()` или `command()`. Это задуманное поведение.

> **⚠️ Важно**
> Методы шагов должны быть **public**. Protected и private методы не будут вызваны ConversationManager.
