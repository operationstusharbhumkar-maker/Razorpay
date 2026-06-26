<?php

$payload = [

    "countryCode" => "+91",

    "phoneNumber" =>
        $customer['mobile'],

    "type" => "Template",

    "template" => [

        "name" =>
            INTERAKT_TEMPLATE,

        "languageCode" => "en",

        "bodyValues" => [

            $customer['full_name'],

            $invoiceNo,

            $amount

        ]
    ]
];

$ch = curl_init();

curl_setopt_array($ch, [

    CURLOPT_URL =>
        "https://api.interakt.ai/v1/public/message/",

    CURLOPT_POST => true,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_HTTPHEADER => [

        "Authorization: Basic "
        . INTERAKT_API_KEY,

        "Content-Type: application/json"
    ],

    CURLOPT_POSTFIELDS =>
        json_encode($payload)

]);

$response = curl_exec($ch);

curl_close($ch);