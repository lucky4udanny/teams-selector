<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Member;
use App\Models\TeamDraft;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDraftFromCsv extends Command
{
    protected $signature = 'draft:import-csv
                            {event : Event ID to import into}
                            {csv : Path to the CSV file}
                            {--user= : User ID to attribute the draft/finalization to (defaults to first user)}
                            {--draft-name= : Name for the imported draft (default: "Imported from CSV")}
                            {--force : Allow running in production}';

    protected $description = '[Testing] Import a finalized team/group assignment CSV into an event draft';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('This command is not allowed in production. Pass --force to override.');

            return self::FAILURE;
        }

        $event = Event::find($this->argument('event'));
        if (! $event instanceof Event) {
            $this->error('Event not found with ID: '.$this->argument('event'));

            return self::FAILURE;
        }

        $csvPath = $this->argument('csv');
        if (! file_exists($csvPath) || ! is_readable($csvPath)) {
            $this->error("CSV file not found or not readable: {$csvPath}");

            return self::FAILURE;
        }

        $user = $this->resolveUser();
        if ($user === null) {
            return self::FAILURE;
        }

        $rows = $this->parseCsv($csvPath);
        if ($rows === null) {
            return self::FAILURE;
        }

        $result = $this->buildState($event, $rows);
        if ($result === null) {
            return self::FAILURE;
        }

        [$state, $warnings] = $result;

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        $teamCount = count($state['teams']);
        $groupCount = count(array_filter($state['groups'], fn (array $g) => count($g['team_indices']) > 0));
        $memberCount = count($state['member_ids']);

        $this->info("Event   : {$event->name} (ID: {$event->id})");
        $this->info("Teams   : {$teamCount}");
        $this->info("Groups  : {$groupCount}");
        $this->info("Members : {$memberCount} assigned");
        $this->newLine();

        if (! $this->confirm('Create a finalized draft and mark this event as finalized?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $draftName = $this->option('draft-name') ?? 'Imported from CSV';

        DB::transaction(function () use ($event, $state, $draftName, $user): void {
            TeamDraft::query()->where('event_id', $event->id)->update(['is_final' => false]);

            $draft = TeamDraft::create([
                'event_id' => $event->id,
                'created_by' => $user->id,
                'name' => $draftName,
                'state' => $state,
                'is_final' => true,
            ]);

            $event->update([
                'final_team_draft_id' => $draft->id,
                'finalized_at' => now(),
                'finalized_by' => $user->id,
                'uses_groups' => count($state['groups']) > 0,
            ]);
        });

        $this->info("Done! Event \"{$event->name}\" is now finalized.");

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $userId = $this->option('user');

        if ($userId !== null) {
            $user = User::find($userId);
            if (! $user instanceof User) {
                $this->error("User ID {$userId} not found.");

                return null;
            }

            return $user;
        }

        $user = User::first();
        if (! $user instanceof User) {
            $this->error('No users exist. Create a user before importing.');

            return null;
        }

        $this->line("Using user: {$user->name} (ID: {$user->id})");

        return $user;
    }

    /**
     * Parse CSV, skip blank rows, detect headers on the first non-blank row.
     *
     * @return list<array<string, string>>|null
     */
    private function parseCsv(string $path): ?array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error('Could not open CSV file.');

            return null;
        }

        $headers = null;
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            $nonBlank = array_filter($line, fn (string $v) => trim($v) !== '');
            if ($nonBlank === []) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn (string $h) => strtolower(trim($h)), $line);
                $this->line('Headers detected: '.implode(', ', $headers));
                continue;
            }

            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = isset($line[$i]) ? trim((string) $line[$i]) : '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        if ($headers === null) {
            $this->error('Could not detect CSV headers — file may be empty.');

            return null;
        }

        $this->line(count($rows).' data rows read.');

        return $rows;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{0: array<string, mixed>, 1: list<string>}|null
     */
    private function buildState(Event $event, array $rows): ?array
    {
        $sampleRow = $rows[0] ?? [];
        $teamCol = null;
        $groupCol = null;

        foreach (array_keys($sampleRow) as $col) {
            if ($teamCol === null && str_contains($col, 'team')) {
                $teamCol = $col;
            }
            if ($groupCol === null && str_contains($col, 'group')) {
                $groupCol = $col;
            }
        }

        if ($teamCol === null) {
            $this->error('No column containing "team" found in CSV headers.');

            return null;
        }

        $this->line("Team column  : \"{$teamCol}\"");
        $this->line('Group column : '.($groupCol !== null ? "\"{$groupCol}\"" : '(none — event will have no groups)'));
        $this->newLine();

        $orgMembers = Member::query()
            ->where('organization_id', $event->organization_id)
            ->get(['id', 'first_name', 'last_name']);

        $memberIndex = [];
        foreach ($orgMembers as $m) {
            $key = strtolower(trim($m->first_name)).'|'.strtolower(trim($m->last_name));
            $memberIndex[$key] = $m->id;
        }

        /** @var array<string, list<int>> $teamMembers  teamLabel => memberIds */
        $teamMembers = [];
        /** @var array<string, list<string>> $groupTeams  groupLabel => teamLabels */
        $groupTeams = [];
        $warnings = [];

        foreach ($rows as $row) {
            $firstName = $row['first_name'] ?? '';
            $lastName = $row['last_name'] ?? '';
            $teamLabel = trim($row[$teamCol] ?? '');
            $groupLabel = $groupCol !== null ? trim($row[$groupCol] ?? '') : '';

            if ($firstName === '' && $lastName === '') {
                continue;
            }

            if ($teamLabel === '') {
                continue;
            }

            $key = strtolower($firstName).'|'.strtolower($lastName);
            if (! isset($memberIndex[$key])) {
                $warnings[] = "Member not found in org: \"{$firstName} {$lastName}\" — skipped.";
                continue;
            }

            $memberId = $memberIndex[$key];

            if (! isset($teamMembers[$teamLabel])) {
                $teamMembers[$teamLabel] = [];
            }
            if (! in_array($memberId, $teamMembers[$teamLabel], true)) {
                $teamMembers[$teamLabel][] = $memberId;
            }

            if ($groupLabel !== '' && $groupCol !== null) {
                if (! isset($groupTeams[$groupLabel])) {
                    $groupTeams[$groupLabel] = [];
                }
                if (! in_array($teamLabel, $groupTeams[$groupLabel], true)) {
                    $groupTeams[$groupLabel][] = $teamLabel;
                }
            }
        }

        if ($teamMembers === []) {
            $this->error('No team assignments found in CSV (all team cells are blank?).');

            return null;
        }

        // Sort teams numerically if all labels are numeric, otherwise alphabetically
        $allNumeric = array_reduce(array_keys($teamMembers), fn (bool $c, string $l) => $c && is_numeric($l), true);
        uksort($teamMembers, $allNumeric
            ? fn (string $a, string $b) => (int) $a - (int) $b
            : fn (string $a, string $b) => strcmp($a, $b)
        );

        $teams = [];
        $teamLabelToIndex = [];
        $teamNames = [];

        foreach ($teamMembers as $label => $memberIds) {
            $teamLabelToIndex[$label] = count($teams);
            $teams[] = ['member_ids' => $memberIds];
            $teamNames[] = (string) $label;
        }

        ksort($groupTeams);

        $groups = [];
        $groupNames = [];

        foreach ($groupTeams as $gLabel => $teamLabels) {
            $indices = [];
            foreach ($teamLabels as $tLabel) {
                if (isset($teamLabelToIndex[$tLabel])) {
                    $indices[] = $teamLabelToIndex[$tLabel];
                }
            }
            sort($indices);
            $groups[] = ['team_indices' => $indices];
            $groupNames[] = (string) $gLabel;
        }

        $allMemberIds = array_values(array_unique(array_merge(...array_values($teamMembers))));

        $state = [
            'teams' => $teams,
            'groups' => $groups,
            'violations' => [],
            'total_penalty' => 0,
            'blocking_errors' => [],
            'conflicts' => [],
            'member_ids' => $allMemberIds,
            'team_names' => $teamNames,
            'group_names' => $groupNames,
        ];

        return [$state, $warnings];
    }
}
