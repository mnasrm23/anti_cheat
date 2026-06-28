<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
     protected $fillable = [
        'exam_id','question_id','student_id',
        'option_id','answer_text','is_correct'
    ];

    protected $hidden = [
        'is_correct'
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function option()
    {
        return $this->belongsTo(Option::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class,'student_id');
    }
}
