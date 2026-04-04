<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Artisan-команда: создать VK-middleware.
 *
 * @example php artisan vk:make:middleware AdminMiddleware
 */
final class MakeMiddlewareCommand extends Command
{
    protected $signature   = 'vk:make:middleware {name : Имя класса middleware}';
    protected $description = 'Создать класс VK-middleware в app/VK/Middleware/';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $path = app_path("VK/Middleware/{$name}.php");

        if ($this->files->exists($path)) {
            $this->error("Middleware [{$name}] уже существует.");
            return self::FAILURE;
        }

        $this->files->ensureDirectoryExists(app_path('VK/Middleware'));
        $this->files->put($path, $this->buildStub($name));

        $this->info("Middleware создан: app/VK/Middleware/{$name}.php");

        return self::SUCCESS;
    }

    private function buildStub(string $name): string
    {
        $namespace = $this->laravel->getNamespace() . 'VK\\Middleware';

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Tiamenti\VkBotSdk\Context\MessageContext;
        use Tiamenti\VkBotSdk\Middleware\VkMiddleware;

        final class {$name} implements VkMiddleware
        {
            public function handle(MessageContext \$ctx, callable \$next): void
            {
                // TODO: логика middleware

                \$next(\$ctx);
            }
        }
        PHP;
    }
}
