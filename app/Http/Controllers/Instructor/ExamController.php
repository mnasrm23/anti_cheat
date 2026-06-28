<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index()
    {
        $exams = Exam::where('instructor_id', auth()->id())->with('course')->paginate(10);
        return response()->json([
            'status' => true,
            'message' => 'Exams retrieved successfully',
            'data' => $exams
        ]);
    }

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

        $course = \App\Models\Course::findOrFail($validated['course_id']);
        if ($course->instructor_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this course.');
        }

        $exam = Exam::create([
            'title' => $validated['title'],
            'course_id' => $validated['course_id'],
            'instructor_id' => auth()->id(),
            'total_marks' => $validated['total_marks'],
            'duration' => $validated['duration'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Exam created successfully',
            'data' => $exam
        ], 201);
    }

    public function show(Exam $exam)
    {
        if ($exam->instructor_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this exam.');
        }

        $exam->load('questions.options');
        $exam->questions->each(fn ($question) => $question->options->each->makeVisible(['is_correct']));

        return response()->json([
            'status' => true,
            'message' => 'Exam retrieved successfully',
            'data' => $exam
        ]);
    }

    public function update(Request $request, Exam $exam)
    {
        if ($exam->instructor_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this exam.');
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'course_id' => 'nullable|exists:courses,id',
            'total_marks' => 'nullable|integer|min:1',
            'duration' => 'nullable|integer|min:1',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after:start_time',
        ]);

        if (isset($validated['course_id'])) {
            $course = \App\Models\Course::findOrFail($validated['course_id']);
            if ($course->instructor_id !== auth()->id()) {
                abort(403, 'Unauthorized access to this course.');
            }
        }

        $exam->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Exam updated successfully',
            'data' => $exam
        ]);
    }

    public function destroy(Exam $exam)
    {
        if ($exam->instructor_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this exam.');
        }

        $exam->delete();

        return response()->json([
            'status' => true,
            'message' => 'Exam deleted successfully',
            'data' => null
        ]);
    }
}
