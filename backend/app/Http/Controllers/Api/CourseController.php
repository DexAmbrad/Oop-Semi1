<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    use Concerns\ResolvesCourseAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Course::query()
            ->when($user->isTeacher(), fn ($q) => $q->where('teacher_id', $user->id))
            ->when($user->isStudent(), fn ($q) => $q->whereHas(
                'enrollments',
                fn ($e) => $e->where('user_id', $user->id)->where('status', 'active')
            ))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('subject'), fn ($q) => $q->where('subject', $request->string('subject')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search')->toString();
                $like = '%'.$term.'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('code', 'like', $like));
            })
            ->with('teacher')
            ->withCount(['enrollments', 'assignments', 'announcements', 'materials'])
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderByDesc('created_at');

        return CourseResource::collection($query->paginate($request->integer('per_page', 12))->withQueryString());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isStudent()) {
            throw new AuthorizationException('Only teachers and administrators may create courses.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:24', 'unique:courses,code'],
            'subject' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:4000'],
            'accent' => ['nullable', 'in:violet,sky,emerald,amber,rose,indigo'],
            'icon' => ['nullable', 'in:book,flask,code,palette,globe,music,scale,compass'],
            'term' => ['nullable', 'string', 'max:60'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $teacherId = $user->isAdmin() && ! empty($data['teacher_id']) ? $data['teacher_id'] : $user->id;

        if ($user->isAdmin() && empty($data['teacher_id'])) {
            throw ValidationException::withMessages(['teacher_id' => ['Administrators must assign a teacher.']]);
        }

        $course = Course::create([
            ...$data,
            'teacher_id' => $teacherId,
            'code' => $data['code'] ?? strtoupper(Str::random(3)).random_int(100, 999),
            'room_code' => strtoupper(Str::random(6)),
            'status' => 'active',
        ]);

        ActivityLog::record('course.created', $course->title, ['code' => $course->code]);

        return response()->json([
            'message' => $course->title.' is ready to go.',
            'course' => new CourseResource($course->load('teacher')->loadCount(['enrollments', 'assignments'])),
        ], 201);
    }

    public function show(Request $request, Course $course): CourseResource
    {
        $this->courseOrFail($request, $course);

        return new CourseResource(
            $course->load('teacher')
                ->loadCount(['enrollments', 'assignments', 'announcements', 'materials'])
                ->load(['enrollments' => fn ($q) => $q->where('status', 'active')->with('user')])
        );
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'code' => ['sometimes', 'string', 'max:24', Rule::unique('courses', 'code')->ignore($course->id)],
            'subject' => ['sometimes', 'nullable', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'accent' => ['sometimes', 'in:violet,sky,emerald,amber,rose,indigo'],
            'icon' => ['sometimes', 'in:book,flask,code,palette,globe,music,scale,compass'],
            'term' => ['sometimes', 'nullable', 'string', 'max:60'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'status' => ['sometimes', 'in:active,archived'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        $course->update($data);
        ActivityLog::record('course.updated', $course->title);

        return response()->json([
            'message' => 'Course updated.',
            'course' => new CourseResource($course->fresh('teacher')->loadCount(['enrollments', 'assignments'])),
        ]);
    }

    public function destroy(Request $request, Course $course): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');

        $name = $course->title;
        $course->delete();
        ActivityLog::record('course.deleted', $name);

        return response()->json(['message' => $name.' has been archived.']);
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_code' => ['required', 'string', 'max:12'],
        ]);

        $course = Course::where('room_code', strtoupper($data['room_code']))->first();

        if (! $course || ! $course->isActive()) {
            throw ValidationException::withMessages([
                'room_code' => ['No active class matches that code.'],
            ]);
        }

        if ($course->teacher_id === $request->user()->id) {
            throw ValidationException::withMessages(['room_code' => ['You already own this class.']]);
        }

        $existing = Enrollment::where('course_id', $course->id)->where('user_id', $request->user()->id)->first();

        if ($existing && $existing->status === 'active') {
            throw ValidationException::withMessages(['room_code' => ['You are already enrolled.']]);
        }

        if ($course->isFull() && ! $request->user()->isTeacher()) {
            throw ValidationException::withMessages(['room_code' => ['This class is full.']]);
        }

        Enrollment::updateOrCreate(
            ['course_id' => $course->id, 'user_id' => $request->user()->id],
            [
                'role_in_course' => $request->user()->isTeacher() ? 'assistant_teacher' : 'student',
                'status' => 'active',
                'joined_at' => now(),
            ]
        );

        ActivityLog::record('course.joined', $course->title, [], $request->user()->id);

        return response()->json([
            'message' => 'You joined '.$course->title.'.',
            'course' => new CourseResource($course->load('teacher')->loadCount('enrollments')),
        ]);
    }

    public function leave(Request $request, Course $course): JsonResponse
    {
        Enrollment::where('course_id', $course->id)
            ->where('user_id', $request->user()->id)
            ->update(['status' => 'dropped']);

        ActivityLog::record('course.left', $course->title, [], $request->user()->id);

        return response()->json(['message' => 'You left '.$course->title.'.']);
    }

    public function people(Request $request, Course $course): AnonymousResourceCollection
    {
        $this->courseOrFail($request, $course);

        $people = Enrollment::where('course_id', $course->id)
            ->where('status', 'active')
            ->with('user')
            ->orderByRaw("case when role_in_course = 'assistant_teacher' then 0 else 1 end")
            ->get()
            ->sortBy(fn (Enrollment $e) => $e->user?->name)
            ->pluck('user')
            ->filter();

        return UserResource::collection(
            $people->push($course->teacher)->unique('id')->values()
        );
    }
}
