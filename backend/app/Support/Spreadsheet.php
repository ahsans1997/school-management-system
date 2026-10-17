<?php

namespace App\Support;

use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvReaderOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxReaderOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Reading and writing CSV / Excel (.xlsx) files for imports and exports
 * (OpenSpout, streaming).
 *
 * Values are always written as text cells: OpenSpout would otherwise turn a
 * string starting with "=" into a formula. CSV has no cell types, so
 * formula-looking text is also prefixed with an apostrophe (CSV injection).
 */
final class Spreadsheet
{
    public const FORMATS = ['csv', 'xlsx'];

    public const MIME_TYPES = [
        'csv' => 'text/csv; charset=UTF-8',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * The non-empty rows of the first sheet, each with its line number in
     * the file (so errors can point at the right row).
     *
     * @return list<array{line: int, cells: list<mixed>}>
     */
    public static function read(string $path, string $format): array
    {
        $reader = $format === 'xlsx'
            ? new XlsxReader(new XlsxReaderOptions(SHOULD_PRESERVE_EMPTY_ROWS: true))
            : new CsvReader(new CsvReaderOptions(SHOULD_PRESERVE_EMPTY_ROWS: true, FIELD_DELIMITER: self::csvDelimiter($path)));

        $reader->open($path);
        try {
            return self::firstSheetRows($reader);
        } finally {
            $reader->close();
        }
    }

    /**
     * Write a header row and data rows to a temporary file; returns its path.
     *
     * @param  list<string>  $header
     * @param  iterable<list<string|int|null>>  $rows
     */
    public static function write(string $format, array $header, iterable $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheet');
        $writer = $format === 'xlsx' ? new XlsxWriter : new CsvWriter; // CSV gets a UTF-8 BOM for Excel
        $writer->openToFile($path);

        $bold = (new Style)->withFontBold(true);
        $writer->addRow(new Row(array_map(fn (string $h) => new StringCell($h, $bold), $header)));
        foreach ($rows as $values) {
            $writer->addRow(new Row(array_map(fn ($v) => self::cell($v, $format), $values)));
        }
        $writer->close();

        return $path;
    }

    private static function cell(string|int|null $value, string $format): Cell
    {
        if (is_int($value)) {
            return new NumericCell($value, null);
        }
        $text = (string) $value;

        return new StringCell($format === 'csv' ? self::csvSafe($text) : $text, null);
    }

    /**
     * Neutralise text a spreadsheet app would run as a formula. Plain numbers
     * such as "+8801711000000" or "-5" are left alone.
     */
    public static function csvSafe(string $text): string
    {
        $formulaLike = preg_match('/^[=@\t\r]/', $text) === 1
            || (preg_match('/^[+-]/', $text) === 1 && preg_match('/^[+-]?[0-9][0-9 ().-]*$/', $text) !== 1);

        return $formulaLike ? "'".$text : $text;
    }

    /**
     * @return list<array{line: int, cells: list<mixed>}>
     */
    private static function firstSheetRows(ReaderInterface $reader): array
    {
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            $line = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $line++;
                $cells = array_map(
                    fn ($v) => is_string($v) ? trim($v) : $v,
                    $row->toArray(),
                );
                if (array_filter($cells, fn ($v) => $v !== null && $v !== '') !== []) {
                    $rows[] = ['line' => $line, 'cells' => $cells];
                }
            }

            return $rows; // first sheet only
        }

        return [];
    }

    /** Excel saves CSV with ";" in some locales: use whichever the header line has more of. */
    private static function csvDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $firstLine = $handle ? (string) fgets($handle, 8192) : '';
        if ($handle) {
            fclose($handle);
        }

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    /**
     * An Excel serial day number (days since 1899-12-30, as stored when a
     * date column isn't formatted as a date) as "YYYY-MM-DD". Numbers
     * outside 1900–2199 are returned unchanged for validation to reject.
     */
    public static function excelSerialDate(int $serial): string
    {
        if ($serial < 1 || $serial > 109_574) {
            return (string) $serial;
        }

        return (new \DateTimeImmutable('1899-12-30'))->modify("+{$serial} days")->format('Y-m-d');
    }

    /** "2014-05-10" for a date cell, else the value unchanged. */
    public static function dateCell(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
    }
}
