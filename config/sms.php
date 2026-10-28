<?php

use App\Support\Sms\Senders\LogSmsSender;

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | The default driver used for SMS delivery. Currently only the "log"
    | driver exists, which writes messages to the application log. Replace
    | "log" with a real gateway driver once a provider is integrated.
    |
    */

    'default' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | SMS Drivers
    |--------------------------------------------------------------------------
    |
    | Map of driver name to its SmsSender implementation class.
    |
    */

    'drivers' => [
        'log' => LogSmsSender::class,
    ],

];
