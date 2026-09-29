<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'user_id',
        'item_type',
        'item_id',
        'item_title',
        'score',
        'max_score',
        'weight',
        'feedback',
        'graded_by',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'weight' => 'decimal:2',
            'graded_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function percentage(): float
    {
        if ((float) $this->max_score <= 0) {
            return 0.0;
        }

        return round(((float) $this->score / (float) $this->max_score) * 100, 1);
    }
}
