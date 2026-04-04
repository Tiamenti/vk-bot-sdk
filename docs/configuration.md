# Конфигурация

Конфигурация пакета находится в `config/vk-bot.php`. Все параметры можно переопределить через переменные окружения `.env`.

---

## Параметры

### `mode`

**Тип:** `string`  
**По умолчанию:** `'callback'`  
**Переменная:** `VK_BOT_MODE`

Режим работы бота:

| Значение | Описание |
|---|---|
| `callback` | VK присылает события на вебхук (POST-запросы) |
| `longpoll` | Бот сам опрашивает VK Long Poll сервер |

---

### `token`

**Тип:** `string`  
**Переменная:** `VK_BOT_TOKEN`

Токен доступа сообщества. Генерируется в: Управление → Настройки → Работа с API → Ключи доступа.

Необходимые права: `messages`, `photos`, `docs` (в зависимости от функционала бота).

---

### `group_id`

**Тип:** `int`  
**Переменная:** `VK_BOT_GROUP_ID`

Числовой ID сообщества ВКонтакте (без минуса).

---

### `secret`

**Тип:** `string`  
**Переменная:** `VK_BOT_SECRET`

Секретный ключ Callback API. Если задан — каждый входящий запрос проверяется на совпадение поля `secret`. Рекомендуется устанавливать всегда.

> **⚠️ Важно**
> Если `secret` задан, но в запросе от VK он отсутствует или не совпадает — запрос вернёт HTTP 403.

---

### `confirmation_token`

**Тип:** `string`  
**Переменная:** `VK_BOT_CONFIRMATION_TOKEN`

Строка для подтверждения адреса сервера. VK отправляет запрос с `"type": "confirmation"`, бот должен ответить этой строкой.

---

### `api_version`

**Тип:** `string`  
**По умолчанию:** `'5.199'`  
**Переменная:** `VK_API_VERSION`

Версия VK API. Рекомендуется использовать актуальную версию.

---

### `routes.path`

**Тип:** `string`  
**По умолчанию:** `'routes/vk.php'`  
**Переменная:** `VK_BOT_ROUTES_PATH`

Путь к файлу с обработчиками относительно корня проекта (`base_path()`).

```php
// config/vk-bot.php
'routes' => [
    'path' => 'routes/vk.php',
],
```

---

### `conversations`

| Ключ | Тип | По умолчанию | Описание |
|---|---|---|---|
| `driver` | `string` | `'cache'` | Драйвер хранения: `cache` или `database` |
| `ttl` | `int` | `60` | Время жизни состояния в минутах |
| `table` | `string` | `'vk_conversations'` | Таблица БД (только для database-драйвера) |

---

### `facade`

**Тип:** `bool`  
**По умолчанию:** `true`  
**Переменная:** `VK_BOT_FACADE`

Установите в `false`, чтобы отключить регистрацию фасада `Vk::`. В этом случае используйте инъекцию зависимости:

```php
use Tiamenti\VkBotSdk\VkBot;

class MyController
{
    public function __construct(private readonly VkBot $bot) {}
}
```

---

### `longpoll`

| Ключ | Тип | По умолчанию | Описание |
|---|---|---|---|
| `wait` | `int` | `25` | Время ожидания ответа сервера (сек., макс. 90) |
| `retry_delay` | `int` | `5` | Задержка при ошибке (сек.) |

---

### `http.timeout`

**Тип:** `int`  
**По умолчанию:** `30`  
**Переменная:** `VK_BOT_HTTP_TIMEOUT`

Таймаут HTTP-запросов к VK API в секундах.

---

## Пример полного `.env`

```dotenv
VK_BOT_MODE=callback
VK_BOT_TOKEN=vk1.a.XXXXXXXXXXXXXXXX
VK_BOT_GROUP_ID=123456789
VK_BOT_SECRET=my_super_secret
VK_BOT_CONFIRMATION_TOKEN=abc123xyz
VK_API_VERSION=5.199
VK_BOT_ROUTES_PATH=routes/vk.php
VK_BOT_CONVERSATIONS_DRIVER=cache
VK_BOT_CONVERSATIONS_TTL=60
VK_BOT_LONGPOLL_WAIT=25
```
