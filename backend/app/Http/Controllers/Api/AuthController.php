<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['sometimes', 'in:'.User::ROLE_TEACHER.','.User::ROLE_STUDENT],
            'headline' => ['nullable', 'string', 'max:160'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'] ?? User::ROLE_STUDENT,
            'headline' => $data['headline'] ?? null,
            'last_seen_at' => now(),
        ]);

        ActivityLog::record('user.registered', $user->name, ['role' => $user->role], $user->id);

        return response()->json([
            'message' => 'Welcome aboard, '.$user->name.'.',
            'token' => $user->createToken('web')->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['This account has been suspended. Please contact an administrator.'],
            ]);
        }

        $user->forceFill(['last_seen_at' => now()])->save();
        ActivityLog::record('auth.login', $user->name, [], $user->id);

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'web')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadCount(['courses', 'taughtCourses']);

        return response()->json([
            'user' => new UserResource($user),
            'stats' => [
                'courses_count' => $user->courses()->where('status', 'active')->count(),
                'taught_courses_count' => $user->taughtCourses()->where('status', 'active')->count(),
                'unread_messages' => $user->receivedMessages()->whereNull('read_at')->count(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'headline' => ['sometimes', 'nullable', 'string', 'max:160'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'email' => ['sometimes', 'email', 'max:190', 'unique:users,email,'.$user->id],
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['That password is incorrect.'],
            ]);
        }

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => 'Password changed.']);
    }
}
