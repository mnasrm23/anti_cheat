<?php

use App\Http\Controllers\AntiCheatController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Instructor\CourseController;
use App\Http\Controllers\Instructor\ExamController;
use App\Http\Controllers\Instructor\InstructorDashboardController;
use App\Http\Controllers\Instructor\OptionController;
use App\Http\Controllers\Instructor\QuestionController;
use App\Http\Controllers\Instructor\StudentImportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\StudentCourseController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Student\StudentExamController;
use App\Http\Controllers\ViolationController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [ProfileController::class, 'show']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/mark-all-read', [NotificationController::class, 'destroyAll']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    Route::middleware('role:student')->group(function () {
        Route::post('/violations', [ViolationController::class, 'store']);
        Route::get('/student/dashboard', [StudentDashboardController::class, 'index']);
        Route::post('/courses/join', [StudentCourseController::class, 'join']);
        Route::get('/student/courses', [StudentCourseController::class, 'enrolled']);

        Route::get('/exams/available', [StudentExamController::class, 'available']);
        Route::post('/exam/check-frame', [AntiCheatController::class, 'checkFrame']);
        Route::post('/exams/{exam}/start', [StudentExamController::class, 'start']);
        Route::post('/exams/{exam}/submit', [StudentExamController::class, 'submit']);
        Route::get('/exams/{exam}/result', [StudentExamController::class, 'result']);
    });

    Route::middleware('role:instructor')->group(function () {
        Route::get('/instructor/dashboard', [InstructorDashboardController::class, 'index']);

        Route::get('/courses', [CourseController::class, 'index']);
        Route::post('/courses', [CourseController::class, 'store']);
        Route::get('/courses/{course}', [CourseController::class, 'show']);
        Route::put('/courses/{course}', [CourseController::class, 'update']);
        Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
        Route::post('/courses/{course}/import-students', [StudentImportController::class, 'import']);

        Route::get('/exams', [ExamController::class, 'index']);
        Route::post('/exams', [ExamController::class, 'store']);
        Route::get('/exams/{exam}', [ExamController::class, 'show']);
        Route::put('/exams/{exam}', [ExamController::class, 'update']);
        Route::delete('/exams/{exam}', [ExamController::class, 'destroy']);
        Route::get('/exams/{examId}/violations', [ViolationController::class, 'forExam']);

        Route::get('/exams/{examId}/questions', [QuestionController::class, 'index']);
        Route::post('/exams/{examId}/questions', [QuestionController::class, 'store']);
        Route::get('/exams/{examId}/questions/{question}', [QuestionController::class, 'show']);
        Route::put('/exams/{examId}/questions/{question}', [QuestionController::class, 'update']);
        Route::delete('/exams/{examId}/questions/{question}', [QuestionController::class, 'destroy']);

        Route::get('/exams/{examId}/questions/{question}/options', [OptionController::class, 'index']);
        Route::post('/exams/{examId}/questions/{question}/options', [OptionController::class, 'store']);
        Route::get('/exams/{examId}/questions/{question}/options/{option}', [OptionController::class, 'show']);
        Route::put('/exams/{examId}/questions/{question}/options/{option}', [OptionController::class, 'update']);
        Route::delete('/exams/{examId}/questions/{question}/options/{option}', [OptionController::class, 'destroy']);
    });
});
