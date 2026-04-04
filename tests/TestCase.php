<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Tiamenti\VkBotSdk\VkServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [VkServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('vk-bot.token', 'test_token_12345');
        $app['config']->set('vk-bot.group_id', 123456);
        $app['config']->set('vk-bot.secret', 'test_secret');
        $app['config']->set('vk-bot.confirmation_token', 'abc123confirm');
        $app['config']->set('vk-bot.api_version', '5.199');
        $app['config']->set('vk-bot.routes.path', 'routes/vk_test.php');
        $app['config']->set('vk-bot.conversations.driver', 'cache');
        $app['config']->set('vk-bot.conversations.ttl', 60);
        $app['config']->set('cache.default', 'array');
    }
}
