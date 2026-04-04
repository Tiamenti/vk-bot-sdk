<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Artisan-команда: создать класс диалога (Conversation).
 *
 * @example php artisan vk:make:conversation RegistrationConversation
 */
final class MakeConversationCommand extends Command
{
    protected $signature = 'vk:make:conversation {name : Имя класса диалога}';

    protected $description = 'Создать класс Conversation в app/VK/Conversations/';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $path = app_path("VK/Conversations/{$name}.php");

        if ($this->files->exists($path)) {
            $this->error("Conversation [{$name}] уже существует.");

            return self::FAILURE;
        }

        $this->files->ensureDirectoryExists(app_path('VK/Conversations'));
        $this->files->put($path, $this->buildStub($name));

        $this->info("Conversation создан: app/VK/Conversations/{$name}.php");

        return self::SUCCESS;
    }

    private function buildStub(string $name): string
    {
        $namespace = $this->laravel->getNamespace().'VK\\Conversations';

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Tiamenti\VkBotSdk\Context\MessageContext;
        use Tiamenti\VkBotSdk\Conversations\Conversation;

        class {$name} extends Conversation
        {
            /**
             * Начальный шаг — вызывается при запуске диалога.
             */
            public function start(MessageContext \$ctx): void
            {
                \$ctx->reply('Привет! Начинаем диалог.');
                \$this->next('nextStep');
            }

            /**
             * Следующий шаг.
             */
            public function nextStep(MessageContext \$ctx): void
            {
                \$ctx->reply('Спасибо за ответ: ' . \$ctx->getText());
                \$this->end();
            }

            /**
             * Вызывается при завершении диалога через end().
             */
            public function closing(MessageContext \$ctx): void
            {
                // необязательно
            }
        }
        PHP;
    }
}
