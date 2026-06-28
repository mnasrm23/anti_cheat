<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Option;
use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index($examId)
    {
        $exam = \App\Models\Exam::findOrFail($examId);
        if ($exam->instructor_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this exam.');
        }

        $questions = $exam->questions()->with('options')->paginate(10);
        $questions->each(fn ($q) => $q->options->each->makeVisible(['is_correct']));

        return response()->json([
            'status' => true,
            'message' => 'Questions retrieved successfully',
            'data' => $questions
        ]);
    }

    public function store(Request $request, $examId)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'type' => 'required|in:mcq,true_false,text',
            'mark' => 'required|integer|min:1',
            'options' => 'exclude_if:type,text|required|array|min:2',
            'options.*.text' => 'exclude_if:type,text|required|string',
            'options.*.is_correct' => 'exclude_if:type,text|boolean',
        ]);

        // Require exactly one correct option for MCQ and true/false questions
        if (in_array($validated['type'], ['mcq', 'true_false'])) {
            $correctOptionsCount = collect($validated['options'])->filter(fn ($opt) => $opt['is_correct'] ?? false)->count();
            if ($correctOptionsCount !== 1) {
                abort(400, 'MCQ and true/false questions must have exactly one correct option.');
            }
        }

        $exam = \App\Models\Exam::findOrFail($examId);
        if ($exam->instructor_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this exam.');
        }

        $question = Question::create([
            'exam_id' => $examId,
            'question_text' => $validated['question_text'],
            'type' => $validated['type'],
            'mark' => $validated['mark'],
        ]);

        if (isset($validated['options'])) {
            foreach ($validated['options'] as $opt) {
                Option::create([
                    'question_id' => $question->id,
                    'option_text' => $opt['text'],
                    'is_correct' => $opt['is_correct'] ?? false,
                ]);
            }
        }

        $question->load('options');
        $question->options->each->makeVisible(['is_correct']);

        return response()->json([
            'status' => true,
            'message' => 'Question created successfully',
            'data' => $question
        ], 201);
    }

    public function show($examId, Question $question)
    {
        $exam = \App\Models\Exam::findOrFail($examId);
        if ($exam->instructor_id !== auth()->id() || $question->exam_id !== $exam->id) {
            abort(403, 'Unauthorized access to this question.');
        }

        $question->load('options');
        $question->options->each->makeVisible(['is_correct']);

        return response()->json([
            'status' => true,
            'message' => 'Question retrieved successfully',
            'data' => $question
        ]);
    }

    public function update(Request $request, $examId, Question $question)
    {
        $exam = \App\Models\Exam::findOrFail($examId);
        if ($exam->instructor_id !== auth()->id() || $question->exam_id !== $exam->id) {
            abort(403, 'Unauthorized access to this question.');
        }

        $validated = $request->validate([
            'question_text' => 'nullable|string',
            'type' => 'nullable|in:mcq,true_false,text',
            'mark' => 'nullable|integer|min:1',
        ]);

        $question->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Question updated successfully',
            'data' => $question
        ]);
    }

    public function destroy($examId, Question $question)
    {
        $exam = \App\Models\Exam::findOrFail($examId);
        if ($exam->instructor_id !== auth()->id() || $question->exam_id !== $exam->id) {
            abort(403, 'Unauthorized access to this question.');
        }

        $question->delete();

        return response()->json([
            'status' => true,
            'message' => 'Question deleted successfully',
            'data' => null
        ]);
    }
}
