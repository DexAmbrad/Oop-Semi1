<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORIES = ['general', 'schedule', 'resource', 'event', 'urgent'];

    protected $fillable = [
        'course_id',
        'user_id',
        'title',
        'body',
        'category',
        'pinned',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $announcement) {
            $announcement->published_at ??= now();
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class);
    }
}
