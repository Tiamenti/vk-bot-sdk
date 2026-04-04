# Long Poll режим

Long Poll — альтернатива Callback API. Бот сам опрашивает серверы VK, не требуя публичного URL.

---

## Когда использовать Long Poll

| Long Poll | Callback API |
|---|---|
| Локальная разработка | Продакшн с HTTPS |
| Нет публичного домена | Есть настроенный вебхук |
| Нагрузка небольшая | Высокая нагрузка / масштабирование |

---

## Настройка

В `.env`:

```dotenv
VK_BOT_MODE=longpoll
VK_BOT_LONGPOLL_WAIT=25
VK_BOT_LONGPOLL_RETRY_DELAY=5
```

---

## Запуск

```bash
php artisan vk:bot:listen
```

Команда запускает бесконечный цикл. Для остановки — `Ctrl+C`.

```
VK Long Poll запущен. Ожидание событий...
Событие: message_new
Событие: message_new
```

---

## Работа в фоне

### С помощью Supervisor

Создайте конфиг `/etc/supervisor/conf.d/vk-bot.conf`:

```ini
[program:vk-bot]
command=php /var/www/html/artisan vk:bot:listen
directory=/var/www/html
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/vk-bot.log
stopwaitsecs=10
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start vk-bot
```

### С помощью systemd

```ini
# /etc/systemd/system/vk-bot.service
[Unit]
Description=VK Bot Long Poll
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html
ExecStart=/usr/bin/php artisan vk:bot:listen
Restart=always
RestartSec=5s

[Install]
WantedBy=multi-user.target
```

```bash
systemctl enable vk-bot
systemctl start vk-bot
systemctl status vk-bot
```

---

## Обработчики

Те же самые обработчики из `routes/vk.php` работают и в Long Poll, и в Callback API — никаких изменений не требуется.

---

## Важные ограничения

> **⚠️ Важно**
> Long Poll **не рекомендуется** для продакшна с высокой нагрузкой. Одна команда обслуживает события последовательно. Для масштабирования используйте Callback API + Laravel Queue.

> **⚠️ Важно**
> Убедитесь, что в настройках сообщества ВКонтакте (Настройки → Работа с API → Long Poll API) включены нужные типы событий.
