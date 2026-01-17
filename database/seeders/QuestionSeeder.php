<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $exams = Exam::all();

        if ($exams->isEmpty()) {
            $this->command->warn('No exams found. Please run ExamSeeder first.');
            return;
        }

        $questionCount = 0;
        $optionCount = 0;

        foreach ($exams as $exam) {
            // Create 8-10 questions per exam
            $numQuestions = rand(8, 10);

            for ($i = 0; $i < $numQuestions; $i++) {
                // Mix of question types
                $type = $this->getQuestionType($i);
                $questionData = $this->getQuestionData($type, $i + 1);

                $question = Question::create([
                    'exam_id' => $exam->id,
                    'question_text' => $questionData['text'],
                    'type' => $type,
                    'mark' => $questionData['mark'],
                ]);
                $questionCount++;

                // Create options based on question type
                if ($type === 'mcq') {
                    $options = $this->getMcqOptions($i);
                    foreach ($options as $index => $optionText) {
                        Option::create([
                            'question_id' => $question->id,
                            'option_text' => $optionText,
                            'is_correct' => $index === 0, // First option is correct
                        ]);
                        $optionCount++;
                    }
                } elseif ($type === 'true_false') {
                    Option::create([
                        'question_id' => $question->id,
                        'option_text' => 'True',
                        'is_correct' => $i % 2 === 0, // Alternate correct answers
                    ]);
                    Option::create([
                        'question_id' => $question->id,
                        'option_text' => 'False',
                        'is_correct' => $i % 2 !== 0,
                    ]);
                    $optionCount += 2;
                }
                // Text questions don't need options
            }
        }

        $this->command->info("Created {$questionCount} questions and {$optionCount} options successfully!");
    }

    /**
     * Get question type based on index (mix of types)
     */
    private function getQuestionType(int $index): string
    {
        $types = ['mcq', 'mcq', 'mcq', 'true_false', 'true_false', 'text'];
        return $types[$index % count($types)];
    }

    /**
     * Get question data based on type
     */
    private function getQuestionData(string $type, int $number): array
    {
        $questions = [
            'mcq' => [
                'What is the time complexity of binary search?',
                'Which data structure uses LIFO principle?',
                'What does OOP stand for?',
                'Which sorting algorithm has the best average case?',
                'What is the purpose of an index in a database?',
                'Which HTTP method is used to update a resource?',
                'What is encapsulation in OOP?',
                'Which design pattern ensures only one instance exists?',
            ],
            'true_false' => [
                'Arrays in most programming languages are zero-indexed.',
                'HTTP is a stateful protocol.',
                'A binary tree can have at most two children per node.',
                'SQL injection is a type of XSS attack.',
                'REST APIs must use JSON format.',
                'A stack follows FIFO principle.',
                'Git is a centralized version control system.',
                'Primary keys can contain NULL values.',
            ],
            'text' => [
                'Explain the difference between abstract classes and interfaces.',
                'Describe how the MVC pattern works in web applications.',
                'What are the ACID properties in database transactions?',
                'Explain the concept of polymorphism with an example.',
                'Describe the difference between authentication and authorization.',
                'What is normalization and why is it important?',
            ],
        ];

        $questionTexts = $questions[$type];
        $text = $questionTexts[($number - 1) % count($questionTexts)];

        $mark = match($type) {
            'mcq' => rand(2, 5),
            'true_false' => rand(1, 2),
            'text' => rand(5, 10),
        };

        return ['text' => $text, 'mark' => $mark];
    }

    /**
     * Get MCQ options
     */
    private function getMcqOptions(int $index): array
    {
        $optionSets = [
            ['O(log n)', 'O(n)', 'O(n log n)', 'O(n²)'],
            ['Stack', 'Queue', 'Array', 'Linked List'],
            ['Object-Oriented Programming', 'Object-Oriented Protocol', 'Objective Oriented Programming', 'None of the above'],
            ['Merge Sort', 'Bubble Sort', 'Selection Sort', 'Insertion Sort'],
            ['To speed up queries', 'To slow down queries', 'To delete data', 'To create tables'],
            ['PUT', 'GET', 'POST', 'DELETE'],
            ['Hiding internal details', 'Showing all details', 'Deleting data', 'Creating objects'],
            ['Singleton', 'Factory', 'Observer', 'Strategy'],
        ];

        return $optionSets[$index % count($optionSets)];
    }
}
