<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_member_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_member_id')->constrained('event_members')->cascadeOnDelete();
            $table->string('status', 16);
            $table->timestamp('changed_at');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_member_status_histories');
    }
};
