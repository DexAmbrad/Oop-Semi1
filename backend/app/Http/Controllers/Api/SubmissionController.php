<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubmissionResource;
use App\Models\ActivityLog;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Grade;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    use Concerns\ResolvesCourseAccess;

    public function index(Request $request, Course $course, Assignment $assignment): AnonymousResourceCollection
    {
        $this->courseOrFail($request, $course);
        abort_unless($assignment->course_id === $course->id, 404);

        $user = $request->user();
        $canTeach = $user->isAdmin() || $course->teacher_id === $user->id;

        $query = $assignment->submissions()->with('user')->latest();

        if (! $canTeach) {
            $query->where('user_id', $user->id);
        }

        return SubmissionResource::collection($query->get());
    }

    public function store(Request $request, Course $course, Assignment $assignment): JsonResponse
    {
        $this->courseOrFail($request, $course);
        abort_unless($assignment->course_id === $course->id, 404);

        $user = $request->user();

        if (! $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists()) {
            throw new AuthorizationException('Only enrolled students can submit work.');
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        if (empty($data['body']) && empty($data['link_url']) && ! $request->hasFile('file')) {
            throw ValidationException::withMessages([
                'body' => ['Add a written response, a link, or an attachment before submitting.'],
            ]);
        }

        $submission = AssignmentSubmission::firstOrNew([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
        ]);

        $late = $assignment->due_at && now()->gt($assignment->due_at);

        if ($late && ! $assignment->allow_late) {
            throw ValidationException::withMessages([
                'body' => ['The deadline for this assignment has passed and late work is not accepted.'],
            ]);
        }

        $submission->fill([
            'course_id' => $course->id,
            'body' => $data['body'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'status' => $late ? 'late' : 'submitted',
            'submitted_at' => now(),
        ]);

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('submissions/'.$assignment->id, 'public');
            $submission->file_path = $path;
            $submission->file_name = $request->file('file')->getClientOriginalName();
        }

        $submission->save();

        ActivityLog::record('submission.created', $assignment->title, [
            'course' => $course->title,
            'late' => $late,
        ], $user->id);

        return response()->json([
            'message' => $late ? 'Submitted — flagged as late.' : 'Work submitted. Nice one.',
            'submission' => new SubmissionResource($submission->load('user')),
        ], 201);
    }

    public function mySubmission(Request $request, Course $course, Assignment $assignment): JsonResponse
    {
        $this->courseOrFail($request, $course);
        abort_unless($assignment->course_id === $course->id, 404);

        $submission = $assignment->submissions()
            ->where('user_id', $request->user()->id)
            ->with('grader')
            ->first();

        return response()->json([
            'submission' => $submission ? new SubmissionResource($submission) : null,
        ]);
    }

    public function grade(Request $request, Course $course, Assignment $assignment, AssignmentSubmission $submission): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');
        abort_unless($submission->assignment_id === $assignment->id, 404);

        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$assignment->max_points],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($submission, $assignment, $course, $data, $request) {
            $submission->update([
                'score' => $data['score'],
                'feedback' => $data['feedback'] ?? null,
                'status' => 'graded',
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
            ]);

            Grade::updateOrCreate([
                'course_id' => $course->id,
                'user_id' => $submission->user_id,
                'item_type' => 'assignment',
                'item_id' => $assignment->id,
            ], [
                'item_title' => $assignment->title,
                'score' => $data['score'],
                'max_score' => $assignment->max_points,
                'weight' => 1,
                'feedback' => $data['feedback'] ?? null,
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
            ]);
        });

        ActivityLog::record('submission.graded', $assignment->title, [
            'score' => $data['score'],
            'course' => $course->title,
        ]);

        return response()->json([
            'message' => 'Grade saved.',
            'submission' => new SubmissionResource($submission->fresh('user')),
        ]);
    }

    public function bulkGrade(Request $request, Course $course, Assignment $assignment): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');

        $data = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*.submission_id' => ['required', 'integer', 'exists:assignment_submissions,id'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0', 'max:'.$assignment->max_points],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $graded = 0;

        DB::transaction(function () use ($data, $assignment, $course, $request, &$graded) {
            foreach ($data['scores'] as $row) {
                $submission = AssignmentSubmission::where('id', $row['submission_id'])
                    ->where('assignment_id', $assignment->id)
                    ->firstOrFail();

                if ($row['score'] === null) {
                    $submission->update([
                        'score' => null,
                        'status' => 'submitted',
                        'graded_by' => null,
                        'graded_at' => null,
                    ]);
                    Grade::where([
                        'course_id' => $course->id,
                        'user_id' => $submission->user_id,
                        'item_type' => 'assignment',
                        'item_id' => $assignment->id,
                    ])->delete();

                    continue;
                }

                $submission->update([
                    'score' => $row['score'],
                    'feedback' => $data['feedback'] ?? null,
                    'status' => 'graded',
                    'graded_by' => $request->user()->id,
                    'graded_at' => now(),
                ]);

                Grade::updateOrCreate([
                    'course_id' => $course->id,
                    'user_id' => $submission->user_id,
                    'item_type' => 'assignment',
                    'item_id' => $assignment->id,
                ], [
                    'item_title' => $assignment->title,
                    'score' => $row['score'],
                    'max_score' => $assignment->max_points,
                    'weight' => 1,
                    'feedback' => $data['feedback'] ?? null,
                    'graded_by' => $request->user()->id,
                    'graded_at' => now(),
                ]);

                $graded++;
            }
        });

        ActivityLog::record('assignment.bulk_graded', $assignment->title, ['count' => $graded]);

        return response()->json([
            'message' => $graded.' submission(s) graded.',
        ]);
    }
}
