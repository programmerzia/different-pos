<?php

declare(strict_types=1);

namespace App\Reports;

/**
 * One report column. Money cells hold integer paisa, qty cells integer milli-units, int cells plain
 * integers, date cells `YYYY-MM-DD` in the tenant timezone.
 */
final readonly class ReportColumn
{
    /**
     * @param  string  $label  English header, used by the CSV export (the UI translates `reports.col.{key}`)
     * @param  bool  $profit  only shown with reports.profit (cost, margin)
     * @param  string|null  $valuePrefix  text cells hold a code the UI translates as `{prefix}{value}`
     */
    public function __construct(
        public string $key,
        public string $label,
        public ColumnType $type,
        public bool $profit = false,
        public ?string $valuePrefix = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'profit' => $this->profit,
            'valuePrefix' => $this->valuePrefix,
        ];
    }
}
