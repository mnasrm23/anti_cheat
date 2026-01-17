<?php

namespace Database\Factories;

use App\Models\Answer;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Answer>
 */
class AnswerFactory extends Factory
{
    protected $model = Answer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $question = Question::factory()->create();
        $exam = $question->exam;
        
        // Determine answer based on question type
        $answerData = [
            'exam_id' => $exam->id,
            'question_id' => $question->id,
            'student_id' => User::factory()->student(),
            'option_id' => null,
            'answer_text' => null,
            'is_correct' => null,
        ];

        if ($question->type === 'text') {
            // For text questions, provide a text answer
            $answerData['answer_text'] = fake()->paragraph(2);
            $answerData['is_correct'] = null; // Needs manual grading
        } else {
            // For MCQ and true/false, select a random option
            $options = $question->options;
            if ($options->isNotEmpty()) {
                $selectedOption = $options->random();
                $answerData['option_id'] = $selectedOption->id;
                $answerData['is_correct'] = $selectedOption->is_correct;
            }
        }

        return $answerData;
    }

    /**
     * Indicate that the answer is correct.
     */
    public function correct(): static
    {
        return $this->state(function (array $attributes) {
            $question = Question::find($attributes['question_id']);
            
            if ($question->type === 'text') {
                return [
                    'is_correct' => true,
                    'answer_text' => fake()->paragraph(2),
                ];
            } else {
                $correctOption = $question->options()->where('is_correct', true)->first();
                return [
                    'option_id' => $correctOption?->id,
                    'is_correct' => true,
                ];
            }
        });
    }

    /**
     * Indicate that the answer is incorrect.
     */
    public function incorrect(): static
    {
        return $this->state(function (array $attributes) {
            $question = Question::find($attributes['question_id']);
            
            if ($question->type === 'text') {
                return [
                    'is_correct' => false,
                    'answer_text' => fake()->sentence(),
                ];
            } else {
                $incorrectOption = $question->options()->where('is_correct', false)->first();
                return [
                    'option_id' => $incorrectOption?->id,
                    'is_correct' => false,
                ];
            }
        });
    }
}
