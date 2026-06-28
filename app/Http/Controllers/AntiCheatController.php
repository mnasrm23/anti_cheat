<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamStudent;
use App\Models\ExamViolation;
use App\Models\Notification;
use App\Services\AntiCheatService;
use App\Services\AntiCheatServiceException;
use Illuminate\Http\Request;

class AntiCheatController extends Controller
{
    public function __construct(protected AntiCheatService $antiCheat) {}

    public function checkFrame(Request $request)
    {
        $validated = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'session_id' => 'required|string',
            'image' => 'required|image|max:5120',
        ]);

        $exam = Exam::findOrFail($validated['exam_id']);

        if (! auth()->user()->enrolledCourses()->where('courses.id', $exam->course_id)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'You are not enrolled in this course.',
                'data' => null,
            ], 403);
        }

        $examStudent = ExamStudent::where('exam_id', $exam->id)
            ->where('student_id', auth()->id())
            ->first();

        if (! $examStudent || ! $examStudent->started_at) {
            return response()->json([
                'status' => false,
                'message' => 'You must start the exam before sending frames.',
                'data' => null,
            ], 403);
        }

        if ($examStudent->submitted_at) {
            return response()->json([
                'status' => false,
                'message' => 'This exam has already been submitted.',
                'data' => null,
            ], 403);
        }

        if ((string) $examStudent->id !== (string) $validated['session_id']) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid proctoring session.',
                'data' => null,
            ], 403);
        }

        if (now() < $exam->start_time || now() > $exam->end_time) {
            return response()->json([
                'status' => false,
                'message' => 'Exam is not available at this time.',
                'data' => null,
            ], 403);
        }

        try {
            $result = $this->antiCheat->analyzeFrame(
                $validated['session_id'],
                $request->file('image')
            );
        } catch (AntiCheatServiceException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], $e->getStatusCode());
        }

        $violation = $this->recordViolationIfNeeded($exam, $examStudent, $result);

        $antiCheatStatus = $result['anti_cheat']['status'] ?? 'OK';
        $canContinue = $antiCheatStatus !== 'TERMINATED';

        return response()->json([
            'status' => true,
            'message' => 'Frame analyzed successfully',
            'data' => array_merge($result, [
                'violation_recorded' => $violation !== null,
                'can_continue' => $canContinue,
            ]),
        ]);
    }

    private function recordViolationIfNeeded(Exam $exam, ExamStudent $examStudent, array $result): ?ExamViolation
    {
        $antiCheat = $result['anti_cheat'] ?? [];
        $status = $antiCheat['status'] ?? 'OK';

        if ($status === 'OK' || $status === 'WATCHING') {
            return null;
        }

        $cheatReason = $antiCheat['cheat_reason'] ?? 'unknown';
        $type = $this->mapCheatReasonToViolationType($cheatReason);
        $description = $this->buildViolationDescription($status, $cheatReason, $result);

        $violation = ExamViolation::create([
            'exam_id' => $exam->id,
            'student_id' => auth()->id(),
            'exam_student_id' => $examStudent->id,
            'type' => $type,
            'description' => $description,
            'metadata' => [
                'source' => 'ai',
                'anti_cheat_status' => $status,
                'cheat_reason' => $cheatReason,
                'yolo' => $result['yolo'] ?? null,
                'gaze' => $result['gaze'] ?? null,
            ],
        ]);

        Notification::create([
            'user_id' => $exam->instructor_id,
            'type' => 'exam_violation',
            'title' => 'Exam Violation Detected',
            'message' => 'A violation was detected for student '.auth()->user()->name.' in exam: '.$exam->title,
            'data' => ['violation_id' => $violation->id],
        ]);

        return $violation;
    }

    private function mapCheatReasonToViolationType(string $cheatReason): string
    {
        return match ($cheatReason) {
            'multiple_faces_detected' => 'multiple_faces',
            'no_eye_detected', 'no_face_detected', 'looking_away_from_screen', 'looking_at_phone_or_paper' => 'face_not_detected',
            default => 'face_not_detected',
        };
    }

    private function buildViolationDescription(string $status, string $cheatReason, array $result): string
    {
        $gazeDirection = $result['gaze']['gaze_direction'] ?? 'UNKNOWN';

        return "AI proctoring detected {$cheatReason} (status: {$status}, gaze: {$gazeDirection}).";
    }
}
