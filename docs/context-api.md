# Context API (MessageContext)

`MessageContext` — единственный аргумент во всех обработчиках. Содержит данные входящего события и методы для ответа.

---

## Геттеры

### Событие и его тип

```php
$ctx->getEvent();        // EventType — тип события
$ctx->getEventObject();  // array — сырой объект события (поле 'object')
```

### Данные сообщения

```php
$ctx->getMessage();    // array|null — объект сообщения
$ctx->getMessageId();  // int|null — ID сообщения
$ctx->getText();       // string|null — текст сообщения (null если пусто)
$ctx->text();          // string|null — псевдоним getText()
```

### Участники

```php
$ctx->getPeerId();   // int — ID беседы или пользователя
$ctx->getFromId();   // int — ID отправителя
```

### Payload кнопки

```php
$ctx->getPayload(); // array|null — декодированный JSON payload

// Пример использования:
$payload = $ctx->getPayload();
if ($payload !== null && $payload['action'] === 'buy') {
    // ...
}
```

### Callback-кнопки (event)

```php
$ctx->getEventId();       // string|null — event_id (только для MessageEvent)
$ctx->getMessageEvent();  // array|null — весь объект события (только для MessageEvent)
```

### Клиент API

```php
$ctx->getApi(); // VKApiClient — для прямого вызова методов VK API
```

---

## Отправка сообщений

### reply() — основной метод

Все параметры — именованные. `peer_id` и `random_id` подставляются автоматически.

```php
$ctx->reply(
    message: 'Текст сообщения',              // необязателен, если есть attachment/sticker
    attachment: 'photo-1234_5678',           // вложение (строка или массив)
    keyboard: $keyboard,                     // Keyboard или array
    stickerId: 9001,                         // ID стикера
    dontParseLinks: false,                   // не парсить ссылки
    disableMentions: false,                  // отключить упоминания
    contentSource: null,                     // источник контента
    randomId: null,                          // по умолчанию 0
    replyTo: 12345,                          // ID сообщения для ответа
    forwardMessages: '1,2,3',               // пересылаемые сообщения
    template: '{"type":"carousel",...}',    // JSON-шаблон (карусель и т.д.)
);
```

Примеры:

```php
// Только текст
$ctx->reply('Привет!');

// Текст + клавиатура
$ctx->reply('Выберите:', keyboard: Keyboard::make()->button('OK'));

// Только вложение
$ctx->reply(attachment: 'photo-1234_5678');

// Стикер
$ctx->reply(stickerId: 9001);
// или
$ctx->sendSticker(9001);

// Ответ на конкретное сообщение
$ctx->reply('Ответ на сообщение', replyTo: $ctx->getMessageId());
```

`reply()` возвращает `int` — ID отправленного сообщения.

---

## Редактирование и удаление

### editMessage()

```php
$ctx->editMessage(
    messageId: 12345,
    message: 'Новый текст',
    keyboard: $newKeyboard,    // необязательно
    attachment: 'photo...',    // необязательно
);
```

### deleteMessage()

```php
// Одно сообщение
$ctx->deleteMessage(12345);

// Несколько сообщений
$ctx->deleteMessage([12345, 12346, 12347]);
```

---

## Активность

### setActivity()

```php
$ctx->setActivity('typing');        // печатает
$ctx->setActivity('audiomessage'); // записывает голосовое
```

---

## Пересылка сообщений

### forwardMessage()

```php
// Переслать в текущий чат
$ctx->forwardMessage(messageId: 12345);

// Переслать в другой чат
$ctx->forwardMessage(messageId: 12345, toPeerId: 67890);

// Как ответ (не как пересланное)
$ctx->forwardMessage(messageId: 12345, asForward: false);
```

---

## Ответ на callback-кнопку

### sendMessageEventAnswer()

Используется для ответа на нажатие callback-кнопки (event button).

```php
Vk::on(EventType::MessageEvent, function (MessageContext $ctx): void {
    $eventId = $ctx->getEventId();
    $userId  = $ctx->getFromId();

    // Показать всплывающее сообщение
    $ctx->sendMessageEventAnswer($eventId, $userId, [
        'type' => 'show_snackbar',
        'text' => 'Действие выполнено!',
    ]);

    // Открыть ссылку
    $ctx->sendMessageEventAnswer($eventId, $userId, [
        'type' => 'open_link',
        'link' => 'https://vk.com',
    ]);
});
```

---

## Прямой вызов VK API

Для нестандартных методов используйте клиент напрямую:

```php
$ctx->getApi()->messages()->send($ctx->getToken(), [
    'peer_id'   => $ctx->getPeerId(),
    'message'   => 'Привет',
    'random_id' => 0,
]);

// Другие методы API
$ctx->getApi()->users()->get($token, ['user_ids' => $ctx->getFromId()]);
$ctx->getApi()->groups()->getById($token, ['group_id' => 123456]);
```
