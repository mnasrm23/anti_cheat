<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;

class ExamController extends Controller
{
     public function store(Request $request)
    {
        return Exam::create([
            'title' => $request->title,
            'course_id' => $request->course_id,
            'instructor_id' => auth()->id(),
            'total_marks' => $request->total_marks,
            'duration' => $request->duration,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);
    }

    public function show($id)
    {
        return Exam::with('questions.options')->findOrFail($id);
    }
}
