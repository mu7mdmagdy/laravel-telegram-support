<?php

namespace MoMagdy\TelegramSupport\Traits;

use MoMagdy\TelegramSupport\Services\TelegramService;

/**
 * Add to any Eloquent model (typically User) to send Telegram messages.
 *
 * Usage:
 *   class User extends Authenticatable {
 *       use HasTelegramNotifications;
 *   }
 *
 *   $user->sendTelegramMessage('Hello!');
 */
trait HasTelegramNotifications
{
    /**
     * Send a Telegram message to this model's linked chat.
     *
     * @param  string $text     Message body
     * @param  array  $extra    Extra Telegram parameters
     * @throws \RuntimeException if no chat_id is stored
     */
    public function sendTelegramMessage(string $text, array $extra = []): array
    {
        $column = config('telegram.chat_id_column', 'telegram_chat_id');
        $chatId = $this->{$column} ?? null;

        if (! $chatId) {
            throw new \RuntimeException(
                "Cannot send Telegram message: {$column} is not set on " . static::class . " #{$this->getKey()}."
            );
        }

        return app(TelegramService::class)->sendMessage($text, (string) $chatId, $extra);
    }

    /**
     * Check whether this model has a linked Telegram chat.
     */
    public function hasTelegramLinked(): bool
    {
        $column = config('telegram.chat_id_column', 'telegram_chat_id');
        return ! empty($this->{$column});
    }
}
