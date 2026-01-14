<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
   protected $fillable = [
        'name','code','description','instructor_id','credit_hours'
    ];

    public function instructor()
    {
        return $this->belongsTo(User::class,'instructor_id');
    }

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }
}
