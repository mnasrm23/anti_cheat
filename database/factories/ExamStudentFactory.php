<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExamStudent>
 */
class ExamStudentFactory extends Factory
{
    protected $model = ExamStudent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $exam = Exam::factory()->create();
        $startedAt = fake()->dateTimeBetween($exam->start_time, $exam->end_time);
        
        // 70% chance the exam is submitted, 30% still in progress
        $isSubmitted = fake()->boolean(70);
        
        return [
            'exam_id' => $exam->id,
            'student_id' => User::factory()->student(),
            'started_at' => $startedAt,
            'submitted_at' => $isSubmitted ? fake()->dateTimeBetween($startedAt, $exam->end_time) : null,
            'score' => $isSubmitted ? fake()->numberBetween(0, $exam->total_marks) : null,
        ];
    }

    /**
     * Indicate that the exam is submitted.
     */
    public function submitted(): static
    {
        return $this->state(function (array $attributes) {
            $exam = Exam::find($attributes['exam_id']);
            $startedAt = $attributes['started_at'];
            
            return [
                'submitted_at' => fake()->dateTimeBetween($startedAt, $exam->end_time),
                'score' => fake()->numberBetween(0, $exam->total_marks),
            ];
        });
    }

    /**
     * Indicate that the exam is in progress (not submitted).
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_at' => null,
            'score' => null,
        ]);
    }

    /**
     * Set a specific score for the exam.
     */
    public function withScore(int $score): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => $score,
        ]);
    }
}
