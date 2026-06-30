<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamStudent;
use App\Models\ExamViolation;
use App\Models\User;
use App\Services\InstructorExamResultsService;
use Illuminate\Http\Request;

class InstructorExamResultsController extends Controller
{
    public function __construct(protected InstructorExamResultsService $resultsService) {}

    public function index(Request $request, Exam $exam)
    {
        if ($exam->instructor_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this exam.');
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $attempts = ExamStudent::where('exam_id', $exam->id)
            ->whereNotNull('started_at')
            ->with('student:id,name')
            ->withCount('violations')
            ->orderByDesc('submitted_at')
            ->orderByDesc('started_at')
            ->paginate($perPage);

        $attempts->load(['violations:id,exam_student_id,metadata,type']);

        $attempts->getCollection()->transform(function (ExamStudent $attempt) {
            return [
                'student_id' => $attempt->student_id,
                'student_name' => $attempt->student->name,
                'score' => $attempt->score,
                'submitted_at' => $attempt->submitted_at,
                'exam_status' => $this->resultsService->deriveExamStatus($attempt),
                'anti_cheat_status' => $this->resultsService->deriveAntiCheatStatus($attempt->violations),
                'total_violations' => $attempt->violations_count,
            ];
        });

        $this->resultsService->markExamResultsNotificationAsRead($exam, auth()->id());

        return response()->json([
            'status' => true,
            'message' => 'Exam results retrieved successfully',
            'data' => $attempts,
        ]);
    }

    public function show(Exam $exam, User $student)
    {
        if ($exam->instructor_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this exam.');
        }

        $attempt = ExamStudent::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->whereNotNull('started_at')
            ->firstOrFail();

        $answers = Answer::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->with(['question:id,exam_id,question_text,type,mark', 'option:id,question_id,option_text'])
            ->get()
            ->each(fn (Answer $answer) => $answer->makeVisible(['is_correct']));

        $violations = ExamViolation::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Student exam details retrieved successfully',
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                ],
                'exam' => [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'total_marks' => $exam->total_marks,
                    'duration' => $exam->duration,
                    'start_time' => $exam->start_time,
                    'end_time' => $exam->end_time,
                ],
                'answers' => $answers,
                'score' => $attempt->score,
                'submission_time' => $attempt->submitted_at,
                'violation_timeline' => $violations,
                'ai_status' => $this->resultsService->deriveAntiCheatStatus($violations),
                'violation_reasons' => $this->resultsService->deriveViolationReasons($violations),
            ],
        ]);
    }
}
