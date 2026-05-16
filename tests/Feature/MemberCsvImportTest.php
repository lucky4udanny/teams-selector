<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MemberCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_import_members_from_csv(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Organizer->value]);

        $csv = implode("\n", [
            'first_name,last_name,email',
            'Ada,Lovelace,ada@example.com',
            'Grace,Hopper,grace@example.com',
        ]);

        $file = UploadedFile::fake()->createWithContent('members.csv', $csv);

        $response = $this->actingAs($user)->post(
            route('organizations.members.import', $org),
            ['file' => $file],
        );

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Imported 2 members.');
        $this->assertSame(2, Member::query()->where('organization_id', $org->id)->count());
        $this->assertDatabaseHas('members', [
            'organization_id' => $org->id,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
        ]);
    }

    public function test_import_accepts_semicolon_delimited_csv_with_extra_columns(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Organizer->value]);

        $csv = implode("\n", [
            'company;sector;email;first_name;last_name;2024 team;2024 group',
            'Acme;Engineering;ada@example.com;Ada;Lovelace;T1;G1',
        ]);

        $file = UploadedFile::fake()->createWithContent('members.csv', $csv);

        $response = $this->actingAs($user)->post(
            route('organizations.members.import', $org),
            ['file' => $file],
        );

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Imported 1 member.');
        $this->assertDatabaseHas('members', [
            'organization_id' => $org->id,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'company' => 'Acme',
        ]);
    }

    public function test_import_accepts_utf16_le_excel_csv(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Organizer->value]);

        $lines = [
            'company,sector,email,first_name,last_name,2024 team,2024 group,2025 team,2025 group',
            'Acme,Engineering,ada@example.com,Ada,Lovelace,T1,G1,T2,G2',
        ];
        $utf16 = "\xFF\xFE";
        foreach ($lines as $line) {
            $utf16 .= mb_convert_encoding($line."\r\n", 'UTF-16LE', 'UTF-8');
        }

        $file = UploadedFile::fake()->createWithContent('members.csv', $utf16);

        $response = $this->actingAs($user)->post(
            route('organizations.members.import', $org),
            ['file' => $file],
        );

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Imported 1 member.');
        $this->assertDatabaseHas('members', [
            'organization_id' => $org->id,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'company' => 'Acme',
        ]);
    }

    public function test_import_accepts_utf8_bom_and_spaced_header_labels(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Organizer->value]);

        $csv = "\xEF\xBB\xBFcompany,sector,email,First Name,Last Name\nAcme,,bob@example.com,Bob,Smith\n";

        $file = UploadedFile::fake()->createWithContent('members.csv', $csv);

        $response = $this->actingAs($user)->post(
            route('organizations.members.import', $org),
            ['file' => $file],
        );

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Imported 1 member.');
        $this->assertDatabaseHas('members', [
            'organization_id' => $org->id,
            'first_name' => 'Bob',
            'last_name' => 'Smith',
        ]);
    }

    public function test_import_rejects_csv_without_name_columns(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Organizer->value]);

        $file = UploadedFile::fake()->createWithContent('bad.csv', "email\nfoo@example.com\n");

        $response = $this->actingAs($user)->post(
            route('organizations.members.import', $org),
            ['file' => $file],
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('file');
        $this->assertSame(0, Member::query()->where('organization_id', $org->id)->count());
    }
}
