<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'recipient_id',
        'course_id',
        'body',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeConversation(Builder $query, User $a, User $b): Builder
    {
        return $query->where(function (Builder $q) use ($a, $b) {
            $q->where(fn (Builder $x) => $x->where('sender_id', $a->id)->where('recipient_id', $b->id))
                ->orWhere(fn (Builder $x) => $x->where('sender_id', $b->id)->where('recipient_id', $a->id));
        });
    }
}
