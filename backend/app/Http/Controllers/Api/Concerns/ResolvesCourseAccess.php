<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

trait ResolvesCourseAccess
{
    protected function courseOrFail(Request $request, Course $course, string $ability = 'view'): Course
    {
        $user = $request->user();

        $allowed = match ($ability) {
            'view' => $course->grants($user),
            'teach' => $user->isAdmin() || $course->teacher_id === $user->id,
            default => false,
        };

        if (! $allowed) {
            throw new AuthorizationException('You do not have access to this course.');
        }

        $this->assertCourseIsActive($course, $ability);

        return $course;
    }

    protected function assertCourseIsActive(Course $course, string $ability): void
    {
        if (! $course->isActive() && $ability === 'teach') {
            throw new AuthorizationException('This course is archived and read-only.');
        }
    }

    protected function assertSelfOrTeacher(Request $request, User $student): void
    {
        $user = $request->user();

        if ($user->isAdmin() || $student->is($user)) {
            return;
        }

        throw new AuthorizationException('You may only manage your own record.');
    }
}
