<?php

return [

    'name' => env(
        'COI_APP_NAME',
        'Conflict of Interest Declaration System',
    ),

    'url' => env(
        'COI_APP_URL',
        env('APP_URL')
    ),

    'support_email' => env(
        'COI_SUPPORT_EMAIL',
        env('MAIL_FROM_ADDRESS')
    ),

];