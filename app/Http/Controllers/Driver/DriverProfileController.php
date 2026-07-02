<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DriverProfileController extends Controller
{
    // =========================================================================
    //  GET /api/driver/profile
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/profile',
        operationId: 'driverProfile',
        summary: 'Page profil conducteur',
        description: "Retourne toutes les données de la page profil : hero (nom, note, trajets, ancienneté), verification_items (téléphone, identité, permis, véhicule), stats (gains, passagers, note, trajets), personal_info, vehicles, documents (identité + véhicule), performance (niveau, progression, badges), preferences (disponibilité auto, notifications).",
        tags: ['🚗 Driver — Profil'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données profil conducteur',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Profil conducteur.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'hero',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'full_name',      type: 'string',  example: 'Koffi Mensah'),
                                        new OA\Property(property: 'phone',          type: 'string',  example: '+22997000000'),
                                        new OA\Property(property: 'badge',          type: 'string',  example: 'Conducteur élite'),
                                        new OA\Property(property: 'level',          type: 'string',  example: 'Argent'),
                                        new OA\Property(property: 'level_number',   type: 'integer', example: 2),
                                        new OA\Property(property: 'location',       type: 'string',  example: 'Cotonou, Bénin'),
                                        new OA\Property(property: 'rating',         type: 'number',  nullable: true, example: 4.8),
                                        new OA\Property(property: 'trips_count',    type: 'integer', example: 28),
                                        new OA\Property(property: 'tenure_months',  type: 'integer', example: 6),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'verification_items',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'key',      type: 'string',  example: 'phone'),
                                            new OA\Property(property: 'title',    type: 'string',  example: 'Téléphone'),
                                            new OA\Property(property: 'status',   type: 'string',  example: 'Vérifié'),
                                            new OA\Property(property: 'verified', type: 'boolean', example: true),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'stats',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'key',        type: 'string', example: 'earnings'),
                                            new OA\Property(property: 'value',      type: 'string', example: '145 000 XOF'),
                                            new OA\Property(property: 'label',      type: 'string', example: 'Gains totaux'),
                                            new OA\Property(property: 'emphasized', type: 'boolean', example: true),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'personal_info',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'first_name',      type: 'string'),
                                        new OA\Property(property: 'last_name',       type: 'string'),
                                        new OA\Property(property: 'full_name',       type: 'string'),
                                        new OA\Property(property: 'phone',           type: 'string'),
                                        new OA\Property(property: 'email',           type: 'string', nullable: true),
                                        new OA\Property(property: 'gender',          type: 'string', nullable: true),
                                        new OA\Property(property: 'city',            type: 'string', nullable: true),
                                        new OA\Property(property: 'neighborhood',    type: 'string', nullable: true),
                                        new OA\Property(property: 'address_details', type: 'string', nullable: true),
                                        new OA\Property(property: 'member_since',    type: 'string', example: 'Janvier 2026'),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'vehicles',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'uuid',                type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'brand',               type: 'string', example: 'Toyota'),
                                            new OA\Property(property: 'model',               type: 'string', example: 'Camry'),
                                            new OA\Property(property: 'color',               type: 'string', example: 'Noir'),
                                            new OA\Property(property: 'year',                type: 'integer', nullable: true),
                                            new OA\Property(property: 'license_plate',       type: 'string', example: 'RB 1234 X'),
                                            new OA\Property(property: 'available_seats',     type: 'integer'),
                                            new OA\Property(property: 'verification_status', type: 'string', enum: ['pending', 'approved', 'rejected', 'suspended']),
                                            new OA\Property(property: 'is_active',           type: 'boolean'),
                                            new OA\Property(property: 'vehicle_type',        type: 'string', nullable: true),
                                            new OA\Property(property: 'vehicle_photo_url',   type: 'string', nullable: true),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'documents',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'key',      type: 'string', example: 'id_card'),
                                            new OA\Property(property: 'title',    type: 'string', example: "Pièce d'identité"),
                                            new OA\Property(property: 'subtitle', type: 'string', example: 'Approuvée'),
                                            new OA\Property(property: 'has_file', type: 'boolean'),
                                            new OA\Property(property: 'url',      type: 'string', nullable: true),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'performance',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'current_level',  type: 'string',  example: 'Argent'),
                                        new OA\Property(property: 'progress',       type: 'number',  example: 0.45),
                                        new OA\Property(property: 'next_level',     type: 'string',  nullable: true, example: 'Or'),
                                        new OA\Property(property: 'trips_to_next',  type: 'integer', example: 22),
                                        new OA\Property(property: 'badges_count',   type: 'integer', example: 3),
                                        new OA\Property(property: 'top_percent',    type: 'integer', nullable: true, example: 10),
                                        new OA\Property(property: 'bonus_count',    type: 'integer', example: 0),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'preferences',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'auto_availability',     type: 'boolean', example: false),
                                        new OA\Property(property: 'notifications_enabled', type: 'boolean', example: true),
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
    public function profile(Request $request): JsonResponse
    {
        $user    = $request->user()->load(['profile', 'vehicle.vehicleType']);
        $profile = $user->profile;
        $vehicle = $user->vehicle;

        // ── Trajets complétés ──────────────────────────────────────────────────
        $completedTripIds = Trip::where('user_id', $user->id)
            ->where('status', 'completed')
            ->pluck('id');

        $tripsCompleted = $completedTripIds->count();
        $avgRating      = $user->averageRating();

        // ── Gains & wallet ─────────────────────────────────────────────────────
        $totalEarnings = Payment::whereHas(
            'booking',
            fn ($q) => $q->whereIn('trip_id', $completedTripIds)
        )->where('status', 'success')->sum('net_amount');

        $passengersTransported = (int) Booking::whereIn('trip_id', $completedTripIds)
            ->where('status', 'accepted')
            ->sum('seats_booked');

        // ── Ancienneté ────────────────────────────────────────────────────────
        $tenureMonths = (int) $user->created_at->diffInMonths(now());

        // ── Niveau ────────────────────────────────────────────────────────────
        $level = $this->computeLevel($tripsCompleted, $avgRating, $passengersTransported);

        // ── Vérification ──────────────────────────────────────────────────────
        $kycApproved     = $profile?->kyc_status === 'approved';
        $hasLicense      = ! empty($profile?->driving_license_number);
        $vehicleApproved = $vehicle?->isApproved() ?? false;

        $verificationItems = [
            [
                'key'      => 'phone',
                'title'    => 'Téléphone',
                'status'   => $user->phone_verified_at ? 'Vérifié' : 'Non vérifié',
                'verified' => (bool) $user->phone_verified_at,
            ],
            [
                'key'      => 'identity',
                'title'    => "Pièce d'identité",
                'status'   => $kycApproved ? 'Approuvée' : ($profile?->kyc_status === 'pending' ? 'En attente' : 'Non soumise'),
                'verified' => $kycApproved,
            ],
            [
                'key'      => 'license',
                'title'    => 'Permis de conduire',
                'status'   => $hasLicense ? ($kycApproved ? 'Vérifié' : 'Soumis') : 'Non soumis',
                'verified' => $hasLicense && $kycApproved,
            ],
            [
                'key'      => 'vehicle',
                'title'    => 'Véhicule',
                'status'   => $vehicleApproved ? 'Approuvé' : ($vehicle?->isPending() ? 'En attente' : 'Non soumis'),
                'verified' => $vehicleApproved,
            ],
        ];

        // ── Stats cards ───────────────────────────────────────────────────────
        $stats = [
            [
                'key'        => 'earnings',
                'value'      => number_format((int) $totalEarnings, 0, ',', ' ') . ' XOF',
                'raw_value'  => (int) $totalEarnings,
                'label'      => 'Gains totaux',
                'emphasized' => true,
            ],
            [
                'key'        => 'passengers',
                'value'      => (string) $passengersTransported,
                'raw_value'  => $passengersTransported,
                'label'      => 'Passagers',
                'emphasized' => false,
            ],
            [
                'key'        => 'rating',
                'value'      => $avgRating ? number_format($avgRating, 1) : '—',
                'raw_value'  => $avgRating,
                'label'      => 'Note moyenne',
                'emphasized' => false,
            ],
            [
                'key'        => 'trips',
                'value'      => (string) $tripsCompleted,
                'raw_value'  => $tripsCompleted,
                'label'      => 'Trajets',
                'emphasized' => false,
            ],
        ];

        // ── Informations personnelles ──────────────────────────────────────────
        $personalInfo = [
            'first_name'      => $profile?->first_name,
            'last_name'       => $profile?->last_name,
            'full_name'       => $profile?->fullName() ?? '',
            'phone'           => $user->phone,
            'email'           => $profile?->email,
            'gender'          => $profile?->gender,
            'city'            => $profile?->city,
            'neighborhood'    => $profile?->neighborhood,
            'address_details' => $profile?->address_details,
            'member_since'    => $user->created_at->translatedFormat('F Y'),
        ];

        // ── Véhicules ─────────────────────────────────────────────────────────
        $vehicles = $vehicle ? [[
            'uuid'                => $vehicle->uuid ?? null,
            'brand'               => $vehicle->brand,
            'model'               => $vehicle->model,
            'color'               => $vehicle->color,
            'year'                => $vehicle->year,
            'license_plate'       => $vehicle->license_plate,
            'available_seats'     => $vehicle->available_seats,
            'verification_status' => $vehicle->verification_status ?? 'pending',
            'is_active'           => $vehicle->isApproved(),
            'vehicle_type'        => $vehicle->vehicleType?->name,
            'vehicle_photo_url'   => $vehicle->vehicle_photo ? asset('storage/' . $vehicle->vehicle_photo) : null,
        ]] : [];

        // ── Documents ─────────────────────────────────────────────────────────
        $kycStatusLabel = match ($profile?->kyc_status) {
            'approved' => 'Approuvée',
            'pending'  => 'En attente',
            'rejected' => 'Rejetée',
            default    => 'Non soumis',
        };

        $documents = [
            [
                'key'      => 'id_card',
                'title'    => "Pièce d'identité (CNI)",
                'subtitle' => $kycStatusLabel,
                'has_file' => ! empty($profile?->id_card_front),
                'url'      => $profile?->id_card_front ? asset('storage/' . $profile->id_card_front) : null,
            ],
            [
                'key'      => 'driving_license',
                'title'    => 'Permis de conduire',
                'subtitle' => ! empty($profile?->driving_license_number) ? ($kycApproved ? 'Vérifié' : 'Soumis') : 'Non soumis',
                'has_file' => ! empty($profile?->driving_license_photo),
                'url'      => $profile?->driving_license_photo ? asset('storage/' . $profile->driving_license_photo) : null,
            ],
        ];

        if ($vehicle) {
            $vehicleStatus = $vehicleApproved ? 'Approuvé' : ($vehicle->isPending() ? 'En attente' : 'Rejeté');

            $documents[] = ['key' => 'vehicle_photo',      'title' => 'Photo du véhicule',   'subtitle' => $vehicle->vehicle_photo      ? $vehicleStatus : 'Non ajoutée', 'has_file' => ! empty($vehicle->vehicle_photo),      'url' => $vehicle->vehicle_photo      ? asset('storage/' . $vehicle->vehicle_photo)      : null];
            $documents[] = ['key' => 'registration',       'title' => 'Carte grise',          'subtitle' => $vehicle->registration_doc   ? $vehicleStatus : 'Non ajoutée', 'has_file' => ! empty($vehicle->registration_doc),   'url' => $vehicle->registration_doc   ? asset('storage/' . $vehicle->registration_doc)   : null];
            $documents[] = ['key' => 'insurance',          'title' => 'Assurance',            'subtitle' => $vehicle->insurance_doc      ? $vehicleStatus : 'Non ajoutée', 'has_file' => ! empty($vehicle->insurance_doc),      'url' => $vehicle->insurance_doc      ? asset('storage/' . $vehicle->insurance_doc)      : null];
            $documents[] = ['key' => 'tvm',                'title' => 'TVM',                  'subtitle' => $vehicle->tvm_doc            ? $vehicleStatus : 'Non ajoutée', 'has_file' => ! empty($vehicle->tvm_doc),            'url' => $vehicle->tvm_doc            ? asset('storage/' . $vehicle->tvm_doc)            : null];
            $documents[] = ['key' => 'technical_control',  'title' => 'Visite technique',     'subtitle' => $vehicle->technical_control_doc ? $vehicleStatus : 'Non ajoutée', 'has_file' => ! empty($vehicle->technical_control_doc), 'url' => $vehicle->technical_control_doc ? asset('storage/' . $vehicle->technical_control_doc) : null];
        }

        return $this->apiResponse(true, 'Profil conducteur.', [
            'hero' => [
                'full_name'     => $profile?->fullName() ?? '',
                'phone'         => $user->phone,
                'badge'         => $this->badgeLabel($level['current_level']),
                'level'         => $level['current_level'],
                'level_number'  => $this->levelNumber($level['current_level']),
                'location'      => $this->locationLabel($profile),
                'rating'        => $avgRating,
                'trips_count'   => $tripsCompleted,
                'tenure_months' => $tenureMonths,
            ],
            'verification_items' => $verificationItems,
            'stats'              => $stats,
            'personal_info'      => $personalInfo,
            'vehicles'           => $vehicles,
            'documents'          => $documents,
            'performance'        => [
                'current_level' => $level['current_level'],
                'progress'      => $level['progress'],
                'next_level'    => $level['next_level'],
                'trips_to_next' => $level['trips_to_next'],
                'badges_count'  => count($level['badges']),
                'badges'        => $level['badges'],
                'top_percent'   => $this->topPercent($avgRating),
                'bonus_count'   => 0,
            ],
            'preferences' => [
                'auto_availability'     => (bool) $user->auto_availability,
                'notifications_enabled' => (bool) ($user->notifications_enabled ?? true),
            ],
        ]);
    }

    // =========================================================================
    //  PUT /api/driver/profile
    // =========================================================================

    #[OA\Put(
        path: '/api/driver/profile',
        operationId: 'driverUpdateProfile',
        summary: 'Mettre à jour les informations personnelles',
        description: "Met à jour les infos personnelles du conducteur connecté (prénom, nom, email, ville, quartier, adresse).",
        tags: ['🚗 Driver — Profil'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name', 'city', 'neighborhood'],
                properties: [
                    new OA\Property(property: 'first_name',      type: 'string', example: 'Koffi'),
                    new OA\Property(property: 'last_name',       type: 'string', example: 'MENSAH'),
                    new OA\Property(property: 'email',           type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'city',            type: 'string', example: 'Cotonou'),
                    new OA\Property(property: 'neighborhood',    type: 'string', example: 'Fidjrossè'),
                    new OA\Property(property: 'address_details', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour'),
            new OA\Response(response: 422, description: 'Données invalides', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function update(Request $request): JsonResponse
    {
        $user    = $request->user();
        $profile = $user->profile;

        if (! $profile) {
            return $this->apiResponse(false, 'Profil introuvable.', [], 404);
        }

        $validated = $request->validate([
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'email'           => 'nullable|email|unique:profiles,email,' . $profile->id,
            'city'            => 'required|string|max:100',
            'neighborhood'    => 'required|string|max:100',
            'address_details' => 'nullable|string|max:255',
        ]);

        $profile->update([
            'first_name'      => \Illuminate\Support\Str::title(strtolower($validated['first_name'])),
            'last_name'       => strtoupper($validated['last_name']),
            'email'           => $validated['email'] ?? $profile->email,
            'city'            => ucfirst(strtolower($validated['city'])),
            'neighborhood'    => ucfirst(strtolower($validated['neighborhood'])),
            'address_details' => $validated['address_details'] ?? $profile->address_details,
        ]);

        return $this->apiResponse(true, 'Profil mis à jour.', [
            'full_name'       => $profile->fresh()->fullName(),
            'email'           => $profile->email,
            'city'            => $profile->city,
            'neighborhood'    => $profile->neighborhood,
            'address_details' => $profile->address_details,
        ]);
    }

    // =========================================================================
    //  PATCH /api/driver/preferences
    // =========================================================================

    #[OA\Patch(
        path: '/api/driver/preferences',
        operationId: 'driverUpdatePreferences',
        summary: 'Mettre à jour les préférences conducteur',
        description: "Active / désactive la disponibilité automatique et les notifications push.",
        tags: ['🚗 Driver — Profil'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'auto_availability',     type: 'boolean', example: true,  description: 'Disponibilité automatique'),
                    new OA\Property(property: 'notifications_enabled', type: 'boolean', example: false, description: 'Notifications push'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Préférences mises à jour',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'auto_availability',     type: 'boolean', example: true),
                                new OA\Property(property: 'notifications_enabled', type: 'boolean', example: false),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Données invalides', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auto_availability'     => 'sometimes|boolean',
            'notifications_enabled' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $user->update($validated);

        return $this->apiResponse(true, 'Préférences mises à jour.', [
            'auto_availability'     => (bool) $user->auto_availability,
            'notifications_enabled' => (bool) $user->notifications_enabled,
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function computeLevel(int $trips, ?float $rating, int $passengers): array
    {
        [$levelName, $progress, $nextLevel, $tripsToNext] = match (true) {
            $trips >= 100 => ['Platine', 1.0,                 null,      0],
            $trips >= 50  => ['Or',      ($trips - 50) / 50,  'Platine', 100 - $trips],
            $trips >= 10  => ['Argent',  ($trips - 10) / 40,  'Or',      50  - $trips],
            default       => ['Bronze',   $trips        / 10,  'Argent',  10  - $trips],
        };

        $badges = [];
        if ($trips >= 1)                        $badges[] = ['key' => 'first_trip', 'label' => '1er trajet'];
        if ($rating !== null && $rating >= 4.5) $badges[] = ['key' => 'top_rated',  'label' => '5 étoiles'];
        if ($trips >= 10)                       $badges[] = ['key' => 'veteran',    'label' => 'Vétéran'];
        if ($passengers >= 100)                 $badges[] = ['key' => 'popular',    'label' => 'Populaire'];

        return [
            'current_level' => $levelName,
            'progress'      => round(min(1.0, $progress), 2),
            'next_level'    => $nextLevel,
            'trips_to_next' => $tripsToNext,
            'badges'        => $badges,
        ];
    }

    private function badgeLabel(string $level): string
    {
        return match ($level) {
            'Platine' => 'Conducteur élite',
            'Or'      => 'Conducteur gold',
            'Argent'  => 'Conducteur confirmé',
            default   => 'Conducteur débutant',
        };
    }

    private function levelNumber(string $level): int
    {
        return match ($level) {
            'Platine' => 4,
            'Or'      => 3,
            'Argent'  => 2,
            default   => 1,
        };
    }

    private function locationLabel(?\App\Models\Profile $profile): string
    {
        if (! $profile) return 'Bénin';
        $parts = array_filter([$profile->city, 'Bénin']);
        return implode(', ', $parts);
    }

    private function topPercent(?float $rating): ?int
    {
        if ($rating === null) return null;
        if ($rating >= 4.8)  return 5;
        if ($rating >= 4.5)  return 10;
        if ($rating >= 4.0)  return 25;
        return null;
    }
}
