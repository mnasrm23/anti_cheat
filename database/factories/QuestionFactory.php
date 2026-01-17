<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['mcq', 'true_false', 'text']);
        
        $questions = [
            'mcq' => [
                'What is the primary purpose of object-oriented programming?',
                'Which data structure uses LIFO principle?',
                'What does SQL stand for?',
                'Which algorithm has O(n log n) time complexity?',
                'What is the output of the following code snippet?',
                'Which design pattern is used for creating objects?',
                'What is the difference between abstract class and interface?',
                'Which HTTP method is idempotent?',
            ],
            'true_false' => [
                'Python is a compiled programming language.',
                'Arrays in JavaScript are zero-indexed.',
                'HTTP is a stateless protocol.',
                'Binary search works on unsorted arrays.',
                'CSS stands for Cascading Style Sheets.',
                'A stack follows FIFO principle.',
                'JSON is a data interchange format.',
                'Git is a distributed version control system.',
            ],
            'text' => [
                'Explain the concept of polymorphism in object-oriented programming.',
                'Describe the difference between TCP and UDP protocols.',
                'What are the advantages of using a database index?',
                'Explain the MVC architectural pattern.',
                'Describe the process of normalization in databases.',
                'What is the purpose of dependency injection?',
                'Explain how garbage collection works.',
                'Describe the CAP theorem in distributed systems.',
            ],
        ];

        return [
            'exam_id' => Exam::factory(),
            'question_text' => fake()->randomElement($questions[$type]),
            'type' => $type,
            'mark' => fake()->randomElement([1, 2, 3, 5, 10]),
        ];
    }

    /**
     * Indicate that the question is multiple choice.
     */
    public function mcq(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'mcq',
        ]);
    }

    /**
     * Indicate that the question is true/false.
     */
    public function trueFalse(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'true_false',
        ]);
    }

    /**
     * Indicate that the question is text-based.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
        ]);
    }
}
