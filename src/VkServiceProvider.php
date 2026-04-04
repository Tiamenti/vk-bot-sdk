<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Tiamenti\VkBotSdk\Console\Commands\BotListenCommand;
use Tiamenti\VkBotSdk\Console\Commands\MakeConversationCommand;
use Tiamenti\VkBotSdk\Console\Commands\MakeHandlerCommand;
use Tiamenti\VkBotSdk\Console\Commands\MakeMiddlewareCommand;
use Tiamenti\VkBotSdk\Conversations\ConversationManager;
use Tiamenti\VkBotSdk\Handlers\HandlerCollection;
use Tiamenti\VkBotSdk\Handlers\Router;
use Tiamenti\VkBotSdk\Polling\LongPollListener;
use VK\Client\VKApiClient;

/**
 * Service Provider пакета tiamenti/vk-bot-sdk.
 */
final class VkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/vk-bot.php',
            'vk-bot',
        );

        $this->registerVkApiClient();
        $this->registerConversationManager();
        $this->registerHandlerCollection();
        $this->registerRouter();
        $this->registerVkBot();
        $this->registerLongPollListener();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishConfig();
            $this->registerCommands();
            $this->publishMigrations();
        }

        if (config('vk-bot.facade', true)) {
            $this->app->alias('vk-bot', \Tiamenti\VkBotSdk\Facades\Vk::class);
        }
    }

    // -------------------------------------------------------------------------
    // Регистрация биндингов
    // -------------------------------------------------------------------------

    private function registerVkApiClient(): void
    {
        $this->app->singleton(VKApiClient::class, function (Application $app): VKApiClient {
            return new VKApiClient(config('vk-bot.api_version', '5.199'));
        });
    }

    private function registerConversationManager(): void
    {
        $this->app->singleton(ConversationManager::class, function (Application $app): ConversationManager {
            $driver = config('vk-bot.conversations.driver', 'cache');
            $ttl    = (int) config('vk-bot.conversations.ttl', 60);
            $table  = (string) config('vk-bot.conversations.table', 'vk_conversations');

            $db = null;
            if ($driver === 'database') {
                $db = $app->make('db.connection');
            }

            return new ConversationManager(
                driver: $driver,
                cache: $app->make(CacheRepository::class),
                db: $db,
                table: $table,
                ttlMinutes: $ttl,
            );
        });
    }

    private function registerHandlerCollection(): void
    {
        $this->app->singleton(HandlerCollection::class, fn (): HandlerCollection => new HandlerCollection());
    }

    private function registerRouter(): void
    {
        $this->app->singleton(Router::class, function (Application $app): Router {
            return new Router(
                collection: $app->make(HandlerCollection::class),
                container: $app,
            );
        });
    }

    private function registerVkBot(): void
    {
        $this->app->singleton(VkBot::class, function (Application $app): VkBot {
            return new VkBot(
                router: $app->make(Router::class),
                api: $app->make(VKApiClient::class),
                token: (string) config('vk-bot.token', ''),
                secret: (string) config('vk-bot.secret', ''),
                confirmationToken: (string) config('vk-bot.confirmation_token', ''),
                routesPath: (string) config('vk-bot.routes.path', 'routes/vk.php'),
                container: $app,
            );
        });

        $this->app->alias(VkBot::class, 'vk-bot');
    }

    private function registerLongPollListener(): void
    {
        $this->app->singleton(LongPollListener::class, function (Application $app): LongPollListener {
            return new LongPollListener(
                bot: $app->make(VkBot::class),
                api: $app->make(VKApiClient::class),
                token: (string) config('vk-bot.token', ''),
                groupId: (int) config('vk-bot.group_id', 0),
                wait: (int) config('vk-bot.longpoll.wait', 25),
                retryDelay: (int) config('vk-bot.longpoll.retry_delay', 5),
                logger: $app->make('log'),
            );
        });
    }

    // -------------------------------------------------------------------------
    // Публикация ресурсов
    // -------------------------------------------------------------------------

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/vk-bot.php' => config_path('vk-bot.php'),
        ], 'vk-bot-config');
    }

    private function publishMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'vk-bot-migrations');
    }

    private function registerCommands(): void
    {
        $this->commands([
            MakeHandlerCommand::class,
            MakeMiddlewareCommand::class,
            MakeConversationCommand::class,
            BotListenCommand::class,
        ]);
    }
}
