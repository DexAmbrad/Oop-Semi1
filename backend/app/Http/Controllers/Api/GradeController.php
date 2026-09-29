<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GradeResource;
use App\Models\Course;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GradeController extends Controller
{
    use Concerns\ResolvesCourseAccess;

    public function gradebook(Request $request, Course $course): JsonResponse
    {
        $this->courseOrFail($request, $course);

        $user = $request->user();
        $canTeach = $user->isAdmin() || $course->teacher_id === $user->id;

        $items = $course->grades()
            ->where('item_type', 'assignment')
            ->whereNotNull('item_id')
            ->select('item_id', 'item_title')
            ->distinct()
            ->get()
            ->sortBy('item_id')
            ->values();

        if ($canTeach) {
            $people = $course->enrollments()
                ->where('status', 'active')
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter()
                ->sortBy('name')
                ->values();
        } else {
            $people = collect([$user]);
        }

        $matrix = $course->grades()
            ->whereIn('user_id', $people->pluck('id'))
            ->get()
            ->groupBy('user_id');

        $rows = $people->map(function (User $person) use ($items, $matrix) {
            $mine = $matrix[$person->id] ?? collect();
            $cells = $items->map(fn ($item) => $mine->firstWhere('item_id', $item->item_id));

            $earned = $cells->filter()->sum(fn (Grade $g) => (float) $g->score);
            $possible = $cells->filter()->sum(fn (Grade $g) => (float) $g->max_score);

            return [
                'user' => [
                    'id' => $person->id,
                    'name' => $person->name,
                    'email' => $person->email,
                    'initials' => $person->initials(),
                ],
                'cells' => $items->map(fn ($item) => [
                    'item_id' => $item->item_id,
                    'score' => ($cell = $mine->firstWhere('item_id', $item->item_id)) ? (float) $cell->score : null,
                    'max_score' => $cell ? (float) $cell->max_score : null,
                ])->values(),
                'total' => round($earned, 2),
                'possible' => round($possible, 2),
                'percentage' => $possible > 0 ? round(($earned / $possible) * 100, 1) : null,
            ];
        })->values();

        return response()->json([
            'course' => ['id' => $course->id, 'title' => $course->title, 'accent' => $course->accent, 'code' => $course->code],
            'items' => $items->map(fn ($i, $idx) => [
                'item_id' => $i->item_id,
                'label' => 'A'.($idx + 1),
                'title' => $i->item_title,
            ])->values(),
            'rows' => $rows,
            'class_average' => $rows->whereNotNull('percentage')->avg('percentage')
                ? round($rows->whereNotNull('percentage')->avg('percentage'), 1)
                : null,
        ]);
    }

    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        $this->courseOrFail($request, $course);

        $user = $request->user();
        $query = $course->grades()->with('user')->latest('graded_at');

        if (! $user->isAdmin() && $course->teacher_id !== $user->id) {
            $query->where('user_id', $user->id);
        }

        return GradeResource::collection($query->get());
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'item_title' => ['required', 'string', 'max:180'],
            'score' => ['required', 'numeric', 'min:0'],
            'max_score' => ['required', 'numeric', 'min:1'],
            'weight' => ['sometimes', 'numeric', 'min:0.1', 'max:10'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $grade = Grade::create([
            ...$data,
            'course_id' => $course->id,
            'item_type' => 'manual',
            'item_id' => null,
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Grade recorded.',
            'grade' => new GradeResource($grade->load('user')),
        ], 201);
    }

    public function gradeCell(Request $request, Course $course): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'item_id' => ['required', 'integer'],
            'score' => ['nullable', 'numeric', 'min:0'],
        ]);

        $assignment = $course->assignments()->find($data['item_id']);
        abort_unless($assignment, 404);

        abort_unless(
            $course->enrollments()->where('user_id', $data['user_id'])->where('status', 'active')->exists(),
            422,
            'That learner is not enrolled in this classroom.'
        );

        $existing = $course->grades()
            ->where('user_id', $data['user_id'])
            ->where('item_type', 'assignment')
            ->where('item_id', $assignment->id)
            ->first();

        if ($data['score'] === null) {
            $existing?->delete();

            return response()->json(['message' => 'Grade cleared.']);
        }

        $grade = Grade::updateOrCreate(
            [
                'course_id' => $course->id,
                'user_id' => $data['user_id'],
                'item_type' => 'assignment',
                'item_id' => $assignment->id,
            ],
            [
                'item_title' => $assignment->title,
                'score' => $data['score'],
                'max_score' => $assignment->max_points,
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Gradebook updated.',
            'grade' => new GradeResource($grade->load('user')),
        ]);
    }

    public function destroy(Request $request, Course $course, Grade $grade): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');
        abort_unless($grade->course_id === $course->id, 404);

        $grade->delete();

        return response()->json(['message' => 'Grade removed.']);
    }
}
