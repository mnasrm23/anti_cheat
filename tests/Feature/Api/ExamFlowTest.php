<?php

use App\Models\Course;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->instructor = User::factory()->instructor()->create();
    $this->student = User::factory()->student()->create();

    $this->course = Course::create([
        'name' => 'Test Course',
        'code' => 'TC101',
        'description' => 'Test',
        'instructor_id' => $this->instructor->id,
        'join_code' => 'JOINCODE',
        'credit_hours' => 3,
    ]);

    $this->exam = Exam::create([
        'title' => 'Test Exam',
        'course_id' => $this->course->id,
        'instructor_id' => $this->instructor->id,
        'total_marks' => 10,
        'duration' => 60,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
    ]);

    $this->question = Question::create([
        'exam_id' => $this->exam->id,
        'question_text' => 'Pick one',
        'type' => 'mcq',
        'mark' => 10,
    ]);

    $this->correctOption = Option::create([
        'question_id' => $this->question->id,
        'option_text' => 'Correct',
        'is_correct' => true,
    ]);

    Option::create([
        'question_id' => $this->question->id,
        'option_text' => 'Wrong',
        'is_correct' => false,
    ]);

    $this->course->enrolledStudents()->attach($this->student->id);
});

test('student can list available exams', function () {
    Sanctum::actingAs($this->student);

    $this->getJson('/api/exams/available')
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonCount(1, 'data');
});

test('student can start submit and view result with auto grading', function () {
    Sanctum::actingAs($this->student);

    $this->postJson("/api/exams/{$this->exam->id}/start")
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->postJson("/api/exams/{$this->exam->id}/submit", [
        'answers' => [
            [
                'question_id' => $this->question->id,
                'option_id' => $this->correctOption->id,
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.score', 10);

    $this->getJson("/api/exams/{$this->exam->id}/result")
        ->assertOk()
        ->assertJsonPath('data.score', 10);
});

test('student cannot submit without starting exam', function () {
    Sanctum::actingAs($this->student);

    $this->postJson("/api/exams/{$this->exam->id}/submit", [
        'answers' => [
            [
                'question_id' => $this->question->id,
                'option_id' => $this->correctOption->id,
            ],
        ],
    ])
        ->assertForbidden()
        ->assertJsonPath('status', false);
});

test('student cannot submit option that does not belong to question', function () {
    Sanctum::actingAs($this->student);

    $otherQuestion = Question::create([
        'exam_id' => $this->exam->id,
        'question_text' => 'Other',
        'type' => 'mcq',
        'mark' => 5,
    ]);

    $otherOption = Option::create([
        'question_id' => $otherQuestion->id,
        'option_text' => 'Other correct',
        'is_correct' => true,
    ]);

    $this->postJson("/api/exams/{$this->exam->id}/start")->assertOk();

    $this->postJson("/api/exams/{$this->exam->id}/submit", [
        'answers' => [
            [
                'question_id' => $this->question->id,
                'option_id' => $otherOption->id,
            ],
        ],
    ])->assertStatus(422);
});

test('student role cannot access instructor courses', function () {
    Sanctum::actingAs($this->student);

    $this->getJson('/api/courses')->assertForbidden();
});

test('instructor role cannot access student dashboard', function () {
    Sanctum::actingAs($this->instructor);

    $this->getJson('/api/student/dashboard')->assertForbidden();
});
