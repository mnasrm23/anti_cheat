<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamStudent;

class StudentDashboardController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        $enrolledCourses = auth()->user()->enrolledCourses()->with('instructor')->get();

        $examsTaken = ExamStudent::where('student_id', $userId)->with('exam')->get();
        $totalExamsTakenCount = $examsTaken->count();
        $totalScore = $examsTaken->sum('score');
        
        $enrolledCourseIds = auth()->user()->enrolledCourses()->pluck('courses.id');
        
        $availableExams = Exam::where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->whereIn('course_id', $enrolledCourseIds)
            ->whereDoesntHave('students', function($q) use ($userId) {
                $q->where('student_id', $userId)->whereNotNull('submitted_at');
            })
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Dashboard data retrieved successfully',
            'data' => [
                'enrolled_courses' => $enrolledCourses,
                'total_exams_taken' => $totalExamsTakenCount,
                'total_score' => $totalScore,
                'available_exams' => $availableExams,
            ]
        ]);
    }
}
