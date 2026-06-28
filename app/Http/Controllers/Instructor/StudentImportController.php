<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentImportController extends Controller
{
    public function import(Request $request, Course $course)
    {
        // Check ownership
        if ($course->instructor_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getPathname(), 'r');
        $header = fgetcsv($handle); // Skip header row
        $importedCount = 0;
        $skippedCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            // Expected CSV format: name, email, password (optional)
            $name = $row[0] ?? null;
            $email = $row[1] ?? null;
            $password = $row[2] ?? Str::random(12);

            // Validate email format
            if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skippedCount++;
                continue;
            }

            // Check if user exists and is an instructor
            $existingUser = User::where('email', $email)->first();
            if ($existingUser && $existingUser->role === 'instructor') {
                $skippedCount++;
                continue;
            }

            // Find or create user
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'role' => 'student',
                ]
            );

            // Enroll in course if not already enrolled
            if (!$course->enrolledStudents()->where('student_id', $user->id)->exists()) {
                $course->enrolledStudents()->attach($user->id);
                $importedCount++;
            } else {
                $skippedCount++;
            }
        }

        fclose($handle);

        return response()->json([
            'status' => true,
            'message' => 'Import completed',
            'data' => [
                'imported_count' => $importedCount,
                'skipped_count' => $skippedCount
            ]
        ], 200);
    }
}
