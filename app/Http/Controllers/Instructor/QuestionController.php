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
        $question = Question::create([
            'exam_id' => $examId,
            'question_text' => $request->question_text,
            'type' => $request->type,
            'mark' => $request->mark,
        ]);

        foreach ($request->options as $opt) {
            Option::create([
                'question_id' => $question->id,
                'option_text' => $opt['text'],
                'is_correct' => $opt['is_correct'] ?? false,
            ]);
        }

        return $question;
    }
}
