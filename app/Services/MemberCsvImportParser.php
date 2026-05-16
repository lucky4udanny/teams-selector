<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

final class MemberCsvImportParser
{
    private const DELIMITERS = [',', ';', "\t"];

    /**
     * @return array{
     *     normalized_header: list<string>,
     *     rows: list<list<string>>,
     *     indices: array{first: ?int, last: ?int, email: ?int, phone: ?int, company: ?int, sector: ?int, notes: ?int}
     * }
     */
    public function parseFile(string $path, ?string $clientExtension = null): array
    {
        $extension = strtolower($clientExtension ?? pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls', 'ods'], true)) {
            return $this->parseSpreadsheet($path);
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            throw new RuntimeException('The file is empty.');
        }

        return $this->parseCsvBytes($raw);
    }

    /**
     * @return array{
     *     normalized_header: list<string>,
     *     rows: list<list<string>>,
     *     indices: array{first: ?int, last: ?int, email: ?int, phone: ?int, company: ?int, sector: ?int, notes: ?int}
     * }
     */
    public function parseCsvBytes(string $raw): array
    {
        $best = null;
        $bestScore = -1;

        foreach ($this->utf8Candidates($raw) as $utf8) {
            foreach (self::DELIMITERS as $delimiter) {
                $attempt = $this->tryParseUtf8($utf8, $delimiter);
                if ($attempt === null) {
                    continue;
                }

                $score = $this->scoreAttempt($attempt['normalized_header']);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $attempt;
                }
            }
        }

        if ($best === null || ! $this->hasNameColumns($best['normalized_header'])) {
            throw new RuntimeException(
                'Could not find first_name or last_name columns. Use a header row with name columns (any order); extra columns are fine.',
            );
        }

        $best['indices'] = $this->resolveIndices($best['normalized_header']);

        return $best;
    }

    /**
     * @return array{
     *     normalized_header: list<string>,
     *     rows: list<list<string>>,
     *     indices: array{first: ?int, last: ?int, email: ?int, phone: ?int, company: ?int, sector: ?int, notes: ?int}
     * }
     */
    private function parseSpreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, false);

        if ($matrix === []) {
            throw new RuntimeException('The spreadsheet is empty.');
        }

        $matrix = $this->trimLeadingBlankMatrixRows($matrix);
        if ($matrix === []) {
            throw new RuntimeException('The spreadsheet is empty.');
        }

        $stringMatrix = [];
        foreach ($matrix as $row) {
            if (! is_array($row)) {
                continue;
            }
            $stringMatrix[] = array_map(fn ($cell) => $this->cellToString($cell), $row);
        }

        $headerIndex = $this->locateHeaderRowIndex($stringMatrix);
        if ($headerIndex === null) {
            throw new RuntimeException(
                'Could not find first_name or last_name columns. Use a header row with name columns (any order); extra columns are fine.',
            );
        }

        $header = $stringMatrix[$headerIndex];
        $normalizedHeader = array_map(fn (string $col) => $this->normalizeColumnName($col), $header);

        $rows = [];
        foreach (array_slice($stringMatrix, $headerIndex + 1) as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }
            $rows[] = $row;
        }

        return [
            'normalized_header' => $normalizedHeader,
            'rows' => $rows,
            'indices' => $this->resolveIndices($normalizedHeader),
        ];
    }

    /**
     * @return list<string>
     */
    private function utf8Candidates(string $raw): array
    {
        $candidates = [];

        $push = function (?string $utf8) use (&$candidates): void {
            if ($utf8 === null || $utf8 === '') {
                return;
            }
            if (! in_array($utf8, $candidates, true)) {
                $candidates[] = $utf8;
            }
        };

        if (str_starts_with($raw, "\xFF\xFE")) {
            $push(mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE'));
        }

        if (str_starts_with($raw, "\xFE\xFF")) {
            $push(mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE'));
        }

        $utf8Direct = $this->stripUtf8Bom($raw);
        if (mb_check_encoding($utf8Direct, 'UTF-8')) {
            $push($utf8Direct);
        }

        $sample = substr($raw, 0, min(8192, strlen($raw)));
        if (substr_count($sample, "\0") > (int) (strlen($sample) * 0.08)) {
            $push(mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE'));
            $push(mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE'));
        }

        $push(mb_convert_encoding($raw, 'UTF-8', 'Windows-1252'));
        $push(mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1'));

        return $candidates;
    }

    /**
     * @return ?array{normalized_header: list<string>, rows: list<list<string>>}
     */
    private function tryParseUtf8(string $utf8, string $delimiter): ?array
    {
        $utf8 = $this->stripLeadingBlankLines($utf8);

        $fh = fopen('php://memory', 'r+');
        if ($fh === false) {
            return null;
        }

        fwrite($fh, $utf8);
        rewind($fh);

        $allRows = [];
        while (($row = $this->readCsvRow($fh, $delimiter)) !== false) {
            $allRows[] = $row;
        }

        fclose($fh);

        if ($allRows === []) {
            return null;
        }

        $headerIndex = $this->locateHeaderRowIndex($allRows);
        if ($headerIndex === null) {
            return null;
        }

        $header = $allRows[$headerIndex];
        $normalizedHeader = array_map(fn (string $col) => $this->normalizeColumnName($col), $header);
        $rows = [];

        foreach (array_slice($allRows, $headerIndex + 1) as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }
            $rows[] = $row;
        }

        return [
            'normalized_header' => $normalizedHeader,
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function locateHeaderRowIndex(array $rows): ?int
    {
        $bestIndex = null;
        $bestScore = -1;
        $limit = min(count($rows), 15);

        for ($i = 0; $i < $limit; $i++) {
            if ($this->isBlankRow($rows[$i])) {
                continue;
            }

            $normalized = array_map(fn (string $col) => $this->normalizeColumnName($col), $rows[$i]);
            $score = $this->scoreAttempt($normalized);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $i;
            }
        }

        if ($bestIndex === null || $bestScore < 1000) {
            return null;
        }

        return $bestIndex;
    }

    /**
     * @param  list<array<int, mixed>>  $matrix
     * @return list<array<int, mixed>>
     */
    private function trimLeadingBlankMatrixRows(array $matrix): array
    {
        while ($matrix !== []) {
            $row = $matrix[0];
            if (! is_array($row)) {
                array_shift($matrix);

                continue;
            }

            $stringRow = array_map(fn ($cell) => $this->cellToString($cell), $row);
            if ($this->isBlankRow($stringRow)) {
                array_shift($matrix);

                continue;
            }

            break;
        }

        return $matrix;
    }

    private function stripLeadingBlankLines(string $utf8): string
    {
        $lines = preg_split('/\R/u', $utf8) ?: [];
        while ($lines !== [] && $this->isBlankDelimiterLine($lines[0])) {
            array_shift($lines);
        }

        return implode("\n", $lines);
    }

    private function isBlankDelimiterLine(string $line): bool
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return true;
        }

        return preg_match('/^[,;\t\s]*$/', $trimmed) === 1;
    }

    /**
     * @param  list<string>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $normalizedHeader
     */
    private function scoreAttempt(array $normalizedHeader): int
    {
        $nonEmpty = array_values(array_filter($normalizedHeader, fn (string $h) => $h !== ''));

        if (count($nonEmpty) <= 1) {
            return 0;
        }

        $score = count($nonEmpty) * 2;

        if ($this->hasNameColumns($normalizedHeader)) {
            $score += 1000;
        }

        if ($this->columnIndex($normalizedHeader, 'email') !== null) {
            $score += 5;
        }

        return $score;
    }

    /**
     * @param  list<string>  $normalizedHeader
     */
    private function hasNameColumns(array $normalizedHeader): bool
    {
        return $this->columnIndex($normalizedHeader, 'first_name', ['firstname', 'first', 'given_name']) !== null
            || $this->columnIndex($normalizedHeader, 'last_name', ['lastname', 'last', 'surname', 'family_name']) !== null;
    }

    /**
     * @param  list<string>  $normalizedHeader
     * @return array{first: ?int, last: ?int, email: ?int, phone: ?int, company: ?int, sector: ?int, notes: ?int}
     */
    private function resolveIndices(array $normalizedHeader): array
    {
        return [
            'first' => $this->columnIndex($normalizedHeader, 'first_name', ['firstname', 'first', 'given_name']),
            'last' => $this->columnIndex($normalizedHeader, 'last_name', ['lastname', 'last', 'surname', 'family_name']),
            'email' => $this->columnIndex($normalizedHeader, 'email'),
            'phone' => $this->columnIndex($normalizedHeader, 'phone'),
            'company' => $this->columnIndex($normalizedHeader, 'company'),
            'sector' => $this->columnIndex($normalizedHeader, 'sector'),
            'notes' => $this->columnIndex($normalizedHeader, 'notes'),
        ];
    }

    /**
     * @param  list<string>  $normalizedHeader
     * @param  list<string>  $aliases
     */
    private function columnIndex(array $normalizedHeader, string $canonical, array $aliases = []): ?int
    {
        foreach (array_unique([$canonical, ...$aliases]) as $name) {
            $i = array_search($name, $normalizedHeader, true);
            if ($i !== false) {
                return (int) $i;
            }
        }

        return null;
    }

    private function normalizeColumnName(string $name): string
    {
        $name = str_replace("\0", '', $name);
        $name = trim($name);
        if (str_starts_with($name, "\xEF\xBB\xBF")) {
            $name = substr($name, 3);
        }

        $name = preg_replace('/[\x{00A0}\x{200B}\x{FEFF}]/u', '', $name) ?? $name;
        $name = strtolower(trim($name));
        $name = preg_replace('/[\s\-]+/u', '_', $name) ?? $name;

        return $name;
    }

    private function stripUtf8Bom(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        return $content;
    }

    /**
     * @return list<string>|false
     */
    private function readCsvRow($handle, string $delimiter): array|false
    {
        $row = fgetcsv($handle, 0, $delimiter, '"', '\\');

        if ($row === false) {
            return false;
        }

        return array_map(fn ($cell) => is_string($cell) ? $cell : (string) $cell, $row);
    }

    private function cellToString(mixed $cell): string
    {
        if ($cell === null) {
            return '';
        }

        if (is_string($cell)) {
            return trim($cell);
        }

        if (is_int($cell) || is_float($cell)) {
            return trim((string) $cell);
        }

        if ($cell instanceof \DateTimeInterface) {
            return $cell->format('Y-m-d');
        }

        return trim((string) $cell);
    }
}
