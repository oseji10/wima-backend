<?php

/*
|--------------------------------------------------------------------------
| GoTRACT Programme Configuration
|--------------------------------------------------------------------------
|
| Central place for the option lists used across validation, seeding and
| reporting. Keep these in sync with the frontend constants in
| GoTractApplication.tsx so the two never drift apart.
|
*/

return [

    // Beneficiary targets (from the GoTRACT proposal).
    'target_per_lga' => 90,
    'total_target'   => 990, // 11 LGAs * 90 beneficiaries each

    // Applications per LGA are capped at this number; once reached, that LGA
    // stops accepting new submissions. Defaults to the per-LGA target — raise it
    // via GOTRACT_APPLICATION_CAP if you want a screening buffer (collect more
    // applications than slots, then approve the best 40).
    'application_cap_per_lga' => (int) env('GOTRACT_APPLICATION_CAP', 90),

    // The 11 Local Government Areas of Gombe State.
    'lgas' => [
        'Akko', 'Balanga', 'Billiri', 'Dukku', 'Funakaye', 'Gombe',
        'Kaltungo', 'Kwami', 'Nafada', 'Shongom', 'Yamaltu/Deba',
    ],

    // Preferred mechanization service IDs (must match the frontend chip IDs).
    'services' => [
        'ploughing', 'planting', 'harvesting', 'harrowing',
        'tilling', 'threshing', 'water-pumping', 'other',
    ],

    // Capacity-building training area IDs.
    'training_areas' => [
        'mechanization-operation', 'business-financial', 'group-leadership', 'other',
    ],

    'genders'           => ['Male', 'Female'],
    'marital_statuses'  => ['Single', 'Married', 'Widowed', 'Other'],
    'land_ownership'    => ['Owned', 'Rented', 'Communal'],
    'disability_types'  => ['Physical', 'Mental', 'Both', 'Other'],

    // Screening workflow states.
    'statuses' => ['pending', 'screening', 'approved', 'rejected'],

    // Secret token gating the public, read-only government oversight page.
    // Set GOTRACT_OVERSIGHT_TOKEN in .env to a long random string.
    'oversight_token' => env('GOTRACT_OVERSIGHT_TOKEN'),

    // Optional advisory age window for youth (not enforced by default).
    'youth_age_min' => 18,
    'youth_age_max' => 35,


    // Scan types.
    'scan_types' => ['attendance', 'meal'],
 
    // Sessions the scanner offers. Training runs across days; each day has an
    // attendance session and its meal sittings. Edit to match the programme.
    'sessions' => [
        'attendance' => [
            'day-1' => 'Day 1 — Attendance',
            'day-2' => 'Day 2 — Attendance',
        ],
        'meal' => [
            'day-1-breakfast' => 'Day 1 — Breakfast',
            'day-1-lunch'     => 'Day 1 — Lunch',
            'day-2-breakfast' => 'Day 2 — Breakfast',
            'day-2-lunch'     => 'Day 2 — Lunch',
        ],
    ],
 
    // Who may be accredited at the desk. Everyone starts as `pending`, so the
    // desk accepts any of these; only `rejected` applicants are turned away.
    'accreditable_statuses' => ['pending', 'screening', 'approved'],
 
    // Accrediting a participant marks them approved (they showed up and were
    // verified at the desk). Set to null to leave the status untouched.
    'status_on_accreditation' => 'approved',
];