<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MessageController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();

        $partnerIds = Message::where('sender_id', $user->id)->pluck('recipient_id')
            ->merge(Message::where('recipient_id', $user->id)->pluck('sender_id'))
            ->unique();

        $partners = User::whereIn('id', $partnerIds)->get()->keyBy('id');

        $conversations = $partnerIds->map(function ($partnerId) use ($user, $partners) {
            $thread = Message::conversation($user, $partners[$partnerId] ?? new User(['id' => $partnerId]))
                ->with(['sender', 'course'])
                ->latest()
                ->limit(50)
                ->get()
                ->sortBy('created_at')
                ->values();

            if ($thread->isEmpty()) {
                return null;
            }

            $last = $thread->last();

            return [
                'partner' => [
                    'id' => $partnerId,
                    'name' => $partners[$partnerId]->name ?? 'Unknown',
                    'initials' => $partners[$partnerId]->initials() ?? '?',
                    'role' => $partners[$partnerId]->role ?? 'student',
                    'last_seen_at' => $partners[$partnerId]->last_seen_at?->toIso8601String(),
                ],
                'last_message' => $last->body,
                'last_at' => $last->created_at?->toIso8601String(),
                'unread' => $thread->where('recipient_id', $user->id)->whereNull('read_at')->count(),
            ];
        })->filter()->sortByDesc('last_at')->values();

        return response()->json($conversations);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $me = $request->user();

        Message::conversation($me, $user)
            ->where('recipient_id', $me->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = Message::conversation($me, $user)
            ->with(['sender', 'course'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'partner' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'initials' => $user->initials(),
                'headline' => $user->headline,
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            ],
            'messages' => MessageResource::collection($messages),
        ]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
        ]);

        $message = Message::create([
            'sender_id' => $request->user()->id,
            'recipient_id' => $user->id,
            'course_id' => $data['course_id'] ?? null,
            'body' => $data['body'],
        ]);

        return response()->json([
            'message' => new MessageResource($message->load(['sender', 'recipient', 'course'])),
        ], 201);
    }

    public function contacts(Request $request): AnonymousResourceCollection
    {
        $me = $request->user();

        $query = User::where('id', '!=', $me->id)
            ->where('status', 'active')
            ->search($request->string('search')->toString() ?: null)
            ->orderBy('name');

        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }

        return \App\Http\Resources\UserResource::collection($query->limit(50)->get());
    }

    public function courseRoster(Request $request): AnonymousResourceCollection
    {
        $courseIds = $request->user()->isTeacher()
            ? $request->user()->taughtCourses()->pluck('courses.id')
            : Enrollment::where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->pluck('course_id');

        $people = User::whereIn('id', function ($q) use ($courseIds) {
            $q->select('user_id')->from('enrollments')
                ->whereIn('course_id', $courseIds)
                ->where('status', 'active');
        })->where('id', '!=', $request->user()->id)->orderBy('name')->get();

        return \App\Http\Resources\UserResource::collection($people);
    }
}
