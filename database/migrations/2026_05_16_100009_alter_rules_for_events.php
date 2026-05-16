<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rules')->delete();

        Schema::table('rules', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });

        Schema::table('rules', function (Blueprint $table) {
            $table->dropColumn('organization_id');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('rules')->delete();

        Schema::table('rules', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
        });

        Schema::table('rules', function (Blueprint $table) {
            $table->dropColumn('event_id');
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
        });
    }
};
