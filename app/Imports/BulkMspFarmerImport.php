<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

class BulkMspFarmerImport implements ToCollection, WithHeadingRow
{
    public function sheets(): array
    {
        return [
            0 => $this,           // first sheet (index 0)
            // or 'Sheet1' => $this,  // by name
            // or 1 => $this,        // second sheet (index 1)
        ];
    }
    
    public function collection(Collection $rows)
    {
        // $rows already has nice keys (after WithHeadingRow)
        return $rows;
    }

    // Optional: define exact expected headings
    public function headingRow(): int
    {
        return 1; // first row = headers
    }

    
}