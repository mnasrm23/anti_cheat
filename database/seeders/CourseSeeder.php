<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all instructors
        $instructors = User::where('role', 'instructor')->get();

        if ($instructors->isEmpty()) {
            $this->command->warn('No instructors found. Creating some instructors first...');
            $instructors = User::factory()->instructor()->count(5)->create();
        }

        // Create 10 courses distributed among instructors
        $courses = [
            ['name' => 'Introduction to Computer Science', 'code' => 'CS101', 'credit_hours' => 3],
            ['name' => 'Data Structures and Algorithms', 'code' => 'CS201', 'credit_hours' => 4],
            ['name' => 'Database Management Systems', 'code' => 'CS301', 'credit_hours' => 3],
            ['name' => 'Web Development Fundamentals', 'code' => 'WEB101', 'credit_hours' => 3],
            ['name' => 'Advanced Mathematics', 'code' => 'MATH301', 'credit_hours' => 4],
            ['name' => 'Operating Systems', 'code' => 'CS302', 'credit_hours' => 3],
            ['name' => 'Software Engineering', 'code' => 'SE401', 'credit_hours' => 4],
            ['name' => 'Artificial Intelligence', 'code' => 'AI501', 'credit_hours' => 3],
            ['name' => 'Machine Learning', 'code' => 'ML502', 'credit_hours' => 4],
            ['name' => 'Cybersecurity Essentials', 'code' => 'SEC301', 'credit_hours' => 3],
        ];

        foreach ($courses as $index => $courseData) {
            Course::create([
                'name' => $courseData['name'],
                'code' => $courseData['code'],
                'description' => "This course covers fundamental and advanced topics in {$courseData['name']}. Students will learn through lectures, assignments, and practical projects.",
                'credit_hours' => $courseData['credit_hours'],
                'instructor_id' => $instructors[$index % $instructors->count()]->id,
            ]);
        }

        $this->command->info('Created ' . count($courses) . ' courses successfully!');
    }
}
