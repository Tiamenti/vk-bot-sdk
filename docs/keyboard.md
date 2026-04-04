# Клавиатура

Fluent-builder для создания клавиатур VK.

---

## Быстрый старт

```php
use Tiamenti\VkBotSdk\Enums\ButtonColor;
use Tiamenti\VkBotSdk\Keyboard\Keyboard;

$keyboard = Keyboard::make()
    ->button('Кнопка 1', ButtonColor::Primary, ['action' => 'btn1'])
    ->button('Кнопка 2', ButtonColor::Secondary)
    ->row()
    ->button('Назад', ButtonColor::Negative, ['action' => 'back'])
    ->oneTime();

$ctx->reply('Выберите действие:', keyboard: $keyboard);
```

---

## Типы кнопок

### Текстовая кнопка

```php
Keyboard::make()
    ->button('Нажми меня', ButtonColor::Primary)
    ->button('С payload', ButtonColor::Secondary, ['key' => 'value']);
```

| Цвет | Константа | Описание |
|---|---|---|
| Синий | `ButtonColor::Primary` | Основное действие |
| Белый | `ButtonColor::Secondary` | Нейтральное действие |
| Красный | `ButtonColor::Negative` | Деструктивное действие |
| Зелёный | `ButtonColor::Positive` | Подтверждение |

### Кнопка-ссылка

```php
Keyboard::make()
    ->openLink('Перейти на сайт', 'https://tiamenti.ru')
    ->openLink('С якорем', 'https://vk.com', '#section');
```

### Callback-кнопка (event)

Не отправляет сообщение, а триггерит событие `message_event`. Используйте совместно с `Vk::on(EventType::MessageEvent, ...)`.

```php
Keyboard::make()
    ->callback('Нажми', ['event' => 'button_clicked'], ButtonColor::Primary);
```

### Кнопка VK Pay

```php
Keyboard::make()
    ->vkPay(['action' => 'pay', 'amount' => 1000, 'currency' => 'RUB']);
```

### Кнопка мини-приложения

```php
Keyboard::make()
    ->openApp(
        label:   'Открыть приложение',
        appId:   12345,
        ownerId: -67890,
        hash:    'start',
    );
```

### Кнопка геолокации

```php
Keyboard::make()->location();
```

> **⚠️ Важно**
> Кнопка геолокации должна быть **единственной** в строке.

---

## Модификаторы клавиатуры

### Новая строка

```php
Keyboard::make()
    ->button('Строка 1, кнопка 1')
    ->button('Строка 1, кнопка 2')
    ->row()
    ->button('Строка 2, кнопка 1');
```

### Inline-клавиатура

Отображается под сообщением, а не под полем ввода.

```php
Keyboard::make()
    ->button('Inline кнопка')
    ->inline();
```

> **⚠️ Важно**
> `oneTime()` не работает с `inline()` — они взаимоисключающие по логике VK.

### Скрытие после нажатия

```php
Keyboard::make()
    ->button('Нажать и скрыть')
    ->oneTime();
```

### Скрытие клавиатуры

```php
$ctx->reply('Клавиатура убрана', keyboard: Keyboard::remove());
```

---

## Сериализация

```php
$keyboard = Keyboard::make()->button('OK');

// В массив (для передачи в messages.send напрямую)
$array = $keyboard->toArray();

// В JSON-строку
$json = $keyboard->toJson();
```

---

## Пример: главное меню

```php
use Tiamenti\VkBotSdk\Enums\ButtonColor;
use Tiamenti\VkBotSdk\Keyboard\Keyboard;

$menu = Keyboard::make()
    ->button('📋 Мои заказы',  ButtonColor::Primary,   ['action' => 'orders'])
    ->button('⚙️ Настройки',  ButtonColor::Secondary, ['action' => 'settings'])
    ->row()
    ->button('📞 Поддержка',  ButtonColor::Positive,  ['action' => 'support'])
    ->button('❌ Отмена',     ButtonColor::Negative,  ['action' => 'cancel'])
    ->oneTime();

Vk::command('start', function (MessageContext $ctx) use ($menu): void {
    $ctx->reply('Добро пожаловать!', keyboard: $menu);
});
```
