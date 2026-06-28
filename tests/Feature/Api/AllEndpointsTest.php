<?php

use App\Models\Course;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->instructor = User::factory()->instructor()->create([
        'email' => 'instructor-api-' . uniqid() . '@test.com',
    ]);
    $this->student = User::factory()->student()->create([
        'email' => 'student-api-' . uniqid() . '@test.com',
    ]);
});

test('auth and profile endpoints work', function () {
    $this->postJson('/api/register', [
        'name' => 'New Student',
        'email' => 'new-student-' . uniqid() . '@test.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'student',
    ])->assertCreated()->assertJsonPath('status', true);

    $this->postJson('/api/login', [
        'email' => $this->instructor->email,
        'password' => 'password',
    ])->assertOk()->assertJsonPath('status', true);

    Sanctum::actingAs($this->instructor);

    $this->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->postJson('/api/logout')
        ->assertOk()
        ->assertJsonPath('status', true);
});

test('instructor full crud flow works', function () {
    Sanctum::actingAs($this->instructor);

    $this->getJson('/api/instructor/dashboard')
        ->assertOk()
        ->assertJsonPath('status', true);

    $courseResponse = $this->postJson('/api/courses', [
        'name' => 'API Test Course',
        'code' => 'API' . uniqid(),
        'description' => 'Test course',
        'credit_hours' => 3,
    ])->assertCreated()->assertJsonPath('status', true);

    $courseId = $courseResponse->json('data.id');
    $joinCode = $courseResponse->json('data.join_code');

    $this->getJson('/api/courses')
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->getJson("/api/courses/{$courseId}")
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->putJson("/api/courses/{$courseId}", [
        'name' => 'Updated Course',
    ])->assertOk()->assertJsonPath('status', true);

    Storage::fake('local');
    $csvContent = "name,email,password\nImported Student,imported-" . uniqid() . "@test.com,password123\n";
    $csvFile = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

    $this->post("/api/courses/{$courseId}/import-students", [
        'csv_file' => $csvFile,
    ])->assertOk()->assertJsonPath('status', true);

    $examResponse = $this->postJson('/api/exams', [
        'title' => 'API Test Exam',
        'course_id' => $courseId,
        'total_marks' => 20,
        'duration' => 60,
        'start_time' => now()->subHour()->toDateTimeString(),
        'end_time' => now()->addHours(2)->toDateTimeString(),
    ])->assertCreated()->assertJsonPath('status', true);

    $examId = $examResponse->json('data.id');

    $this->getJson('/api/exams')
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->getJson("/api/exams/{$examId}")
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->putJson("/api/exams/{$examId}", [
        'title' => 'Updated Exam',
    ])->assertOk()->assertJsonPath('status', true);

    $questionResponse = $this->postJson("/api/exams/{$examId}/questions", [
        'question_text' => 'What is 2+2?',
        'type' => 'mcq',
        'mark' => 10,
        'options' => [
            ['text' => '3', 'is_correct' => false],
            ['text' => '4', 'is_correct' => true],
        ],
    ])->assertCreated()->assertJsonPath('status', true);

    $questionId = $questionResponse->json('data.id');
    $optionId = $questionResponse->json('data.options.0.id');

    $this->getJson("/api/exams/{$examId}/questions")
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->getJson("/api/exams/{$examId}/questions/{$questionId}")
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->putJson("/api/exams/{$examId}/questions/{$questionId}", [
        'question_text' => 'Updated question',
    ])->assertOk()->assertJsonPath('status', true);

    $this->getJson("/api/exams/{$examId}/questions/{$questionId}/options")
        ->assertOk()
        ->assertJsonPath('status', true);

    $newOptionResponse = $this->postJson("/api/exams/{$examId}/questions/{$questionId}/options", [
        'option_text' => '5',
        'is_correct' => false,
    ])->assertCreated()->assertJsonPath('status', true);

    $newOptionId = $newOptionResponse->json('data.id');

    $this->getJson("/api/exams/{$examId}/questions/{$questionId}/options/{$optionId}")
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->putJson("/api/exams/{$examId}/questions/{$questionId}/options/{$newOptionId}", [
        'option_text' => 'Updated option',
    ])->assertOk()->assertJsonPath('status', true);

    $this->getJson("/api/exams/{$examId}/violations")
        ->assertOk();

    $this->deleteJson("/api/exams/{$examId}/questions/{$questionId}/options/{$newOptionId}")
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->deleteJson("/api/exams/{$examId}/questions/{$questionId}")
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->deleteJson("/api/exams/{$examId}")
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->deleteJson("/api/courses/{$courseId}")
        ->assertOk()
        ->assertJsonPath('status', true);

    expect($joinCode)->not->toBeEmpty();
});

test('student flow and notifications work', function () {
    $course = Course::create([
        'name' => 'Student Flow Course',
        'code' => 'SFC' . uniqid(),
        'description' => 'Test',
        'instructor_id' => $this->instructor->id,
        'join_code' => Course::generateJoinCode(),
        'credit_hours' => 3,
    ]);

    $exam = Exam::create([
        'title' => 'Student Flow Exam',
        'course_id' => $course->id,
        'instructor_id' => $this->instructor->id,
        'total_marks' => 10,
        'duration' => 60,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
    ]);

    $question = Question::create([
        'exam_id' => $exam->id,
        'question_text' => 'Pick one',
        'type' => 'mcq',
        'mark' => 10,
    ]);

    $correctOption = Option::create([
        'question_id' => $question->id,
        'option_text' => 'Correct',
        'is_correct' => true,
    ]);

    Option::create([
        'question_id' => $question->id,
        'option_text' => 'Wrong',
        'is_correct' => false,
    ]);

    Sanctum::actingAs($this->student);

    $this->getJson('/api/student/dashboard')
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->postJson('/api/courses/join', [
        'join_code' => $course->join_code,
    ])->assertCreated()
        ->assertJsonPath('status', true);

    $this->getJson('/api/student/courses')
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->getJson('/api/exams/available')
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->postJson("/api/exams/{$exam->id}/start")
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->postJson('/api/violations', [
        'exam_id' => $exam->id,
        'type' => 'tab_switch',
        'description' => 'Student switched tabs',
        'metadata' => ['count' => 1],
    ])->assertCreated();

    $this->postJson("/api/exams/{$exam->id}/submit", [
        'answers' => [
            [
                'question_id' => $question->id,
                'option_id' => $correctOption->id,
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('status', true);

    $this->getJson("/api/exams/{$exam->id}/result")
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('status', true);

    $notificationId = $this->student->notifications()->first()->id;

    $this->putJson("/api/notifications/{$notificationId}/read")
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->putJson('/api/notifications/mark-all-read')
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->deleteJson("/api/notifications/{$notificationId}")
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->deleteJson('/api/notifications/mark-all-read')
        ->assertOk()
        ->assertJsonPath('status', true);
});

test('instructor can view violations after student reports', function () {
    $course = Course::create([
        'name' => 'Violation Course',
        'code' => 'VC' . uniqid(),
        'instructor_id' => $this->instructor->id,
        'join_code' => Course::generateJoinCode(),
        'credit_hours' => 3,
    ]);

    $exam = Exam::create([
        'title' => 'Violation Exam',
        'course_id' => $course->id,
        'instructor_id' => $this->instructor->id,
        'total_marks' => 10,
        'duration' => 60,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
    ]);

    $course->enrolledStudents()->attach($this->student->id);

    Sanctum::actingAs($this->student);
    $this->postJson("/api/exams/{$exam->id}/start")->assertOk();
    $this->postJson('/api/violations', [
        'exam_id' => $exam->id,
        'type' => 'face_not_detected',
        'description' => 'Face not visible',
    ])->assertCreated();

    Sanctum::actingAs($this->instructor);
    $this->getJson("/api/exams/{$exam->id}/violations")
        ->assertOk()
        ->assertJsonCount(1);
});
