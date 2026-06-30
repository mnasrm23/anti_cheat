<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamStudent;
use App\Models\Notification;
use App\Services\InstructorExamResultsService;
use Illuminate\Http\Request;

class StudentExamController extends Controller
{
    public function __construct(protected InstructorExamResultsService $resultsService) {}

    public function available()
    {
        $enrolledCourseIds = auth()->user()->enrolledCourses()->pluck('courses.id');
        
        $exams = Exam::where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->whereIn('course_id', $enrolledCourseIds)
            ->whereDoesntHave('students', function ($query) {
                $query->where('student_id', auth()->id())
                      ->whereNotNull('submitted_at');
            })
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Available exams retrieved successfully',
            'data' => $exams
        ]);
    }

    public function start(Exam $exam)
    {
        if (!auth()->user()->enrolledCourses()->where('courses.id', $exam->course_id)->exists()) {
            abort(403, 'You are not enrolled in this course.');
        }

        // Single attempt check: if already started and submitted, prevent
        $existingAttempt = ExamStudent::where('exam_id', $exam->id)
            ->where('student_id', auth()->id())
            ->first();

        if ($existingAttempt && $existingAttempt->submitted_at) {
            return response()->json([
                'status' => false,
                'message' => 'You have already submitted this exam.',
                'data' => null
            ], 403);
        }

        // Check if exam is within start/end time
        if (now() < $exam->start_time || now() > $exam->end_time) {
            return response()->json([
                'status' => false,
                'message' => 'Exam is not available at this time.',
                'data' => null
            ], 403);
        }

        $examStudent = ExamStudent::firstOrCreate([
            'exam_id' => $exam->id,
            'student_id' => auth()->id(),
        ],[
            'started_at' => now()
        ]);

        $exam->load('questions.options');
        $exam->questions->each(fn ($question) => $question->options->each->makeHidden(['is_correct']));

        return response()->json([
            'status' => true,
            'message' => 'Exam started successfully',
            'exam_student_id' => $examStudent->id,
            'data' => $exam
        ]);
    }

    public function submit(Request $request, Exam $exam)
    {
        if (!auth()->user()->enrolledCourses()->where('courses.id', $exam->course_id)->exists()) {
            abort(403, 'You are not enrolled in this course.');
        }

        $existingAttempt = ExamStudent::where('exam_id', $exam->id)
            ->where('student_id', auth()->id())
            ->first();

        if (!$existingAttempt || !$existingAttempt->started_at) {
            return response()->json([
                'status' => false,
                'message' => 'You must start the exam before submitting.',
                'data' => null
            ], 403);
        }

        if ($existingAttempt->submitted_at) {
            return response()->json([
                'status' => false,
                'message' => 'You have already submitted this exam.',
                'data' => null
            ], 403);
        }

        if (now() > $exam->end_time) {
            return response()->json([
                'status' => false,
                'message' => 'Exam submission window has closed.',
                'data' => null
            ], 403);
        }

        $timeLimit = $exam->duration * 60;
        if (now()->diffInSeconds($existingAttempt->started_at) > $timeLimit) {
            return response()->json([
                'status' => false,
                'message' => 'Exam time has expired.',
                'data' => null
            ], 403);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.option_id' => 'nullable|exists:options,id',
            'answers.*.answer_text' => 'nullable|string',
        ]);

        // Verify all question_ids belong to the exam
        $examQuestionIds = $exam->questions()->pluck('id')->toArray();
        foreach ($validated['answers'] as $ans) {
            if (!in_array($ans['question_id'], $examQuestionIds)) {
                abort(400, "Question ID {$ans['question_id']} does not belong to this exam.");
            }
        }

        $score = 0;
        $questions = $exam->questions()->with('options')->get()->keyBy('id');
        $answersToInsert = [];

        foreach ($validated['answers'] as $ans) {
            $question = $questions->get($ans['question_id']);
            $isCorrect = false;

            if (in_array($question->type, ['mcq', 'true_false'], true)) {
                if (empty($ans['option_id'])) {
                    return response()->json([
                        'status' => false,
                        'message' => "Question {$question->id} requires an option_id.",
                        'data' => null,
                    ], 422);
                }

                $option = $question->options->firstWhere('id', $ans['option_id']);
                if (!$option) {
                    return response()->json([
                        'status' => false,
                        'message' => "Option {$ans['option_id']} does not belong to question {$question->id}.",
                        'data' => null,
                    ], 422);
                }

                if ($option->is_correct) {
                    $isCorrect = true;
                    $score += $question->mark;
                }
            } elseif ($question->type === 'text' && empty($ans['answer_text'])) {
                return response()->json([
                    'status' => false,
                    'message' => "Question {$question->id} requires an answer_text.",
                    'data' => null,
                ], 422);
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

        Answer::insert($answersToInsert);

        $existingAttempt->update([
            'score' => $score,
            'submitted_at' => now()
        ]);

        // Notify student
        Notification::create([
            'user_id' => auth()->id(),
            'type' => 'exam_submitted',
            'title' => 'Exam Submitted Successfully',
            'message' => "Your exam \"{$exam->title}\" has been submitted. Your score is {$score}/{$exam->total_marks}.",
            'data' => ['exam_id' => $exam->id, 'score' => $score],
        ]);

        $this->resultsService->upsertExamSubmissionNotification($exam);

        return response()->json([
            'status' => true,
            'message' => 'Exam submitted successfully',
            'data' => ['score' => $score]
        ]);
    }

    public function result(Exam $exam)
    {
        if (!auth()->user()->enrolledCourses()->where('courses.id', $exam->course_id)->exists()) {
            abort(403, 'You are not enrolled in this course.');
        }

        $result = ExamStudent::where('exam_id', $exam->id)
            ->where('student_id', auth()->id())
            ->firstOrFail();

        return response()->json([
            'status' => true,
            'message' => 'Exam result retrieved successfully',
            'data' => $result
        ]);
    }

}
