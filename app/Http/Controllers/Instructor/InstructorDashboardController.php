<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamViolation;

class InstructorDashboardController extends Controller
{
    public function index()
    {
        $userId = auth()->id();
        $courses = Course::where('instructor_id', $userId)->get();
        $exams = Exam::where('instructor_id', $userId)->get();

        return response()->json([
            'status' => true,
            'message' => 'Dashboard data retrieved successfully',
            'data' => [
                'total_courses' => $courses->count(),
                'total_exams' => $exams->count(),
                'total_students' => $courses->sum(fn($c) => $c->enrolledStudents()->count()),
                'total_violations' => ExamViolation::whereIn('exam_id', $exams->pluck('id'))->count(),
                'recent_violations' => ExamViolation::whereIn('exam_id', $exams->pluck('id'))->latest()->take(5)->with('student', 'exam')->get(),
                'recent_exams' => $exams->sortByDesc('created_at')->take(5)->values()->load('course'),
            ]
        ]);
    }
}
