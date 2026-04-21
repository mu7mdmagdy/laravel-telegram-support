<?php

namespace MoMagdy\TelegramSupport\Http\Controllers;

use MoMagdy\TelegramSupport\Models\TelegramChat;
use MoMagdy\TelegramSupport\Models\TelegramMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WidgetController extends Controller
{
    /**
     * POST /api/widget/session
     *
     * Returns an existing session or creates a new anonymous one.
     */
    public function startSession(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id');
        $name      = $request->input('name') ?: 'Visitor';

        if ($sessionId) {
            $chat = TelegramChat::where('chat_id', $sessionId)
                ->where('source', 'widget')
                ->first();

            if ($chat) {
                return response()->json(['success' => true, 'session_id' => $chat->chat_id, 'created' => false]);
            }
        }

        $sessionId = 'widget_' . Str::substr(Str::uuid()->toString(), 0, 8);

        $chat = TelegramChat::create([
            'chat_id'  => $sessionId,
            'name'     => $name,
            'username' => null,
            'type'     => 'private',
            'source'   => 'widget',
        ]);

        Cache::forget('telegram.chats');

        return response()->json(['success' => true, 'session_id' => $chat->chat_id, 'created' => true], 201);
    }

    /**
     * GET /api/widget/{sessionId}/messages
     */
    public function getMessages(string $sessionId): JsonResponse
    {
        if (! $this->sessionExists($sessionId)) {
            return response()->json(['success' => false, 'error' => 'Session not found'], 404);
        }

        $after    = (int) request()->query('after', 0);
        $cacheKey = "telegram.messages.{$sessionId}.{$after}";

        $messages = Cache::remember($cacheKey, 3, function () use ($sessionId, $after) {
            return TelegramMessage::where('chat_id', $sessionId)
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
                ]);
        });

        return response()->json(['success' => true, 'messages' => $messages]);
    }

    /**
     * POST /api/widget/{sessionId}/send
     */
    public function sendMessage(Request $request, string $sessionId): JsonResponse
    {
        if (! $this->sessionExists($sessionId)) {
            return response()->json(['success' => false, 'error' => 'Session not found'], 404);
        }

        $request->validate(['message' => 'required|string|max:4096']);

        $name = $request->input('name') ?: 'Visitor';
        $text = $request->input('message');

        $message = TelegramMessage::create([
            'chat_id'             => $sessionId,
            'telegram_message_id' => null,
            'direction'           => 'in',
            'from_name'           => $name,
            'from_username'       => null,
            'text'                => $text,
            'raw'                 => null,
            'sent_at'             => now(),
        ]);

        TelegramChat::where('chat_id', $sessionId)->update([
            'last_message_text' => $text,
            'last_message_at'   => now(),
        ]);
        TelegramChat::where('chat_id', $sessionId)->increment('unread_count');

        Cache::forget('telegram.chats');
        Cache::forget("telegram.messages.{$sessionId}.0");

        return response()->json([
            'success' => true,
            'message' => [
                'id'        => $message->id,
                'direction' => 'in',
                'from_name' => $name,
                'text'      => $text,
                'sent_at'   => $message->sent_at->format('H:i'),
            ],
        ], 201);
    }

    private function sessionExists(string $sessionId): bool
    {
        return TelegramChat::where('chat_id', $sessionId)->where('source', 'widget')->exists();
    }
}
