<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
                                            new OA\Property(property: 'available_seats',    type: 'integer', example: 4),
                                            new OA\Property(property: 'license_plate',      type: 'string',  example: 'RB 1234 X'),
                                            new OA\Property(property: 'color',              type: 'string',  nullable: true),
                                            new OA\Property(property: 'year',               type: 'integer', nullable: true),
                                        ]
                                    )
                                ),
                                new OA\Property(property: 'has_approved_vehicle', type: 'boolean'),
                                new OA\Property(property: 'cities',    type: 'array', items: new OA\Items(type: 'string')),
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
                    'departure_city', 'departure_neighborhood',
                    'arrival_city', 'arrival_neighborhood',
                    'departure_date', 'departure_time',
                    'price_per_seat',
                ],
                properties: [
                    new OA\Property(property: 'vehicle_id',              type: 'integer', example: 5),
                    // Départ
                    new OA\Property(property: 'departure_city',          type: 'string',  example: 'Cotonou'),
                    new OA\Property(property: 'departure_neighborhood',  type: 'string',  example: 'Akpakpa'),
                    new OA\Property(property: 'departure_point',         type: 'string',  example: 'Carrefour Étoile Rouge', nullable: true),
                    new OA\Property(property: 'departure_latitude',      type: 'number',  example: 6.3703,  nullable: true),
                    new OA\Property(property: 'departure_longitude',     type: 'number',  example: 2.3912,  nullable: true),
                    // Arrivée
                    new OA\Property(property: 'arrival_city',            type: 'string',  example: 'Parakou'),
                    new OA\Property(property: 'arrival_neighborhood',    type: 'string',  example: 'Centre-ville'),
                    new OA\Property(property: 'arrival_point',           type: 'string',  example: 'Gare routière', nullable: true),
                    new OA\Property(property: 'arrival_latitude',        type: 'number',  example: 9.3370,  nullable: true),
                    new OA\Property(property: 'arrival_longitude',       type: 'number',  example: 2.6280,  nullable: true),
                    // Date & heure (depuis les pickers Flutter — format local)
                    new OA\Property(property: 'departure_date',          type: 'string',  example: '10/07/2026', description: 'Format jj/mm/aaaa'),
                    new OA\Property(property: 'departure_time',          type: 'string',  example: '07:00',       description: 'Format HH:mm'),
                    new OA\Property(property: 'estimated_duration_minutes', type: 'integer', example: 300, nullable: true, description: 'Durée estimée en minutes'),
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

            // Départ
            'departure_city'             => 'required|string|max:100',
            'departure_neighborhood'     => 'required|string|max:100',
            'departure_point'            => 'nullable|string|max:200',
            'departure_latitude'         => 'nullable|numeric|between:-90,90',
            'departure_longitude'        => 'nullable|numeric|between:-180,180',

            // Arrivée
            'arrival_city'               => 'required|string|max:100',
            'arrival_neighborhood'       => 'required|string|max:100',
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
        $totalSeats   = $validated['total_seats'] ?? $vehicle->available_seats;
        $maxPerBooking = min($validated['max_per_booking'] ?? $totalSeats, $totalSeats);
        $isPublished  = $validated['is_published'] ?? true;

        $estimatedArrival = isset($validated['estimated_duration_minutes'])
            ? $departureAt->copy()->addMinutes($validated['estimated_duration_minutes'])
            : null;

        // ── Créer le trajet ───────────────────────────────────────────────────
        $trip = Trip::create([
            'user_id'  => $request->user()->id,
            'vehicle_id' => $vehicle->id,

            'departure_city'         => ucfirst(strtolower($validated['departure_city'])),
            'departure_neighborhood' => ucfirst(strtolower($validated['departure_neighborhood'])),
            'departure_point'        => $validated['departure_point'] ?? null,
            'departure_latitude'     => $validated['departure_latitude'] ?? null,
            'departure_longitude'    => $validated['departure_longitude'] ?? null,

            'arrival_city'           => ucfirst(strtolower($validated['arrival_city'])),
            'arrival_neighborhood'   => ucfirst(strtolower($validated['arrival_neighborhood'])),
            'arrival_point'          => $validated['arrival_point'] ?? null,
            'arrival_latitude'       => $validated['arrival_latitude'] ?? null,
            'arrival_longitude'      => $validated['arrival_longitude'] ?? null,

            'price_per_seat'             => $validated['price_per_seat'],
            'departure_time'             => $departureAt,
            'estimated_duration_minutes' => $validated['estimated_duration_minutes'] ?? null,
            'estimated_arrival_time'     => $estimatedArrival,

            'total_seats'    => $totalSeats,
            'available_seats'=> $totalSeats,
            'booking_mode'   => $validated['booking_mode'] ?? 'instant',
            'max_per_booking'=> $maxPerBooking,

            'description'         => $validated['description'] ?? null,
            'waypoints'           => $validated['waypoints'] ?? null,
            'preferences'         => array_values(array_unique($validated['preferences'] ?? [])) ?: null,
            'cancellation_policy' => $validated['cancellation_policy'] ?? 'flexible',

            'is_recurring'      => $validated['is_recurring'] ?? false,
            'recurring_days'    => ($validated['is_recurring'] ?? false) ? ($validated['recurring_days'] ?? null) : null,
            'recurring_end_date'=> ($validated['is_recurring'] ?? false) ? ($validated['recurring_end_date'] ?? null) : null,

            'commission_rate' => self::DEFAULT_COMMISSION,

            'status'       => 'pending',
            'is_published' => $isPublished,
            'published_at' => $isPublished ? now() : null,
        ]);

        return $this->apiResponse(true, $isPublished ? 'Trajet publié avec succès.' : 'Brouillon sauvegardé.', [
            'uuid'                       => $trip->uuid,
            'status'                     => $trip->status,
            'is_published'               => $trip->is_published,
            'booking_mode'               => $trip->booking_mode,
            'cancellation_policy'        => $trip->cancellation_policy,
            'route'                      => $trip->route(),
            'departure_time'             => $trip->departure_time,
            'estimated_arrival_time'     => $trip->estimated_arrival_time,
            'estimated_duration_minutes' => $trip->estimated_duration_minutes,
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
            new OA\Property(property: 'route',                      type: 'string',  example: 'Cotonou → Parakou'),
            new OA\Property(property: 'departure_time',             type: 'string',  format: 'date-time'),
            new OA\Property(property: 'estimated_arrival_time',     type: 'string',  format: 'date-time', nullable: true),
            new OA\Property(property: 'estimated_duration_minutes', type: 'integer', nullable: true),
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
}
