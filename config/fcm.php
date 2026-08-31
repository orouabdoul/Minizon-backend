<?php

return [
    /*
     * FCM HTTP v1 — authentification via Service Account (OAuth2 / JWT).
     *
     * 1. Firebase Console → Project Settings → Service accounts → Generate new private key
     * 2. Placer le fichier JSON dans storage/app/ (ex : firebase-service-account.json)
     * 3. Renseigner les variables ci-dessous dans .env
     */
    'project_id'       => env('FCM_PROJECT_ID', ''),
    'credentials_path' => env('FCM_CREDENTIALS_PATH', storage_path('app/firebase-service-account.json')),

    /*
     * Icône et couleur par défaut pour les notifications Android.
     */
    'icon'  => 'ic_notification',
    'color' => '#F97316', // orange MINIZON
];
