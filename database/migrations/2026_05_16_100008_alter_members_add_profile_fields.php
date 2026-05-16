<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('organization_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('last_name');
            $table->string('company')->nullable()->after('phone');
            $table->foreignId('sector_id')->nullable()->after('company')->constrained()->nullOnDelete();
        });

        DB::statement('UPDATE members SET first_name = name');

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('name')->nullable()->after('organization_id');
        });

        $driver = Schema::getConnection()->getDriverName();
        $sql = match ($driver) {
            'sqlite' => 'UPDATE members SET name = TRIM(CASE WHEN COALESCE(last_name, \'\') = \'\' THEN first_name ELSE first_name || \' \' || last_name END)',
            'pgsql' => "UPDATE members SET name = TRIM(BOTH FROM CONCAT_WS(' ', NULLIF(TRIM(first_name), ''), NULLIF(TRIM(last_name), '')))",
            default => "UPDATE members SET name = TRIM(CONCAT_WS(' ', NULLIF(TRIM(first_name), ''), NULLIF(TRIM(last_name), '')))",
        };
        DB::statement($sql);

        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['sector_id']);
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'company',
                'sector_id',
            ]);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }
};
