<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index()
    {
        return Course::where('instructor_id',auth()->id())->get();
    }
// validation more secure validation error
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:courses,code|max:50',
            'description' => 'nullable|string',
            'credit_hours' => 'required|integer|min:1|max:10',
        ]);

        return Course::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'credit_hours' => $validated['credit_hours'],
            'instructor_id' => auth()->id(),
        ]);
    }

    public function show(Course $course)
    {
        if ($course->instructor_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this course.');
        }

        return $course->load('exams');
    }
}
