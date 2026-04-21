<?php

use Illuminate\Support\Facades\Route;
use MoMagdy\TelegramSupport\Http\Controllers\TelegramController;
use MoMagdy\TelegramSupport\Http\Controllers\WidgetController;
use MoMagdy\TelegramSupport\Http\Controllers\TelegramConnectController;

// ─── Admin / Bot API ──────────────────────────────────────────────────────────

Route::prefix('api/telegram')->middleware('api')->group(function () {
    Route::post('send',                          [TelegramController::class, 'apiSend']);
    Route::get('me',                             [TelegramController::class, 'apiGetMe']);
    Route::get('updates',                        [TelegramController::class, 'apiGetUpdates']);
    Route::post('sync',                          [TelegramController::class, 'apiSync']);
    Route::get('chats',                          [TelegramController::class, 'apiChats']);
    Route::get('chats/{chatId}/messages',        [TelegramController::class, 'apiMessages']);
    Route::post('chats/{chatId}/send',           [TelegramController::class, 'apiChatSend']);
    Route::post('chats/{chatId}/read',           [TelegramController::class, 'apiMarkRead']);

    Route::post('connect/generate',              [TelegramConnectController::class, 'generate']);
    Route::get('connect/status',                 [TelegramConnectController::class, 'status']);
});

// ─── Customer Widget API ──────────────────────────────────────────────────────

Route::prefix('api/widget')->middleware('api')->group(function () {
    Route::post('session',                       [WidgetController::class, 'startSession']);
    Route::get('{sessionId}/messages',           [WidgetController::class, 'getMessages']);
    Route::post('{sessionId}/send',              [WidgetController::class, 'sendMessage']);
});
