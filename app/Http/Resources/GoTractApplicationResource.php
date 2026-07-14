<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal confirmation payload returned after a successful submission.
 * Shaped in camelCase to match what the React success modal reads:
 * response.data.data.{referenceId, fullName, lga, phoneNumber}.
 *
 * Deliberately omits PII (NIN, BVN, bank details) — those are only ever
 * exposed through the authenticated admin endpoints.
 */
class GoTractApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'referenceId' => $this->reference_id,
            'fullName'    => $this->full_name,
            'phoneNumber' => $this->phone_number,
            'lga'         => $this->lga,
            'status'      => $this->status,
            'submittedAt' => optional($this->submitted_at)->toIso8601String(),
        ];
    }
}
