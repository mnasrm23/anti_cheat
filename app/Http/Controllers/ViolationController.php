<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamStudent;
use App\Models\ExamViolation;
use App\Models\Notification;
use Illuminate\Http\Request;

class ViolationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'type' => 'required|in:face_not_detected,multiple_faces,tab_switch,screen_share',
            'description' => 'required|string',
            'metadata' => 'nullable|array',
        ]);

        $exam = Exam::findOrFail($validated['exam_id']);

        if (!auth()->user()->enrolledCourses()->where('courses.id', $exam->course_id)->exists()) {
            abort(403, 'You are not enrolled in this course.');
        }

        $examStudent = ExamStudent::where('exam_id', $validated['exam_id'])
            ->where('student_id', auth()->id())
            ->firstOrFail();

        $violation = ExamViolation::create([
            'exam_id' => $validated['exam_id'],
            'student_id' => auth()->id(),
            'exam_student_id' => $examStudent->id,
            'type' => $validated['type'],
            'description' => $validated['description'],
            'metadata' => $validated['metadata'] ?? null,
        ]);

        // Notify instructor
        Notification::create([
            'user_id' => $exam->instructor_id,
            'type' => 'exam_violation',
            'title' => 'Exam Violation Detected',
            'message' => "A violation was detected for student " . auth()->user()->name . " in exam: " . $exam->title,
            'data' => ['violation_id' => $violation->id],
        ]);

        return response()->json(['message' => 'Violation reported successfully', 'violation' => $violation], 201);
    }

    public function forExam($examId)
    {
        $exam = Exam::findOrFail($examId);
        if ($exam->instructor_id !== auth()->id()) {
            abort(403);
        }
        return $exam->violations()->with('student')->get();
    }
}
