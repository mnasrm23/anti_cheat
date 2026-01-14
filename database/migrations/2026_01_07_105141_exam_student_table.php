<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_student', function (Blueprint $table) {
    $table->id();
    $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
    $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
    $table->integer('score')->nullable();
    $table->dateTime('started_at')->nullable();
    $table->dateTime('submitted_at')->nullable();
    $table->unique(['exam_id', 'student_id']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
         {
        Schema::dropIfExists('exam_student');
    }
    }
};
