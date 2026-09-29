<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Material */
class MaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'url' => $this->url,
            'file_name' => $this->file_name,
            'has_file' => (bool) $this->file_path,
            'file_url' => MediaUrl::public($this->file_path),
            'topic' => $this->topic,
            'uploader' => new UserResource($this->whenLoaded('uploader')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
