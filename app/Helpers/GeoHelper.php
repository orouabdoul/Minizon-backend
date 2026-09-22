<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class GeoHelper
{
    // =========================================================================
    //  TABLE DE COORDONNÉES — Communes et arrondissements du Bénin
    //  Utilisée pour résoudre les GPS quand ils ne sont pas fournis.
    //  Clés normalisées en minuscules sans accents (via normalizeKey()).
    // =========================================================================

    private static array $COORDINATES = [
        // ── Cotonou ──────────────────────────────────────────────────────────
        'cotonou' => [
            '_center'              => [6.3696, 2.3912],
            '1er arrondissement'   => [6.3530, 2.4293],
            '2eme arrondissement'  => [6.3590, 2.4027],
            '3eme arrondissement'  => [6.3637, 2.3800],
            '4eme arrondissement'  => [6.3762, 2.3623],
            '5eme arrondissement'  => [6.3875, 2.3756],
            '6eme arrondissement'  => [6.3610, 2.4250],
            '7eme arrondissement'  => [6.3944, 2.4183],
            '8eme arrondissement'  => [6.3819, 2.4476],
            '9eme arrondissement'  => [6.3583, 2.4594],
            '10eme arrondissement' => [6.3471, 2.4476],
            '11eme arrondissement' => [6.3297, 2.4328],
            '12eme arrondissement' => [6.3247, 2.4066],
            '13eme arrondissement' => [6.3247, 2.3722],
            // Quartiers Cotonou — points précis
            'akpakpa'              => [6.3570, 2.4319],
            'jonquet'              => [6.3527, 2.4295],
            'zongo'                => [6.3594, 2.4219],
            'dantokpa'             => [6.3566, 2.4258],
            'fidjrosse'            => [6.3883, 2.3519],
            'houeyiho'             => [6.3919, 2.3817],
            'agla'                 => [6.3878, 2.3747],
            'cadjehoun'            => [6.3769, 2.3628],
            'haie vive'            => [6.3750, 2.3657],
            'haievive'             => [6.3750, 2.3657],
            'aidjedo'              => [6.3608, 2.4014],
            'vedoko'               => [6.3947, 2.4092],
            'menontin'             => [6.3975, 2.4097],
            'fifadji'              => [6.3953, 2.4217],
            'ladji'                => [6.3614, 2.3742],
            'xwlacodji'            => [6.3656, 2.3572],
            'missebo'              => [6.3656, 2.4058],
            'placodji'             => [6.3596, 2.4033],
            'gbedjromedji'         => [6.3817, 2.4442],
            'gbedjromede'          => [6.3817, 2.4442],
            'vossa'                => [6.3625, 2.4389],
            'agontikon'            => [6.3660, 2.3900],
            'zogbo'                => [6.3641, 2.3819],
            'port bouet'           => [6.3480, 2.4050],
        ],
        // ── Porto-Novo ────────────────────────────────────────────────────────
        'porto-novo' => [
            '_center'             => [6.4969, 2.6289],
            '1er arrondissement'  => [6.4969, 2.6289],
            '2eme arrondissement' => [6.4900, 2.6100],
            '3eme arrondissement' => [6.5100, 2.6200],
            '4eme arrondissement' => [6.5200, 2.6400],
            '5eme arrondissement' => [6.5300, 2.6500],
            // Quartiers
            'ouando'              => [6.4813, 2.6425],
            'tokpota'             => [6.4969, 2.6081],
            'agoudohoue'          => [6.4814, 2.6136],
            'houinme'             => [6.5228, 2.6408],
            'djassin'             => [6.4988, 2.6381],
            'segbeya'             => [6.4958, 2.6192],
            'gbede'               => [6.5011, 2.6289],
            'hondjin'             => [6.5000, 2.6500],
        ],
        // ── Abomey-Calavi ─────────────────────────────────────────────────────
        'abomey-calavi' => [
            '_center'                  => [6.4492, 2.3553],
            '1er arrondissement'       => [6.4492, 2.3553],
            '2eme arrondissement'      => [6.4200, 2.3700],
            '3eme arrondissement'      => [6.4700, 2.3200],
            '4eme arrondissement'      => [6.5100, 2.2900],
            '5eme arrondissement'      => [6.5600, 2.2600],
            '6eme arrondissement'      => [6.6000, 2.2400],
            '7eme arrondissement'      => [6.4800, 2.3800],
            '8eme arrondissement'      => [6.4300, 2.3100],
            '9eme arrondissement'      => [6.4600, 2.3400],
            '10eme arrondissement'     => [6.4000, 2.3600],
            // Quartiers
            'godomey'                  => [6.4100, 2.3800],
            'togba'                    => [6.4800, 2.3200],
            'zinvie'                   => [6.5600, 2.2600],
            'glo-djigbe'               => [6.6200, 2.2400],
            'kpanroun'                 => [6.5000, 2.2800],
            'ouedo'                    => [6.4200, 2.3100],
            'hevie'                    => [6.4300, 2.2900],
            'akassato'                 => [6.4600, 2.3400],
            'misserete'                => [6.4800, 2.4000],
        ],
        // ── Sèmè-Kpodji ──────────────────────────────────────────────────────
        'seme-kpodji' => [
            '_center'             => [6.3825, 2.5769],
            '1er arrondissement'  => [6.3825, 2.5769],
            '2eme arrondissement' => [6.3900, 2.5500],
            '3eme arrondissement' => [6.4100, 2.5900],
            '4eme arrondissement' => [6.4300, 2.6100],
            // Arrondissements (noms propres utilisés comme clés)
            'agblangandan'        => [6.3658, 2.5128],
            'djeregbe'            => [6.3750, 2.6010],
            'ekpe'                => [6.3380, 2.5950],
            'aholouyeme'          => [6.4200, 2.5800],
            'tohoue'              => [6.4100, 2.5700],
            // Quartiers
            'kpoba'               => [6.3900, 2.5400],
            'seme-kpodji centre'  => [6.3825, 2.5769],
        ],
        // ── Parakou ───────────────────────────────────────────────────────────
        'parakou' => [
            '_center'             => [9.3370, 2.6280],
            '1er arrondissement'  => [9.3370, 2.6280],
            '2eme arrondissement' => [9.3500, 2.6100],
            '3eme arrondissement' => [9.3200, 2.6400],
            // Quartiers
            'zongo'               => [9.3400, 2.6200],
            'gah'                 => [9.3300, 2.6100],
            'titirou'             => [9.3500, 2.6400],
            'banikanni'           => [9.3600, 2.6300],
            'madina'              => [9.3200, 2.6200],
        ],
        // ── Natitingou ────────────────────────────────────────────────────────
        'natitingou' => [
            '_center'             => [10.3161, 1.3781],
            '1er arrondissement'  => [10.3161, 1.3781],
            '2eme arrondissement' => [10.3300, 1.3600],
            '3eme arrondissement' => [10.3000, 1.3900],
        ],
        // ── Djougou ───────────────────────────────────────────────────────────
        'djougou' => [
            '_center'             => [9.7089, 1.6678],
            '1er arrondissement'  => [9.7089, 1.6678],
            '2eme arrondissement' => [9.7200, 1.6500],
            '3eme arrondissement' => [9.6900, 1.6800],
        ],
        // ── Bohicon ───────────────────────────────────────────────────────────
        'bohicon' => [
            '_center'             => [7.1786, 2.0669],
            '1er arrondissement'  => [7.1786, 2.0669],
            '2eme arrondissement' => [7.1900, 2.0500],
            '3eme arrondissement' => [7.1600, 2.0800],
        ],
        // ── Abomey ────────────────────────────────────────────────────────────
        'abomey' => [
            '_center'             => [7.1836, 1.9831],
            '1er arrondissement'  => [7.1836, 1.9831],
            '2eme arrondissement' => [7.1900, 1.9700],
            '3eme arrondissement' => [7.1700, 1.9900],
        ],
        // ── Lokossa ───────────────────────────────────────────────────────────
        'lokossa' => [
            '_center'             => [6.6351, 1.7173],
            '1er arrondissement'  => [6.6351, 1.7173],
            '2eme arrondissement' => [6.6500, 1.7000],
            '3eme arrondissement' => [6.6200, 1.7300],
        ],
        // ── Ouidah ────────────────────────────────────────────────────────────
        'ouidah' => [
            '_center'             => [6.3636, 2.0865],
            '1er arrondissement'  => [6.3636, 2.0865],
            '2eme arrondissement' => [6.3800, 2.0700],
            '3eme arrondissement' => [6.3500, 2.1000],
            '4eme arrondissement' => [6.3300, 2.1200],
            '5eme arrondissement' => [6.3100, 2.0900],
            '6eme arrondissement' => [6.3800, 2.1200],
        ],
        // ── Kandi ─────────────────────────────────────────────────────────────
        'kandi' => [
            '_center'             => [11.1346, 2.9372],
            '1er arrondissement'  => [11.1346, 2.9372],
            '2eme arrondissement' => [11.1500, 2.9200],
            '3eme arrondissement' => [11.1200, 2.9500],
        ],
        // ── Malanville ────────────────────────────────────────────────────────
        'malanville' => [
            '_center'             => [11.8686, 3.3872],
            '1er arrondissement'  => [11.8686, 3.3872],
            '2eme arrondissement' => [11.8800, 3.3700],
        ],
        // ── Autres communes ───────────────────────────────────────────────────
        'dassa-zoume'  => ['_center' => [7.7594, 2.1898]],
        'savalou'      => ['_center' => [7.9281, 1.9764]],
        'bante'        => ['_center' => [8.4253, 1.8755]],
        'tchaourou'    => ['_center' => [8.8764, 2.6063]],
        'nikki'        => ['_center' => [9.9403, 3.2097]],
        'banikoara'    => ['_center' => [11.3047, 2.4367]],
        'gogounou'     => ['_center' => [10.8383, 2.8397]],
        'sinende'      => ['_center' => [10.0011, 2.3769]],
        'bembereke'    => ['_center' => [10.2256, 2.6658]],
        'ndali'        => ['_center' => [9.8569, 2.7311]],
        'dogbo'        => ['_center' => [6.7914, 1.7808]],
        'come'         => ['_center' => [6.4108, 1.8717]],
        'ketou'        => ['_center' => [7.3600, 2.5989]],
        'pobe'         => ['_center' => [6.9764, 2.6600]],
        'adjohoun'     => ['_center' => [6.6800, 2.4900]],
        'zagnanado'    => ['_center' => [7.2542, 2.3394]],
        'cove'         => ['_center' => [7.2200, 2.3900]],
        'sakete'       => ['_center' => [6.7328, 2.6561]],
        'allada'       => ['_center' => [6.6697, 2.1514]],
        'kpomasse'     => ['_center' => [6.5453, 2.1394]],
        'athieme'      => ['_center' => [6.5814, 1.6653]],
        'azove'        => ['_center' => [6.9653, 1.5247]],
        'aplahoue'     => ['_center' => [6.9236, 1.6908]],
        'glazoue'      => ['_center' => [7.9736, 2.2483]],
        'tanguieta'       => ['_center' => [10.6194, 1.2675]],
        'so-ava'          => ['_center' => [6.5167, 2.4000]],
        'toffo'           => ['_center' => [6.9667, 2.0667]],
        'tori-bossito'    => ['_center' => [6.5667, 2.1333]],
        'ze'              => ['_center' => [6.7667, 2.2167]],
        'kalale'          => ['_center' => [10.2833, 3.3833]],
        'perere'          => ['_center' => [10.6333, 3.1000]],
        'karimama'        => ['_center' => [12.0667, 3.1833]],
        'segbana'         => ['_center' => [10.9333, 3.7000]],
        'boukombe'        => ['_center' => [10.1833, 1.1000]],
        'cobly'           => ['_center' => [10.6667, 1.3667]],
        'kerou'           => ['_center' => [10.8167, 2.1167]],
        'kouande'         => ['_center' => [10.3333, 1.6833]],
        'materi'          => ['_center' => [10.7000, 1.0667]],
        'pehunco'         => ['_center' => [10.2333, 1.5167]],
        'toucountouna'    => ['_center' => [10.5667, 1.5833]],
        'bassila'         => ['_center' => [9.0083, 1.6667]],
        'copargo'         => ['_center' => [9.8167, 1.5500]],
        'ouake'           => ['_center' => [9.7167, 1.3833]],
        'ouesse'          => ['_center' => [8.4500, 2.5333]],
        'save'            => ['_center' => [8.0333, 2.4667]],
        'djakotome'       => ['_center' => [6.9000, 1.7000]],
        'klouekamne'      => ['_center' => [6.9667, 1.9000]],
        'lalo'            => ['_center' => [7.0000, 1.9333]],
        'toviklin'        => ['_center' => [6.9333, 1.9500]],
        'bopa'            => ['_center' => [6.5833, 1.9833]],
        'grand-popo'      => ['_center' => [6.2833, 1.8167]],
        'houeyogbe'       => ['_center' => [6.7500, 1.8500]],
        'akpro-misserete' => ['_center' => [6.5833, 2.6333]],
        'avrankou'        => ['_center' => [6.5667, 2.7000]],
        'bonou'           => ['_center' => [6.7167, 2.6000]],
        'dangbo'          => ['_center' => [6.6000, 2.5667]],
        'adja-ouere'      => ['_center' => [7.1000, 2.7333]],
        'ifangni'         => ['_center' => [6.6667, 2.6833]],
        'agbangnizoun'    => ['_center' => [7.0833, 1.9833]],
        'djidja'          => ['_center' => [7.3333, 1.9667]],
        'ouinhi'          => ['_center' => [7.0000, 2.4833]],
        'za-kpota'        => ['_center' => [7.1667, 2.1000]],
        'zogbodomey'      => ['_center' => [7.1000, 2.1333]],
    ];

    // =========================================================================
    //  RÉSOLUTION DE COORDONNÉES GPS
    //  commune → arrondissement → quartier (lookup progressif)
    // =========================================================================

    /**
     * Résout les coordonnées GPS à partir de la hiérarchie géographique.
     * Priorité : quartier → arrondissement → commune.
     * Retourne [lat, lng] ou null si commune inconnue.
     */
    public static function resolveCoordinates(
        string  $city,
        ?string $arrondissement = null,
        ?string $neighborhood   = null
    ): ?array {
        // ── 1. Photon (OpenStreetMap) — coordonnées exactes par quartier/arrondissement ──
        // Appelé dès qu'on a un détail (quartier ou arrondissement) pour dépasser
        // la précision commune-level de la table statique.
        if ($neighborhood || $arrondissement) {
            $photon = \App\Services\GeocodingService::geocode($city, $arrondissement, $neighborhood);
            if ($photon) {
                return $photon;
            }
        }

        // ── 2. Table statique (fallback rapide, sans réseau) ─────────────────
        $cityKey  = self::normalizeKey($city);
        $cityData = self::$COORDINATES[$cityKey] ?? null;

        if (! $cityData) {
            // Commune inconnue de la table → dernier recours Photon avec juste la commune
            return \App\Services\GeocodingService::geocode($city);
        }

        // 2a. Quartier (plus précis)
        if ($neighborhood) {
            $nKey = self::normalizeKey($neighborhood);
            if (isset($cityData[$nKey])) {
                return $cityData[$nKey];
            }
            // Correspondance partielle (ex. "Akpakpa Centre" → "akpakpa")
            foreach ($cityData as $key => $coords) {
                if ($key !== '_center' && str_contains($nKey, $key)) {
                    return $coords;
                }
            }
        }

        // 2b. Arrondissement
        if ($arrondissement) {
            $aKey = self::normalizeKey($arrondissement);
            if (isset($cityData[$aKey])) {
                return $cityData[$aKey];
            }
            // Correspondance partielle (ex. "6ème Arrondissement" → "6eme arrondissement")
            foreach ($cityData as $key => $coords) {
                if ($key !== '_center' && str_contains($aKey, $key)) {
                    return $coords;
                }
            }
        }

        // 2c. Centre de la commune
        return $cityData['_center'] ?? null;
    }

    // =========================================================================
    //  CALCUL DE DISTANCE
    // =========================================================================

    /**
     * Distance à vol d'oiseau entre deux points GPS (formule Haversine).
     * Retourne la distance en kilomètres (sans coefficient routier).
     */
    public static function haversineKm(
        float $lat1, float $lon1,
        float $lat2, float $lon2
    ): float {
        $R    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Distance routière réelle entre deux points GPS.
     * Priorité : ORS API → Haversine × 1.3 (coefficient routes béninoises).
     */
    public static function distanceKm(
        float $lat1, float $lng1,
        float $lat2, float $lng2
    ): float {
        $ors = self::orsRouteKm($lat1, $lng1, $lat2, $lng2);
        if ($ors !== null) {
            return $ors;
        }
        // Haversine × 1.3 pour tenir compte des routes béninoises
        return round(self::haversineKm($lat1, $lng1, $lat2, $lng2) * 1.3, 1);
    }

    /**
     * Appelle OpenRouteService pour obtenir la distance routière réelle.
     * Timeout 5 s — retourne null si API indisponible ou clé absente.
     */
    public static function orsRouteKm(float $lat1, float $lng1, float $lat2, float $lng2): ?float
    {
        $key = config('services.ors.key');
        if (! $key) return null;

        try {
            $resp = Http::timeout(5)->get('https://api.openrouteservice.org/v2/directions/driving-car', [
                'api_key' => $key,
                'start'   => "{$lng1},{$lat1}",
                'end'     => "{$lng2},{$lat2}",
            ]);

            if ($resp->successful()) {
                $summary = $resp->json('routes.0.summary');
                if ($summary && isset($summary['distance'])) {
                    return round($summary['distance'] / 1000, 1);
                }
            }
        } catch (\Throwable) {}

        return null;
    }

    // =========================================================================
    //  RÉSOLUTION GPS COMBINÉE (coords stockées + fallback geocoding)
    // =========================================================================

    /**
     * Retourne les meilleures coordonnées GPS disponibles pour un point.
     *
     * Priorité :
     *   1. Coordonnées déjà stockées (si valides — non nulles et non nulles-à-zéro)
     *   2. GeocodingService (Photon) via commune+arrondissement+quartier
     *   3. Table statique
     *
     * Retourne [lat, lng] ou [null, null] si aucun moyen de résoudre.
     */
    public static function bestCoords(
        ?float  $storedLat,
        ?float  $storedLng,
        string  $city,
        ?string $arrondissement = null,
        ?string $neighborhood   = null
    ): array {
        $lat = ($storedLat && abs($storedLat) > 0.0001) ? $storedLat : null;
        $lng = ($storedLng && abs($storedLng) > 0.0001) ? $storedLng : null;

        if ($lat && $lng) {
            return [$lat, $lng];
        }

        $resolved = self::resolveCoordinates($city, $arrondissement, $neighborhood);
        return $resolved ?? [null, null];
    }

    // =========================================================================
    //  CALCUL DU PRIX PASSAGER
    // =========================================================================

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

        $ratio = min($passengerDistanceKm / $tripDistanceKm, 1.0);

        return max((int) round($ratio * $tripPricePerSeat), $minimumPriceXof);
    }

    // =========================================================================
    //  HELPERS INTERNES
    // =========================================================================

    /**
     * Normalise une chaîne pour la recherche dans la table :
     * minuscules, suppression des accents, trim.
     */
    private static function normalizeKey(string $value): string
    {
        $map = [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];

        $normalized = mb_strtolower(trim($value));
        // Supprime les apostrophes droites et typographiques (ex: N'Dali → ndali)
        $normalized = preg_replace("/['\x{2019}\x{2018}\x{0060}]/u", '', $normalized);
        return strtr($normalized, $map);
    }
}
