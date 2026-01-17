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
}
