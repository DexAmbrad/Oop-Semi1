<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'sender_id' => $this->sender_id,
            'recipient_id' => $this->recipient_id,
            'sender' => new UserResource($this->whenLoaded('sender')),
            'recipient' => new UserResource($this->whenLoaded('recipient')),
            'course' => $this->whenLoaded('course', fn () => [
                'id' => $this->course->id,
                'title' => $this->course->title,
                'accent' => $this->course->accent,
            ]),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
