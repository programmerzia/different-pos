<?php

declare(strict_types=1);

namespace App\Legacy;

/** migration_report.csv: one line per anomaly or decision the owner must be able to see. */
final class MigrationReport
{
    /** @var list<array{kind: string, legacy_table: string, legacy_id: string, message: string}> */
    private array $lines = [];

    public function add(string $kind, string $legacyTable, ?int $legacyId, string $message): void
    {
        $this->lines[] = [
            'kind' => $kind,
            'legacy_table' => $legacyTable,
            'legacy_id' => $legacyId === null ? '' : (string) $legacyId,
            'message' => $message,
        ];
    }

    /** @return list<array{kind: string, legacy_table: string, legacy_id: string, message: string}> */
    public function lines(): array
    {
        return $this->lines;
    }

    /** @return array<string, int> kind => lines */
    public function countsByKind(): array
    {
        $counts = [];
        foreach ($this->lines as $line) {
            $counts[$line['kind']] = ($counts[$line['kind']] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /** @return list<array{kind: string, legacy_table: string, legacy_id: string, message: string}> */
    public function ofKind(string $kind): array
    {
        return array_values(array_filter($this->lines, static fn (array $line): bool => $line['kind'] === $kind));
    }
}
