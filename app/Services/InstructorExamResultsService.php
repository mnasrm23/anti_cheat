<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamStudent;
use App\Models\ExamViolation;
use App\Models\Notification;
use Illuminate\Support\Collection;

class InstructorExamResultsService
{
    private const ANTI_CHEAT_PRIORITY = [
        'TERMINATED' => 4,
        'WATCHING' => 2,
        'OK' => 1,
    ];

    public function deriveExamStatus(ExamStudent $attempt): string
    {
        if ($attempt->submitted_at) {
            return 'submitted';
        }

        return 'in_progress';
    }

    public function deriveAntiCheatStatus(Collection $violations): string
    {
        $highestStatus = 'OK';
        $highestPriority = 0;

        foreach ($violations as $violation) {
            $status = $violation->metadata['anti_cheat_status'] ?? null;

            if (! $status) {
                continue;
            }

            $priority = $this->antiCheatStatusPriority($status);

            if ($priority > $highestPriority) {
                $highestPriority = $priority;
                $highestStatus = $status;
            }
        }

        if ($highestStatus === 'OK' && $violations->isNotEmpty()) {
            return 'WARNING';
        }

        return $highestStatus;
    }

    private function antiCheatStatusPriority(string $status): int
    {
        if ($status === 'TERMINATED') {
            return 4;
        }

        if (str_starts_with($status, 'WARNING')) {
            return 3;
        }

        return self::ANTI_CHEAT_PRIORITY[$status] ?? 0;
    }

    public function deriveViolationReasons(Collection $violations): array
    {
        return $violations
            ->map(fn (ExamViolation $violation) => $violation->metadata['cheat_reason'] ?? $violation->type)
            ->unique()
            ->values()
            ->all();
    }

    public function upsertExamSubmissionNotification(Exam $exam): void
    {
        $submittedCount = ExamStudent::where('exam_id', $exam->id)
            ->whereNotNull('submitted_at')
            ->count();

        $violationsCount = ExamViolation::where('exam_id', $exam->id)->count();

        $data = [
            'exam_id' => $exam->id,
            'exam_name' => $exam->title,
            'submitted_students_count' => $submittedCount,
            'total_violations_count' => $violationsCount,
        ];

        $message = "{$submittedCount} student(s) have submitted \"{$exam->title}\" ({$violationsCount} total violations).";

        $notification = Notification::where('user_id', $exam->instructor_id)
            ->where('type', 'exam_submitted')
            ->where('data->exam_id', $exam->id)
            ->first();

        if ($notification && ! $notification->read) {
            $notification->update([
                'title' => 'Exam Submissions',
                'message' => $message,
                'data' => $data,
            ]);

            return;
        }

        if ($notification) {
            $notification->update([
                'title' => 'Exam Submissions',
                'message' => $message,
                'data' => $data,
                'read' => false,
            ]);

            return;
        }

        Notification::create([
            'user_id' => $exam->instructor_id,
            'type' => 'exam_submitted',
            'title' => 'Exam Submissions',
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function markExamResultsNotificationAsRead(Exam $exam, int $instructorId): void
    {
        Notification::where('user_id', $instructorId)
            ->where('type', 'exam_submitted')
            ->where('data->exam_id', $exam->id)
            ->where('read', false)
            ->update(['read' => true]);
    }
}
