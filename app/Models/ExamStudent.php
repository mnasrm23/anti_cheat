<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamStudent extends Model
{
    protected $table = 'exam_student';

    protected $fillable = [
        'exam_id','student_id','score','started_at','submitted_at'
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function violations()
    {
        return $this->hasMany(ExamViolation::class);
    }
}
