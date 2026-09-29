<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Assignment */
class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        $mySubmission = $this->relationLoaded('submissions') && $user
            ? $this->submissions->firstWhere('user_id', $user->id)
            : null;

        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'summary' => $this->summary,
            'instructions' => $this->instructions,
            'type' => $this->type,
            'max_points' => (int) $this->max_points,
            'due_at' => $this->due_at?->toIso8601String(),
            'allow_late' => (bool) $this->allow_late,
            'published_at' => $this->published_at?->toIso8601String(),
            'status' => $this->status,
            'is_overdue' => $this->isOverdue(),
            'is_due_soon' => $this->isDueSoon(),
            'author' => new UserResource($this->whenLoaded('author')),
            'course' => new CourseResource($this->whenLoaded('course')),
            'submissions_count' => $this->when(isset($this->submissions_count), (int) $this->submissions_count)
                ?? $this->whenCounted('submissions'),
            'graded_count' => $this->when(isset($this->graded_count), (int) $this->graded_count),
            'pending_count' => $this->when(isset($this->pending_count), (int) $this->pending_count),
            'my_submission' => $mySubmission ? [
                'id' => $mySubmission->id,
                'status' => $mySubmission->status,
                'body' => $mySubmission->body,
                'link_url' => $mySubmission->link_url,
                'file_name' => $mySubmission->file_name,
                'submitted_at' => $mySubmission->submitted_at?->toIso8601String(),
                'score' => $mySubmission->score !== null ? (float) $mySubmission->score : null,
                'feedback' => $mySubmission->feedback,
                'is_late' => $mySubmission->isLate(),
                'graded_at' => $mySubmission->graded_at?->toIso8601String(),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
