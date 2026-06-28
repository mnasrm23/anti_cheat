<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class StudentCourseController extends Controller
{
    public function join(Request $request)
    {
        $validated = $request->validate([
            'join_code' => 'required|string',
        ]);

        $course = Course::where('join_code', $validated['join_code'])->firstOrFail();

        // Check if already enrolled
        if ($course->enrolledStudents()->where('student_id', auth()->id())->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'You are already enrolled in this course.',
                'data' => null
            ], 409);
        }

        $course->enrolledStudents()->attach(auth()->id());

        return response()->json([
            'status' => true,
            'message' => 'Successfully enrolled in course!',
            'data' => $course
        ], 201);
    }

    public function enrolled()
    {
        $courses = auth()->user()->enrolledCourses()->with('instructor')->paginate(10);
        return response()->json([
            'status' => true,
            'message' => 'Enrolled courses retrieved successfully',
            'data' => $courses
        ]);
    }
}
