<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'category',
        'pin',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }

    public function assignments()
    {
        return $this->hasMany(QuizAssignment::class);
    }

    public function participants()
    {
        return $this->hasMany(QuizParticipant::class);
    }
}
