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

    public function store(Request $request)
    {
        return Course::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'credit_hours' => $request->credit_hours,
            'instructor_id' => auth()->id(),
        ]);
    }

    public function show(Course $course)
    {
        return $course->load('exams');
    }
}
