<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Export laporan generik ke Excel */
class ArrayExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private array $report) {}

    public function headings(): array
    {
        return array_map(fn ($c) => $c[1], $this->report['columns']);
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->report['rows'] as $row) {
            $row = (array) $row;
            $rows[] = array_map(fn ($c) => $row[$c[0]] ?? '', $this->report['columns']);
        }
        if (! empty($this->report['summary'])) {
            $rows[] = [];
            foreach ($this->report['summary'] as $s) {
                $rows[] = [$s[0], $s[1]];
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return mb_substr($this->report['title'], 0, 30);
    }
}
