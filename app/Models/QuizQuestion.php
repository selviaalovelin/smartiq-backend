<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    protected $fillable = [
        'quiz_id',
        'text',
        'image',
        'answers',
        'correct',
        'time_limit',
        'position',
    ];

    protected $casts = [
        'answers' => 'array',
        'time_limit' => 'integer',
        'position' => 'integer',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}
