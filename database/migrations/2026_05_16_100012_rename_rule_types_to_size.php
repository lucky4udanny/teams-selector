<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // team_size rules: config already uses 'size', just rename the type
        DB::statement("UPDATE rules SET type = 'size' WHERE type = 'team_size'");

        // group_size rules: rename config key 'teams_per_group' → 'size', then rename the type
        DB::statement("
            UPDATE rules
            SET
                config = (config::jsonb - 'teams_per_group')
                         || jsonb_build_object('size', (config::jsonb->>'teams_per_group')::integer),
                type = 'size'
            WHERE type = 'group_size'
              AND config::jsonb ? 'teams_per_group'
        ");

        // Catch any group_size rows whose config already lacked teams_per_group
        DB::statement("UPDATE rules SET type = 'size' WHERE type = 'group_size'");
    }

    public function down(): void
    {
        // Restore team-scoped size rules
        DB::statement("UPDATE rules SET type = 'team_size' WHERE type = 'size' AND scope = 'team'");

        // Restore group-scoped size rules and rename config key back
        DB::statement("
            UPDATE rules
            SET
                config = (config::jsonb - 'size')
                         || jsonb_build_object('teams_per_group', (config::jsonb->>'size')::integer),
                type = 'group_size'
            WHERE type = 'size' AND scope = 'group'
              AND config::jsonb ? 'size'
        ");
    }
};
