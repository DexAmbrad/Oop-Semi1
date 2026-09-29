<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Grade */
class GradeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'item_type' => $this->item_type,
            'item_id' => $this->item_id,
            'item_title' => $this->item_title,
            'score' => (float) $this->score,
            'max_score' => (float) $this->max_score,
            'weight' => (float) $this->weight,
            'percentage' => $this->percentage(),
            'feedback' => $this->feedback,
            'graded_at' => $this->graded_at?->toIso8601String(),
        ];
    }
}
