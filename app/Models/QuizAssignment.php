<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAssignment extends Model
{
    protected $fillable = [
        'quiz_id',
        'deadline',
        'host',
    ];

    protected $casts = [
        'deadline' => 'datetime',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function participants()
    {
        return $this->hasMany(QuizParticipant::class, 'assignment_id');
    }
}
