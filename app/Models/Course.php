<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
   protected $fillable = [
        'name','code','join_code','description','instructor_id','credit_hours'
    ];

    public function instructor()
    {
        return $this->belongsTo(User::class,'instructor_id');
    }

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function enrolledStudents()
    {
        return $this->belongsToMany(User::class, 'course_student', 'course_id', 'student_id')
            ->withTimestamps();
    }

    public static function generateJoinCode()
    {
        do {
            $code = strtoupper(str()->random(8));
        } while (static::where('join_code', $code)->exists());
        return $code;
    }
}
