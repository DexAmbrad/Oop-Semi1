<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(match (true) {
            $user->isAdmin() => $this->admin(),
            $user->isTeacher() => $this->teacher($user),
            default => $this->student($user),
        });
    }

    private function admin(): array
    {
        $now = now();

        return [
            'role' => 'admin',
            'metrics' => [
                ['label' => 'Total users', 'value' => User::count(), 'icon' => 'users', 'tone' => 'violet'],
                ['label' => 'Teachers', 'value' => User::role('teacher')->count(), 'icon' => 'badge', 'tone' => 'sky'],
                ['label' => 'Students', 'value' => User::role('student')->count(), 'icon' => 'graduation', 'tone' => 'emerald'],
                ['label' => 'Active courses', 'value' => Course::where('status', 'active')->count(), 'icon' => 'book', 'tone' => 'amber'],
            ],
            'role_split' => User::select('role', DB::raw('count(*) as total'))
                ->groupBy('role')
                ->pluck('total', 'role'),
            'course_load' => Course::with('teacher')
                ->withCount('enrollments')
                ->orderByDesc('enrollments_count')
                ->limit(5)
                ->get()
                ->map(fn (Course $c) => [
                    'id' => $c->id,
                    'title' => $c->title,
                    'code' => $c->code,
                    'accent' => $c->accent,
                    'teacher' => $c->teacher?->name,
                    'students' => $c->enrollments_count,
                ]),
            'recent_activity' => ActivityLog::with('user')->latest()->limit(12)->get()
                ->map(fn (ActivityLog $l) => [
                    'action' => $l->action,
                    'subject' => $l->subject,
                    'user' => $l->user?->name,
                    'created_at' => $l->created_at?->toIso8601String(),
                ]),
            'recent_users' => User::latest()->limit(6)->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'initials' => $u->initials(),
                    'status' => $u->status,
                    'created_at' => $u->created_at?->toIso8601String(),
                ]),
            'signups_by_month' => $this->signupsByMonth(),
        ];
    }

    private function teacher(User $user): array
    {
        $courses = Course::where('teacher_id', $user->id)
            ->withCount(['enrollments', 'assignments'])
            ->latest()
            ->get();

        $courseIds = $courses->pluck('id');

        return [
            'role' => 'teacher',
            'metrics' => [
                ['label' => 'My courses', 'value' => $courses->count(), 'icon' => 'book', 'tone' => 'violet'],
                ['label' => 'Total students', 'value' => Enrollment::whereIn('course_id', $courseIds)->where('status', 'active')->distinct('user_id')->count(), 'icon' => 'users', 'tone' => 'sky'],
                ['label' => 'To grade', 'value' => Assignment::whereIn('course_id', $courseIds)->published()->get()->sum(fn ($a) => $a->submissions()->whereNotNull('submitted_at')->whereNull('score')->count()), 'icon' => 'clipboard', 'tone' => 'amber'],
                ['label' => 'Open assignments', 'value' => Assignment::whereIn('course_id', $courseIds)->published()->count(), 'icon' => 'sparkles', 'tone' => 'emerald'],
            ],
            'courses' => $courses->map(fn (Course $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'code' => $c->code,
                'accent' => $c->accent,
                'icon' => $c->icon,
                'students_count' => $c->enrollments_count,
                'assignments_count' => $c->assignments_count,
            ]),
            'needs_grading' => Assignment::whereIn('course_id', $courseIds)
                ->published()
                ->with('course')
                ->withCount('submissions')
                ->get()
                ->map(function (Assignment $a) {
                    $submitted = $a->submissions()->whereNotNull('submitted_at')->count();
                    $ungraded = $a->submissions()->whereNotNull('submitted_at')->whereNull('score')->count();

                    return [
                        'id' => $a->id,
                        'title' => $a->title,
                        'due_at' => $a->due_at?->toIso8601String(),
                        'submitted' => $submitted,
                        'ungraded' => $ungraded,
                        'course' => ['id' => $a->course->id, 'title' => $a->course->title, 'accent' => $a->course->accent],
                    ];
                })
                ->filter(fn ($row) => $row['ungraded'] > 0)
                ->sortByDesc('ungraded')
                ->values()
                ->take(5),
            'upcoming' => Assignment::whereIn('course_id', $courseIds)
                ->published()
                ->whereNotNull('due_at')
                ->where('due_at', '>=', now())
                ->with('course')
                ->orderBy('due_at')
                ->limit(5)
                ->get()
                ->map(fn (Assignment $a) => [
                    'id' => $a->id,
                    'course_id' => $a->course_id,
                    'title' => $a->title,
                    'type' => $a->type,
                    'due_at' => $a->due_at?->toIso8601String(),
                    'course_title' => $a->course->title,
                    'accent' => $a->course->accent,
                ]),
        ];
    }

    private function student(User $user): array
    {
        $courseIds = $user->courses()->wherePivot('status', 'active')->pluck('courses.id');

        $upcoming = Assignment::whereIn('course_id', $courseIds)
            ->published()
            ->with('course')
            ->orderByRaw('due_at is null, due_at asc')
            ->limit(6)
            ->get()
            ->map(function (Assignment $a) use ($user) {
                $submission = $a->submissions()->where('user_id', $user->id)->first();

                return [
                    'id' => $a->id,
                    'course_id' => $a->course_id,
                    'title' => $a->title,
                    'type' => $a->type,
                    'max_points' => (int) $a->max_points,
                    'due_at' => $a->due_at?->toIso8601String(),
                    'is_overdue' => $a->isOverdue(),
                    'course_title' => $a->course->title,
                    'accent' => $a->course->accent,
                    'submitted' => (bool) $submission?->submitted_at,
                    'score' => $submission?->score !== null ? (float) $submission->score : null,
                ];
            });

        $grades = Grade::where('user_id', $user->id)->whereIn('course_id', $courseIds)->get();
        $earned = $grades->sum(fn (Grade $g) => (float) $g->score);
        $possible = $grades->sum(fn (Grade $g) => (float) $g->max_score);

        return [
            'role' => 'student',
            'metrics' => [
                ['label' => 'Enrolled courses', 'value' => $courseIds->count(), 'icon' => 'book', 'tone' => 'violet'],
                ['label' => 'Due this week', 'value' => Assignment::whereIn('course_id', $courseIds)->published()->whereBetween('due_at', [now(), now()->addWeek()])->count(), 'icon' => 'clock', 'tone' => 'amber'],
                ['label' => 'Submitted', 'value' => Assignment::whereIn('course_id', $courseIds)->published()->get()->filter(fn ($a) => $a->submissions()->where('user_id', $user->id)->whereNotNull('submitted_at')->exists())->count(), 'icon' => 'check', 'tone' => 'emerald'],
                ['label' => 'Average score', 'value' => $possible > 0 ? round(($earned / $possible) * 100, 1) : null, 'suffix' => '%', 'icon' => 'trophy', 'tone' => 'sky'],
            ],
            'upcoming' => $upcoming,
            'announcements' => Announcement::whereIn('course_id', $courseIds)
                ->with(['course', 'author'])
                ->latest('published_at')
                ->limit(4)
                ->get()
                ->map(fn (Announcement $a) => [
                    'id' => $a->id,
                    'course_id' => $a->course_id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'category' => $a->category,
                    'pinned' => (bool) $a->pinned,
                    'author' => $a->author?->name,
                    'course_title' => $a->course->title,
                    'accent' => $a->course->accent,
                    'published_at' => $a->published_at?->toIso8601String(),
                ]),
            'grade_summary' => $courseIds->map(function ($id) use ($user) {
                $grades = Grade::where('user_id', $user->id)->where('course_id', $id)->get();
                $earned = $grades->sum(fn (Grade $g) => (float) $g->score);
                $possible = $grades->sum(fn (Grade $g) => (float) $g->max_score);
                $course = Course::find($id);

                return [
                    'course_id' => $id,
                    'title' => $course?->title,
                    'accent' => $course?->accent,
                    'percentage' => $possible > 0 ? round(($earned / $possible) * 100, 1) : null,
                    'graded_count' => $grades->count(),
                ];
            })->values(),
        ];
    }

    private function signupsByMonth(): array
    {
        $rows = User::selectRaw("strftime('%Y-%m', created_at) as period, count(*) as total")
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('total', 'period');

        $months = collect(range(5, 0))->map(function (int $back) {
            $date = now()->subMonths($back);

            return [
                'label' => $date->format('M'),
                'total' => (int) ($rows[$date->format('Y-m')] ?? 0),
            ];
        });

        return $months->all();
    }
}
