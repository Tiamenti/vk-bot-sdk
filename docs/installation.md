# Установка

## Требования

| Пакет | Версия |
|---|---|
| PHP | `^8.2` |
| Laravel | `^11.0`, `^12.0` или `^13.0` |
| vkcom/vk-php-sdk | `^6.0` |

---

## Шаг 1 — Установка через Composer

```bash
composer require tiamenti/vk-bot-sdk
```

Laravel автоматически зарегистрирует `VkServiceProvider` и фасад `Vk::` через Package Discovery.

---

## Шаг 2 — Публикация конфигурации

```bash
php artisan vendor:publish --tag=vk-bot-config
```

Файл конфигурации появится по пути `config/vk-bot.php`.

---

## Шаг 3 — Настройка переменных окружения

Добавьте в `.env`:

```dotenv
VK_BOT_TOKEN=your_community_token
VK_BOT_GROUP_ID=123456789
VK_BOT_SECRET=your_secret_key
VK_BOT_CONFIRMATION_TOKEN=your_confirmation_string
VK_BOT_MODE=callback
```

Значения получаются в ВКонтакте:
- **VK_BOT_TOKEN** — Управление сообществом → Настройки → Работа с API → Ключи доступа
- **VK_BOT_GROUP_ID** — ID сообщества (числовой)
- **VK_BOT_SECRET** — Управление → Настройки → Callback API → Секретный ключ
- **VK_BOT_CONFIRMATION_TOKEN** — Управление → Настройки → Callback API → Строка для подтверждения

---

## Шаг 4 — Создание файла обработчиков

```bash
# Файл создаётся автоматически только если его нет
touch routes/vk.php
```

Или скопируйте пример из пакета:

```bash
cp vendor/tiamenti/vk-bot-sdk/routes/vk.php routes/vk.php
```

---

## Шаг 5 — Подключение вебхука (Callback API)

Добавьте маршрут в `routes/web.php` или `routes/api.php`:

```php
use Tiamenti\VkBotSdk\Http\Controllers\CallbackController;

Route::post('/vk/webhook', CallbackController::class)->name('vk.webhook');
```

> **⚠️ Важно**
> Маршрут должен быть исключён из CSRF-защиты. Добавьте его в `bootstrap/app.php`:
>
> ```php
> ->withMiddleware(function (Middleware $middleware) {
>     $middleware->validateCsrfTokens(except: [
>         'vk/webhook',
>     ]);
> })
> ```

---

## Опциональные шаги

### Публикация миграций (если используется database-драйвер для Conversations)

```bash
php artisan vendor:publish --tag=vk-bot-migrations
php artisan migrate
```

### Проверка установки

```bash
php artisan about
```

В секции "Package Information" должен отображаться `tiamenti/vk-bot-sdk`.

---

## Что дальше?

- [Конфигурация](configuration.md)
- [Обработчики](handlers.md)
- [Клавиатура](keyboard.md)
- [Conversations](conversations.md)
