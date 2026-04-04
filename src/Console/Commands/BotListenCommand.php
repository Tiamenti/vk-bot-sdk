<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Console\Commands;

use Illuminate\Console\Command;
use Tiamenti\VkBotSdk\Polling\LongPollListener;

/**
 * Artisan-команда: запустить Long Poll бота.
 *
 * @example php artisan vk:bot:listen
 */
final class BotListenCommand extends Command
{
    protected $signature = 'vk:bot:listen {--once : Обработать одно событие и выйти (для тестов)}';

    protected $description = 'Запустить VK-бот в режиме Long Poll';

    public function handle(LongPollListener $listener): int
    {
        if (config('vk-bot.mode') !== 'longpoll') {
            $this->warn('Бот настроен в режиме callback. Для Long Poll установите VK_BOT_MODE=longpoll в .env');
            $this->warn('Продолжаем всё равно...');
        }

        $this->info('Запуск VK Long Poll...');
        $this->info('Для остановки нажмите Ctrl+C');

        $listener->listen($this->output);

        return self::SUCCESS;
    }
}
