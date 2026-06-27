<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizParticipant extends Model
{
    protected $fillable = [
        'quiz_id',
        'assignment_id',
        'name',
        'score',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function assignment()
    {
        return $this->belongsTo(QuizAssignment::class, 'assignment_id');
    }

    public function answers()
    {
        return $this->hasMany(QuizAnswer::class, 'participant_id');
    }
}
