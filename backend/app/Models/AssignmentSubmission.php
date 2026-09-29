<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'user_id',
        'course_id',
        'body',
        'file_path',
        'file_name',
        'link_url',
        'status',
        'submitted_at',
        'score',
        'feedback',
        'graded_by',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'score' => 'decimal:2',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function isGraded(): bool
    {
        return $this->status === 'graded' || $this->score !== null;
    }

    public function isLate(): bool
    {
        if (! $this->submitted_at || ! $this->assignment) {
            return false;
        }

        return $this->assignment->due_at !== null && $this->submitted_at->gt($this->assignment->due_at);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('score')->whereNotNull('submitted_at');
    }
}
