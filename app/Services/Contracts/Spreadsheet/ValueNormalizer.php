<?php

namespace App\Services\Contracts\Spreadsheet;

use App\Support\ContractStatus;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class ValueNormalizer
{
    /**
     * Keys are produced by key(): lowercase, transliterated, without any
     * non-alphanumeric character.
     *
     * @var array<string, string>
     */
    private const COUNTRY_ALIASES = [
        'india' => 'India',
        'china' => 'China',
        'egito' => 'Egito',
        'vietna' => 'Vietnã',
        'peru' => 'Peru',
        'paquistao' => 'Paquistão',
        'canada' => 'Canadá',
        'turquia' => 'Turquia',
        'espanha' => 'Espanha',
        'indonesia' => 'Indonesia',
        'malasia' => 'Malásia',
        'guatemala' => 'Guatemala',
        'uruguai' => 'Uruguai',
        'sudao' => 'Sudão',
        'nigeria' => 'Nigeria',
        'costadomarfim' => 'Costa do Marfim',
        'mexico' => 'México',
        'franca' => 'França',
        'brasil' => 'Brasil',
        'portugal' => 'Portugal',
    ];

    /**
     * Company names that ended up in the "País" column.
     */
    private const NON_COUNTRY_VALUES = ['expin', 'emporium', 'vanucci', 'condbras'];

    /**
     * Words that are not company names but ended up in the party columns.
     */
    private const NON_PARTY_VALUES = ['cancelado', 'cancelada'];

    /**
     * Excel serials outside this window are artefacts, never real dates.
     */
    private const MIN_SERIAL = 20000;

    private const MAX_SERIAL = 60000;

    private const MIN_YEAR = 1990;

    private const MAX_YEAR = 2100;

    /** @var array<string, array<string, array<string, int>>> */
    private array $spellings = [];

    /** @var array<string, array<int, string>> */
    private array $rejectedValues = [];

    public function text(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        if (is_float($value) && floor($value) == $value) {
            $value = (string) (int) $value;
        }

        $text = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $text === '' ? null : $text;
    }

    public function number(mixed $value): ?float
    {
        if ($value === null || $value === '' || is_bool($value)) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    public function integer(mixed $value): ?int
    {
        $number = $this->number($value);

        return $number === null ? null : (int) round($number);
    }

    public function date(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $this->plausibleDate($value->format('Y-m-d'));
        }

        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial < self::MIN_SERIAL || $serial > self::MAX_SERIAL) {
                return null;
            }

            try {
                return $this->plausibleDate(ExcelDate::excelToDateTimeObject($serial)->format('Y-m-d'));
            } catch (\Exception) {
                return null;
            }
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || strtotime($trimmed) === false) {
                return null;
            }

            return $this->plausibleDate(date('Y-m-d', strtotime($trimmed)));
        }

        return null;
    }

    /**
     * Formula artefacts such as `=Data Status - 7` on an empty cell produce
     * 1899/1900 values. They must never reach the database.
     */
    private function plausibleDate(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $year = (int) substr($date, 0, 4);

        return $year < self::MIN_YEAR || $year > self::MAX_YEAR ? null : $date;
    }

    public function country(mixed $value, int $row): ?string
    {
        $text = $this->text($value);
        if ($text === null) {
            return null;
        }

        $key = $this->key($text);

        if (in_array($key, self::NON_COUNTRY_VALUES, true)) {
            $this->flag('país', $text, $row, 'Valor é o nome de uma empresa, não um país. Ignorado.');

            return null;
        }

        if (! isset(self::COUNTRY_ALIASES[$key])) {
            $this->flag('país', $text, $row, 'País fora da lista padrão. Mantido como está; confirme no cadastro.');

            return $text;
        }

        return self::COUNTRY_ALIASES[$key];
    }

    public function party(mixed $value, string $label, int $row): ?string
    {
        if (is_numeric($value) && ! is_string($value)) {
            $this->flag($label, (string) $value, $row, 'Valor numérico (serial de data) no lugar do nome. Ignorado.');

            return null;
        }

        $text = $this->text($value);
        if ($text === null) {
            return null;
        }

        if (in_array($this->key($text), self::NON_PARTY_VALUES, true)) {
            $this->flag($label, $text, $row, 'Valor parece ser um status, não um nome de empresa.');

            return $text;
        }

        return $this->canonical($label, $text, $row);
    }

    public function seller(mixed $value, int $row): ?string
    {
        $text = $this->text($value);
        if ($text === null) {
            return null;
        }

        return $this->canonical('vendedor', $text, $row);
    }

    public function status(mixed $value, int $row): ?string
    {
        $text = $this->text($value);
        if ($text === null) {
            return null;
        }

        if (! ContractStatus::isValid($text)) {
            $this->flag('STATUS', $text, $row, 'Status fora da lista oficial. Linha mantida sem status.');

            return null;
        }

        return $text;
    }

    public function incident(mixed $value): ?string
    {
        $text = $this->text($value);
        if ($text === null) {
            return null;
        }

        if (preg_match('/^0+(\.0+)?$/', $text)) {
            return null;
        }

        return $text;
    }

    /**
     * The "Data DHL" column mixes dates with the sentinels "...", "CONCLUÍDO"
     * and "não se aplica". Only real dates are kept.
     */
    public function dhlDate(mixed $value): ?string
    {
        return $this->date($value);
    }

    /**
     * Tracking codes must stay readable. The spreadsheet stores real dates in
     * the "DHL Nº" column for some rows, so serials are converted instead of
     * being persisted as meaningless integers.
     */
    public function trackingCode(mixed $value, string $label, int $row): ?string
    {
        $looksLikeDate = $value instanceof \DateTimeInterface
            || (is_numeric($value) && (float) $value >= self::MIN_SERIAL && (float) $value <= self::MAX_SERIAL);

        if ($looksLikeDate) {
            $raw = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
            $date = $this->date($value);

            if ($date !== null) {
                $this->flag($label, $date, $row, 'Data preenchida no lugar de um código de rastreio.');

                return $date;
            }

            $this->flag($label, $raw, $row, 'Valor com aparência de data inválido. Ignorado.');

            return null;
        }

        return $this->text($value);
    }

    /**
     * "Video enviado" holds tracking codes, free text or dates.
     *
     * @return array{sent: bool, reference: ?string, date: ?string}
     */
    public function video(mixed $reference, mixed $date, int $row): array
    {
        $dateValue = $this->date($date);
        $referenceText = null;

        $looksLikeDate = $reference instanceof \DateTimeInterface
            || (is_numeric($reference) && (float) $reference >= self::MIN_SERIAL && (float) $reference <= self::MAX_SERIAL);

        if ($looksLikeDate) {
            $converted = $this->date($reference);

            if ($converted !== null) {
                $dateValue ??= $converted;
            } else {
                // Protocol numbers such as 30039 fall inside the Excel serial
                // window but are not dates: keep them as the reference.
                $referenceText = $this->text($reference);
            }
        } else {
            $referenceText = $this->text($reference);
        }

        return [
            'sent' => $referenceText !== null || $dateValue !== null,
            'reference' => $referenceText,
            'date' => $dateValue,
        ];
    }

    public function commissionRate(mixed $formula, ?float $commission, ?float $sales): ?float
    {
        if (is_string($formula) && preg_match('/\*\s*([0-9]+(?:[.,][0-9]+)?)\s*%/', $formula, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        if ($sales !== null && $sales > 0 && $commission !== null) {
            $derived = round($commission / $sales * 100, 4);
            if (abs($derived - round($derived)) < 0.0001) {
                return round($derived, 2);
            }
        }

        return null;
    }

    public function toPayRate(mixed $formula, ?float $toPay, ?float $sales): ?float
    {
        return $this->commissionRate($formula, $toPay, $sales);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function flagged(): array
    {
        return $this->rejectedValues;
    }

    /**
     * Spellings that were automatically collapsed into a single canonical value.
     *
     * @return array<string, array<int, string>>
     */
    public function merged(): array
    {
        $merged = [];

        foreach ($this->spellings as $label => $groups) {
            foreach ($groups as $spellings) {
                if (count($spellings) < 2) {
                    continue;
                }

                $winner = $this->winner($spellings);
                $others = array_values(array_filter(array_keys($spellings), static fn (string $s) => $s !== $winner));

                $merged[$label][] = implode(' | ', $others).'  ->  '.$winner;
            }
        }

        return $merged;
    }

    /**
     * Names that look like variants of each other but were kept apart because
     * they are not exact matches. Only reported, never merged automatically.
     *
     * @return array<string, array<int, string>>
     */
    public function possibleVariants(): array
    {
        $variants = [];

        foreach ($this->spellings as $label => $groups) {
            $names = [];

            foreach ($groups as $spellings) {
                $names[] = $this->winner($spellings);
            }

            $names = array_values(array_unique($names));
            sort($names);

            for ($i = 0, $count = count($names); $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($this->looksLikeVariant($names[$i], $names[$j])) {
                        $variants[$label][] = "{$names[$i]}  ~  {$names[$j]}";
                    }
                }
            }
        }

        return $variants;
    }

    private function looksLikeVariant(string $a, string $b): bool
    {
        $keyA = $this->key($a);
        $keyB = $this->key($b);

        if ($keyA === '' || $keyB === '' || $keyA === $keyB) {
            return false;
        }

        if (str_contains($keyA, $keyB) || str_contains($keyB, $keyA)) {
            return true;
        }

        $lengthDelta = abs(strlen($keyA) - strlen($keyB));

        return $lengthDelta <= 2 && levenshtein($keyA, $keyB) <= 2;
    }

    private function canonical(string $label, string $text, int $row): string
    {
        $key = $this->key($text);

        $this->spellings[$label][$key][$text] = ($this->spellings[$label][$key][$text] ?? 0) + 1;

        return $this->winner($this->spellings[$label][$key]);
    }

    /**
     * @param  array<string, int>  $spellings
     */
    private function winner(array $spellings): string
    {
        uksort($spellings, function (string $a, string $b) use ($spellings): int {
            $byCount = $spellings[$b] <=> $spellings[$a];
            if ($byCount !== 0) {
                return $byCount;
            }

            $byLength = mb_strlen($b) <=> mb_strlen($a);

            return $byLength !== 0 ? $byLength : strcmp($a, $b);
        });

        return array_key_first($spellings);
    }

    private function flag(string $label, string $value, int $row, string $reason): void
    {
        $this->rejectedValues[$label][] = "Linha {$row}: \"{$value}\" — {$reason}";
    }

    private function key(string $value): string
    {
        $key = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($value));

        return preg_replace('/[^a-z0-9]+/', '', (string) $key) ?? '';
    }
}
