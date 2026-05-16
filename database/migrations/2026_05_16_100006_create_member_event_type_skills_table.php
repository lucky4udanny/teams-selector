<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_event_type_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('event_type_id')->constrained('event_types')->cascadeOnDelete();
            $table->unsignedTinyInteger('skill_level');
            $table->timestamps();

            $table->unique(['member_id', 'event_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_event_type_skills');
    }
};
