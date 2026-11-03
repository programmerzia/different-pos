<?php

declare(strict_types=1);

namespace App\Support\Export;

/** The download formats every tabular export (reports and list pages) offers. */
enum ExportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';

    /** Validation rule for a `format` parameter. */
    public static function rule(): string
    {
        return 'in:'.implode(',', array_map(static fn (self $f): string => $f->value, self::cases()));
    }

    /** Route constraint for a `/export.{format}` segment. */
    public static function pattern(): string
    {
        return implode('|', array_map(static fn (self $f): string => $f->value, self::cases()));
    }

    public static function fromInput(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Csv) : self::Csv;
    }

    public function extension(): string
    {
        return $this->value;
    }

    public function contentType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv; charset=UTF-8',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Pdf => 'application/pdf',
        };
    }
}
