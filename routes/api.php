<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AntiCheatController;
use App\Http\Controllers\Instructor\CourseController;
use App\Http\Controllers\Instructor\ExamController;
use App\Http\Controllers\Instructor\QuestionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\StudentExamController;
use Illuminate\Support\Facades\Route;



// register and login routes    

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [ProfileController::class, 'show']);

     // Anti-Cheat AI
    Route::post('/exam/check-frame', [AntiCheatController::class, 'checkFrame']);


    // Instructor Routes 
    
    Route::middleware('role:instructor')->group(function () {
        
        // Course Management
        Route::get('/courses', [CourseController::class, 'index']);
        Route::post('/courses', [CourseController::class, 'store']);
        Route::get('/courses/{course}', [CourseController::class, 'show']);

        // Exam Management
        Route::post('/exams', [ExamController::class, 'store']);
        Route::get('/exams/{id}', [ExamController::class, 'show'])->whereNumber('id');

        // Question Management
        Route::post('/exams/{examId}/questions', [QuestionController::class, 'store']);
    });

    // Student Routes - Student Role Required
    
    Route::middleware('role:student')->group(function () {
        
        // Exam Taking
        Route::get('/exams/available', [StudentExamController::class, 'available']);
        Route::post('/exams/{exam}/start', [StudentExamController::class, 'start']);
        Route::post('/exams/{exam}/submit', [StudentExamController::class, 'submit']);
        Route::get('/exams/{exam}/result', [StudentExamController::class, 'result']);
    });
});
