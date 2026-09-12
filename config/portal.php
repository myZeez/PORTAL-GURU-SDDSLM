<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PID Manager WhatsApp Number
    |--------------------------------------------------------------------------
    |
    | The WhatsApp number (international format, digits only, e.g. 6281234567890)
    | that the pre-filled loan message for an approved PID reservation is sent to.
    | Set PID_MANAGER_PHONE in .env to the real number.
    |
    */

    'pid_manager_phone' => env('PID_MANAGER_PHONE', '6280000000000'),

];
