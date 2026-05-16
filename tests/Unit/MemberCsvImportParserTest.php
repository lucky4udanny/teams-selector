<?php

namespace Tests\Unit;

use App\Services\MemberCsvImportParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MemberCsvImportParserTest extends TestCase
{
    private MemberCsvImportParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MemberCsvImportParser;
    }

    public function test_parses_comma_utf8_csv(): void
    {
        $csv = "company,email,first_name,last_name\nAcme,a@x.com,Ada,Lovelace\n";

        $result = $this->parser->parseCsvBytes($csv);

        $this->assertSame('first_name', $result['normalized_header'][2]);
        $this->assertCount(1, $result['rows']);
        $this->assertSame(2, $result['indices']['first']);
        $this->assertSame(3, $result['indices']['last']);
    }

    public function test_parses_semicolon_csv(): void
    {
        $csv = "company;email;first_name;last_name\nAcme;a@x.com;Ada;Lovelace\n";

        $result = $this->parser->parseCsvBytes($csv);

        $this->assertSame('Ada', $result['rows'][0][$result['indices']['first']]);
        $this->assertNotNull($result['indices']['last']);
    }

    public function test_parses_utf16_le_with_bom(): void
    {
        $line = "company,sector,email,first_name,last_name,2024 team\nAcme,Eng,a@x.com,Ada,Lovelace,T1\n";
        $utf16 = "\xFF\xFE".mb_convert_encoding($line, 'UTF-16LE', 'UTF-8');

        $result = $this->parser->parseCsvBytes($utf16);

        $this->assertNotNull($result['indices']['first']);
        $this->assertSame('Ada', $result['rows'][0][$result['indices']['first']]);
    }

    #[DataProvider('headerLabelProvider')]
    public function test_accepts_spaced_header_labels(string $headerLine): void
    {
        $csv = $headerLine."\nAcme,,bob@example.com,Bob,Smith\n";

        $result = $this->parser->parseCsvBytes($csv);

        $this->assertNotNull($result['indices']['first']);
        $this->assertNotNull($result['indices']['last']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function headerLabelProvider(): array
    {
        return [
            'underscores' => ['company,email,first_name,last_name'],
            'spaces' => ['company,email,First Name,Last Name'],
        ];
    }

    public function test_skips_leading_blank_comma_row_before_header(): void
    {
        $csv = implode("\r\n", [
            ',,,,,,,,',
            'company,sector,email,first_name,last_name,2024 team,2024 group',
            'Acme,Eng,a@x.com,Aaron,Dyck,1,A',
        ]);

        $result = $this->parser->parseCsvBytes($csv);

        $this->assertNotNull($result['indices']['first']);
        $this->assertSame('Aaron', $result['rows'][0][$result['indices']['first']]);
        $this->assertSame('Dyck', $result['rows'][0][$result['indices']['last']]);
    }

    public function test_throws_when_name_columns_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('first_name or last_name');

        $this->parser->parseCsvBytes("company,email\nAcme,a@x.com\n");
    }
}
