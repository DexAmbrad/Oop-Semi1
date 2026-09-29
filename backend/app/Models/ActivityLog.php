<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'subject',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?string $subject = null, array $properties = [], ?int $userId = null): self
    {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'subject' => $subject,
            'properties' => $properties ?: null,
        ]);
    }
}
