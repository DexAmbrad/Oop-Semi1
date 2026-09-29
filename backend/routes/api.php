<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::patch('auth/profile', [AuthController::class, 'updateProfile']);
        Route::patch('auth/password', [AuthController::class, 'changePassword']);

        Route::get('dashboard', DashboardController::class);

        Route::get('assignments', [AssignmentController::class, 'timeline']);

        Route::post('courses/join', [CourseController::class, 'join']);
        Route::get('courses', [CourseController::class, 'index']);
        Route::post('courses', [CourseController::class, 'store']);
        Route::get('courses/{course}', [CourseController::class, 'show']);
        Route::patch('courses/{course}', [CourseController::class, 'update']);
        Route::delete('courses/{course}', [CourseController::class, 'destroy']);
        Route::post('courses/{course}/leave', [CourseController::class, 'leave']);
        Route::get('courses/{course}/people', [CourseController::class, 'people']);

        Route::get('courses/{course}/assignments', [AssignmentController::class, 'index']);
        Route::post('courses/{course}/assignments', [AssignmentController::class, 'store']);
        Route::get('courses/{course}/assignments/{assignment}', [AssignmentController::class, 'show']);
        Route::patch('courses/{course}/assignments/{assignment}', [AssignmentController::class, 'update']);
        Route::delete('courses/{course}/assignments/{assignment}', [AssignmentController::class, 'destroy']);

        Route::get('courses/{course}/assignments/{assignment}/submissions', [SubmissionController::class, 'index']);
        Route::post('courses/{course}/assignments/{assignment}/submissions', [SubmissionController::class, 'store']);
        Route::get('courses/{course}/assignments/{assignment}/my-submission', [SubmissionController::class, 'mySubmission']);
        Route::post('courses/{course}/assignments/{assignment}/submissions/bulk-grade', [SubmissionController::class, 'bulkGrade']);
        Route::patch('courses/{course}/assignments/{assignment}/submissions/{submission}/grade', [SubmissionController::class, 'grade']);

        Route::get('courses/{course}/announcements', [AnnouncementController::class, 'index']);
        Route::post('courses/{course}/announcements', [AnnouncementController::class, 'store']);
        Route::get('courses/{course}/announcements/{announcement}', [AnnouncementController::class, 'show']);
        Route::patch('courses/{course}/announcements/{announcement}', [AnnouncementController::class, 'update']);
        Route::delete('courses/{course}/announcements/{announcement}', [AnnouncementController::class, 'destroy']);
        Route::post('courses/{course}/announcements/{announcement}/comments', [AnnouncementController::class, 'storeComment']);
        Route::delete('courses/{course}/announcements/{announcement}/comments/{comment}', [AnnouncementController::class, 'destroyComment']);

        Route::get('courses/{course}/materials', [MaterialController::class, 'index']);
        Route::post('courses/{course}/materials', [MaterialController::class, 'store']);
        Route::patch('courses/{course}/materials/{material}', [MaterialController::class, 'update']);
        Route::delete('courses/{course}/materials/{material}', [MaterialController::class, 'destroy']);

        Route::get('courses/{course}/gradebook', [GradeController::class, 'gradebook']);
        Route::post('courses/{course}/gradebook/cell', [GradeController::class, 'gradeCell']);
        Route::get('courses/{course}/grades', [GradeController::class, 'index']);
        Route::post('courses/{course}/grades', [GradeController::class, 'store']);
        Route::delete('courses/{course}/grades/{grade}', [GradeController::class, 'destroy']);

        Route::get('messages', [MessageController::class, 'conversations']);
        Route::get('messages/contacts', [MessageController::class, 'contacts']);
        Route::get('messages/roster', [MessageController::class, 'courseRoster']);
        Route::get('messages/{user}', [MessageController::class, 'show']);
        Route::post('messages/{user}', [MessageController::class, 'store']);

        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::get('activity', [UserController::class, 'activity']);
            Route::post('users', [UserController::class, 'store']);
            Route::get('users/{user}', [UserController::class, 'show']);
            Route::patch('users/{user}', [UserController::class, 'update']);
            Route::delete('users/{user}', [UserController::class, 'destroy']);
        });
    });
});
