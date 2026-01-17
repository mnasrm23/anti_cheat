<?php

namespace Database\Factories;

use App\Models\Option;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Option>
 */
class OptionFactory extends Factory
{
    protected $model = Option::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $options = [
            'To encapsulate data and behavior',
            'To improve code reusability',
            'To enhance maintainability',
            'All of the above',
            'Stack',
            'Queue',
            'Linked List',
            'Array',
            'Structured Query Language',
            'Simple Query Language',
            'Standard Query Language',
            'Sequential Query Language',
            'Merge Sort',
            'Quick Sort',
            'Bubble Sort',
            'Linear Search',
            'True',
            'False',
        ];

        return [
            'question_id' => Question::factory(),
            'option_text' => fake()->randomElement($options),
            'is_correct' => false, // Default to false, will be set by seeder
        ];
    }

    /**
     * Indicate that this option is correct.
     */
    public function correct(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => true,
        ]);
    }

    /**
     * Indicate that this option is incorrect.
     */
    public function incorrect(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => false,
        ]);
    }
}
