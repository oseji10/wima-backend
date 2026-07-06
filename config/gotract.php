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
    'target_per_lga' => 60,
    'total_target'   => 660, // 11 LGAs * 60 beneficiaries each

    // Applications per LGA are capped at this number; once reached, that LGA
    // stops accepting new submissions. Defaults to the per-LGA target — raise it
    // via GOTRACT_APPLICATION_CAP if you want a screening buffer (collect more
    // applications than slots, then approve the best 40).
    'application_cap_per_lga' => (int) env('GOTRACT_APPLICATION_CAP', 60),

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
];