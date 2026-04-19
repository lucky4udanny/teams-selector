<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approved_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('selection_draft_id')->nullable()->constrained('selection_drafts')->nullOnDelete();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approved_selections');
    }
};
