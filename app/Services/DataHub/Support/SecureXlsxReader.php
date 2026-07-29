<?php

declare(strict_types=1);

namespace App\Services\DataHub\Support;

use App\Services\DataHub\Exceptions\TranslationFailureException;
use App\Services\DataHub\Services\TranslationFailureCatalog;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

final class SecureXlsxReader
{
    private const SHEET_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const OFFICE_REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const MAX_ENTRIES = 2000;
    private const MAX_UNCOMPRESSED_BYTES = 100 * 1024 * 1024;

    public function __construct(private readonly TranslationFailureCatalog $failures)
    {
    }

    /**
     * @return array{
     *   sheets: array<int, array{name: string, rows: array<int, array<string, string>>, formulas: int, merged_ranges: array<int, string>}>,
     *   warnings: array<int, string>
     * }
     */
    public function read(string $path): array
    {
        $zip = new ZipArchive();
        if (true !== $zip->open($path, ZipArchive::RDONLY)) {
            throw $this->corrupted('archive_open_failed');
        }
        try {
            $warnings = $this->validateArchive($zip);
            $sharedStrings = $this->sharedStrings($zip);
            $relationships = $this->relationships($zip);
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $workbook->registerXPathNamespace('m', self::SHEET_NS);
            $sheets = [];
            foreach ($workbook->xpath('//m:sheets/m:sheet') ?: [] as $sheet) {
                $relationshipId = (string) $sheet->attributes(self::OFFICE_REL_NS)['id'];
                $target = $relationships[$relationshipId] ?? null;
                if (null === $target) {
                    continue;
                }
                $worksheetPath = str_starts_with($target, '/')
                    ? ltrim($target, '/')
                    : 'xl/'.ltrim($target, '/');
                $sheets[] = $this->worksheet($zip, $worksheetPath, (string) $sheet['name'], $sharedStrings);
            }
            if ([] === $sheets) {
                throw $this->corrupted('no_readable_worksheets');
            }

            return ['sheets' => $sheets, 'warnings' => $warnings];
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, string> */
    private function validateArchive(ZipArchive $zip): array
    {
        if ($zip->numFiles > self::MAX_ENTRIES) {
            throw new RuntimeException('The Excel workbook contains too many package entries.');
        }
        $uncompressed = 0;
        $warnings = [];
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $stat = $zip->statIndex($index);
            $name = (string) ($stat['name'] ?? '');
            if (str_contains($name, '..') || str_starts_with($name, '/')) {
                throw new RuntimeException('The Excel workbook contains an unsafe package path.');
            }
            $uncompressed += (int) ($stat['size'] ?? 0);
            if ($uncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('The uncompressed Excel workbook exceeds the inspection safety limit.');
            }
            $lower = mb_strtolower($name);
            if (str_contains($lower, 'vbaproject.bin') || str_starts_with($lower, 'xl/externallinks/')) {
                throw new RuntimeException('Workbooks containing macros or external workbook links are not supported.');
            }
            if (str_starts_with($lower, 'xl/activex/')) {
                $warnings[] = 'ActiveX controls are present but were ignored during secure inspection.';
            }
        }
        foreach (['[Content_Types].xml', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels'] as $required) {
            if (false === $zip->locateName($required)) {
                throw $this->corrupted('missing_required_package_part');
            }
        }

        return array_values(array_unique($warnings));
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        if (false === $zip->locateName('xl/sharedStrings.xml')) {
            return [];
        }
        $xml = $this->xml($zip, 'xl/sharedStrings.xml');
        $xml->registerXPathNamespace('m', self::SHEET_NS);

        return array_map(function (SimpleXMLElement $item): string {
            $item->registerXPathNamespace('m', self::SHEET_NS);

            return implode('', array_map(fn (SimpleXMLElement $text): string => (string) $text, $item->xpath('.//m:t') ?: []));
        }, $xml->xpath('//m:si') ?: []);
    }

    /** @return array<string, string> */
    private function relationships(ZipArchive $zip): array
    {
        $xml = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
        $xml->registerXPathNamespace('r', self::REL_NS);
        $relationships = [];
        foreach ($xml->xpath('//r:Relationship') ?: [] as $relationship) {
            if (str_contains((string) $relationship['Type'], '/worksheet')) {
                $relationships[(string) $relationship['Id']] = (string) $relationship['Target'];
            }
        }

        return $relationships;
    }

    /**
     * @param array<int, string> $sharedStrings
     * @return array{name: string, rows: array<int, array<string, string>>, formulas: int, merged_ranges: array<int, string>}
     */
    private function worksheet(ZipArchive $zip, string $path, string $name, array $sharedStrings): array
    {
        $xml = $this->xml($zip, $path);
        $xml->registerXPathNamespace('m', self::SHEET_NS);
        $rows = [];
        $formulaCount = 0;
        foreach ($xml->xpath('//m:sheetData/m:row') ?: [] as $row) {
            $row->registerXPathNamespace('m', self::SHEET_NS);
            $values = [];
            foreach ($row->xpath('./m:c') ?: [] as $cell) {
                $cell->registerXPathNamespace('m', self::SHEET_NS);
                $reference = (string) $cell['r'];
                preg_match('/^[A-Z]+/', $reference, $matches);
                $column = $matches[0] ?? '';
                if ('' === $column) {
                    continue;
                }
                if ([] !== ($cell->xpath('./m:f') ?: [])) {
                    ++$formulaCount;
                }
                $valueNodes = $cell->xpath('./m:v') ?: [];
                $value = isset($valueNodes[0]) ? (string) $valueNodes[0] : '';
                if ('s' === (string) $cell['t'] && '' !== $value) {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ('inlineStr' === (string) $cell['t']) {
                    $value = implode('', array_map(fn (SimpleXMLElement $text): string => (string) $text, $cell->xpath('.//m:t') ?: []));
                }
                $values[$column] = trim(str_replace("\u{00A0}", ' ', $value));
            }
            if ([] !== array_filter($values, fn (string $value): bool => '' !== $value)) {
                $rows[(int) $row['r']] = $values;
            }
        }
        $merged = array_map(
            fn (SimpleXMLElement $range): string => (string) $range['ref'],
            $xml->xpath('//m:mergeCells/m:mergeCell') ?: []
        );

        return ['name' => $name, 'rows' => $rows, 'formulas' => $formulaCount, 'merged_ranges' => $merged];
    }

    private function xml(ZipArchive $zip, string $path): SimpleXMLElement
    {
        $contents = $zip->getFromName($path);
        if (false === $contents) {
            throw $this->corrupted('missing_referenced_package_part');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
            if (false === $xml) {
                throw $this->corrupted('invalid_workbook_xml');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function corrupted(string $reason): TranslationFailureException
    {
        return new TranslationFailureException($this->failures->warning(
            'corrupted_spreadsheet',
            ['reason' => $reason],
        ));
    }
}
