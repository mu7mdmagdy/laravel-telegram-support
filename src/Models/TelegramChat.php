<?php

namespace MoMagdy\TelegramSupport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramChat extends Model
{
    protected $fillable = [
        'chat_id',
        'name',
        'username',
        'type',
        'source',
        'last_message_text',
        'last_message_at',
        'unread_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'unread_count'    => 'integer',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class, 'chat_id', 'chat_id');
    }

    public function getAvatarLetterAttribute(): string
    {
        return strtoupper(mb_substr($this->name ?: '?', 0, 1));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: ($this->username ? '@' . $this->username : 'Chat ' . $this->chat_id);
    }
}
