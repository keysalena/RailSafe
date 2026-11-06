<?php

return [
    // false = ESP32 tanpa header Authorization tetap diterima (dipetakan ke Node 2 pertama). Untuk demo di jaringan lokal.
    // true  = wajib header "Authorization: Bearer <token>" (disarankan untuk deployment).
    'require_token' => env('RAILSAFE_REQUIRE_TOKEN', false),
];
