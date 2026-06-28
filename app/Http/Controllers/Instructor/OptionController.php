<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use Illuminate\Http\Request;

class OptionController extends Controller
{
    public function index($examId, $questionId)
    {
        $exam = Exam::findOrFail($examId);
        $question = Question::findOrFail($questionId);

        if ($exam->instructor_id !== auth()->id() || $question->exam_id !== $exam->id) {
            abort(403, 'Unauthorized access.');
        }

        $options = $question->options()->paginate(10);
        $options->each(fn ($o) => $o->makeVisible(['is_correct']));

        return response()->json([
            'status' => true,
            'message' => 'Options retrieved successfully',
            'data' => $options
        ]);
    }

    public function store(Request $request, $examId, $questionId)
    {
        $exam = Exam::findOrFail($examId);
        $question = Question::findOrFail($questionId);

        if ($exam->instructor_id !== auth()->id() || $question->exam_id !== $exam->id) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'option_text' => 'required|string',
            'is_correct' => 'required|boolean',
        ]);

        $option = Option::create([
            'question_id' => $question->id,
            'option_text' => $validated['option_text'],
            'is_correct' => $validated['is_correct'],
        ]);

        $option->makeVisible(['is_correct']);

        return response()->json([
            'status' => true,
            'message' => 'Option created successfully',
            'data' => $option
        ], 201);
    }

    public function show($examId, $questionId, Option $option)
    {
        $exam = Exam::findOrFail($examId);
        $question = Question::findOrFail($questionId);

        if ($exam->instructor_id !== auth()->id() || $question->exam_id !== $exam->id || $option->question_id !== $question->id) {
            abort(403, 'Unauthorized access.');
        }

        $option->makeVisible(['is_correct']);

        return response()->json([
            'status' => true,
            'message' => 'Option retrieved successfully',
            'data' => $option
        ]);
    }

    public function update(Request $request, $examId, $questionId, Option $option)
    {
        $exam = Exam::findOrFail($examId);
        $question = Question::findOrFail($questionId);

        if ($exam->instructor_id !== auth()->id() || $question->exam_id !== $exam->id || $option->question_id !== $question->id) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'option_text' => 'nullable|string',
            'is_correct' => 'nullable|boolean',
        ]);

        $option->update($validated);
        $option->makeVisible(['is_correct']);

        return response()->json([
            'status' => true,
            'message' => 'Option updated successfully',
            'data' => $option
        ]);
    }

    public function destroy($examId, $questionId, Option $option)
    {
        $exam = Exam::findOrFail($examId);
        $question = Question::findOrFail($questionId);

        if ($exam->instructor_id !== auth()->id() || $question->exam_id !== $exam->id || $option->question_id !== $question->id) {
            abort(403, 'Unauthorized access.');
        }

        $option->delete();

        return response()->json([
            'status' => true,
            'message' => 'Option deleted successfully',
            'data' => null
        ]);
    }
}
