<?php

namespace MoMagdy\TelegramSupport\Services;

use MoMagdy\TelegramSupport\Models\TelegramChat;
use MoMagdy\TelegramSupport\Models\TelegramMessage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected Client $http;
    protected string $baseUrl;
    protected string $token;
    protected ?string $parseMode;

    public function __construct()
    {
        $this->token     = config('telegram.bot_token');
        $this->parseMode = config('telegram.parse_mode');
        $this->baseUrl   = rtrim(config('telegram.api_url'), '/') . '/bot' . $this->token;

        $this->http = new Client([
            'timeout' => config('telegram.timeout', 10),
        ]);
    }

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Send a plain-text or HTML/Markdown message.
     *
     * @param  string      $text    Message body
     * @param  string $chatId  Recipient chat ID (falls back to config default)
     * @param  array       $extra   Any extra Telegram parameters
     * @return array                Decoded Telegram API response
     */
    public function sendMessage(string $text, string $chatId, array $extra = []): array
    {
        return $this->call('sendMessage', array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => $this->parseMode,
        ], $extra));
    }

    /**
     * Send a message with an inline keyboard.
     *
     * @param  array  $buttons  [ ['text'=>'Label','callback_data'=>'value'], … ]
     */
    public function sendMessageWithButtons(string $text, array $buttons, string $chatId): array
    {
        return $this->sendMessage($text, $chatId, [
            'reply_markup' => json_encode(['inline_keyboard' => [$buttons]]),
        ]);
    }

    /**
     * Send a photo by URL or file_id.
     */
    public function sendPhoto(string $photo, string $caption = '', string $chatId): array
    {
        return $this->call('sendPhoto', [
            'chat_id'    => $chatId,
            'photo'      => $photo,
            'caption'    => $caption,
            'parse_mode' => $this->parseMode,
        ]);
    }

    /**
     * Get information about the bot itself.
     */
    public function getMe(): array
    {
        return $this->call('getMe', []);
    }

    /**
     * Return the bot's @username, auto-discovered via getMe() when not configured.
     * Result is cached for 24 hours.
     */
    public function getBotUsername(): string
    {
        $configured = config('telegram.bot_username', '');
        if ($configured !== '') {
            return $configured;
        }

        return Cache::remember('telegram.bot_username', 86400, function () {
            try {
                return $this->call('getMe', [])['result']['username'] ?? '';
            } catch (\Exception $e) {
                Log::warning('TelegramService: could not fetch bot username: ' . $e->getMessage());
                return '';
            }
        });
    }

    /**
     * Retrieve recent updates.
     */
    public function getUpdates(int $limit = 10): array
    {
        return $this->call('getUpdates', ['limit' => $limit]);
    }

    // ─── Chat / Support Inbox ─────────────────────────────────────────────────

    /**
     * Handle a /connect TOKEN command sent by a user in Telegram.
     */
    protected function handleConnectCommand(array $msg): void
    {
        $text   = trim($msg['text'] ?? '');
        $chatId = (string) ($msg['chat']['id'] ?? '');
        $from   = $msg['from'] ?? [];
        $name   = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: 'User';

        $parts = explode(' ', $text, 2);
        $token = strtoupper(trim($parts[1] ?? ''));

        if (! $token || ! $chatId) {
            return;
        }

        $row = DB::table('telegram_connect_tokens')
            ->where('token', $token)
            ->whereNull('connected_at')
            ->first();

        if (! $row) {
            try {
                $this->sendMessage('❌ Token not found or already used. Please generate a new connection code on the website.', $chatId);
            } catch (\Exception) { /* silent */ }
            return;
        }

        if (now()->isAfter($row->expires_at)) {
            try {
                $this->sendMessage('⏰ This code has expired. Please generate a new one on the website.', $chatId);
            } catch (\Exception) { /* silent */ }
            return;
        }

        DB::table('telegram_connect_tokens')
            ->where('id', $row->id)
            ->update(['chat_id' => $chatId, 'connected_at' => now(), 'updated_at' => now()]);

        $chat = $msg['chat'] ?? [];
        TelegramChat::updateOrCreate(
            ['chat_id' => $chatId],
            [
                'name'     => trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '')) ?: $name,
                'username' => $chat['username'] ?? null,
                'type'     => $chat['type'] ?? 'private',
            ]
        );

        Cache::forget('telegram.chats');

        try {
            $this->sendMessage('✅ Connected! Your Telegram account is now linked. The support team can message you directly here.', $chatId);
        } catch (\Exception) { /* silent */ }
    }

    /**
     * Pull updates from Telegram, persist new messages + chats, advance the offset.
     * Returns the number of new messages stored.
     */
    public function syncUpdates(): int
    {
        if (! Cache::add('telegram.sync_lock', 1, now()->addSeconds(2))) {
            return 0;
        }

        if (! $this->getSetting('webhook_deleted', false)) {
            try {
                $this->call('deleteWebhook', ['drop_pending_updates' => false]);
                $this->setSetting('webhook_deleted', '1');
            } catch (\Exception) { /* ignore */ }
        }

        $offset  = (int) $this->getSetting('last_update_id', 0);
        $payload = ['limit' => 100, 'timeout' => 0];
        if ($offset > 0) {
            $payload['offset'] = $offset + 1;
        }

        try {
            $response = $this->call('getUpdates', $payload);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), '409')) {
                $this->setSetting('webhook_deleted', '');
                return 0;
            }
            throw $e;
        }

        $updates     = $response['result'] ?? [];
        $newMessages = 0;

        foreach ($updates as $update) {
            $msg = $update['message'] ?? $update['edited_message'] ?? null;

            if ($msg) {
                $text = $msg['text'] ?? '';
                if (str_starts_with(trim($text), '/connect ')) {
                    $this->handleConnectCommand($msg);
                } else {
                    $this->storeIncomingMessage($msg);
                    $newMessages++;
                }
            }

            if ($update['update_id'] > $offset) {
                $offset = $update['update_id'];
            }
        }

        if ($offset > 0) {
            $this->setSetting('last_update_id', $offset);
        }

        return $newMessages;
    }

    /**
     * Store an incoming Telegram message into the local database.
     */
    public function storeIncomingMessage(array $msg): TelegramMessage
    {
        $chat     = $msg['chat'] ?? [];
        $from     = $msg['from'] ?? [];
        $chatId   = (string) ($chat['id'] ?? '');
        $text     = $msg['text'] ?? $msg['caption'] ?? '[media]';
        $name     = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: null;
        $username = $from['username'] ?? null;
        $sentAt   = isset($msg['date']) ? \Carbon\Carbon::createFromTimestamp($msg['date']) : now();

        $telegramChat = TelegramChat::updateOrCreate(
            ['chat_id' => $chatId],
            [
                'name'              => trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '')) ?: ($chat['title'] ?? $name),
                'username'          => $chat['username'] ?? null,
                'type'              => $chat['type'] ?? 'private',
                'last_message_text' => $text,
                'last_message_at'   => $sentAt,
            ]
        );

        $exists = TelegramMessage::where('chat_id', $chatId)
            ->where('telegram_message_id', $msg['message_id'] ?? null)
            ->exists();

        if (! $exists) {
            $telegramChat->increment('unread_count');
        }

        $message = TelegramMessage::firstOrCreate(
            ['chat_id' => $chatId, 'telegram_message_id' => $msg['message_id'] ?? null],
            [
                'direction'     => 'in',
                'from_name'     => $name,
                'from_username' => $username,
                'text'          => $text,
                'raw'           => $msg,
                'sent_at'       => $sentAt,
            ]
        );

        Cache::forget('telegram.chats');
        Cache::forget("telegram.messages.{$chatId}.0");

        return $message;
    }

    /**
     * Store an outgoing message in the local database.
     */
    public function storeOutgoingMessage(string $chatId, string $text, ?array $telegramResponse = null): TelegramMessage
    {
        $message = TelegramMessage::create([
            'chat_id'             => $chatId,
            'telegram_message_id' => $telegramResponse['result']['message_id'] ?? null,
            'direction'           => 'out',
            'from_name'           => 'Support',
            'from_username'       => null,
            'text'                => $text,
            'raw'                 => $telegramResponse,
            'sent_at'             => now(),
        ]);

        TelegramChat::where('chat_id', $chatId)->update([
            'last_message_text' => $text,
            'last_message_at'   => now(),
        ]);

        Cache::forget('telegram.chats');
        Cache::forget("telegram.messages.{$chatId}.0");

        return $message;
    }

    /**
     * Mark all messages in a chat as read (reset unread_count).
     */
    public function markChatRead(string $chatId): void
    {
        TelegramChat::where('chat_id', $chatId)->update(['unread_count' => 0]);
        Cache::forget('telegram.chats');
    }

    // ─── Settings persistence ─────────────────────────────────────────────────

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return Cache::remember("telegram.setting.{$key}", 3600, function () use ($key, $default) {
            $row = DB::table('telegram_settings')->where('key', $key)->first();
            return $row ? $row->value : $default;
        });
    }

    public function setSetting(string $key, mixed $value): void
    {
        DB::table('telegram_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => (string) $value]
        );
        Cache::put("telegram.setting.{$key}", (string) $value, 3600);
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    protected function call(string $method, array $payload): array
    {
        $url = "{$this->baseUrl}/{$method}";

        try {
            $response = $this->http->post($url, ['json' => $payload]);
            $body     = json_decode((string) $response->getBody(), true);
            Log::info("Telegram [{$method}] OK", ['payload' => $payload, 'response' => $body]);
            return $body;
        } catch (GuzzleException $e) {
            Log::error("Telegram [{$method}] HTTP error: " . $e->getMessage(), ['payload' => $payload]);
            throw new \RuntimeException("Telegram HTTP error: " . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            Log::error("Telegram [{$method}] error: " . $e->getMessage());
            throw new \RuntimeException("Telegram error: " . $e->getMessage(), 0, $e);
        }
    }
}
