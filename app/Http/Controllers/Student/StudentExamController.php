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
        return Exam::where('start_time','<=',now())
            ->where('end_time','>=',now())
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
        $score = 0;

        foreach ($request->answers as $ans) {
            $answer = Answer::create([
                'exam_id' => $exam->id,
                'question_id' => $ans['question_id'],
                'student_id' => auth()->id(),
                'option_id' => $ans['option_id'] ?? null,
                'answer_text' => $ans['answer_text'] ?? null,
            ]);

            if ($answer->option && $answer->option->is_correct) {
                $answer->is_correct = true;
                $score += $answer->question->mark;
            } else {
                $answer->is_correct = false;
            }

            $answer->save();
        }

        ExamStudent::where('exam_id',$exam->id)
            ->where('student_id',auth()->id())
            ->update([
                'score' => $score,
                'submitted_at' => now()
            ]);

        return ['score' => $score];
    }

    public function result(Exam $exam)
    {
        return ExamStudent::where('exam_id',$exam->id)
            ->where('student_id',auth()->id())
            ->first();
    }

}
