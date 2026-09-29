<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->search($request->string('search')->toString() ?: null)
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role' => ['required', 'in:admin,teacher,student'],
            'headline' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $user = User::create($data + ['password' => Hash::make($data['password']), 'last_seen_at' => now()]);
        ActivityLog::record('user.created', $user->name, ['role' => $user->role]);

        return response()->json([
            'message' => $user->name.' has been added.',
            'user' => new UserResource($user),
        ], 201);
    }

    public function activity(Request $request): AnonymousResourceCollection
    {
        $logs = ActivityLog::query()
            ->with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->string('action')->toString().'%'))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $like = '%'.$request->string('search')->toString().'%';
                $q->where(fn ($w) => $w->where('subject', 'like', $like)->orWhere('action', 'like', $like));
            })
            ->latest()
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return ActivityLogResource::collection($logs);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->loadCount(['courses', 'taughtCourses', 'submissions']));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:190', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', 'nullable', Password::min(8)],
            'role' => ['sometimes', 'in:admin,teacher,student'],
            'headline' => ['sometimes', 'nullable', 'string', 'max:160'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'status' => ['sometimes', 'in:active,suspended'],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($user->is($request->user()) && isset($data['role']) && $data['role'] !== 'admin') {
            throw ValidationException::withMessages([
                'role' => ['You cannot remove your own administrator access.'],
            ]);
        }

        $user->update($data);
        ActivityLog::record('user.updated', $user->name, $data);

        return response()->json([
            'message' => $user->name.' has been updated.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => ['You cannot delete your own account.']]);
        }

        $user->delete();
        ActivityLog::record('user.deleted', $user->name);

        return response()->json(['message' => $user->name.' has been removed.']);
    }
}
