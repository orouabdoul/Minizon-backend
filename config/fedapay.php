<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FedaPay API Keys
    |--------------------------------------------------------------------------
    | Clés disponibles dans le Dashboard FedaPay :
    | https://app.fedapay.com/settings/api-keys
    */

    'secret_key'     => env('FEDAPAY_SECRET_KEY', ''),
    'public_key'     => env('FEDAPAY_PUBLIC_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Environnement : 'sandbox' | 'live'
    |--------------------------------------------------------------------------
    */

    'environment'    => env('FEDAPAY_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Secret du Webhook (distinct du secret API)
    |--------------------------------------------------------------------------
    | Récupéré dans Dashboard → Webhooks → ton endpoint → Signing secret
    */

    'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | URL de callback après paiement (pour les paiements par lien)
    |--------------------------------------------------------------------------
    */

    'callback_url'   => env('FEDAPAY_CALLBACK_URL', env('APP_URL') . '/api/payments/callback'),

    /*
    |--------------------------------------------------------------------------
    | Commission MINIZON (pourcentage prélevé sur chaque transaction)
    |--------------------------------------------------------------------------
    */

    'commission_rate' => env('FEDAPAY_COMMISSION_RATE', 10), // 10 %

    /*
    |--------------------------------------------------------------------------
    | Mapping providers internes → modes FedaPay
    |--------------------------------------------------------------------------
    */

    'modes' => [
        'mtn'     => 'mtn_open',  // MTN Mobile Money Bénin
        'moov'    => 'moov',      // Moov Money Bénin
        'celtiis' => 'sbin',      // Celtiis / Liberté Bénin
        'card'    => null,        // Paiement par lien (redirect)
    ],

];
