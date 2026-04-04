<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Artisan-команда: создать VK-обработчик.
 *
 * @example php artisan vk:make:handler OrderHandler
 */
final class MakeHandlerCommand extends Command
{
    protected $signature   = 'vk:make:handler {name : Имя класса обработчика}';
    protected $description = 'Создать класс VK-обработчика в app/VK/Handlers/';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $path = app_path("VK/Handlers/{$name}.php");

        if ($this->files->exists($path)) {
            $this->error("Обработчик [{$name}] уже существует.");
            return self::FAILURE;
        }

        $this->files->ensureDirectoryExists(app_path('VK/Handlers'));
        $this->files->put($path, $this->buildStub($name));

        $this->info("Обработчик создан: app/VK/Handlers/{$name}.php");

        return self::SUCCESS;
    }

    private function buildStub(string $name): string
    {
        $namespace = $this->laravel->getNamespace() . 'VK\\Handlers';

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Tiamenti\VkBotSdk\Context\MessageContext;

        final class {$name}
        {
            public function __invoke(MessageContext \$ctx): void
            {
                // TODO: реализовать логику обработчика
            }
        }
        PHP;
    }
}
