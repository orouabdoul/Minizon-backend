<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;

class DriverAddTripController extends Controller
{
    // =========================================================================
    //  Catalogues statiques
    // =========================================================================

    private const PREFERENCE_CATALOG = [
        ['option' => 'no_smoking',  'title' => 'Non-fumeur',      'subtitle' => 'Cigarettes interdites dans le véhicule', 'icon' => 'smoke_free'],
        ['option' => 'music',       'title' => 'Musique',          'subtitle' => 'Musique autorisée en trajet',            'icon' => 'music_note'],
        ['option' => 'ac',          'title' => 'Climatisé',        'subtitle' => 'Climatisation disponible',               'icon' => 'ac_unit'],
        ['option' => 'chat',        'title' => 'Discussion',       'subtitle' => 'Ambiance conviviale et bavarde',         'icon' => 'chat_bubble_outline'],
        ['option' => 'no_luggage',  'title' => 'Bagages limités',  'subtitle' => 'Bagages légers uniquement',              'icon' => 'luggage'],
        ['option' => 'female_only', 'title' => 'Femmes seulement', 'subtitle' => 'Réservé aux passagères',                'icon' => 'female'],
        ['option' => 'pets',        'title' => 'Animaux acceptés', 'subtitle' => 'Les animaux de compagnie sont bienvenus', 'icon' => 'pets'],
        ['option' => 'quiet',       'title' => 'Silence',          'subtitle' => 'Trajet calme, pas de téléphone',         'icon' => 'volume_off'],
    ];

    private const CANCELLATION_POLICIES = [
        [
            'policy'      => 'flexible',
            'title'       => 'Flexible',
            'description' => 'Remboursement complet jusqu\'à 1h avant le départ.',
        ],
        [
            'policy'      => 'moderate',
            'title'       => 'Modérée',
            'description' => '50 % remboursé si annulé au moins 24h avant le départ.',
        ],
        [
            'policy'      => 'strict',
            'title'       => 'Stricte',
            'description' => 'Aucun remboursement après confirmation de la réservation.',
        ],
    ];

    private const BOOKING_MODES = [
        [
            'mode'        => 'instant',
            'title'       => 'Réservation instantanée',
            'description' => 'Les passagers sont acceptés automatiquement dès la réservation.',
            'icon'        => 'bolt',
        ],
        [
            'mode'        => 'approval',
            'title'       => 'Sur approbation',
            'description' => 'Chaque demande de réservation vous est soumise pour validation.',
            'icon'        => 'how_to_reg',
        ],
    ];

    private const RECURRING_DAY_OPTIONS = [
        ['key' => 'monday',    'label' => 'Lundi'],
        ['key' => 'tuesday',   'label' => 'Mardi'],
        ['key' => 'wednesday', 'label' => 'Mercredi'],
        ['key' => 'thursday',  'label' => 'Jeudi'],
        ['key' => 'friday',    'label' => 'Vendredi'],
        ['key' => 'saturday',  'label' => 'Samedi'],
        ['key' => 'sunday',    'label' => 'Dimanche'],
    ];

    private const BENIN_CITIES = [
        'Cotonou', 'Porto-Novo', 'Abomey-Calavi', 'Bohicon', 'Parakou',
        'Abomey', 'Natitingou', 'Lokossa', 'Ouidah', 'Kandi',
        'Djougou', 'Malanville', 'Azovè', 'Dassa-Zoumé', 'Savè',
        'Pobè', 'Aplahoué', 'Comè', 'Bembèrèkè', 'Tchaourou',
        'Nikki', 'Bassila', 'Tanguiéta', 'Banikoara', 'Gogounou',
        'Sinendé', 'N\'Dali', 'Kpomassè', 'Sèmè-Kpodji', 'Allada',
        'Zagnanado', 'Covè', 'Adjohoun', 'Dogbo', 'Athiémé',
        'Glazoué', 'Savalou', 'Bantè', 'Kétou', 'Sakété',
    ];

    /**
     * Arrondissements par commune — hiérarchie administrative du Bénin.
     * Clé = nom commune (minuscules normalisées), valeur = liste des arrondissements.
     */
    private const ARRONDISSEMENTS = [
        'cotonou' => [
            '1er Arrondissement', '2ème Arrondissement', '3ème Arrondissement',
            '4ème Arrondissement', '5ème Arrondissement', '6ème Arrondissement',
            '7ème Arrondissement', '8ème Arrondissement', '9ème Arrondissement',
            '10ème Arrondissement', '11ème Arrondissement', '13ème Arrondissement',
        ],
        'porto-novo' => [
            '1er Arrondissement', '2ème Arrondissement', '3ème Arrondissement',
            '4ème Arrondissement', '5ème Arrondissement',
        ],
        'parakou' => [
            '1er Arrondissement', '2ème Arrondissement', '3ème Arrondissement',
        ],
        'abomey-calavi' => [
            'Abomey-Calavi', 'Godomey', 'Hêvié', 'Kpanroun', 'Ouèdo',
            'Togba', 'Zinvié', 'Akassato', 'Glo-Djigbé', 'Sékou',
        ],
        'bohicon' => [
            'Bohicon I', 'Bohicon II', 'Avogbanna', 'Gnidjazoun',
            'Kpokissa', 'Ouinhi', 'Zogbodomey',
        ],
        'abomey' => [
            'Abomey', 'Agbangnizoun', 'Cové', 'Djidja', 'Ouinhi',
            'Za-Kpota', 'Zogbodomey',
        ],
        'natitingou' => [
            'Natitingou I', 'Natitingou II', 'Kouarfa', 'Perma',
        ],
        'lokossa' => [
            'Lokossa', 'Koudo', 'Ouèdèmè', 'Houin',
        ],
        'ouidah' => [
            'Ouidah I', 'Ouidah II', 'Ouidah III', 'Ouidah IV',
            'Avlékété', 'Houakpè-Daho', 'Pahou', 'Savi',
        ],
        'kandi' => [
            'Kandi I', 'Kandi II', 'Kandi III',
        ],
        'djougou' => [
            'Djougou I', 'Djougou II', 'Djougou III',
        ],
        'sèmè-kpodji' => [
            'Sèmè-Kpodji', 'Agblangandan', 'Djèrègbé', 'Ekpè',
        ],
        'allada' => [
            'Allada', 'Attogon', 'Avakpa', 'Hinvi', 'Kpannou',
            'Lissègazoun', 'Lon-Agonmey', 'Sékou', 'Togoudo', 'Tokpa',
        ],
        'glazoué' => [
            'Glazoué', 'Assanté', 'Djaloukou', 'Gomé', 'Kpingni',
            'Ouèssè', 'Sokponta', 'Thio',
        ],
        'dassa-zoumé' => [
            'Dassa I', 'Dassa II', 'Dassa III',
        ],
        'savè' => [
            'Savè', 'Besse', 'Kaboua', 'Ouèssè', 'Tchaourou',
        ],
    ];

    // Taux de commission plateforme par défaut (%)
    private const DEFAULT_COMMISSION = 10;

    // =========================================================================
    //  GET /api/driver/trip-form
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/trip-form',
        operationId: 'driverTripFormData',
        summary: "Données d'initialisation du formulaire de création de trajet",
        description: "Retourne en un seul appel toutes les données nécessaires pour afficher le formulaire : véhicules approuvés, villes du Bénin, préférences, modes de réservation, politiques d'annulation, jours de récurrence, taux de commission et prix suggéré.",
        tags: ['🚗 Driver — Ajouter un trajet'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données du formulaire',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Données du formulaire.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'vehicles',
                                    type: 'array',
                                    description: 'Véhicules approuvés du conducteur',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id',                 type: 'integer'),
                                            new OA\Property(property: 'brand',              type: 'string',  example: 'Toyota'),
                                            new OA\Property(property: 'model',              type: 'string',  example: 'Camry'),
                                            new OA\Property(property: 'vehicle_type',       type: 'string',  enum: ['car', 'moto', 'other']),
                                            new OA\Property(property: 'vehicle_type_label', type: 'string',  example: 'Voiture'),
                                            new OA\Property(property: 'vehicle_type_slug',  type: 'string',  nullable: true, example: 'voiture', description: 'Slug du type de véhicule'),
                                            new OA\Property(property: 'available_seats',    type: 'integer', example: 4),
                                            new OA\Property(property: 'license_plate',      type: 'string',  example: 'RB 1234 X'),
                                            new OA\Property(property: 'color',              type: 'string',  nullable: true),
                                            new OA\Property(property: 'year',               type: 'integer', nullable: true),
                                        ]
                                    )
                                ),
                                                new OA\Property(property: 'has_approved_vehicle', type: 'boolean'),
                                new OA\Property(property: 'cities',    type: 'array', description: 'Liste des communes du Bénin', items: new OA\Items(type: 'string')),
                                new OA\Property(
                                    property: 'arrondissements',
                                    type: 'object',
                                    description: 'Arrondissements par commune (clé = commune en minuscules). Ex: {"cotonou": ["1er Arrondissement", ...]}. Flutter : filtrer selon la commune choisie.',
                                    additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string'))
                                ),
                                new OA\Property(property: 'preferences',            type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'booking_modes',          type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'cancellation_policies',  type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'recurring_day_options',  type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(
                                    property: 'commission',
                                    type: 'object',
                                    description: 'Taux de commission plateforme et exemple de calcul',
                                    properties: [
                                        new OA\Property(property: 'rate_percent',       type: 'integer', example: 10),
                                        new OA\Property(property: 'driver_share',        type: 'integer', example: 90, description: 'Part conducteur (%)'),
                                        new OA\Property(property: 'example_price',       type: 'integer', example: 5000),
                                        new OA\Property(property: 'example_driver_net',  type: 'integer', example: 4500),
                                        new OA\Property(property: 'example_platform_fee',type: 'integer', example: 500),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'price_suggestion',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'default', type: 'integer', example: 5000),
                                        new OA\Property(property: 'min',     type: 'integer', example: 500),
                                        new OA\Property(property: 'max',     type: 'integer', example: 50000),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Compte non approuvé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function formData(Request $request): JsonResponse
    {
        $user = $request->user();

        $vehicles = Vehicle::where('user_id', $user->id)
            ->where('verification_status', 'approved')
            ->with('vehicleType')
            ->get()
            ->map(fn (Vehicle $v) => [
                'id'                 => $v->id,
                'brand'              => $v->brand,
                'model'              => $v->model,
                'vehicle_type'       => $this->resolveVehicleType($v),
                'vehicle_type_label' => $this->resolveVehicleTypeLabel($v),
                'vehicle_type_slug'  => $v->vehicleType?->slug,
                'available_seats'    => $v->available_seats,
                'license_plate'      => $v->license_plate,
                'color'              => $v->color,
                'year'               => $v->year,
            ]);

        $commission = self::DEFAULT_COMMISSION;
        $example    = 5000;

        return $this->apiResponse(true, 'Données du formulaire.', [
            'vehicles'             => $vehicles,
            'has_approved_vehicle' => $vehicles->isNotEmpty(),
            'cities'               => self::BENIN_CITIES,
            // Arrondissements par commune : { "cotonou": ["1er Arrondissement", ...], ... }
            // Flutter : charger dynamiquement selon la commune choisie
            'arrondissements'      => self::ARRONDISSEMENTS,
            'preferences'          => self::PREFERENCE_CATALOG,
            'booking_modes'        => self::BOOKING_MODES,
            'cancellation_policies'=> self::CANCELLATION_POLICIES,
            'recurring_day_options'=> self::RECURRING_DAY_OPTIONS,
            'commission'           => [
                'rate_percent'        => $commission,
                'driver_share'        => 100 - $commission,
                'example_price'       => $example,
                'example_driver_net'  => (int) round($example * (1 - $commission / 100)),
                'example_platform_fee'=> (int) round($example * $commission / 100),
            ],
            'price_suggestion'     => ['default' => 5000, 'min' => 500, 'max' => 50000],
        ]);
    }

    // =========================================================================
    //  POST /api/driver/trip-publish
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/trip-publish',
        operationId: 'driverTripPublish',
        summary: 'Publier un nouveau trajet',
        description: "Crée et publie un trajet depuis le formulaire mobile. Supporte les champs enrichis : GPS précis, mode de réservation (instant/approval), durée estimée, arrêts intermédiaires (waypoints), politique d'annulation, récurrence et préférences conducteur. Le conducteur doit posséder au moins un véhicule approuvé.",
        tags: ['🚗 Driver — Ajouter un trajet'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'vehicle_id',
                    'departure_city',
                    'arrival_city',
                    'departure_date', 'departure_time',
                    'price_per_seat',
                ],
                properties: [
                    new OA\Property(property: 'vehicle_id',              type: 'integer', example: 5),

                    // ── Départ (commune → arrondissement → quartier → point précis) ──
                    new OA\Property(property: 'departure_city',           type: 'string',  example: 'Cotonou',             description: 'Commune / ville de départ'),
                    new OA\Property(property: 'departure_arrondissement', type: 'string',  example: '6ème Arrondissement', nullable: true, description: 'Arrondissement de départ'),
                    new OA\Property(property: 'departure_neighborhood',   type: 'string',  example: 'Akpakpa',             nullable: true, description: 'Quartier de départ'),
                    new OA\Property(property: 'departure_point',          type: 'string',  example: 'Face pharmacie Centrale', nullable: true, description: 'Point de rendez-vous précis'),
                    new OA\Property(property: 'departure_latitude',       type: 'number',  example: 6.3703,  nullable: true),
                    new OA\Property(property: 'departure_longitude',      type: 'number',  example: 2.3912,  nullable: true),

                    // ── Arrivée (commune → arrondissement → quartier → point précis) ──
                    new OA\Property(property: 'arrival_city',             type: 'string',  example: 'Parakou',             description: 'Commune / ville d\'arrivée'),
                    new OA\Property(property: 'arrival_arrondissement',   type: 'string',  example: '1er Arrondissement',  nullable: true, description: 'Arrondissement d\'arrivée'),
                    new OA\Property(property: 'arrival_neighborhood',     type: 'string',  example: 'Zongo',               nullable: true, description: 'Quartier d\'arrivée'),
                    new OA\Property(property: 'arrival_point',            type: 'string',  example: 'Gare routière centrale', nullable: true, description: 'Point de dépôt précis'),
                    new OA\Property(property: 'arrival_latitude',         type: 'number',  example: 9.3370,  nullable: true),
                    new OA\Property(property: 'arrival_longitude',        type: 'number',  example: 2.6280,  nullable: true),
                    // Date & heure (depuis les pickers Flutter — format local)
                    new OA\Property(property: 'departure_date',          type: 'string',  example: '10/07/2026', description: 'Format jj/mm/aaaa'),
                    new OA\Property(property: 'departure_time',          type: 'string',  example: '07:00',       description: 'Format HH:mm'),
                    new OA\Property(property: 'estimated_duration_minutes', type: 'integer', example: 300, nullable: true, description: 'Durée estimée en minutes (optionnel — backend calcule si absent)'),
                    // Capacité & réservation
                    new OA\Property(property: 'total_seats',             type: 'integer', example: 3,     nullable: true),
                    new OA\Property(property: 'booking_mode',            type: 'string',  example: 'instant', enum: ['instant', 'approval'], nullable: true),
                    new OA\Property(property: 'max_per_booking',         type: 'integer', example: 2,     nullable: true, description: 'Places max par passager (1-total_seats)'),
                    // Prix
                    new OA\Property(property: 'price_per_seat',          type: 'integer', example: 5000),
                    // Contenu
                    new OA\Property(property: 'description',             type: 'string',  example: 'Pas de gros bagages.', nullable: true),
                    new OA\Property(
                        property: 'waypoints',
                        type: 'array',
                        nullable: true,
                        description: 'Arrêts intermédiaires',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'city',                    type: 'string',  example: 'Bohicon'),
                                new OA\Property(property: 'neighborhood',            type: 'string',  example: 'Carrefour Bohicon'),
                                new OA\Property(property: 'arrival_offset_minutes',  type: 'integer', example: 90,   description: 'Minutes après l\'heure de départ'),
                                new OA\Property(property: 'extra_price',             type: 'integer', example: 2000, description: 'Prix supplémentaire pour passagers s\'arrêtant ici', nullable: true),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'preferences',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'string', example: 'no_smoking')
                    ),
                    new OA\Property(property: 'cancellation_policy', type: 'string', enum: ['flexible', 'moderate', 'strict'], nullable: true),
                    // Récurrence
                    new OA\Property(property: 'is_recurring',        type: 'boolean', example: false, nullable: true),
                    new OA\Property(
                        property: 'recurring_days',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(type: 'string', example: 'monday')
                    ),
                    new OA\Property(property: 'recurring_end_date',  type: 'string', format: 'date', example: '2026-09-30', nullable: true),
                    // Brouillon
                    new OA\Property(property: 'is_published', type: 'boolean', example: true, nullable: true, description: 'false = sauvegarder en brouillon'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Trajet publié avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Trajet publié avec succès.'),
                        new OA\Property(property: 'body',    ref: '#/components/schemas/TripPublishResponse'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Véhicule non approuvé ou non autorisé', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données invalides',                       content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function publish(Request $request): JsonResponse
    {
        $validPreferences = array_column(self::PREFERENCE_CATALOG, 'option');
        $validDays        = array_column(self::RECURRING_DAY_OPTIONS, 'key');

        $validated = $request->validate([
            'vehicle_id'                 => 'required|integer|exists:vehicles,id',

            // Départ — hiérarchie : commune → arrondissement → quartier → point précis
            'departure_city'             => 'required|string|max:100',
            'departure_arrondissement'   => 'nullable|string|max:150',
            'departure_neighborhood'     => 'nullable|string|max:100',
            'departure_point'            => 'nullable|string|max:200',
            'departure_latitude'         => 'nullable|numeric|between:-90,90',
            'departure_longitude'        => 'nullable|numeric|between:-180,180',

            // Arrivée — hiérarchie : commune → arrondissement → quartier → point précis
            'arrival_city'               => 'required|string|max:100',
            'arrival_arrondissement'     => 'nullable|string|max:150',
            'arrival_neighborhood'       => 'nullable|string|max:100',
            'arrival_point'              => 'nullable|string|max:200',
            'arrival_latitude'           => 'nullable|numeric|between:-90,90',
            'arrival_longitude'          => 'nullable|numeric|between:-180,180',

            // Date & heure
            'departure_date'             => 'required|string',
            'departure_time'             => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'estimated_duration_minutes' => 'nullable|integer|min:1|max:1440',

            // Capacité & réservation
            'total_seats'                => 'nullable|integer|min:1|max:20',
            'booking_mode'               => 'nullable|string|in:instant,approval',
            'max_per_booking'            => 'nullable|integer|min:1|max:20',

            // Prix
            'price_per_seat'             => 'required|integer|min:0',

            // Contenu
            'description'                => 'nullable|string|max:500',
            'waypoints'                  => 'nullable|array|max:5',
            'waypoints.*.city'                   => 'required_with:waypoints|string|max:100',
            'waypoints.*.neighborhood'           => 'nullable|string|max:100',
            'waypoints.*.arrival_offset_minutes' => 'required_with:waypoints|integer|min:1|max:1440',
            'waypoints.*.extra_price'            => 'nullable|integer|min:0',

            // Préférences
            'preferences'                => 'nullable|array',
            'preferences.*'              => 'string|in:' . implode(',', $validPreferences),

            // Politique
            'cancellation_policy'        => 'nullable|string|in:flexible,moderate,strict',

            // Récurrence
            'is_recurring'               => 'nullable|boolean',
            'recurring_days'             => 'nullable|array|required_if:is_recurring,true',
            'recurring_days.*'           => 'string|in:' . implode(',', $validDays),
            'recurring_end_date'         => 'nullable|date|after:today',

            // Brouillon
            'is_published'               => 'nullable|boolean',
        ]);

        // ── Vérifier le véhicule ──────────────────────────────────────────────
        $vehicle = Vehicle::where('id', $validated['vehicle_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $vehicle) {
            return $this->apiResponse(false, 'Ce véhicule ne vous appartient pas.', [], 403);
        }

        if ($vehicle->verification_status !== 'approved') {
            return $this->apiResponse(false, 'Votre véhicule doit être approuvé par l\'administration avant de publier un trajet.', [], 403);
        }

        // ── Parser date + heure (format Flutter : jj/mm/aaaa + HH:mm) ────────
        try {
            $departureAt = Carbon::createFromFormat(
                'd/m/Y H:i',
                trim($validated['departure_date']) . ' ' . trim($validated['departure_time']),
                'Africa/Porto-Novo'
            );
        } catch (\Exception) {
            return $this->apiResponse(false, 'Format de date ou d\'heure invalide. Attendu : jj/mm/aaaa et HH:mm.', [], 422);
        }

        if ($departureAt->isPast()) {
            return $this->apiResponse(false, 'L\'heure de départ doit être dans le futur.', [], 422);
        }

        // ── Calculs dérivés ───────────────────────────────────────────────────
        $totalSeats    = $validated['total_seats'] ?? $vehicle->available_seats;
        $maxPerBooking = min($validated['max_per_booking'] ?? $totalSeats, $totalSeats);
        $isPublished   = $validated['is_published'] ?? true;

        // Distance & durée : ORS API → Haversine GPS → table villes
        $routeData = $this->resolveRouteData(
            $validated['departure_city']      ?? null,
            $validated['arrival_city']        ?? null,
            isset($validated['departure_latitude'])  ? (float) $validated['departure_latitude']  : null,
            isset($validated['departure_longitude']) ? (float) $validated['departure_longitude'] : null,
            isset($validated['arrival_latitude'])    ? (float) $validated['arrival_latitude']    : null,
            isset($validated['arrival_longitude'])   ? (float) $validated['arrival_longitude']   : null,
        );

        $distanceKm = $routeData['distance_km'];

        // Durée : priorité au frontend si conducteur a modifié manuellement
        $durationMinutes = $validated['estimated_duration_minutes'] ?? $routeData['duration_minutes'];

        $estimatedArrival = $durationMinutes
            ? $departureAt->copy()->addMinutes($durationMinutes)
            : null;

        // ── Créer le trajet ───────────────────────────────────────────────────
        $trip = Trip::create([
            'user_id'    => $request->user()->id,
            'vehicle_id' => $vehicle->id,

            'departure_city'             => ucfirst(strtolower($validated['departure_city'])),
            'departure_arrondissement'   => isset($validated['departure_arrondissement'])
                                                ? ucfirst($validated['departure_arrondissement'])
                                                : null,
            'departure_neighborhood'     => isset($validated['departure_neighborhood'])
                                                ? ucfirst(strtolower($validated['departure_neighborhood']))
                                                : null,
            'departure_point'            => $validated['departure_point'] ?? null,
            'departure_latitude'         => $validated['departure_latitude'] ?? null,
            'departure_longitude'        => $validated['departure_longitude'] ?? null,

            'arrival_city'               => ucfirst(strtolower($validated['arrival_city'])),
            'arrival_arrondissement'     => isset($validated['arrival_arrondissement'])
                                                ? ucfirst($validated['arrival_arrondissement'])
                                                : null,
            'arrival_neighborhood'       => isset($validated['arrival_neighborhood'])
                                                ? ucfirst(strtolower($validated['arrival_neighborhood']))
                                                : null,
            'arrival_point'              => $validated['arrival_point'] ?? null,
            'arrival_latitude'           => $validated['arrival_latitude'] ?? null,
            'arrival_longitude'          => $validated['arrival_longitude'] ?? null,

            'price_per_seat'             => $validated['price_per_seat'],
            'departure_time'             => $departureAt,
            'distance_km'                => $distanceKm,
            'estimated_duration_minutes' => $durationMinutes,
            'estimated_arrival_time'     => $estimatedArrival,

            'total_seats'     => $totalSeats,
            'available_seats' => $totalSeats,
            'booking_mode'    => $validated['booking_mode'] ?? 'instant',
            'max_per_booking' => $maxPerBooking,

            'description'         => $validated['description'] ?? null,
            'waypoints'           => $validated['waypoints'] ?? null,
            'preferences'         => array_values(array_unique($validated['preferences'] ?? [])) ?: null,
            'cancellation_policy' => $validated['cancellation_policy'] ?? 'flexible',

            'is_recurring'       => $validated['is_recurring'] ?? false,
            'recurring_days'     => ($validated['is_recurring'] ?? false) ? ($validated['recurring_days'] ?? null) : null,
            'recurring_end_date' => ($validated['is_recurring'] ?? false) ? ($validated['recurring_end_date'] ?? null) : null,

            'commission_rate' => self::DEFAULT_COMMISSION,

            'status'       => 'pending',
            'is_published' => $isPublished,
            'published_at' => $isPublished ? now() : null,
        ]);

        // Notifier les admins du nouveau trajet publié
        if ($isPublished) {
            try {
                $profile    = $request->user()->profile;
                $driverName = $profile ? trim("{$profile->first_name} {$profile->last_name}") : $request->user()->phone;
                $route      = ucfirst(strtolower($validated['departure_city'])) . ' → ' . ucfirst(strtolower($validated['arrival_city']));

                AdminNotification::notifyAdmins(
                    type:        'driver',
                    priority:    'normal',
                    title:       'Nouveau trajet publié',
                    description: "{$driverName} a publié un trajet {$route} pour le " . $trip->departure_time?->format('d/m/Y à H:i') . '.',
                    refType:     'trip',
                    refId:       $trip->uuid,
                    userId:      $request->user()->id,
                );
            } catch (\Throwable) {}
        }

        return $this->apiResponse(true, $isPublished ? 'Trajet publié avec succès.' : 'Brouillon sauvegardé.', [
            'uuid'                       => $trip->uuid,
            'status'                     => $trip->status,
            'is_published'               => $trip->is_published,
            'booking_mode'               => $trip->booking_mode,
            'cancellation_policy'        => $trip->cancellation_policy,

            // Géographie complète
            'departure_city'             => $trip->departure_city,
            'departure_arrondissement'   => $trip->departure_arrondissement,
            'departure_neighborhood'     => $trip->departure_neighborhood,
            'departure_point'            => $trip->departure_point,
            'arrival_city'               => $trip->arrival_city,
            'arrival_arrondissement'     => $trip->arrival_arrondissement,
            'arrival_neighborhood'       => $trip->arrival_neighborhood,
            'arrival_point'              => $trip->arrival_point,

            'route'                      => $trip->route(),
            'departure_time'             => $trip->departure_time,
            'estimated_arrival_time'     => $trip->estimated_arrival_time,
            'estimated_duration_minutes' => $trip->estimated_duration_minutes,
            'distance_km'                => $trip->distance_km,
            'distance_source'            => $routeData['source'],
            'price_per_seat'             => $trip->price_per_seat,
            'total_seats'                => $trip->total_seats,
            'max_per_booking'            => $trip->max_per_booking,
            'driver_net_per_seat'        => $trip->driverEarnings(1),
            'platform_fee_per_seat'      => $trip->platformCommission(1),
            'commission_rate'            => $trip->commission_rate,
            'preferences'                => $trip->preferences ?? [],
            'waypoints'                  => $trip->waypoints ?? [],
            'is_recurring'               => $trip->is_recurring,
            'recurring_days'             => $trip->recurring_days ?? [],
        ], 201);
    }

    // =========================================================================
    //  POST /api/driver/trip-estimate
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/trip-estimate',
        operationId: 'driverTripEstimate',
        summary: "Estimer la distance et la durée d'un trajet",
        description: "Retourne la distance en km et la durée estimée en minutes entre deux points.\n\n**Priorité de calcul :**\n1. `ors` — OpenRouteService API (distance routière réelle via GPS)\n2. `gps` — Haversine × 1.3 (GPS disponible mais ORS indisponible)\n3. `city_table` — Table de distances béninoises (noms de villes uniquement)\n\nEnvoyer les coordonnées GPS quand disponibles pour une précision maximale.",
        tags: ['🚗 Driver — Ajouter un trajet'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'departure_city',      type: 'string',  example: 'Cotonou'),
                    new OA\Property(property: 'arrival_city',        type: 'string',  example: 'Parakou'),
                    new OA\Property(property: 'departure_latitude',  type: 'number',  example: 6.3703,  nullable: true),
                    new OA\Property(property: 'departure_longitude', type: 'number',  example: 2.3912,  nullable: true),
                    new OA\Property(property: 'arrival_latitude',    type: 'number',  example: 9.3370,  nullable: true),
                    new OA\Property(property: 'arrival_longitude',   type: 'number',  example: 2.6280,  nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estimation calculée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success',    type: 'boolean', example: true),
                        new OA\Property(property: 'message',    type: 'string'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'distance_km',                type: 'number',  nullable: true, example: 405.0),
                                new OA\Property(property: 'estimated_duration_minutes', type: 'integer', nullable: true, example: 347),
                                new OA\Property(property: 'estimated_duration_label',   type: 'string',  nullable: true, example: '5h 47min'),
                                new OA\Property(property: 'source',                     type: 'string',  example: 'ors', description: 'ors | gps | city_table | unknown'),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function estimate(Request $request): JsonResponse
    {
        $request->validate([
            'departure_city'      => ['nullable', 'string', 'max:100'],
            'arrival_city'        => ['nullable', 'string', 'max:100'],
            'departure_latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'departure_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'arrival_latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'arrival_longitude'   => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $routeData = $this->resolveRouteData(
            $request->input('departure_city'),
            $request->input('arrival_city'),
            $request->filled('departure_latitude')  ? (float) $request->input('departure_latitude')  : null,
            $request->filled('departure_longitude') ? (float) $request->input('departure_longitude') : null,
            $request->filled('arrival_latitude')    ? (float) $request->input('arrival_latitude')    : null,
            $request->filled('arrival_longitude')   ? (float) $request->input('arrival_longitude')   : null,
        );

        $distanceKm  = $routeData['distance_km'];
        $durationMin = $routeData['duration_minutes'];

        return $this->apiResponse(true, 'Estimation calculée.', [
            'distance_km'                => $distanceKm ? round($distanceKm, 1) : null,
            'estimated_duration_minutes' => $durationMin,
            'estimated_duration_label'   => $durationMin ? $this->formatDuration($durationMin) : null,
            'source'                     => $routeData['source'],
        ]);
    }

    // =========================================================================
    //  SCHEMA OA
    // =========================================================================

    #[OA\Schema(
        schema: 'TripPublishResponse',
        properties: [
            new OA\Property(property: 'uuid',                       type: 'string',  format: 'uuid'),
            new OA\Property(property: 'status',                     type: 'string',  example: 'pending'),
            new OA\Property(property: 'is_published',               type: 'boolean'),
            new OA\Property(property: 'booking_mode',               type: 'string',  enum: ['instant', 'approval']),
            new OA\Property(property: 'cancellation_policy',        type: 'string',  enum: ['flexible', 'moderate', 'strict']),
            // ── Géographie départ (commune → arrondissement → quartier → point précis) ──
            new OA\Property(property: 'departure_city',              type: 'string',  example: 'Cotonou',              description: 'Commune de départ'),
            new OA\Property(property: 'departure_arrondissement',    type: 'string',  nullable: true, example: '6ème Arrondissement', description: 'Arrondissement de départ'),
            new OA\Property(property: 'departure_neighborhood',      type: 'string',  nullable: true, example: 'Akpakpa',             description: 'Quartier de départ'),
            new OA\Property(property: 'departure_point',             type: 'string',  nullable: true,                                description: 'Point de rendez-vous précis'),
            new OA\Property(property: 'departure_latitude',          type: 'number',  nullable: true, format: 'float'),
            new OA\Property(property: 'departure_longitude',         type: 'number',  nullable: true, format: 'float'),
            // ── Géographie arrivée ──
            new OA\Property(property: 'arrival_city',                type: 'string',  example: 'Parakou',              description: 'Commune d\'arrivée'),
            new OA\Property(property: 'arrival_arrondissement',      type: 'string',  nullable: true, example: '1er Arrondissement', description: 'Arrondissement d\'arrivée'),
            new OA\Property(property: 'arrival_neighborhood',        type: 'string',  nullable: true, example: 'Zongo',               description: 'Quartier d\'arrivée'),
            new OA\Property(property: 'arrival_point',               type: 'string',  nullable: true,                                description: 'Point d\'arrivée précis'),
            new OA\Property(property: 'arrival_latitude',            type: 'number',  nullable: true, format: 'float'),
            new OA\Property(property: 'arrival_longitude',           type: 'number',  nullable: true, format: 'float'),
            // ── Résumé route ──
            new OA\Property(property: 'route',                      type: 'string',  example: 'Cotonou → Parakou'),
            new OA\Property(property: 'departure_time',             type: 'string',  format: 'date-time'),
            new OA\Property(property: 'estimated_arrival_time',     type: 'string',  format: 'date-time', nullable: true),
            new OA\Property(property: 'estimated_duration_minutes', type: 'integer', nullable: true),
            new OA\Property(property: 'distance_km',                type: 'number',  nullable: true, example: 400.0, description: 'Distance routière réelle (ORS) ou approximée (GPS/villes)'),
            new OA\Property(property: 'distance_source',            type: 'string',  example: 'ors', description: 'ors | gps | city_table | unknown'),
            new OA\Property(property: 'price_per_seat',             type: 'integer'),
            new OA\Property(property: 'total_seats',                type: 'integer'),
            new OA\Property(property: 'max_per_booking',            type: 'integer'),
            new OA\Property(property: 'driver_net_per_seat',        type: 'integer', description: 'Gains nets conducteur par place'),
            new OA\Property(property: 'platform_fee_per_seat',      type: 'integer', description: 'Commission plateforme par place'),
            new OA\Property(property: 'commission_rate',            type: 'integer'),
            new OA\Property(property: 'preferences',                type: 'array',   items: new OA\Items(type: 'string')),
            new OA\Property(property: 'waypoints',                  type: 'array',   items: new OA\Items(type: 'object')),
            new OA\Property(property: 'is_recurring',               type: 'boolean'),
            new OA\Property(property: 'recurring_days',             type: 'array',   items: new OA\Items(type: 'string')),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function resolveVehicleType(Vehicle $vehicle): string
    {
        $typeName = strtolower($vehicle->vehicleType?->name ?? '');
        if (str_contains($typeName, 'moto')) return 'moto';
        if (str_contains($typeName, 'voiture') || str_contains($typeName, 'car')) return 'car';
        return 'other';
    }

    private function resolveVehicleTypeLabel(Vehicle $vehicle): string
    {
        return match ($this->resolveVehicleType($vehicle)) {
            'car'   => 'Voiture',
            'moto'  => 'Moto',
            default => $vehicle->vehicleType?->name ?? 'Autre',
        };
    }

    // ── Distance & durée ─────────────────────────────────────────────────────

    /**
     * Retourne distance + durée + source avec priorité :
     * ORS API (route réelle) → Haversine GPS → table villes.
     */
    private function resolveRouteData(
        ?string $depCity, ?string $arrCity,
        ?float $depLat, ?float $depLng,
        ?float $arrLat, ?float $arrLng,
    ): array {
        // 1. Coordonnées GPS disponibles
        if ($depLat && $depLng && $arrLat && $arrLng) {
            // Essayer ORS en priorité
            $ors = $this->orsRoute($depLat, $depLng, $arrLat, $arrLng);
            if ($ors) {
                return [
                    'distance_km'      => $ors['distance_km'],
                    'duration_minutes' => $ors['duration_minutes'],
                    'source'           => 'ors',
                ];
            }
            // Haversine en fallback si ORS indisponible
            $km = $this->haversine($depLat, $depLng, $arrLat, $arrLng);
            return [
                'distance_km'      => $km,
                'duration_minutes' => $this->estimateDuration($km),
                'source'           => 'gps',
            ];
        }

        // 2. Noms de villes uniquement
        if ($depCity && $arrCity) {
            $km = $this->cityDistance(
                ucfirst(strtolower($depCity)),
                ucfirst(strtolower($arrCity)),
            );
            if ($km !== null) {
                return [
                    'distance_km'      => $km,
                    'duration_minutes' => $this->estimateDuration($km),
                    'source'           => 'city_table',
                ];
            }
        }

        return ['distance_km' => null, 'duration_minutes' => null, 'source' => 'unknown'];
    }

    /**
     * Appelle OpenRouteService pour obtenir la distance routière réelle.
     * Timeout 5s — retourne null si API indisponible ou clé absente.
     */
    private function orsRoute(float $lat1, float $lng1, float $lat2, float $lng2): ?array
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
                if ($summary && isset($summary['distance'], $summary['duration'])) {
                    return [
                        'distance_km'      => round($summary['distance'] / 1000, 1),
                        'duration_minutes' => (int) round($summary['duration'] / 60),
                    ];
                }
            }
        } catch (\Throwable) {}

        return null;
    }

    /** Distance orthodromique (vol d'oiseau × 1.3 pour les routes béninoises). */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $bird = $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($bird * 1.3, 1);
    }

    /** Table des distances routières (km) entre les grandes villes du Bénin. */
    private function cityDistance(string $from, string $to): ?float
    {
        $table = [
            'Cotonou'        => ['Porto-novo' => 35,  'Abomey-calavi' => 20,  'Ouidah' => 40,  'Allada' => 55,  'Sèmè-kpodji' => 20,  'Kpomassè' => 60,  'Bohicon' => 110, 'Abomey' => 130, 'Lokossa' => 110, 'Comè' => 100, 'Aplahoué' => 130, 'Dogbo' => 140, 'Athiémé' => 120, 'Azovè' => 145, 'Dassa-zoumé' => 195, 'Glazoué' => 215, 'Savalou' => 200, 'Bantè' => 230, 'Djougou' => 380, 'Natitingou' => 440, 'Tanguiéta' => 510, 'Parakou' => 400, 'N\'dali' => 450, 'Nikki' => 480, 'Bembèrèkè' => 460, 'Kandi' => 540, 'Gogounou' => 560, 'Sinendé' => 490, 'Banikoara' => 580, 'Malanville' => 650, 'Zagnanado' => 150, 'Covè' => 140, 'Adjohoun' => 55,  'Pobè' => 100, 'Kétou' => 130, 'Sakété' => 65,  'Tchaourou' => 340],
            'Porto-novo'     => ['Cotonou' => 35,  'Abomey-calavi' => 30,  'Bohicon' => 130, 'Parakou' => 430, 'Pobè' => 80,  'Kétou' => 115, 'Sakété' => 45,  'Adjohoun' => 50,  'Zagnanado' => 160],
            'Abomey-calavi'  => ['Cotonou' => 20,  'Porto-novo' => 30,  'Ouidah' => 45,  'Bohicon' => 105, 'Parakou' => 390],
            'Parakou'        => ['Cotonou' => 400, 'Djougou' => 100, 'N\'dali' => 50,  'Kandi' => 145, 'Nikki' => 95,  'Bembèrèkè' => 65,  'Natitingou' => 150, 'Tchaourou' => 60,  'Sinendé' => 100, 'Gogounou' => 165, 'Banikoara' => 185, 'Malanville' => 255],
            'Bohicon'        => ['Cotonou' => 110, 'Abomey' => 20,  'Parakou' => 300, 'Dassa-zoumé' => 85,  'Savalou' => 95,  'Zagnanado' => 55],
            'Abomey'         => ['Cotonou' => 130, 'Bohicon' => 20,  'Lokossa' => 80,  'Aplahoué' => 70,  'Dogbo' => 70,  'Kétou' => 110],
            'Djougou'        => ['Cotonou' => 380, 'Parakou' => 100, 'Natitingou' => 80,  'Tanguiéta' => 150],
            'Natitingou'     => ['Cotonou' => 440, 'Parakou' => 150, 'Djougou' => 80,  'Tanguiéta' => 70,  'Malanville' => 360],
            'Kandi'          => ['Cotonou' => 540, 'Parakou' => 145, 'Malanville' => 110, 'Banikoara' => 80,  'Gogounou' => 45],
            'Dassa-zoumé'    => ['Cotonou' => 195, 'Bohicon' => 85,  'Savalou' => 50,  'Glazoué' => 20,  'Parakou' => 215],
            'Lokossa'        => ['Cotonou' => 110, 'Abomey' => 80,  'Aplahoué' => 50,  'Dogbo' => 60,  'Athiémé' => 25],
        ];

        $fromN = ucfirst(strtolower($from));
        $toN   = ucfirst(strtolower($to));

        if (isset($table[$fromN][$toN])) return (float) $table[$fromN][$toN];
        if (isset($table[$toN][$fromN])) return (float) $table[$toN][$fromN];
        return null;
    }

    /**
     * Estime la durée en minutes selon la distance.
     * Vitesse moyenne adaptée à la qualité des routes béninoises.
     */
    private function estimateDuration(float $distanceKm): int
    {
        $speed = match (true) {
            $distanceKm < 30  => 40,  // urbain / périurbain
            $distanceKm < 100 => 55,  // route nationale courte
            $distanceKm < 300 => 65,  // route nationale longue
            default           => 70,  // grande distance (Parakou+)
        };
        return (int) round(($distanceKm / $speed) * 60);
    }

    private function formatDuration(int $minutes): string
    {
        $h   = intdiv($minutes, 60);
        $min = $minutes % 60;
        if ($h === 0)   return "{$min}min";
        if ($min === 0) return "{$h}h";
        return "{$h}h {$min}min";
    }
}
