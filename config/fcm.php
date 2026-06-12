<?php

return [
    /*
     * Clé serveur FCM (Legacy HTTP API).
     * Récupérer dans Firebase Console → Project Settings → Cloud Messaging → Server key
     */
    'server_key' => env('FCM_SERVER_KEY', ''),

    'url' => 'https://fcm.googleapis.com/fcm/send',

    /*
     * Icône et couleur par défaut pour les notifications Android.
     * À adapter selon les assets de l'app mobile.
     */
    'icon'  => 'ic_notification',
    'color' => '#F97316', // orange MINIZON
];
