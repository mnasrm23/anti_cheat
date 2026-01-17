<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subjects = [
            'Computer Science', 'Mathematics', 'Physics', 'Chemistry', 'Biology',
            'Engineering', 'Data Science', 'Artificial Intelligence', 'Web Development',
            'Mobile Development', 'Database Systems', 'Operating Systems', 'Networks',
            'Software Engineering', 'Algorithms', 'Machine Learning', 'Cybersecurity'
        ];

        $levels = ['Introduction to', 'Advanced', 'Fundamentals of', 'Applied', 'Modern'];
        
        $subject = fake()->randomElement($subjects);
        $level = fake()->randomElement($levels);
        $courseName = "{$level} {$subject}";

        // Generate unique course code (e.g., CS101, MATH201, PHYS301)
        $prefix = strtoupper(substr(preg_replace('/[^A-Z]/', '', $subject), 0, 4));
        $number = fake()->numberBetween(100, 499);
        $courseCode = $prefix . $number;

        return [
            'name' => $courseName,
            'code' => $courseCode,
            'description' => fake()->paragraph(3),
            'credit_hours' => fake()->randomElement([2, 3, 4]),
            'instructor_id' => User::factory()->instructor(),
        ];
    }
}
