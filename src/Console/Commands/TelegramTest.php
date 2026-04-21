<?php

namespace MoMagdy\TelegramSupport\Console\Commands;

use MoMagdy\TelegramSupport\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramTest extends Command
{
    protected $signature   = 'telegram:test {--chat_id= : Override chat ID}';
    protected $description = 'Send a test message via Telegram to verify the integration';

    public function handle(TelegramService $telegram): int
    {
        $chatId = $this->option('chat_id') ?: $this->ask('Please enter chat ID');

        if (! $chatId) {
            $this->error('No chat_id provided and TELEGRAM_DEFAULT_CHAT_ID is not set in .env');
            return Command::FAILURE;
        }

        $this->info("Sending test message to chat_id: {$chatId} …");

        try {
            $result = $telegram->sendMessage('🤖 Laravel Telegram Support test message — it works!', $chatId);

            if ($result['ok'] ?? false) {
                $this->info('✅ Message sent successfully!');
                return Command::SUCCESS;
            }

            $this->error('Telegram returned ok=false: ' . ($result['description'] ?? 'Unknown error'));
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
