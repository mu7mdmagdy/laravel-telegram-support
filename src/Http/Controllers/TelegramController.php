<?php

namespace MoMagdy\TelegramSupport\Http\Controllers;

use MoMagdy\TelegramSupport\Http\Requests\SendTelegramMessageRequest;
use MoMagdy\TelegramSupport\Models\TelegramChat;
use MoMagdy\TelegramSupport\Models\TelegramMessage;
use MoMagdy\TelegramSupport\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class TelegramController extends Controller
{
    public function __construct(protected TelegramService $telegram) {}

    // ─── API endpoints ────────────────────────────────────────────────────────

    /** POST /api/telegram/send */
    public function apiSend(SendTelegramMessageRequest $request): JsonResponse
    {
        try {
            $chatId  = $request->input('chat_id') ?: null;
            $type    = $request->input('type', 'text');
            $message = $request->input('message');

            $result = match ($type) {
                'photo' => $this->telegram->sendPhoto(
                    $request->input('photo'),
                    $message,
                    $chatId
                ),
                'buttons' => $this->telegram->sendMessageWithButtons(
                    $message,
                    [
                        ['text' => '✅ Yes', 'callback_data' => 'yes'],
                        ['text' => '❌ No',  'callback_data' => 'no'],
                    ],
                    $chatId
                ),
                default => $this->telegram->sendMessage($message, $chatId),
            };

            return response()->json([
                'success'  => $result['ok'] ?? false,
                'telegram' => $result,
            ], ($result['ok'] ?? false) ? 200 : 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /api/telegram/me */
    public function apiGetMe(): JsonResponse
    {
        try {
            return response()->json($this->telegram->getMe());
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /api/telegram/updates */
    public function apiGetUpdates(): JsonResponse
    {
        try {
            return response()->json($this->telegram->getUpdates(20));
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─── Support Inbox API ────────────────────────────────────────────────────

    /** POST /api/telegram/sync */
    public function apiSync(): JsonResponse
    {
        try {
            $count = $this->telegram->syncUpdates();
            return response()->json(['success' => true, 'new_messages' => $count]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /api/telegram/chats */
    public function apiChats(): JsonResponse
    {
        $chats = Cache::remember('telegram.chats', 5, function () {
            return TelegramChat::orderByDesc('last_message_at')
                ->get()
                ->map(fn($c) => [
                    'chat_id'           => $c->chat_id,
                    'display_name'      => $c->display_name,
                    'avatar_letter'     => $c->avatar_letter,
                    'last_message_text' => $c->last_message_text,
                    'last_message_at'   => $c->last_message_at?->diffForHumans(),
                    'unread_count'      => $c->unread_count,
                ]);
        });

        return response()->json(['success' => true, 'chats' => $chats]);
    }

    /** GET /api/telegram/chats/{chatId}/messages */
    public function apiMessages(string $chatId): JsonResponse
    {
        $after    = (int) request()->query('after', 0);
        $cacheKey = "telegram.messages.{$chatId}.{$after}";

        $messages = Cache::remember($cacheKey, 5, function () use ($chatId, $after) {
            return TelegramMessage::where('chat_id', $chatId)
                ->when($after, fn($q) => $q->where('id', '>', $after))
                ->orderBy('sent_at')
                ->orderBy('id')
                ->get()
                ->map(fn($m) => [
                    'id'        => $m->id,
                    'direction' => $m->direction,
                    'from_name' => $m->from_name,
                    'text'      => $m->text,
                    'sent_at'   => $m->sent_at?->format('H:i'),
                    'sent_date' => $m->sent_at?->format('Y-m-d'),
                ]);
        });

        return response()->json(['success' => true, 'messages' => $messages]);
    }

    /** POST /api/telegram/chats/{chatId}/send */
    public function apiChatSend(Request $request, string $chatId): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:4096']);

        $chat = TelegramChat::where('chat_id', $chatId)->first();

        // Widget chats: store to DB only — no Telegram API call
        if ($chat && $chat->source === 'widget') {
            $message = $this->telegram->storeOutgoingMessage($chatId, $request->input('message'));
            return response()->json(['success' => true, 'message' => $this->formatMessage($message)]);
        }

        try {
            $response = $this->telegram->sendMessage($request->input('message'), $chatId);

            if (! ($response['ok'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error'   => $response['description'] ?? 'Telegram error',
                ], 422);
            }

            $message = $this->telegram->storeOutgoingMessage($chatId, $request->input('message'), $response);
            return response()->json(['success' => true, 'message' => $this->formatMessage($message)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/telegram/chats/{chatId}/read */
    public function apiMarkRead(string $chatId): JsonResponse
    {
        $this->telegram->markChatRead($chatId);
        return response()->json(['success' => true]);
    }

    private function formatMessage(TelegramMessage $message): array
    {
        return [
            'id'        => $message->id,
            'direction' => $message->direction,
            'from_name' => $message->from_name,
            'text'      => $message->text,
            'sent_at'   => $message->sent_at?->format('H:i'),
            'sent_date' => $message->sent_at?->format('Y-m-d'),
        ];
    }
}
