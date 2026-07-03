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
    'target_per_lga' => 40,
    'total_target'   => 440,

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