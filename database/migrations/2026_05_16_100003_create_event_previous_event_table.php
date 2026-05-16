<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_previous_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('previous_event_id')->constrained('events')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'previous_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_previous_event');
    }
};
