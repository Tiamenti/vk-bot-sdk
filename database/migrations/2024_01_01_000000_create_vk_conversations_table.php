<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция таблицы для хранения состояния диалогов (Conversations).
 * Публикуется командой: php artisan vendor:publish --tag=vk-bot-migrations
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vk_conversations', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('peer_id')->index()->comment('VK peer_id беседы');
            $table->longText('payload')->comment('JSON-состояние диалога');
            $table->timestamp('expires_at')->nullable()->comment('Время истечения');
            $table->timestamps();

            $table->unique('peer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vk_conversations');
    }
};
