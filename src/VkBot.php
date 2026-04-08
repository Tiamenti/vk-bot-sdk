<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk;

use Illuminate\Container\Container;
use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Enums\EventType;
use Tiamenti\VkBotSdk\Handlers\Router;
use Tiamenti\VkBotSdk\Upload\Uploader;
use VK\Client\VKApiClient;

/**
 * Основной класс пакета. Биндится в контейнер под именем 'vk-bot'.
 *
 * Используйте фасад Vk:: или inject через конструктор.
 */
final class VkBot
{
    private bool $routesLoaded = false;

    public function __construct(
        private readonly Router $router,
        private readonly VKApiClient $api,
        private readonly string $token,
        private readonly string $secret,
        private readonly string $confirmationToken,
        private readonly string $routesPath,
        private readonly Container $container,
    ) {}

    // -------------------------------------------------------------------------
    // Регистрация обработчиков (делегируем в Router)
    // -------------------------------------------------------------------------

    /**
     * Зарегистрировать обработчик для типа события.
     *
     * @example
     * Vk::on(EventType::GroupJoin, function (MessageContext $ctx) {
     *     $ctx->reply('Добро пожаловать!');
     * });
     */
    public function on(EventType $event, callable $handler): void
    {
        $this->router->on($event, $handler);
    }

    /**
     * Зарегистрировать обработчик по тексту сообщения.
     * Поддерживает строки, массивы строк и регулярные выражения.
     *
     * @param  string|array<int,string>  $pattern
     *
     * @example
     * Vk::hears('привет', fn($ctx) => $ctx->reply('Привет!'));
     * Vk::hears(['да', 'нет'], fn($ctx) => $ctx->reply('Понял!'));
     * Vk::hears('/^привет/i', fn($ctx) => $ctx->reply('Привет!'));
     */
    public function hears(string|array $pattern, callable|string|array $handler): void
    {
        $this->router->hears($pattern, $handler);
    }

    /**
     * Зарегистрировать обработчик команды.
     * Работает и с /, и без него.
     *
     * @example
     * Vk::command('start', fn($ctx) => $ctx->reply('Привет!'));
     * Vk::command('/help', fn($ctx) => $ctx->reply('Помощь'));
     */
    public function command(string $command, callable|string|array $handler): void
    {
        $this->router->command($command, $handler);
    }

    /**
     * Зарегистрировать обработчик по payload кнопки.
     *
     * @param  string|array<mixed>  $payload
     *
     * @example
     * Vk::onPayload(['action' => 'buy'], fn($ctx) => $ctx->reply('Куплено!'));
     */
    public function onPayload(string|array $payload, callable|string|array $handler): void
    {
        $this->router->onPayload($payload, $handler);
    }

    /**
     * Зарегистрировать fallback-обработчик.
     *
     * @example
     * Vk::fallback(fn($ctx) => $ctx->reply('Не понимаю вас'));
     */
    public function fallback(callable|string|array $handler): void
    {
        $this->router->fallback($handler);
    }

    /**
     * Добавить глобальный middleware или middleware группы.
     *
     * @example
     * Vk::middleware(LoggingMiddleware::class);
     */
    public function middleware(callable|string $middleware): void
    {
        $this->router->middleware($middleware);
    }

    /**
     * Зарегистрировать группу обработчиков с общими middleware.
     *
     * @example
     * Vk::group(function () {
     *     Vk::middleware(AdminMiddleware::class);
     *     Vk::hears('admin', [AdminHandler::class, 'index']);
     * });
     */
    public function group(callable $callback): void
    {
        $this->router->group($callback);
    }

    // -------------------------------------------------------------------------
    // Обработка входящего события
    // -------------------------------------------------------------------------

    /**
     * Обработать входящий payload от VK (Callback API или Long Poll).
     *
     * @param  array<string, mixed>  $payload
     * @return string Строка для ответа VK ('ok' или confirmation_token)
     */
    public function handle(array $payload): string
    {
        $this->ensureRoutesLoaded();

        $typeStr = (string) ($payload['type'] ?? '');

        // Подтверждение Callback API
        if ($typeStr === EventType::Confirmation->value) {
            return $this->confirmationToken;
        }

        $eventType = EventType::tryFrom($typeStr);

        if ($eventType === null) {
            return 'ok';
        }

        $eventObject = $payload['object'] ?? [];

        $ctx = new MessageContext(
            api: $this->api,
            token: $this->token,
            event: $eventType,
            eventObject: $eventObject,
        );

        $this->router->dispatch($ctx);

        return 'ok';
    }

    /**
     * Проверить секретный ключ входящего запроса.
     */
    public function validateSecret(string $incomingSecret): bool
    {
        if ($this->secret === '') {
            return true;
        }

        return hash_equals($this->secret, $incomingSecret);
    }

    /**
     * Получить клиент VK API (для прямого использования).
     */
    public function getApi(): VKApiClient
    {
        return $this->api;
    }

    /**
     * Получить токен сообщества.
     */
    public function getToken(): string
    {
        return $this->token;
    }

    // -------------------------------------------------------------------------
    // Загрузка маршрутов
    // -------------------------------------------------------------------------

    /**
     * Загрузить файл обработчиков (routes/vk.php или кастомный путь).
     */
    private function ensureRoutesLoaded(): void
    {
        if ($this->routesLoaded) {
            return;
        }

        $path = base_path($this->routesPath);

        if (file_exists($path)) {
            require $path;
        }

        $this->routesLoaded = true;
    }

    /**
     * Получить загрузчик вложений.
     *
     * @example
     * $attachment = Vk::upload()->photo()->toMessages($peerId)->fromPath('/tmp/photo.jpg');
     * $ctx->reply(attachment: $attachment);
     */
    public function upload(): Uploader
    {
        return new Uploader($this);
    }
}
