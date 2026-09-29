<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Announcement */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'body' => $this->body,
            'category' => $this->category,
            'pinned' => (bool) $this->pinned,
            'published_at' => $this->published_at?->toIso8601String(),
            'author' => new UserResource($this->whenLoaded('author')),
            'course' => new CourseResource($this->whenLoaded('course')),
            'comments' => AnnouncementCommentResource::collection($this->whenLoaded('comments')),
            'comments_count' => $this->when(isset($this->comments_count), (int) $this->comments_count)
                ?? $this->whenCounted('comments'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
