<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Conversations;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Exceptions\ConversationException;

/**
 * Менеджер состояния диалогов (Conversations).
 *
 * Хранит текущий шаг и данные беседы в Laravel Cache или Database.
 */
final class ConversationManager
{
    private const CACHE_PREFIX = 'vk_conversation:';

    public function __construct(
        private readonly string $driver,
        private readonly CacheRepository $cache,
        private readonly ?ConnectionInterface $db,
        private readonly string $table,
        private readonly int $ttlMinutes,
    ) {}

    // -------------------------------------------------------------------------
    // Публичный API
    // -------------------------------------------------------------------------

    /**
     * Проверить наличие активного диалога для peer_id.
     */
    public function hasActive(int $peerId): bool
    {
        return $this->load($peerId) !== null;
    }

    /**
     * Запустить новый диалог.
     *
     * @param array<string, mixed> $args Дополнительные аргументы
     */
    public function start(int $peerId, string $conversationClass, string $step, array $args = []): void
    {
        $this->save($peerId, [
            'class' => $conversationClass,
            'step'  => $step,
            'data'  => $args,
        ]);
    }

    /**
     * Продолжить активный диалог.
     *
     * @throws ConversationException
     */
    public function resume(MessageContext $ctx): void
    {
        $state = $this->load($ctx->getPeerId());

        if ($state === null) {
            return;
        }

        $class = $state['class'];
        $step  = $state['step'];
        $data  = $state['data'] ?? [];

        /** @var Conversation $conversation */
        $conversation = new $class($this, $ctx->getPeerId(), $data);

        if (! method_exists($conversation, $step)) {
            throw ConversationException::stepNotFound($class, $step);
        }

        $conversation->{$step}($ctx);
    }

    /**
     * Перейти к следующему шагу.
     */
    public function nextStep(int $peerId, string $step): void
    {
        $state = $this->load($peerId);

        if ($state === null) {
            return;
        }

        $state['step'] = $step;
        $this->save($peerId, $state);
    }

    /**
     * Завершить диалог.
     */
    public function end(int $peerId): void
    {
        $this->delete($peerId);
    }

    /**
     * Обновить данные диалога.
     *
     * @param array<string, mixed> $data
     */
    public function updateData(int $peerId, array $data): void
    {
        $state = $this->load($peerId);

        if ($state === null) {
            return;
        }

        $state['data'] = $data;
        $this->save($peerId, $state);
    }

    /**
     * Получить данные диалога.
     *
     * @return array<string, mixed>
     */
    public function getData(int $peerId): array
    {
        $state = $this->load($peerId);

        return $state['data'] ?? [];
    }

    // -------------------------------------------------------------------------
    // Хранилище
    // -------------------------------------------------------------------------

    /**
     * Загрузить состояние из хранилища.
     *
     * @return array<string, mixed>|null
     */
    private function load(int $peerId): ?array
    {
        if ($this->driver === 'database') {
            return $this->loadFromDatabase($peerId);
        }

        $value = $this->cache->get($this->cacheKey($peerId));

        return is_array($value) ? $value : null;
    }

    /**
     * Сохранить состояние в хранилище.
     *
     * @param array<string, mixed> $state
     */
    private function save(int $peerId, array $state): void
    {
        if ($this->driver === 'database') {
            $this->saveToDatabase($peerId, $state);
            return;
        }

        $this->cache->put(
            $this->cacheKey($peerId),
            $state,
            now()->addMinutes($this->ttlMinutes),
        );
    }

    /**
     * Удалить состояние из хранилища.
     */
    private function delete(int $peerId): void
    {
        if ($this->driver === 'database') {
            $this->db?->table($this->table)->where('peer_id', $peerId)->delete();
            return;
        }

        $this->cache->forget($this->cacheKey($peerId));
    }

    private function cacheKey(int $peerId): string
    {
        return self::CACHE_PREFIX . $peerId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadFromDatabase(int $peerId): ?array
    {
        $row = $this->db?->table($this->table)->where('peer_id', $peerId)->first();

        if ($row === null) {
            return null;
        }

        $payload = is_object($row) ? $row->payload : ($row['payload'] ?? null);

        if ($payload === null) {
            return null;
        }

        $decoded = json_decode($payload, associative: true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function saveToDatabase(int $peerId, array $state): void
    {
        $payload = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $expiresAt = now()->addMinutes($this->ttlMinutes)->toDateTimeString();

        $this->db?->table($this->table)->updateOrInsert(
            ['peer_id' => $peerId],
            ['payload' => $payload, 'expires_at' => $expiresAt, 'updated_at' => now()],
        );
    }
}
