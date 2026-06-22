<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('participant_id');
            $table->unsignedBigInteger('quiz_question_id');
            $table->char('selected_option', 1);
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
            $table->unique(['participant_id', 'quiz_question_id']);
            $table->foreign('participant_id')->references('id')->on('quiz_participants')->onDelete('cascade');
            $table->foreign('quiz_question_id')->references('id')->on('quiz_questions')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quiz_answers');
    }
};
