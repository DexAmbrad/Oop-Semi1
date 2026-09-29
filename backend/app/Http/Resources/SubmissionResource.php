<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AssignmentSubmission */
class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'course_id' => $this->course_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'body' => $this->body,
            'link_url' => $this->link_url,
            'file_name' => $this->file_name,
            'has_file' => (bool) $this->file_path,
            'file_url' => MediaUrl::public($this->file_path),
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'score' => $this->score !== null ? (float) $this->score : null,
            'feedback' => $this->feedback,
            'is_late' => $this->isLate(),
            'is_graded' => $this->isGraded(),
            'grader' => new UserResource($this->whenLoaded('grader')),
            'graded_at' => $this->graded_at?->toIso8601String(),
            'assignment' => $this->whenLoaded('assignment', fn () => [
                'id' => $this->assignment->id,
                'title' => $this->assignment->title,
                'max_points' => (int) $this->assignment->max_points,
                'due_at' => $this->assignment->due_at?->toIso8601String(),
            ]),
        ];
    }
}
