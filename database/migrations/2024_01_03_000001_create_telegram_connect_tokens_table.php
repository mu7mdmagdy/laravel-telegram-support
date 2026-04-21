<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_connect_tokens', function (Blueprint $table) {
            $table->id();

            // The short code shown to the user: they send "/connect ABC123" to the bot.
            $table->string('token', 10)->unique();

            // Optionally bound to an authenticated user when generated.
            // Polymorphic so it works with any model (User, Customer, etc.).
            $table->string('model_class')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            // Filled by syncUpdates() once the user sends the /connect command.
            $table->string('chat_id')->nullable();
            $table->timestamp('connected_at')->nullable();

            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['token']);
            $table->index(['user_id', 'model_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_connect_tokens');
    }
};
