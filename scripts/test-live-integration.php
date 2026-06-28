<?php

/**
 * Live integration test: Laravel → FastAPI → AI
 * Run: php scripts/test-live-integration.php
 */

$baseUrl = getenv('APP_URL') ?: 'http://127.0.0.1:8000';
$aiUrl = getenv('AI_SERVICE_URL') ?: 'http://127.0.0.1:8001';

function request(string $method, string $url, ?array $json = null, ?string $token = null): array
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
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
}

function ok(string $label, int $status, array $expected = []): void
{
    $pass = in_array($status, $expected ?: [200, 201], true);
    echo ($pass ? '✓' : '✗')." {$label} => HTTP {$status}\n";
    if (! $pass) {
        echo "  Response: {$status}\n";
        exit(1);
    }
}

echo "=== Live Anti-Cheat Integration Test ===\n\n";

// 1. FastAPI health
$health = request('GET', rtrim($aiUrl, '/').'/health');
ok('FastAPI GET /health', $health['status']);
echo "  ".($health['body'] ?? '')."\n\n";

// 2. Laravel login
$login = request('POST', "{$baseUrl}/api/login", [
    'email' => 'student@test.com',
    'password' => 'password',
]);
ok('Laravel POST /api/login', $login['status'], [200]);
$token = $login['json']['data']['token'] ?? null;
if (! $token) {
    echo "No token returned. Run: php artisan migrate:fresh --seed\n";
    exit(1);
}

// 3. Always create a fresh course + exam for a clean AI session
echo "  Creating fresh course + exam for this run...\n";
$instructorLogin = request('POST', "{$baseUrl}/api/login", [
    'email' => 'instructor@test.com',
    'password' => 'password',
]);
ok('Instructor login', $instructorLogin['status'], [200]);
$instructorToken = $instructorLogin['json']['data']['token'];

$course = request('POST', "{$baseUrl}/api/courses", [
    'name' => 'Live Test Course '.time(),
    'code' => 'LIVE'.time(),
    'description' => 'Auto-created for integration test',
    'credit_hours' => 3,
], $instructorToken);
ok('Instructor POST /api/courses', $course['status'], [201]);
$courseId = $course['json']['data']['id'];
$joinCode = $course['json']['data']['join_code'];

$join = request('POST', "{$baseUrl}/api/courses/join", ['join_code' => $joinCode], $token);
ok('Student POST /api/courses/join', $join['status'], [200, 201]);

$exam = request('POST', "{$baseUrl}/api/exams", [
    'title' => 'Live Proctoring Exam '.time(),
    'course_id' => $courseId,
    'total_marks' => 10,
    'duration' => 60,
    'start_time' => date('Y-m-d H:i:s', time() - 3600),
    'end_time' => date('Y-m-d H:i:s', time() + 3600),
], $instructorToken);
ok('Instructor POST /api/exams', $exam['status'], [201]);
$examId = $exam['json']['data']['id'];
echo "  Using exam_id: {$examId}\n";

// 4. Start exam
$start = request('POST', "{$baseUrl}/api/exams/{$examId}/start", null, $token);
ok('Laravel POST /api/exams/{exam}/start', $start['status']);
$sessionId = $start['json']['exam_student_id'] ?? null;
if (! $sessionId) {
    echo "No exam_student_id in start response.\n";
    exit(1);
}
echo "  exam_student_id: {$sessionId}\n";

// 5. Create test JPEG
$imgPath = sys_get_temp_dir().'/anti_cheat_test_frame.jpg';
$im = imagecreatetruecolor(640, 480);
$bg = imagecolorallocate($im, 200, 180, 160);
imagefilledrectangle($im, 0, 0, 640, 480, $bg);
imagejpeg($im, $imgPath);
imagedestroy($im);

// 6. Check frame (multipart)
$ch = curl_init("{$baseUrl}/api/exam/check-frame");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer '.$token,
    ],
    CURLOPT_POSTFIELDS => [
        'exam_id' => $examId,
        'session_id' => (string) $sessionId,
        'image' => new CURLFile($imgPath, 'image/jpeg', 'frame.jpg'),
    ],
]);
$frameBody = curl_exec($ch);
$frameStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
@unlink($imgPath);

ok('Laravel POST /api/exam/check-frame', $frameStatus);
$frameJson = json_decode($frameBody, true);
echo "  status: ".($frameJson['data']['anti_cheat']['status'] ?? 'unknown')."\n";
echo "  gaze: ".($frameJson['data']['gaze']['gaze_direction'] ?? 'unknown')."\n";
echo "  can_continue: ".json_encode($frameJson['data']['can_continue'] ?? null)."\n\n";

echo "=== All live integration checks passed ===\n";
