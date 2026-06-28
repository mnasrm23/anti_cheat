<?php

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamViolation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

function createProctoringExamContext(User $instructor, User $student): array
{
    $course = Course::create([
        'name' => 'Proctoring Course',
        'code' => 'PC'.uniqid(),
        'instructor_id' => $instructor->id,
        'join_code' => Course::generateJoinCode(),
        'credit_hours' => 3,
    ]);

    $exam = Exam::create([
        'title' => 'Proctoring Exam',
        'course_id' => $course->id,
        'instructor_id' => $instructor->id,
        'total_marks' => 10,
        'duration' => 60,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
    ]);

    $course->enrolledStudents()->attach($student->id);

    return compact('course', 'exam');
}

function fakeAiFrameResponse(array $overrides = []): array
{
    return array_replace_recursive([
        'yolo' => [
            'eye_detected' => true,
            'eye_count' => 1,
        ],
        'gaze' => [
            'face_count' => 1,
            'gaze_direction' => 'CENTER',
            'is_cheating' => false,
            'cheat_reason' => null,
            'details' => [
                'gaze_ratio_left' => 0.5,
                'gaze_ratio_right' => 0.5,
                'yaw' => 0,
                'pitch' => 0,
            ],
        ],
        'anti_cheat' => [
            'last_ok' => time(),
            'warnings' => 0,
            'status' => 'OK',
            'cheat_reason' => null,
            'cheat_log' => [],
        ],
    ], $overrides);
}

beforeEach(function () {
    config([
        'services.ai_anti_cheat.url' => 'http://127.0.0.1:8001',
        'services.ai_anti_cheat.timeout' => 10,
    ]);

    $this->instructor = User::factory()->instructor()->create([
        'email' => 'instructor-proctor-'.uniqid().'@test.com',
    ]);
    $this->student = User::factory()->student()->create([
        'email' => 'student-proctor-'.uniqid().'@test.com',
    ]);
});

test('start exam returns exam student id for proctoring session', function () {
    Sanctum::actingAs($this->student);

    ['exam' => $exam] = createProctoringExamContext($this->instructor, $this->student);

    $this->postJson("/api/exams/{$exam->id}/start")
        ->assertOk()
        ->assertJsonStructure(['exam_student_id', 'data']);
});

test('check frame requires authentication', function () {
    $this->post('/api/exam/check-frame')
        ->assertUnauthorized();
});

test('check frame requires started exam and valid session id', function () {
    Sanctum::actingAs($this->student);

    ['exam' => $exam] = createProctoringExamContext($this->instructor, $this->student);

    $image = UploadedFile::fake()->image('frame.jpg');

    $this->post('/api/exam/check-frame', [
        'exam_id' => $exam->id,
        'session_id' => '999',
        'image' => $image,
    ])->assertForbidden()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'You must start the exam before sending frames.');
});

test('check frame rejects invalid session id after exam start', function () {
    Sanctum::actingAs($this->student);

    ['exam' => $exam] = createProctoringExamContext($this->instructor, $this->student);

    $this->postJson("/api/exams/{$exam->id}/start")->assertOk();

    $image = UploadedFile::fake()->image('frame.jpg');

    $this->post('/api/exam/check-frame', [
        'exam_id' => $exam->id,
        'session_id' => 'wrong-session',
        'image' => $image,
    ])->assertForbidden()
        ->assertJsonPath('message', 'Invalid proctoring session.');
});

test('check frame proxies frame to ai service and returns json', function () {
    Http::fake([
        'http://127.0.0.1:8001/frame' => Http::response(fakeAiFrameResponse(), 200),
    ]);

    Sanctum::actingAs($this->student);

    ['exam' => $exam] = createProctoringExamContext($this->instructor, $this->student);

    $this->postJson("/api/exams/{$exam->id}/start")->assertOk();

    $examStudentId = (string) \App\Models\ExamStudent::where('exam_id', $exam->id)
        ->where('student_id', $this->student->id)
        ->value('id');

    $image = UploadedFile::fake()->image('frame.jpg');

    $this->post('/api/exam/check-frame', [
        'exam_id' => $exam->id,
        'session_id' => $examStudentId,
        'image' => $image,
    ])->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.yolo.eye_detected', true)
        ->assertJsonPath('data.anti_cheat.status', 'OK')
        ->assertJsonPath('data.can_continue', true)
        ->assertJsonPath('data.violation_recorded', false);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://127.0.0.1:8001/frame'
            && $request->method() === 'POST';
    });
});

test('check frame records violation when ai returns warning status', function () {
    Http::fake([
        'http://127.0.0.1:8001/frame' => Http::response(fakeAiFrameResponse([
            'gaze' => [
                'face_count' => 0,
                'gaze_direction' => 'NO_FACE',
                'is_cheating' => true,
                'cheat_reason' => 'no_face_detected',
            ],
            'anti_cheat' => [
                'warnings' => 1,
                'status' => 'WARNING_1',
                'cheat_reason' => 'no_face_detected',
            ],
        ]), 200),
    ]);

    Sanctum::actingAs($this->student);

    ['exam' => $exam] = createProctoringExamContext($this->instructor, $this->student);
    $this->postJson("/api/exams/{$exam->id}/start")->assertOk();

    $examStudentId = (string) \App\Models\ExamStudent::where('exam_id', $exam->id)
        ->where('student_id', $this->student->id)
        ->value('id');

    $image = UploadedFile::fake()->image('frame.jpg');

    $this->post('/api/exam/check-frame', [
        'exam_id' => $exam->id,
        'session_id' => $examStudentId,
        'image' => $image,
    ])->assertOk()
        ->assertJsonPath('data.violation_recorded', true)
        ->assertJsonPath('data.can_continue', true);

    expect(ExamViolation::where('exam_id', $exam->id)->count())->toBe(1);
});

test('check frame sets can continue false when ai returns terminated status', function () {
    Http::fake([
        'http://127.0.0.1:8001/frame' => Http::response(fakeAiFrameResponse([
            'anti_cheat' => [
                'warnings' => 2,
                'status' => 'TERMINATED',
                'cheat_reason' => 'multiple_faces_detected',
            ],
            'gaze' => [
                'face_count' => 2,
                'gaze_direction' => 'UNKNOWN',
                'is_cheating' => true,
                'cheat_reason' => 'multiple_faces_detected',
            ],
        ]), 200),
    ]);

    Sanctum::actingAs($this->student);

    ['exam' => $exam] = createProctoringExamContext($this->instructor, $this->student);
    $this->postJson("/api/exams/{$exam->id}/start")->assertOk();

    $examStudentId = (string) \App\Models\ExamStudent::where('exam_id', $exam->id)
        ->where('student_id', $this->student->id)
        ->value('id');

    $image = UploadedFile::fake()->image('frame.jpg');

    $this->post('/api/exam/check-frame', [
        'exam_id' => $exam->id,
        'session_id' => $examStudentId,
        'image' => $image,
    ])->assertOk()
        ->assertJsonPath('data.can_continue', false)
        ->assertJsonPath('data.violation_recorded', true);
});

test('check frame returns service unavailable when ai service cannot be reached', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
    });

    Sanctum::actingAs($this->student);

    ['exam' => $exam] = createProctoringExamContext($this->instructor, $this->student);
    $this->postJson("/api/exams/{$exam->id}/start")->assertOk();

    $examStudentId = (string) \App\Models\ExamStudent::where('exam_id', $exam->id)
        ->where('student_id', $this->student->id)
        ->value('id');

    $image = UploadedFile::fake()->image('frame.jpg');

    $this->post('/api/exam/check-frame', [
        'exam_id' => $exam->id,
        'session_id' => $examStudentId,
        'image' => $image,
    ])->assertStatus(503)
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'Unable to reach AI service.');
});

test('check frame returns error json when ai service responds with failure', function () {
    Http::fake([
        'http://127.0.0.1:8001/frame' => Http::response(['detail' => 'Internal error'], 500),
    ]);

    Sanctum::actingAs($this->student);

    ['exam' => $exam] = createProctoringExamContext($this->instructor, $this->student);
    $this->postJson("/api/exams/{$exam->id}/start")->assertOk();

    $examStudentId = (string) \App\Models\ExamStudent::where('exam_id', $exam->id)
        ->where('student_id', $this->student->id)
        ->value('id');

    $image = UploadedFile::fake()->image('frame.jpg');

    $this->post('/api/exam/check-frame', [
        'exam_id' => $exam->id,
        'session_id' => $examStudentId,
        'image' => $image,
    ])->assertStatus(502)
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'AI service returned an error.');
});

test('instructor can view ai recorded violations through existing endpoint', function () {
    Http::fake([
        'http://127.0.0.1:8001/frame' => Http::response(fakeAiFrameResponse([
            'anti_cheat' => [
                'warnings' => 1,
                'status' => 'WARNING_1',
                'cheat_reason' => 'no_face_detected',
            ],
            'gaze' => [
                'face_count' => 0,
                'gaze_direction' => 'NO_FACE',
                'is_cheating' => true,
                'cheat_reason' => 'no_face_detected',
            ],
        ]), 200),
    ]);

    Sanctum::actingAs($this->student);

    ['exam' => $exam] = createProctoringExamContext($this->instructor, $this->student);
    $this->postJson("/api/exams/{$exam->id}/start")->assertOk();

    $examStudentId = (string) \App\Models\ExamStudent::where('exam_id', $exam->id)
        ->where('student_id', $this->student->id)
        ->value('id');

    $image = UploadedFile::fake()->image('frame.jpg');

    $this->post('/api/exam/check-frame', [
        'exam_id' => $exam->id,
        'session_id' => $examStudentId,
        'image' => $image,
    ])->assertOk();

    Sanctum::actingAs($this->instructor);

    $this->getJson("/api/exams/{$exam->id}/violations")
        ->assertOk()
        ->assertJsonCount(1);
});
