<?php

namespace MoMagdy\TelegramSupport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    protected $fillable = [
        'chat_id',
        'telegram_message_id',
        'direction',
        'from_name',
        'from_username',
        'text',
        'raw',
        'sent_at',
    ];

    protected $casts = [
        'raw'     => 'array',
        'sent_at' => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegramChat::class, 'chat_id', 'chat_id');
    }

    public function isOutgoing(): bool
    {
        return $this->direction === 'out';
    }

    public function isIncoming(): bool
    {
        return $this->direction === 'in';
    }
}
