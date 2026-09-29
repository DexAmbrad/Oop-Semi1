<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Course */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enrolled = false;
        $roleInCourse = null;

        if ($request->user()) {
            $pivot = $this->enrollments->firstWhere('user_id', $request->user()->id);
            $enrolled = (bool) $pivot && $pivot->status === 'active';
            $roleInCourse = $pivot?->role_in_course;
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'subject' => $this->subject,
            'description' => $this->description,
            'room_code' => $this->room_code,
            'accent' => $this->accent,
            'icon' => $this->icon,
            'term' => $this->term,
            'status' => $this->status,
            'capacity' => (int) $this->capacity,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'teacher' => new UserResource($this->whenLoaded('teacher')),
            'students_count' => $this->countOr('students_count', 'enrollments_count'),
            'assignments_count' => $this->countOr('assignments_count', null),
            'announcements_count' => $this->countOr('announcements_count', null),
            'materials_count' => $this->countOr('materials_count', null),
            'roster' => $this->whenLoaded('enrollments', fn () => $this->enrollments
                ->map(fn ($e) => [
                    'id' => $e->user_id,
                    'name' => $e->user?->name,
                    'email' => $e->user?->email,
                    'initials' => $e->user?->initials(),
                    'role_in_course' => $e->role_in_course,
                ])->values()),
            'my_enrollment' => [
                'is_enrolled' => $enrolled,
                'role' => $roleInCourse,
            ],
            'is_teacher' => $request->user()?->id === $this->teacher_id,
            'is_admin' => (bool) $request->user()?->isAdmin(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function countOr(string $primary, ?string $relationCount): mixed
    {
        $value = $this->{$primary} ?? ($relationCount ? ($this->{$relationCount} ?? null) : null);

        return $value === null ? 0 : (int) $value;
    }
}
