<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('Seeders cannot be run in production!');
            return;
        }

        $this->command->info('🌱 Starting database seeding...');

        // Create Instructors
        $this->command->info('Creating instructors...');
        $instructors = User::factory()->instructor()->count(5)->create();
        $this->command->info('✓ Created 5 instructors');

        // Create Students
        $this->command->info('Creating students...');
        $students = User::factory()->student()->count(20)->create();
        $this->command->info('✓ Created 20 students');

        // Create a test user for easy login
        User::firstOrCreate(
            ['email' => 'instructor@test.com'],
            [
                'name' => 'Test Instructor',
                'password' => 'password',
                'role' => 'instructor',
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'student@test.com'],
            [
                'name' => 'Test Student',
                'password' => 'password',
                'role' => 'student',
                'email_verified_at' => now(),
            ]
        );
        $this->command->info('✓ Created test accounts (instructor@test.com / student@test.com)');

        // Seed Courses
        $this->command->info('Seeding courses...');
        $this->call(CourseSeeder::class);

        // Seed Exams
        $this->command->info('Seeding exams...');
        $this->call(ExamSeeder::class);

        // Seed Questions and Options
        $this->command->info('Seeding questions and options...');
        $this->call(QuestionSeeder::class);

        // Create Exam Attempts (ExamStudent records)
        $this->command->info('Creating exam attempts...');
        $exams = \App\Models\Exam::where('start_time', '<=', now())->get();
        $examAttemptCount = 0;

        foreach ($exams as $exam) {
            // Random 5-10 students attempt each past/current exam
            $attemptingStudents = $students->random(rand(5, 10));

            foreach ($attemptingStudents as $student) {
                $startedAt = fake()->dateTimeBetween($exam->start_time, min($exam->end_time, now()));
                $isSubmitted = fake()->boolean(80); // 80% submitted

                \App\Models\ExamStudent::create([
                    'exam_id' => $exam->id,
                    'student_id' => $student->id,
                    'started_at' => $startedAt,
                    'submitted_at' => $isSubmitted ? fake()->dateTimeBetween($startedAt, min($exam->end_time, now())) : null,
                    'score' => $isSubmitted ? rand(0, $exam->total_marks) : null,
                ]);
                $examAttemptCount++;

                // Create answers for submitted exams
                if ($isSubmitted) {
                    $questions = $exam->questions;
                    foreach ($questions as $question) {
                        $answerData = [
                            'exam_id' => $exam->id,
                            'question_id' => $question->id,
                            'student_id' => $student->id,
                        ];

                        if ($question->type === 'text') {
                            $answerData['answer_text'] = fake()->paragraph(2);
                            $answerData['is_correct'] = fake()->boolean(60);
                        } else {
                            $options = $question->options;
                            if ($options->isNotEmpty()) {
                                $selectedOption = $options->random();
                                $answerData['option_id'] = $selectedOption->id;
                                $answerData['is_correct'] = $selectedOption->is_correct;
                            }
                        }

                        \App\Models\Answer::create($answerData);
                    }
                }
            }
        }

        $this->command->info("✓ Created {$examAttemptCount} exam attempts with answers");

        $this->command->info('');
        $this->command->info('Database seeding completed successfully!');
        $this->command->info('');
        $this->command->info('Test Accounts:');
        $this->command->info('  Instructor: instructor@test.com / password');
        $this->command->info('  Student: student@test.com / password');
    }
}
