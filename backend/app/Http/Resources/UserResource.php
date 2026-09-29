<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'initials' => $this->initials(),
            'avatar_path' => $this->avatar_path,
            'headline' => $this->headline,
            'phone' => $this->phone,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'courses_count' => $this->whenCounted('courses'),
            'taught_courses_count' => $this->whenCounted('taughtCourses'),
            'submissions_count' => $this->whenCounted('submissions'),
        ];
    }
}
