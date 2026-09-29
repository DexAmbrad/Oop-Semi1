<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssignmentResource;
use App\Models\ActivityLog;
use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssignmentController extends Controller
{
    use Concerns\ResolvesCourseAccess;

    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        $this->courseOrFail($request, $course);

        $user = $request->user();
        $canTeach = $user->isAdmin() || $course->teacher_id === $user->id;

        $query = $course->assignments()
            ->when(! $canTeach, fn ($q) => $q->published())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $like = '%'.$request->string('search').'%';
                $q->where('title', 'like', $like);
            })
            ->with(['author', 'course'])
            ->withCount(['submissions' => fn ($q) => $q->whereNotNull('submitted_at')]);

        if ($canTeach) {
            $query->withCount([
                'submissions as graded_count' => fn ($q) => $q->whereNotNull('score'),
                'submissions as pending_count' => fn ($q) => $q->whereNotNull('submitted_at')->whereNull('score'),
            ]);
        }

        $sort = $request->string('sort')->toString();

        match ($sort) {
            'due' => $query->orderByRaw('due_at is null')->orderBy('due_at'),
            'points' => $query->orderByDesc('max_points'),
            'title' => $query->orderBy('title'),
            'created' => $query->latest(),
            default => $query->latest('due_at'),
        };

        $assignments = $query->get()->load(['submissions' => fn ($q) => $q->where('user_id', $user->id)]);

        return AssignmentResource::collection($assignments);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'type' => ['sometimes', 'in:'.implode(',', Assignment::TYPES)],
            'max_points' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'due_at' => ['nullable', 'date'],
            'allow_late' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:published,draft'],
        ]);

        $assignment = $course->assignments()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'published_at' => ($data['status'] ?? 'published') === 'published' ? now() : null,
        ]);

        ActivityLog::record('assignment.created', $assignment->title, ['course' => $course->title]);

        return response()->json([
            'message' => 'Assignment published.',
            'assignment' => new AssignmentResource($assignment->load(['author', 'course'])),
        ], 201);
    }

    public function timeline(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $courseQuery = Course::query()
            ->when($user->isTeacher(), fn ($q) => $q->where('teacher_id', $user->id))
            ->when($user->isStudent(), fn ($q) => $q->whereHas(
                'enrollments',
                fn ($e) => $e->where('user_id', $user->id)->where('status', 'active')
            ));

        $canTeachAll = $user->isAdmin();

        $query = Assignment::query()
            ->whereIn('course_id', $courseQuery->select('id'))
            ->when(! $canTeachAll && ! $user->isTeacher(), fn ($q) => $q->published())
            ->when($request->filled('course'), fn ($q) => $q->where('course_id', $request->integer('course')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->with(['author', 'course.teacher'])
            ->withCount(['submissions' => fn ($q) => $q->whereNotNull('submitted_at')])
            ->orderByRaw('due_at is null')
            ->orderBy('due_at');

        $assignments = $query->limit(200)->get()
            ->load(['submissions' => fn ($q) => $q->where('user_id', $user->id)]);

        return AssignmentResource::collection($assignments);
    }

    public function show(Request $request, Course $course, Assignment $assignment): AssignmentResource
    {
        $this->courseOrFail($request, $course);
        abort_unless($assignment->course_id === $course->id, 404);

        $canTeach = $request->user()->isAdmin() || $course->teacher_id === $request->user()->id;

        if (! $canTeach && $assignment->status !== 'published') {
            throw new AuthorizationException('This assignment is not published yet.');
        }

        return new AssignmentResource(
            $assignment->load(['author', 'course', 'submissions.user'])
                ->loadCount('submissions')
        );
    }

    public function update(Request $request, Course $course, Assignment $assignment): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');
        abort_unless($assignment->course_id === $course->id, 404);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:500'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'type' => ['sometimes', 'in:'.implode(',', Assignment::TYPES)],
            'max_points' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'allow_late' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:published,draft'],
        ]);

        if (isset($data['status']) && $data['status'] === 'published' && ! $assignment->published_at) {
            $data['published_at'] = now();
        }

        $assignment->update($data);

        return response()->json([
            'message' => 'Assignment updated.',
            'assignment' => new AssignmentResource($assignment->fresh(['author', 'course'])),
        ]);
    }

    public function destroy(Request $request, Course $course, Assignment $assignment): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');
        abort_unless($assignment->course_id === $course->id, 404);

        $title = $assignment->title;
        $assignment->delete();
        ActivityLog::record('assignment.deleted', $title, ['course' => $course->title]);

        return response()->json(['message' => 'Assignment removed.']);
    }
}
