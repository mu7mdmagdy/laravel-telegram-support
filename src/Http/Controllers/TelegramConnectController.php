<?php

namespace MoMagdy\TelegramSupport\Http\Controllers;

use MoMagdy\TelegramSupport\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramConnectController extends Controller
{
    /**
     * POST /api/telegram/connect/generate
     *
     * Generates a short-lived one-time token the user sends to the bot
     * via `/connect TOKEN`. No user binding — the developer handles saving
     * the chat_id from the hidden form input.
     */
    public function generate(Request $request): JsonResponse
    {
        // Lazy GC — clean up expired unclaimed tokens
        DB::table('telegram_connect_tokens')
            ->whereNull('connected_at')
            ->where('expires_at', '<', now())
            ->delete();

        do {
            $token = strtoupper(Str::random(6));
        } while (DB::table('telegram_connect_tokens')->where('token', $token)->exists());

        DB::table('telegram_connect_tokens')->insert([
            'token'        => $token,
            'model_class'  => null,
            'user_id'      => null,
            'chat_id'      => null,
            'connected_at' => null,
            'expires_at'   => now()->addMinutes(10),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return response()->json([
            'success'      => true,
            'token'        => $token,
            'bot_username' => app(TelegramService::class)->getBotUsername(),
            'expires_in'   => 600,
        ]);
    }

    /**
     * GET /api/telegram/connect/status?token=ABC123
     *
     * Returns whether the token has been claimed.
     * Also triggers a Telegram sync so the /connect message is picked up
     * even when the admin inbox is not open.
     */
    public function status(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string|max:10']);

        $row = DB::table('telegram_connect_tokens')
            ->where('token', $request->query('token'))
            ->first();

        if (! $row) {
            return response()->json(['success' => false, 'error' => 'Token not found'], 404);
        }

        if (! $row->connected_at && now()->isAfter($row->expires_at)) {
            return response()->json(['success' => true, 'connected' => false, 'expired' => true]);
        }

        if (! $row->connected_at) {
            try {
                app(TelegramService::class)->syncUpdates();
            } catch (\Exception $e) {
                Log::warning('connect/status sync error: ' . $e->getMessage());
            }

            $row = DB::table('telegram_connect_tokens')
                ->where('token', $request->query('token'))
                ->first();
        }

        return response()->json([
            'success'   => true,
            'connected' => (bool) $row->connected_at,
            'chat_id'   => $row->chat_id,
            'expired'   => false,
        ]);
    }
}
