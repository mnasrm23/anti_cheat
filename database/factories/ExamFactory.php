<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $examTypes = [
            'Midterm Exam',
            'Final Exam',
            'Quiz',
            'Practice Test',
            'Monthly Assessment',
            'Unit Test',
            'Comprehensive Exam'
        ];

        $course = Course::factory()->create();
        
        // Generate exam times - mix of past, current, and future
        $timeOffset = fake()->randomElement([
            -7, -5, -3, -1,  // Past exams (days ago)
            0,               // Today
            1, 3, 5, 7, 14   // Future exams (days ahead)
        ]);

        $startTime = now()->addDays($timeOffset)->addHours(fake()->numberBetween(8, 16));
        $duration = fake()->randomElement([60, 90, 120, 150, 180]); // minutes
        $endTime = $startTime->copy()->addMinutes($duration);

        return [
            'title' => fake()->randomElement($examTypes),
            'course_id' => $course->id,
            'instructor_id' => $course->instructor_id,
            'total_marks' => fake()->randomElement([50, 60, 70, 80, 90, 100]),
            'duration' => $duration,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }

    /**
     * Indicate that the exam is in the past.
     */
    public function past(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = now()->subDays(fake()->numberBetween(1, 30));
            $endTime = $startTime->copy()->addMinutes($attributes['duration']);
            
            return [
                'start_time' => $startTime,
                'end_time' => $endTime,
            ];
        });
    }

    /**
     * Indicate that the exam is currently active.
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = now()->subHours(1);
            $endTime = now()->addHours(2);
            
            return [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration' => 180,
            ];
        });
    }

    /**
     * Indicate that the exam is in the future.
     */
    public function future(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = now()->addDays(fake()->numberBetween(1, 30));
            $endTime = $startTime->copy()->addMinutes($attributes['duration']);
            
            return [
                'start_time' => $startTime,
                'end_time' => $endTime,
            ];
        });
    }
}
