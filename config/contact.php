<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Adresse de réception des formulaires de contact
    |--------------------------------------------------------------------------
    |
    | Tu peux la surcharger dans ton .env :
    | CONTACT_FORM_TO="support@mon-domaine.com"
    |
    */

    'to_address' => env('CONTACT_FORM_TO', env('MAIL_FROM_ADDRESS', 'lyravelojaona@gmail.com')),

];
