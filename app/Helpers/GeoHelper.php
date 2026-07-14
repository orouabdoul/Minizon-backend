<?php

namespace App\Helpers;

class GeoHelper
{
    /**
     * Distance à vol d'oiseau entre deux points GPS (formule Haversine).
     * Retourne la distance en kilomètres.
     */
    public static function haversineKm(
        float $lat1, float $lon1,
        float $lat2, float $lon2
    ): float {
        $R    = 6371.0; // Rayon terrestre en km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Prix automatique passager proportionnel à sa distance.
     *
     * prix_passager = (distance_passager / distance_trajet) × prix_par_place
     *
     * Un minimum de 100 XOF est appliqué pour éviter les prix nuls.
     */
    public static function calculatePassengerPrice(
        float $passengerDistanceKm,
        float $tripDistanceKm,
        int   $tripPricePerSeat,
        int   $minimumPriceXof = 100
    ): int {
        if ($tripDistanceKm <= 0) {
            return $tripPricePerSeat;
        }

        $ratio = $passengerDistanceKm / $tripDistanceKm;

        // Plafonner à 1 : un passager ne peut pas payer plus que le prix total
        $ratio = min($ratio, 1.0);

        return max((int) round($ratio * $tripPricePerSeat), $minimumPriceXof);
    }
}
