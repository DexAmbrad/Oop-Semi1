<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'title',
        'subject',
        'description',
        'room_code',
        'accent',
        'icon',
        'teacher_id',
        'term',
        'status',
        'capacity',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $course) {
            $course->room_code ??= strtoupper(Str::random(6));
        });
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->wherePivot('role_in_course', 'student')
            ->wherePivot('status', 'active')
            ->withPivot(['status', 'joined_at'])
            ->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function studentCount(): int
    {
        return $this->enrollments()->where('status', 'active')->count();
    }

    public function isFull(): bool
    {
        return $this->studentCount() >= (int) $this->capacity;
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('teacher_id', $user->id)
                ->orWhereHas('enrollments', fn (Builder $e) => $e->where('user_id', $user->id));
        });
    }

    public function grants(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->teacher_id === $user->id) {
            return true;
        }

        return $this->enrollments()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }
}
