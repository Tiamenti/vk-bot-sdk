<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tiamenti\VkBotSdk\Facades\Vk;
use Tiamenti\VkBotSdk\Http\Controllers\CallbackController;

beforeEach(function (): void {
    Route::post('/vk/webhook', CallbackController::class);
});

describe('CallbackController', function (): void {

    it('возвращает confirmation_token при первом запросе', function (): void {
        $response = $this->postJson('/vk/webhook', [
            'type'   => 'confirmation',
            'secret' => 'test_secret',
            'group_id' => 123456,
        ]);

        $response->assertOk();
        $response->assertSeeText('abc123confirm');
    });

    it('возвращает ok при обработке события', function (): void {
        Vk::on(\Tiamenti\VkBotSdk\Enums\EventType::MessageNew, fn () => null);

        $response = $this->postJson('/vk/webhook', [
            'type'     => 'message_new',
            'secret'   => 'test_secret',
            'group_id' => 123456,
            'object'   => [
                'message' => [
                    'id'      => 1,
                    'peer_id' => 100,
                    'from_id' => 1,
                    'text'    => 'Привет',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertSeeText('ok');
    });

    it('возвращает 403 при неверном секрете', function (): void {
        $response = $this->postJson('/vk/webhook', [
            'type'   => 'message_new',
            'secret' => 'wrong_secret',
        ]);

        $response->assertForbidden();
    });

    it('возвращает 400 при пустом теле запроса', function (): void {
        $response = $this->post('/vk/webhook', [], ['Content-Type' => 'application/json']);

        $response->assertStatus(400);
    });

    it('возвращает ok даже если обработчик выбросил исключение', function (): void {
        Vk::on(\Tiamenti\VkBotSdk\Enums\EventType::MessageNew, function (): void {
            throw new \RuntimeException('Тестовая ошибка');
        });

        $response = $this->postJson('/vk/webhook', [
            'type'     => 'message_new',
            'secret'   => 'test_secret',
            'group_id' => 123456,
            'object'   => [
                'message' => [
                    'id'      => 2,
                    'peer_id' => 100,
                    'from_id' => 1,
                    'text'    => 'тест',
                ],
            ],
        ]);

        // VK ожидает 'ok', иначе будет повторять запросы
        $response->assertOk();
        $response->assertSeeText('ok');
    });

    it('вызывает нужный обработчик по тексту', function (): void {
        $called = false;

        Vk::hears('привет', function () use (&$called): void {
            $called = true;
        });

        $this->postJson('/vk/webhook', [
            'type'     => 'message_new',
            'secret'   => 'test_secret',
            'group_id' => 123456,
            'object'   => [
                'message' => [
                    'id'      => 3,
                    'peer_id' => 100,
                    'from_id' => 1,
                    'text'    => 'привет',
                ],
            ],
        ]);

        expect($called)->toBeTrue();
    });

    it('вызывает fallback если нет совпадений', function (): void {
        $fallbackCalled = false;

        Vk::fallback(function () use (&$fallbackCalled): void {
            $fallbackCalled = true;
        });

        $this->postJson('/vk/webhook', [
            'type'     => 'message_new',
            'secret'   => 'test_secret',
            'group_id' => 123456,
            'object'   => [
                'message' => [
                    'id'      => 4,
                    'peer_id' => 100,
                    'from_id' => 1,
                    'text'    => 'что-то неизвестное',
                ],
            ],
        ]);

        expect($fallbackCalled)->toBeTrue();
    });

});
