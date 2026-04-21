<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_chats', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id')->unique();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('type')->default('private'); // private|group|supergroup|channel
            $table->text('last_message_text')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
        });

        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id');
            $table->bigInteger('telegram_message_id')->nullable();
            $table->enum('direction', ['in', 'out'])->default('in');
            $table->string('from_name')->nullable();
            $table->string('from_username')->nullable();
            $table->text('text')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['chat_id', 'sent_at']);
        });

        Schema::create('telegram_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_settings');
        Schema::dropIfExists('telegram_messages');
        Schema::dropIfExists('telegram_chats');
    }
};
