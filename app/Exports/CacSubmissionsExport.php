<?php

namespace App\Exports;

use App\Models\MSPs;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CacSubmissionsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private array $filters = []) {}

    public function query()
    {
        $query = MSPs::query()
            ->whereNotNull('cac_business_name_1')
            ->with(['users:id,firstName,lastName,phoneNumber,email', 'states', 'lgas', 'projects']);

        if (!empty($this->filters['status'])) {
            $query->where('cac_status', $this->filters['status']);
        }
        if (!empty($this->filters['search'])) {
            $s = $this->filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('cac_business_name_1', 'like', "%{$s}%")
                  ->orWhere('cac_business_name_2', 'like', "%{$s}%")
                  ->orWhere('cac_business_name_3', 'like', "%{$s}%")
                  ->orWhere('alternatePhoneNumber', 'like', "%{$s}%")
                  ->orWhereHas('users', function ($uq) use ($s) {
                      $uq->where('firstName', 'like', "%{$s}%")->orWhere('lastName', 'like', "%{$s}%");
                  });
            });
        }

        return $query->orderByDesc('cac_submitted_at');
    }

    public function headings(): array
    {
        return [
            'MSP ID', 'First Name', 'Last Name', 'Phone', 'Email', 'Cohort',
            'State', 'LGA', 'NIN', 'Business Address',
            'Proposed Name 1', 'Proposed Name 2', 'Proposed Name 3',
            'Approved Name', 'Status', 'Admin Note', 'Submitted At', 'Reviewed At',
        ];
    }

    public function map($msp): array
    {
        $approved = $msp->cac_approved_name ? $msp->{"cac_business_name_{$msp->cac_approved_name}"} : '';

        return [
            $msp->mspId,
            $msp->users->firstName ?? '',
            $msp->users->lastName ?? '',
            $msp->alternatePhoneNumber ?? $msp->users->phoneNumber ?? '',
            $msp->users->email ?? '',
            $msp->cac_cohort,
            $msp->states->stateName ?? '',
            $msp->lgas->lgaName ?? '',
            // $msp->projects->projectName ?? '',
            $msp->nin,
            $msp->cac_business_address,
            $msp->cac_business_name_1,
            $msp->cac_business_name_2,
            $msp->cac_business_name_3,
            $approved,
            $msp->cac_status,
            $msp->cac_admin_note,
            optional($msp->cac_submitted_at)->format('Y-m-d H:i'),
            optional($msp->cac_reviewed_at)->format('Y-m-d H:i'),
        ];
    }
}