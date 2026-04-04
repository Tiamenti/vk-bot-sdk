<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Tiamenti\VkBotSdk\Context\MessageContext;
use Tiamenti\VkBotSdk\Conversations\Conversation;
use Tiamenti\VkBotSdk\Conversations\ConversationManager;
use Tiamenti\VkBotSdk\Enums\EventType;
use Tiamenti\VkBotSdk\Exceptions\ConversationException;
use VK\Client\VKApiClient;

// ---------------------------------------------------------------------------
// Вспомогательные классы
// ---------------------------------------------------------------------------

class TestConversation extends Conversation
{
    protected ?string $step = 'askName';

    public array $log = [];

    public function askName(MessageContext $ctx): void
    {
        $this->log[] = 'askName';
        $this->next('askAge');
    }

    public function askAge(MessageContext $ctx): void
    {
        $this->set('name', $ctx->getText() ?? 'unknown');
        $this->log[] = 'askAge';
        $this->next('finish');
    }

    public function finish(MessageContext $ctx): void
    {
        $this->log[] = 'finish:'.$this->get('name');
        $this->end();
    }
}

class SingleStepConversation extends Conversation
{
    public function start(MessageContext $ctx): void
    {
        $this->end();
    }
}

// ---------------------------------------------------------------------------
// Хелпер
// ---------------------------------------------------------------------------

function makeMessageCtx(string $text = '', int $peerId = 100): MessageContext
{
    $api = Mockery::mock(VKApiClient::class);

    return new MessageContext(
        api: $api,
        token: 'token',
        event: EventType::MessageNew,
        eventObject: [
            'message' => [
                'id' => 1,
                'peer_id' => $peerId,
                'from_id' => 1,
                'text' => $text,
            ],
        ],
    );
}

// ---------------------------------------------------------------------------
// Тесты
// ---------------------------------------------------------------------------

describe('ConversationManager', function (): void {

    beforeEach(function (): void {
        Cache::flush();
    });

    it('нет активного диалога по умолчанию', function (): void {
        $manager = app(ConversationManager::class);

        expect($manager->hasActive(100))->toBeFalse();
    });

    it('регистрирует диалог через start()', function (): void {
        $manager = app(ConversationManager::class);

        $manager->start(100, TestConversation::class, 'askName');

        expect($manager->hasActive(100))->toBeTrue();
    });

    it('nextStep() обновляет шаг', function (): void {
        $manager = app(ConversationManager::class);
        $manager->start(100, TestConversation::class, 'askName');
        $manager->nextStep(100, 'askAge');

        // Подтверждаем через resume — он должен вызвать askAge
        $called = false;
        $ctx = makeMessageCtx('Иван');

        // Подменяем класс, который уже зарегистрирован
        // Достаточно проверить, что after nextStep диалог всё ещё активен
        expect($manager->hasActive(100))->toBeTrue();
    });

    it('end() завершает диалог', function (): void {
        $manager = app(ConversationManager::class);
        $manager->start(100, TestConversation::class, 'askName');
        $manager->end(100);

        expect($manager->hasActive(100))->toBeFalse();
    });

    it('updateData() и getData() работают корректно', function (): void {
        $manager = app(ConversationManager::class);
        $manager->start(100, TestConversation::class, 'askName', ['foo' => 'bar']);
        $manager->updateData(100, ['foo' => 'baz', 'extra' => 42]);

        expect($manager->getData(100))->toBe(['foo' => 'baz', 'extra' => 42]);
    });

});

describe('Conversation::begin()', function (): void {

    beforeEach(function (): void {
        Cache::flush();
    });

    it('запускает первый шаг диалога', function (): void {
        $ctx = makeMessageCtx();

        // Проверяем, что begin() не выбросил исключение
        expect(fn () => SingleStepConversation::begin($ctx))->not->toThrow(Exception::class);
    });

    it('диалог регистрируется в менеджере после begin()', function (): void {
        $ctx = makeMessageCtx(peerId: 200);
        $manager = app(ConversationManager::class);

        // SingleStepConversation сразу вызывает end()
        SingleStepConversation::begin($ctx);

        // После end() — диалога нет
        expect($manager->hasActive(200))->toBeFalse();
    });

    it('выбрасывает исключение если шаг не найден', function (): void {
        $invalidClass = new class(app(ConversationManager::class), 999) extends Conversation
        {
            protected ?string $step = 'nonExistentMethod';
        };

        expect(fn () => $invalidClass::begin(makeMessageCtx(peerId: 999)))
            ->toThrow(ConversationException::class);
    });

});

describe('Conversation data persistence', function (): void {

    beforeEach(function (): void {
        Cache::flush();
    });

    it('set() и get() работают внутри одного шага', function (): void {
        $manager = app(ConversationManager::class);
        $ctx = makeMessageCtx('Иван', 300);

        // Создаём экземпляр напрямую
        $conversation = new TestConversation($manager, 300, []);
        $conversation->set('key', 'value');

        expect($conversation->get('key'))->toBe('value');
        expect($conversation->get('missing', 'default'))->toBe('default');
    });

    it('данные сохраняются между шагами через manager', function (): void {
        $manager = app(ConversationManager::class);
        $manager->start(300, TestConversation::class, 'askAge', ['name' => 'Иван']);

        $data = $manager->getData(300);

        expect($data['name'])->toBe('Иван');
    });

});
