<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Option;
use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function store(Request $request, $examId)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'type' => 'required|in:mcq,true_false',
            'mark' => 'required|integer|min:1',
            'options' => 'required|array|min:2',
            'options.*.text' => 'required|string',
            'options.*.is_correct' => 'boolean',
        ]);

        $question = Question::create([
            'exam_id' => $examId,
            'question_text' => $validated['question_text'],
            'type' => $validated['type'],
            'mark' => $validated['mark'],
        ]);

        foreach ($validated['options'] as $opt) {
            Option::create([
                'question_id' => $question->id,
                'option_text' => $opt['text'],
                'is_correct' => $opt['is_correct'] ?? false,
            ]);
        }

        return $question;
    }
}
