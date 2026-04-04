<?php

declare(strict_types=1);

/**
 * Файл обработчиков VK-бота.
 *
 * Здесь регистрируются все хэндлеры: команды, текстовые паттерны,
 * события, беседы (Conversations) и payload-обработчики.
 *
 * Путь к этому файлу настраивается в config/vk-bot.php → routes.path.
 *
 * @see https://github.com/tiamenti/vk-bot-sdk/blob/main/docs/handlers.md
 */

use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Enums\ButtonColor;
use Tiamenti\VkBotSdk\Enums\EventType;
use Tiamenti\VkBotSdk\Facades\Vk;
use Tiamenti\VkBotSdk\Keyboard\Keyboard;

// ---------------------------------------------------------------------------
// Команды
// ---------------------------------------------------------------------------

Vk::command('start', function (MessageContext $ctx): void {
    $keyboard = Keyboard::make()
        ->button('📋 Помощь', ButtonColor::Primary, ['action' => 'help'])
        ->button('ℹ️ О боте', ButtonColor::Secondary, ['action' => 'about'])
        ->row()
        ->button('📞 Связаться', ButtonColor::Positive, ['action' => 'contact'])
        ->oneTime();

    $ctx->reply(
        message: 'Добро пожаловать! Выберите действие:',
        keyboard: $keyboard,
    );
});

Vk::command('help', function (MessageContext $ctx): void {
    $ctx->reply(
        message: "📖 Доступные команды:\n"
            ."/start — главное меню\n"
            ."/help — помощь\n"
            .'/about — информация о боте',
    );
});

// ---------------------------------------------------------------------------
// Текстовые паттерны
// ---------------------------------------------------------------------------

Vk::hears(['привет', 'hi', 'hello'], function (MessageContext $ctx): void {
    $ctx->reply('Привет! Напишите /start для начала.');
});

// Регулярное выражение
Vk::hears('/^спасибо/ui', function (MessageContext $ctx): void {
    $ctx->reply('Пожалуйста! 😊');
});

// ---------------------------------------------------------------------------
// Payload кнопок
// ---------------------------------------------------------------------------

Vk::onPayload(['action' => 'help'], function (MessageContext $ctx): void {
    $ctx->reply('Вы нажали кнопку помощи!');
});

Vk::onPayload(['action' => 'about'], function (MessageContext $ctx): void {
    $ctx->reply('Это демо-бот на tiamenti/vk-bot-sdk.');
});

// ---------------------------------------------------------------------------
// События сообщества
// ---------------------------------------------------------------------------

Vk::on(EventType::GroupJoin, function (MessageContext $ctx): void {
    $ctx->reply('Спасибо, что вступили в наше сообщество! 🎉');
});

Vk::on(EventType::GroupLeave, function (MessageContext $ctx): void {
    // Нельзя ответить пользователю — он вышел
});

// ---------------------------------------------------------------------------
// Группы с middleware
// ---------------------------------------------------------------------------

// Vk::group(function (): void {
//     Vk::middleware(\App\VK\Middleware\AdminMiddleware::class);
//     Vk::hears('admin', [\App\VK\Handlers\AdminHandler::class, 'index']);
// });

// ---------------------------------------------------------------------------
// Беседы (Conversations)
// ---------------------------------------------------------------------------

// Vk::hears('регистрация', \App\VK\Conversations\RegistrationConversation::class);

// ---------------------------------------------------------------------------
// Fallback
// ---------------------------------------------------------------------------

Vk::fallback(function (MessageContext $ctx): void {
    $ctx->reply('Не понимаю вас. Напишите /help для списка команд.');
});
