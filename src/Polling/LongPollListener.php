<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Polling;

use Illuminate\Console\OutputStyle;
use Psr\Log\LoggerInterface;
use Tiamenti\VkBotSdk\VkBot;
use VK\Client\VKApiClient;

/**
 * Long Poll слушатель.
 *
 * Запускает бесконечный цикл, получает события VK через Long Poll API
 * и передаёт их в VkBot::handle().
 *
 * Используется командой `php artisan vk:bot:listen`.
 */
final class LongPollListener
{
    public function __construct(
        private readonly VkBot $bot,
        private readonly VKApiClient $api,
        private readonly string $token,
        private readonly int $groupId,
        private readonly int $wait,
        private readonly int $retryDelay,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Запустить Long Poll цикл.
     *
     * @param OutputStyle|null $output Вывод Artisan-команды
     */
    public function listen(?OutputStyle $output = null): void
    {
        $output?->writeln('<info>VK Long Poll запущен. Ожидание событий...</info>');

        [$server, $key, $ts] = $this->initLongPoll();

        while (true) {
            try {
                $response = $this->poll($server, $key, $ts);

                if (isset($response['failed'])) {
                    [$server, $key, $ts] = $this->handleFailed((int) $response['failed'], $ts);
                    continue;
                }

                $ts = (string) ($response['ts'] ?? $ts);

                foreach ($response['updates'] ?? [] as $update) {
                    $this->processUpdate($update, $output);
                }
            } catch (\Throwable $e) {
                $this->logger->error('VK Long Poll error: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
                $output?->writeln("<error>Ошибка: {$e->getMessage()}. Повтор через {$this->retryDelay}с...</error>");
                sleep($this->retryDelay);

                // Переинициализируем Long Poll
                try {
                    [$server, $key, $ts] = $this->initLongPoll();
                } catch (\Throwable $initError) {
                    $this->logger->error('VK Long Poll init error: ' . $initError->getMessage());
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Приватные методы
    // -------------------------------------------------------------------------

    /**
     * Инициализировать Long Poll сервер.
     *
     * @return array{string, string, string} [server, key, ts]
     */
    private function initLongPoll(): array
    {
        $response = $this->api->groups()->getLongPollServer($this->token, [
            'group_id' => $this->groupId,
        ]);

        return [
            (string) $response['server'],
            (string) $response['key'],
            (string) $response['ts'],
        ];
    }

    /**
     * Выполнить один запрос к Long Poll серверу.
     *
     * @return array<string, mixed>
     */
    private function poll(string $server, string $key, string $ts): array
    {
        $url = "{$server}?act=a_check&key={$key}&ts={$ts}&wait={$this->wait}";

        $response = file_get_contents($url);

        if ($response === false) {
            throw new \RuntimeException("Long Poll request failed: {$url}");
        }

        $decoded = json_decode($response, associative: true);

        if (! is_array($decoded)) {
            throw new \RuntimeException("Invalid Long Poll response: {$response}");
        }

        return $decoded;
    }

    /**
     * Обработать ошибку Long Poll (failed).
     *
     * @return array{string, string, string} [server, key, ts]
     */
    private function handleFailed(int $failed, string $currentTs): array
    {
        return match ($failed) {
            // Устаревший ts — просто обновляем
            1 => $this->initLongPoll(),
            // Устаревший ключ — получаем новый сервер
            2, 3 => $this->initLongPoll(),
            default => $this->initLongPoll(),
        };
    }

    /**
     * Обработать одно событие Long Poll.
     *
     * @param array<string, mixed>  $update
     */
    private function processUpdate(array $update, ?OutputStyle $output): void
    {
        $type = (string) ($update['type'] ?? '');

        if ($type === '') {
            return;
        }

        $output?->writeln("<comment>Событие: {$type}</comment>");

        try {
            $this->bot->handle($update);
        } catch (\Throwable $e) {
            $this->logger->error("VK Long Poll handler error [{$type}]: " . $e->getMessage());
            $output?->writeln("<error>Ошибка обработчика [{$type}]: {$e->getMessage()}</error>");
        }
    }
}
