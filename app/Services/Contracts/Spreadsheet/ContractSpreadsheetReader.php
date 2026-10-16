<?php

namespace App\Services\Contracts\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class ContractSpreadsheetReader
{
    private const SHEET = 'Painel de info Vendas';

    private const FIRST_DATA_ROW = 3;

    public const COL_DATE = 1;

    public const COL_CONTRACT = 2;

    public const COL_REGISTER = 3;

    public const COL_EXPORTER = 4;

    public const COL_IMPORTER = 5;

    public const COL_COUNTRY = 6;

    public const COL_PRODUCT = 7;

    public const COL_QUANTITY = 8;

    public const COL_PRICE = 9;

    public const COL_SALES = 10;

    public const COL_COMMISSION = 12;

    public const COL_EXCHANGE = 14;

    public const COL_EURO = 15;

    public const COL_ESTIMATED_RECEIPT = 16;

    public const COL_SELLER = 17;

    public const COL_TO_PAY = 18;

    public const COL_RECEIPT = 19;

    public const COL_PAID_IN = 20;

    public const COL_PAID_BRL = 21;

    public const COL_INCIDENT = 22;

    public const COL_VIDEO_REFERENCE = 23;

    public const COL_VIDEO_DATE = 24;

    public const COL_STATUS = 25;

    public const COL_STATUS_DATE = 26;

    public const COL_DHL_DATE = 27;

    public const COL_ETD = 28;

    public const COL_ETA = 29;

    public const COL_DHL_NUMBER = 30;

    public const COL_OBSERVATIONS = 31;

    private const LAST_COLUMN = self::COL_OBSERVATIONS;

    /**
     * The sheet carries formatted-but-empty rows far below the real data.
     */
    private const MAX_CONSECUTIVE_EMPTY_ROWS = 60;

    /**
     * Columns whose leftover formulas create rows far below the real data.
     */
    private const RESIDUE_COLUMNS = [
        self::COL_VIDEO_DATE,
        self::COL_STATUS,
        self::COL_DHL_DATE,
    ];

    private const DATE_COLUMNS = [
        self::COL_DATE,
        self::COL_ESTIMATED_RECEIPT,
        self::COL_RECEIPT,
        self::COL_PAID_IN,
        self::COL_VIDEO_DATE,
        self::COL_STATUS_DATE,
        self::COL_DHL_DATE,
        self::COL_ETD,
        self::COL_ETA,
    ];

    /**
     * Excel serials below this bound are artefacts (zero/negative results of
     * formulas such as `=Data Status - 7`), never real dates.
     */
    private const MIN_EXCEL_SERIAL = 20000;

    public function __construct(private readonly ValueNormalizer $normalizer) {}

    /**
     * @return array{blocks: array<int, array<string, mixed>>, discarded: array<int, array<string, mixed>>, warnings: array<int, string>, residue_rows: array<int, int>}
     */
    public function read(string $path): array
    {
        $spreadsheet = $this->load($path);
        $sheet = $spreadsheet->getSheetByName(self::SHEET);

        if ($sheet === null) {
            throw new \RuntimeException('A aba "Painel de info Vendas" não foi encontrada no arquivo.');
        }

        $blocks = [];
        $discarded = [];
        $warnings = [];
        $residueRows = [];
        $current = null;
        $lastRow = min($sheet->getHighestDataRow(), $this->autofilterLastRow($sheet));
        $consecutiveEmpty = 0;

        for ($row = self::FIRST_DATA_ROW; $row <= $lastRow; $row++) {
            $values = $this->readRow($sheet, $row);

            if (! $this->hasData($values)) {
                if (++$consecutiveEmpty >= self::MAX_CONSECUTIVE_EMPTY_ROWS) {
                    break;
                }

                continue;
            }

            $consecutiveEmpty = 0;

            if (! $this->hasBusinessData($values)) {
                $residueRows[] = $row;

                continue;
            }

            $contractNumber = $this->normalizer->text($values[self::COL_CONTRACT]);
            $date = $this->normalizer->date($values[self::COL_DATE]);
            $hasProduct = $this->normalizer->text($values[self::COL_PRODUCT]) !== null;
            $hasQuantity = $this->normalizer->number($values[self::COL_QUANTITY]) !== null;

            if ($contractNumber === null && $date === null && ! $hasProduct && ! $hasQuantity) {
                $discarded[] = [
                    'row' => $row,
                    'reason' => 'Linha sem contrato, data, produto e quantidade.',
                    'status' => $this->normalizer->text($values[self::COL_STATUS]),
                ];

                continue;
            }

            $startsNewBlock = $current === null
                || $contractNumber === null
                || $contractNumber !== $current['contract_number']
                || $date !== $current['date'];

            if ($startsNewBlock) {
                if ($current !== null) {
                    $blocks = $this->closeBlock($current, $blocks, $warnings);
                }

                $current = [
                    'source_row' => $row,
                    'contract_number' => $contractNumber,
                    'date' => $date,
                    'header' => $values,
                    'items' => [],
                ];

                if ($contractNumber === null) {
                    $warnings[$row] = "Linha {$row} sem número de contrato: importada como contrato avulso.";
                }
            }

            $current['items'][] = [
                'source_row' => $row,
                'values' => $values,
                'formulas' => $this->readFormulas($sheet, $row),
            ];
        }

        if ($current !== null) {
            $blocks = $this->closeBlock($current, $blocks, $warnings);
        }

        $spreadsheet->disconnectWorksheets();

        return [
            'blocks' => $blocks,
            'discarded' => $discarded,
            'warnings' => $warnings,
            'residue_rows' => $residueRows,
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function closeBlock(array $current, array $blocks, array &$warnings): array
    {
        $useful = array_values(array_filter(
            $current['items'],
            fn (array $item): bool => $this->normalizer->text($item['values'][self::COL_PRODUCT]) !== null
                || $this->normalizer->number($item['values'][self::COL_QUANTITY]) !== null
        ));

        if ($useful === []) {
            $warnings[$current['source_row']] = sprintf(
                'Bloco iniciado na linha %d descartado: nenhuma linha com produto ou quantidade.',
                $current['source_row']
            );

            return $blocks;
        }

        $current['items'] = $useful;

        $blocks[] = $current;

        return $blocks;
    }

    private function load(string $path): Spreadsheet
    {
        if (! is_readable($path)) {
            throw new \RuntimeException("Arquivo não encontrado ou sem permissão de leitura: {$path}");
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        return $reader->load($path);
    }

    /**
     * @return array<int, mixed>
     */
    private function readRow(Worksheet $sheet, int $row): array
    {
        $values = [];

        for ($column = 1; $column <= self::LAST_COLUMN; $column++) {
            $values[$column] = $this->extractValue($sheet->getCell([$column, $row]));
        }

        return $values;
    }

    /**
     * @return array<int, string|null>
     */
    private function readFormulas(Worksheet $sheet, int $row): array
    {
        $formulas = [];

        foreach ([self::COL_COMMISSION, self::COL_TO_PAY] as $column) {
            $value = $sheet->getCell([$column, $row])->getValue();
            $formulas[$column] = is_string($value) && str_starts_with($value, '=') ? $value : null;
        }

        return $formulas;
    }

    private function extractValue(Cell $cell): mixed
    {
        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
            return $cell->getOldCalculatedValue();
        }

        return $cell->getValue();
    }

    /**
     * Rows whose only content comes from leftover formulas (expired video date,
     * status template and the "..." DHL sentinel) are residue, not data.
     *
     * @param  array<int, mixed>  $values
     */
    private function hasBusinessData(array $values): bool
    {
        foreach ($values as $column => $value) {
            if (in_array((int) $column, self::RESIDUE_COLUMNS, true)) {
                continue;
            }

            if ($this->isMeaningful((int) $column, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rows whose only content is the "..." sentinel of the "Data DHL" formula
     * carry no business meaning and are treated as empty.
     *
     * @param  array<int, mixed>  $values
     */
    private function hasData(array $values): bool
    {
        foreach ($values as $column => $value) {
            if ($this->isMeaningful((int) $column, $value)) {
                return true;
            }
        }

        return false;
    }

    private function isMeaningful(int $column, mixed $value): bool
    {
        if ($value === null || $value === '' || $value === '...') {
            return false;
        }

        if (is_numeric($value) && in_array($column, self::DATE_COLUMNS, true) && (float) $value < self::MIN_EXCEL_SERIAL) {
            return false;
        }

        return true;
    }

    private function autofilterLastRow(Worksheet $sheet): int
    {
        $range = $sheet->getAutoFilter()?->getRange();

        if (! $range) {
            return PHP_INT_MAX;
        }

        $last = preg_replace('/^.*?(\d+)$/', '$1', $range);

        return is_numeric($last) ? (int) $last : PHP_INT_MAX;
    }
}
