<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
   protected $fillable = [
        'title','course_id','instructor_id',
        'total_marks','duration','start_time','end_time'
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor()
    {
        return $this->belongsTo(User::class,'instructor_id');
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'exam_student', 'exam_id', 'student_id')
            ->withPivot('score', 'started_at', 'submitted_at');
    }

    public function violations()
    {
        return $this->hasMany(ExamViolation::class);
    }
}
