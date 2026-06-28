<?php

/**
 * Backend + AI integration verification (no frontend).
 * Run: php scripts/verify-backend-ai.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$baseUrl = rtrim(getenv('LARAVEL_TEST_URL') ?: 'http://127.0.0.1:8000', '/');
$aiUrl = rtrim(getenv('AI_SERVICE_URL') ?: config('services.ai_anti_cheat.url', 'http://127.0.0.1:8001'), '/');
$report = ['endpoints' => [], 'issues' => []];

function record(array &$report, string $name, int $status, $body, bool $pass = true, ?string $note = null): void
{
    $json = is_string($body) ? json_decode($body, true) : $body;
    $report['endpoints'][] = [
        'name' => $name,
        'status' => $status,
        'pass' => $pass,
        'note' => $note,
        'sample' => is_array($json) ? array_slice($json, 0, 8, true) : $body,
    ];
    if (! $pass) {
        $report['issues'][] = "{$name}: HTTP {$status}".($note ? " ({$note})" : '');
    }
}

function httpJson(string $method, string $url, ?array $json = null, ?string $token = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer '.$token;
    }
    if ($json !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => $body ?: '', 'json' => json_decode($body ?: '', true)];
}

function makeTestImage(): string
{
    $path = storage_path('app/verify_frame_'.uniqid('', true).'.jpg');
    $im = imagecreatetruecolor(640, 480);
    $bg = imagecolorallocate($im, 200, 180, 160);
    imagefilledrectangle($im, 0, 0, 640, 480, $bg);
    imagejpeg($im, $path);
    imagedestroy($im);

    return $path;
}

function multipartFrame(string $url, string $token, array $fields, string $imagePath): array
{
    $ch = curl_init($url);
    $fields['image'] = new CURLFile($imagePath, 'image/jpeg', 'frame.jpg');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer '.$token,
        ],
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => $body ?: '', 'json' => json_decode($body ?: '', true)];
}

echo "=== Backend + AI Verification ===\n\n";

// ── FastAPI direct ───────────────────────────────────────────────────────────
$health = httpJson('GET', "{$aiUrl}/health");
record($report, 'FastAPI GET /health', $health['status'], $health['body'], $health['status'] === 200);

$imgPath = makeTestImage();
$ch = curl_init("{$aiUrl}/frame");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => [
        'session_id' => 'verify-direct-'.time(),
        'image' => new CURLFile($imgPath, 'image/jpeg', 'frame.jpg'),
    ],
    CURLOPT_TIMEOUT => 30,
]);
$frameDirectBody = curl_exec($ch);
$frameDirectStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$frameDirectJson = json_decode($frameDirectBody ?: '', true);
$frameFieldsOk = isset($frameDirectJson['yolo'], $frameDirectJson['gaze'], $frameDirectJson['anti_cheat']['status']);
record(
    $report,
    'FastAPI POST /frame',
    $frameDirectStatus,
    $frameDirectBody,
    $frameDirectStatus === 200 && $frameFieldsOk,
    $frameFieldsOk ? null : 'Missing yolo/gaze/anti_cheat fields'
);

// ── Auth ─────────────────────────────────────────────────────────────────────
$instructorLogin = httpJson('POST', "{$baseUrl}/api/login", [
    'email' => 'instructor@test.com',
    'password' => 'password',
]);
record($report, 'Laravel POST /api/login (instructor)', $instructorLogin['status'], $instructorLogin['body'], $instructorLogin['status'] === 200);
$instructorToken = $instructorLogin['json']['data']['token'] ?? null;

$studentLogin = httpJson('POST', "{$baseUrl}/api/login", [
    'email' => 'student@test.com',
    'password' => 'password',
]);
record($report, 'Laravel POST /api/login (student)', $studentLogin['status'], $studentLogin['body'], $studentLogin['status'] === 200);
$studentToken = $studentLogin['json']['data']['token'] ?? null;

if (! $instructorToken || ! $studentToken) {
    echo "Missing tokens. Run: php artisan migrate:fresh --seed\n";
    exit(1);
}

// ── Setup course + exam (active window) ──────────────────────────────────────
$course = httpJson('POST', "{$baseUrl}/api/courses", [
    'name' => 'Verify Course '.time(),
    'code' => 'V'.time(),
    'description' => 'Backend verification',
    'credit_hours' => 3,
], $instructorToken);
record($report, 'Laravel POST /api/courses', $course['status'], $course['body'], $course['status'] === 201);
$courseId = $course['json']['data']['id'] ?? null;
$joinCode = $course['json']['data']['join_code'] ?? null;

$join = httpJson('POST', "{$baseUrl}/api/courses/join", ['join_code' => $joinCode], $studentToken);
record($report, 'Laravel POST /api/courses/join', $join['status'], $join['body'], in_array($join['status'], [200, 201], true));

$exam = httpJson('POST', "{$baseUrl}/api/exams", [
    'title' => 'Verify Exam '.time(),
    'course_id' => $courseId,
    'total_marks' => 10,
    'duration' => 90,
    'start_time' => date('Y-m-d H:i:s', time() - 3600),
    'end_time' => date('Y-m-d H:i:s', time() + 3600),
], $instructorToken);
record($report, 'Laravel POST /api/exams', $exam['status'], $exam['body'], $exam['status'] === 201);
$examId = $exam['json']['data']['id'] ?? null;

$question = httpJson('POST', "{$baseUrl}/api/exams/{$examId}/questions", [
    'question_text' => 'Verification MCQ',
    'type' => 'mcq',
    'mark' => 10,
    'options' => [
        ['text' => 'Correct', 'is_correct' => true],
        ['text' => 'Wrong', 'is_correct' => false],
    ],
], $instructorToken);
record($report, 'Laravel POST /api/exams/{exam}/questions', $question['status'], $question['body'], $question['status'] === 201);
$questionId = $question['json']['data']['id'] ?? null;
$correctOptionId = collect($question['json']['data']['options'] ?? [])->firstWhere('is_correct', true)['id'] ?? null;

// ── Full exam flow ───────────────────────────────────────────────────────────
$start = httpJson('POST', "{$baseUrl}/api/exams/{$examId}/start", null, $studentToken);
record($report, 'Laravel POST /api/exams/{exam}/start', $start['status'], $start['body'], $start['status'] === 200);
$sessionId = $start['json']['exam_student_id'] ?? null;

$checkFrame = multipartFrame("{$baseUrl}/api/exam/check-frame", $studentToken, [
    'exam_id' => $examId,
    'session_id' => (string) $sessionId,
], $imgPath);
$laravelAiFields = isset(
    $checkFrame['json']['data']['anti_cheat']['status'],
    $checkFrame['json']['data']['can_continue'],
    $checkFrame['json']['data']['violation_recorded']
);
record(
    $report,
    'Laravel POST /api/exam/check-frame',
    $checkFrame['status'],
    $checkFrame['body'],
    $checkFrame['status'] === 200 && $laravelAiFields,
    $laravelAiFields ? null : 'Missing can_continue / violation_recorded / anti_cheat'
);

$violationsBefore = httpJson('GET', "{$baseUrl}/api/exams/{$examId}/violations", null, $instructorToken);
record($report, 'Laravel GET /api/exams/{exam}/violations', $violationsBefore['status'], $violationsBefore['body'], $violationsBefore['status'] === 200);

$submit = httpJson('POST', "{$baseUrl}/api/exams/{$examId}/submit", [
    'answers' => [
        ['question_id' => $questionId, 'option_id' => $correctOptionId, 'answer_text' => null],
    ],
], $studentToken);
record($report, 'Laravel POST /api/exams/{exam}/submit', $submit['status'], $submit['body'], $submit['status'] === 200);

$result = httpJson('GET', "{$baseUrl}/api/exams/{$examId}/result", null, $studentToken);
record($report, 'Laravel GET /api/exams/{exam}/result', $result['status'], $result['body'], $result['status'] === 200);

@unlink($imgPath);

// ── Offline AI (Laravel kernel + unreachable AI URL) ───────────────────────────
config(['services.ai_anti_cheat.url' => 'http://127.0.0.1:59999']);
$student = App\Models\User::where('email', 'student@test.com')->first();
$offlineExam2 = App\Models\Exam::create([
    'title' => 'Offline Verify '.time(),
    'course_id' => $courseId,
    'instructor_id' => App\Models\User::where('email', 'instructor@test.com')->value('id'),
    'total_marks' => 5,
    'duration' => 60,
    'start_time' => now()->subHour(),
    'end_time' => now()->addHour(),
]);
$offlineExamStudent2 = App\Models\ExamStudent::create([
    'exam_id' => $offlineExam2->id,
    'student_id' => $student->id,
    'started_at' => now(),
]);
$imgPath2 = makeTestImage();
$offlineKernel = app()->handle(
    Illuminate\Http\Request::create('/api/exam/check-frame', 'POST', [
        'exam_id' => $offlineExam2->id,
        'session_id' => (string) $offlineExamStudent2->id,
    ], [], [
        'image' => new Illuminate\Http\UploadedFile($imgPath2, 'frame.jpg', 'image/jpeg', null, true),
    ], [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$studentToken,
    ])
);
@unlink($imgPath2);
record(
    $report,
    'Laravel POST /api/exam/check-frame (AI offline)',
    $offlineKernel->getStatusCode(),
    $offlineKernel->getContent(),
    $offlineKernel->getStatusCode() === 503,
    'Expected 503 when AI unreachable'
);
config(['services.ai_anti_cheat.url' => $aiUrl]);

// ── Summary ──────────────────────────────────────────────────────────────────
$passed = count(array_filter($report['endpoints'], fn ($e) => $e['pass']));
$total = count($report['endpoints']);

echo "\nResults: {$passed}/{$total} passed\n\n";
foreach ($report['endpoints'] as $entry) {
    $mark = $entry['pass'] ? '✓' : '✗';
    echo "{$mark} {$entry['name']} => HTTP {$entry['status']}\n";
}

$reportPath = $root.'/storage/app/backend-ai-verification-report.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "\nReport saved: {$reportPath}\n";

if ($passed !== $total) {
    exit(1);
}

echo "\n=== All backend + AI checks passed ===\n";
