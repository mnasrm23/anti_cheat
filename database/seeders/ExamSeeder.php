<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Exam;
use Illuminate\Database\Seeder;

class ExamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courses = Course::all();

        if ($courses->isEmpty()) {
            $this->command->warn('No courses found. Please run CourseSeeder first.');
            return;
        }

        $examTypes = ['Midterm Exam', 'Final Exam', 'Quiz 1', 'Quiz 2', 'Practice Test'];
        $examCount = 0;

        foreach ($courses as $course) {
            // Create 3 exams per course: one past, one current/active, one future
            
            // Past Exam
            $pastDays = rand(7, 30);
            Exam::create([
                'title' => 'Midterm Exam',
                'course_id' => $course->id,
                'instructor_id' => $course->instructor_id,
                'total_marks' => 100,
                'duration' => 120,
                'start_time' => now()->subDays($pastDays),
                'end_time' => now()->subDays($pastDays)->addMinutes(120),
            ]);
            $examCount++;

            // Current/Active Exam (available now)
            Exam::create([
                'title' => 'Quiz 1',
                'course_id' => $course->id,
                'instructor_id' => $course->instructor_id,
                'total_marks' => 50,
                'duration' => 60,
                'start_time' => now()->subHours(1),
                'end_time' => now()->addHours(3),
            ]);
            $examCount++;

            // Future Exam
            $futureDays = rand(5, 20);
            Exam::create([
                'title' => 'Final Exam',
                'course_id' => $course->id,
                'instructor_id' => $course->instructor_id,
                'total_marks' => 100,
                'duration' => 180,
                'start_time' => now()->addDays($futureDays),
                'end_time' => now()->addDays($futureDays)->addMinutes(180),
            ]);
            $examCount++;
        }

        $this->command->info("Created {$examCount} exams successfully!");
    }
}
