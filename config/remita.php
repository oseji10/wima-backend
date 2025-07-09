<?php
return [
    'merchant_id' => env('REMITA_MERCHANT_ID'),
    'api_key' => env('REMITA_API_KEY'),
    'service_type_id' => env('REMITA_SERVICE_TYPE_ID'),
    'base_url' => env('REMITA_BASE_URL', 'https://remitademo.net/remita/exapp/api/v1/send/api/echannelsvc/merchant/api/paymentinit'), // live or sandbox
];
