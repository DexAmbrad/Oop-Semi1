<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['assignment', 'quiz', 'project', 'reading'];

    protected $fillable = [
        'course_id',
        'created_by',
        'title',
        'summary',
        'instructions',
        'type',
        'max_points',
        'due_at',
        'allow_late',
        'published_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'published_at' => 'datetime',
            'allow_late' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'published';
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }

    public function isDueSoon(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isFuture()
            && $this->due_at->diffInHours(now()) <= 72;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
