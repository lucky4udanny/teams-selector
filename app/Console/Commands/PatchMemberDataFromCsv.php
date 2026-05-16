<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Organization;
use Illuminate\Console\Command;

class PatchMemberDataFromCsv extends Command
{
    protected $signature = 'members:patch-from-csv
                            {org : Organization ID or slug}
                            {csv : Path to the CSV file}
                            {--dry-run : Show what would change without writing to the database}
                            {--force : Allow running in production}';

    protected $description = '[Tooling] Patch member sector and company from a CSV file (matches by first + last name)';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('This command is not allowed in production. Pass --force to override.');

            return self::FAILURE;
        }

        $orgArg = $this->argument('org');
        $org = is_numeric($orgArg)
            ? Organization::find($orgArg)
            : Organization::where('slug', $orgArg)->first();

        if (! $org instanceof Organization) {
            $this->error("Organization not found: {$orgArg}");

            return self::FAILURE;
        }

        $csvPath = $this->argument('csv');
        if (! file_exists($csvPath) || ! is_readable($csvPath)) {
            $this->error("CSV file not found or not readable: {$csvPath}");

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        $rows = $this->parseCsv($csvPath);
        if ($rows === null) {
            return self::FAILURE;
        }

        /** @var array<string, int> $sectorsByName  lowercase name → id */
        $sectorsByName = $org->sectors()
            ->get()
            ->mapWithKeys(fn ($s) => [mb_strtolower($s->name) => $s->id])
            ->all();

        $members = Member::query()
            ->where('organization_id', $org->id)
            ->get(['id', 'first_name', 'last_name', 'sector_id', 'company']);

        $memberIndex = [];
        foreach ($members as $m) {
            $key = mb_strtolower(trim($m->first_name)).'|'.mb_strtolower(trim((string) $m->last_name));
            $memberIndex[$key] = $m;
        }

        $updated = 0;
        $notFound = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $first   = trim($row['first_name'] ?? '');
            $last    = trim($row['last_name'] ?? '');
            $sector  = trim($row['sector'] ?? '');
            $company = trim($row['company'] ?? '');

            if ($first === '' && $last === '') {
                continue;
            }

            $key = mb_strtolower($first).'|'.mb_strtolower($last);

            if (! isset($memberIndex[$key])) {
                $this->warn("  Not found: \"{$first} {$last}\"");
                $notFound++;

                continue;
            }

            /** @var Member $member */
            $member = $memberIndex[$key];

            $changes = [];

            if ($sector !== '') {
                $sectorKey = mb_strtolower($sector);
                if (! isset($sectorsByName[$sectorKey])) {
                    if (! $isDryRun) {
                        $newSector = $org->sectors()->create(['name' => $sector]);
                        $sectorsByName[$sectorKey] = $newSector->id;
                        $this->line("  Created sector: \"{$sector}\"");
                    } else {
                        $this->line("  [dry-run] Would create sector: \"{$sector}\"");
                        $sectorsByName[$sectorKey] = 0;
                    }
                }

                $newSectorId = $sectorsByName[$sectorKey];
                if ($member->sector_id !== $newSectorId) {
                    $changes['sector_id'] = $newSectorId;
                }
            }

            if ($company !== '' && $member->company !== $company) {
                $changes['company'] = $company;
            }

            if ($changes === []) {
                $skipped++;

                continue;
            }

            $changeDesc = collect($changes)
                ->map(fn ($v, $k) => "{$k}: \"{$v}\"")
                ->implode(', ');

            $this->line("  {$first} {$last} → {$changeDesc}");

            if (! $isDryRun) {
                $member->update($changes);
            }

            $updated++;
        }

        $this->newLine();
        $this->info("Done.  Updated: {$updated}  |  Already correct / skipped: {$skipped}  |  Not found in org: {$notFound}");

        return self::SUCCESS;
    }

    /**
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
            $nonBlank = array_filter($line, fn ($v) => trim((string) $v) !== '');
            if ($nonBlank === []) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $line);
                $this->line('Headers: '.implode(', ', $headers));
                $this->newLine();
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

        return $rows;
    }
}
