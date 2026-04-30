<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\answer;
use App\Models\Exam;
use App\Models\ExamStudent;
use Illuminate\Http\Request;

class StudentExamController extends Controller
{
    public function available()
    {
        return Exam::where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->whereDoesntHave('students', function ($query) {
                $query->where('student_id', auth()->id())
                      ->whereNotNull('submitted_at');
            })
            ->get();
    }

    public function start(Exam $exam)
    {
        ExamStudent::firstOrCreate([
            'exam_id' => $exam->id,
            'student_id' => auth()->id(),
        ],[
            'started_at' => now()
        ]);

        return $exam->load('questions.options');
    }

    public function submit(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.option_id' => 'nullable|exists:options,id',
            'answers.*.answer_text' => 'nullable|string',
        ]);

        $score = 0;
        $questions = $exam->questions()->with('options')->get()->keyBy('id');
        $answersToInsert = [];

        foreach ($validated['answers'] as $ans) {
            $question = $questions->get($ans['question_id']);
            $isCorrect = false;

            if (isset($ans['option_id'])) {
                $option = $question->options->where('id', $ans['option_id'])->first();
                if ($option && $option->is_correct) {
                    $isCorrect = true;
                    $score += $question->mark;
                }
            }

            $answersToInsert[] = [
                'exam_id' => $exam->id,
                'question_id' => $ans['question_id'],
                'student_id' => auth()->id(),
                'option_id' => $ans['option_id'] ?? null,
                'answer_text' => $ans['answer_text'] ?? null,
                'is_correct' => $isCorrect,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        \App\Models\Answer::insert($answersToInsert);

        ExamStudent::where('exam_id', $exam->id)
            ->where('student_id', auth()->id())
            ->update([
                'score' => $score,
                'submitted_at' => now()
            ]);

        return response()->json(['score' => $score]);
    }

    public function result(Exam $exam)
    {
        return ExamStudent::where('exam_id',$exam->id)
            ->where('student_id',auth()->id())
            ->first();
    }

}
