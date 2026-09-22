<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Géocodage via Photon (Komoot / OpenStreetMap) — gratuit, sans clé API.
 * Convertit commune + arrondissement + quartier en coordonnées GPS précises.
 *
 * Résultats mis en cache en mémoire (durée = requête HTTP entrante).
 */
class GeocodingService
{
    private const PHOTON_URL = 'https://photon.komoot.io/api/';

    // Bounding box Bénin : lon_min,lat_min,lon_max,lat_max
    private const BENIN_BBOX = '0.773,6.142,3.851,12.409';

    // Limites lat/lng pour valider les résultats (Bénin)
    private const LAT_MIN = 6.1;
    private const LAT_MAX = 12.5;
    private const LNG_MIN = 0.7;
    private const LNG_MAX = 3.9;

    // Cache en mémoire pour la durée de la requête
    private static array $cache = [];

    /**
     * Résout commune + arrondissement + quartier → [lat, lng] via Photon.
     * Retourne null si indisponible, hors Bénin ou timeout.
     */
    public static function geocode(
        string  $commune,
        ?string $arrondissement = null,
        ?string $neighborhood   = null
    ): ?array {
        // Construire la requête du plus précis au moins précis
        $parts = array_filter([
            $neighborhood,
            $arrondissement,
            $commune,
            'Bénin',
        ], fn($v) => (string) $v !== '');

        $query    = implode(', ', $parts);
        $cacheKey = strtolower($query);

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        try {
            $resp = Http::timeout(5)->withHeaders([
                'User-Agent' => 'CovoiturageBeninBackend/1.0',
            ])->get(self::PHOTON_URL, [
                'q'     => $query,
                'limit' => 1,
                'lang'  => 'fr',
                'bbox'  => self::BENIN_BBOX,
            ]);

            if (! $resp->successful()) {
                return self::$cache[$cacheKey] = null;
            }

            $features = $resp->json('features') ?? [];
            if (empty($features)) {
                return self::$cache[$cacheKey] = null;
            }

            // GeoJSON : coordinates = [longitude, latitude]
            $coords = $features[0]['geometry']['coordinates'] ?? null;
            if (! $coords || count($coords) < 2) {
                return self::$cache[$cacheKey] = null;
            }

            $lat = (float) $coords[1];
            $lng = (float) $coords[0];

            // Rejeter tout résultat hors du Bénin
            if ($lat < self::LAT_MIN || $lat > self::LAT_MAX
                || $lng < self::LNG_MIN || $lng > self::LNG_MAX) {
                return self::$cache[$cacheKey] = null;
            }

            return self::$cache[$cacheKey] = [$lat, $lng];

        } catch (\Throwable) {
            return self::$cache[$cacheKey] = null;
        }
    }

    /** Vide le cache (tests unitaires). */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
