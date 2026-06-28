<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::where('instructor_id', auth()->id())->paginate(10);
        return response()->json([
            'status' => true,
            'message' => 'Courses retrieved successfully',
            'data' => $courses
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:courses,code|max:50',
            'description' => 'nullable|string',
            'credit_hours' => 'nullable|integer|min:1|max:10',
        ]);

        $course = Course::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'credit_hours' => $validated['credit_hours'] ?? 3,
            'instructor_id' => auth()->id(),
            'join_code' => Course::generateJoinCode(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Course created successfully',
            'data' => $course
        ], 201);
    }

    public function show(Course $course)
    {
        if ($course->instructor_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this course.');
        }

        return response()->json([
            'status' => true,
            'message' => 'Course retrieved successfully',
            'data' => $course->load('exams')
        ]);
    }

    public function update(Request $request, Course $course)
    {
        if ($course->instructor_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this course.');
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|unique:courses,code,' . $course->id . '|max:50',
            'description' => 'nullable|string',
            'credit_hours' => 'nullable|integer|min:1|max:10',
        ]);

        $course->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Course updated successfully',
            'data' => $course
        ]);
    }

    public function destroy(Course $course)
    {
        if ($course->instructor_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this course.');
        }

        $course->delete();

        return response()->json([
            'status' => true,
            'message' => 'Course deleted successfully',
            'data' => null
        ]);
    }
}
