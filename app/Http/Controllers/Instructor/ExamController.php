<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'course_id' => 'required|exists:courses,id',
            'total_marks' => 'required|integer|min:1',
            'duration' => 'required|integer|min:1',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        return Exam::create([
            'title' => $validated['title'],
            'course_id' => $validated['course_id'],
            'instructor_id' => auth()->id(),
            'total_marks' => $validated['total_marks'],
            'duration' => $validated['duration'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
        ]);
    }

    public function show($id)
    {
        return Exam::with('questions.options')->findOrFail($id);
    }
}
