<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementCommentResource;
use App\Http\Resources\AnnouncementResource;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnouncementController extends Controller
{
    use Concerns\ResolvesCourseAccess;

    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        $this->courseOrFail($request, $course);

        $query = $course->announcements()
            ->with(['author', 'course'])
            ->withCount('comments')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->orderByDesc('pinned')
            ->orderByDesc('published_at');

        return AnnouncementResource::collection($query->paginate(20)->withQueryString());
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:20000'],
            'category' => ['sometimes', 'in:'.implode(',', Announcement::CATEGORIES)],
            'pinned' => ['sometimes', 'boolean'],
        ]);

        $announcement = $course->announcements()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        ActivityLog::record('announcement.published', $announcement->title, ['course' => $course->title]);

        return response()->json([
            'message' => 'Announcement posted.',
            'announcement' => new AnnouncementResource($announcement->load(['author', 'course'])->loadCount('comments')),
        ], 201);
    }

    public function show(Request $request, Course $course, Announcement $announcement): AnnouncementResource
    {
        $this->courseOrFail($request, $course);
        abort_unless($announcement->course_id === $course->id, 404);

        return new AnnouncementResource(
            $announcement->load(['author', 'course', 'comments.user'])->loadCount('comments')
        );
    }

    public function update(Request $request, Course $course, Announcement $announcement): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');
        abort_unless($announcement->course_id === $course->id, 404);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'body' => ['sometimes', 'string', 'max:20000'],
            'category' => ['sometimes', 'in:'.implode(',', Announcement::CATEGORIES)],
            'pinned' => ['sometimes', 'boolean'],
        ]);

        $announcement->update($data);

        return response()->json([
            'message' => 'Announcement updated.',
            'announcement' => new AnnouncementResource($announcement->fresh(['author', 'course'])->loadCount('comments')),
        ]);
    }

    public function destroy(Request $request, Course $course, Announcement $announcement): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');
        abort_unless($announcement->course_id === $course->id, 404);

        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted.']);
    }

    public function storeComment(Request $request, Course $course, Announcement $announcement): JsonResponse
    {
        $this->courseOrFail($request, $course);
        abort_unless($announcement->course_id === $course->id, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment = $announcement->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json([
            'message' => 'Reply posted.',
            'comment' => new AnnouncementCommentResource($comment->load('user')),
        ], 201);
    }

    public function destroyComment(Request $request, Course $course, Announcement $announcement, int $comment): JsonResponse
    {
        $this->courseOrFail($request, $course);
        abort_unless($announcement->course_id === $course->id, 404);

        $model = $announcement->comments()->findOrFail($comment);

        $user = $request->user();

        if (! $user->isAdmin() && $model->user_id !== $user->id && $course->teacher_id !== $user->id) {
            abort(403, 'You may only delete your own replies.');
        }

        $model->delete();

        return response()->json(['message' => 'Reply removed.']);
    }
}
