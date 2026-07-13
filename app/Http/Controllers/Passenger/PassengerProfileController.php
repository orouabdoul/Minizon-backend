<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\EmergencyContact;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class PassengerProfileController extends Controller
{
    // ── Couleurs ARGB des providers MoMo ─────────────────────────────────────
    private const PROVIDER_COLORS = [
        'mtn'    => ['start' => 0xFFFFCC00, 'end' => 0xFFFF9900, 'label' => 'MTN'],
        'moov'   => ['start' => 0xFF0066CC, 'end' => 0xFF0044AA, 'label' => 'Moov'],
        'celtiis'=> ['start' => 0xFFE53935, 'end' => 0xFFC62828, 'label' => 'Cel'],
    ];

    // =========================================================================
    //  GET /api/passenger/profile
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/profile',
        operationId: 'passengerProfile',
        summary: 'Page profil passager',
        description: "Retourne toutes les données de la page profil passager en un seul appel : summary (avatar, nom, téléphone), metrics (note, trajets effectués), trust (espace confiance, vérifications), settings (tiles de navigation), payment_methods (MoMo liés), recent_trips (5 derniers trajets).",
        tags: ['👤 Passenger — Profil'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données profil passager',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Profil passager.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'summary',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'avatar_url',  type: 'string',  example: 'https://...'),
                                        new OA\Property(property: 'name',        type: 'string',  example: 'Koffi Mensah'),
                                        new OA\Property(property: 'phone',       type: 'string',  example: '+229 97 00 00 00'),
                                        new OA\Property(property: 'is_verified', type: 'boolean', example: true),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'metrics',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'key',   type: 'string', example: 'rating'),
                                            new OA\Property(property: 'value', type: 'string', example: '4.8'),
                                            new OA\Property(property: 'label', type: 'string', example: 'Note'),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'trust',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'title',             type: 'string', example: 'Espace Confiance'),
                                        new OA\Property(property: 'level',             type: 'string', example: 'Vérifié'),
                                        new OA\Property(property: 'verified_number',   type: 'string', example: 'Numéro de téléphone vérifié'),
                                        new OA\Property(property: 'identity_document', type: 'string', example: 'Pièce d\'identité validée'),
                                        new OA\Property(property: 'verified_email',    type: 'string', example: 'Adresse email non renseignée'),
                                        new OA\Property(
                                            property: 'items',
                                            type: 'array',
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(property: 'key',      type: 'string',  example: 'phone'),
                                                    new OA\Property(property: 'title',    type: 'string',  example: 'Numéro de téléphone'),
                                                    new OA\Property(property: 'status',   type: 'string',  example: 'Vérifié'),
                                                    new OA\Property(property: 'verified', type: 'boolean', example: true),
                                                ]
                                            )
                                        ),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'settings',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'icon',  type: 'string', enum: ['edit', 'shield', 'notifications', 'support']),
                                            new OA\Property(property: 'title', type: 'string', example: 'Modifier le profil'),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'payment_methods',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/PassengerPaymentMethod')
                                ),
                                new OA\Property(
                                    property: 'recent_trips',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/PassengerRecentTrip')
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $user    = $request->user();
        $profile = $user->profile;

        return $this->apiResponse(true, 'Profil passager.', [
            'summary'            => $this->buildSummary($user, $profile),
            'metrics'            => $this->buildMetrics($user),
            'trust'              => $this->buildTrust($user, $profile),
            'settings'           => $this->buildSettings(),
            'payment_methods'    => $this->buildPaymentMethods($user),
            'recent_trips'       => $this->buildRecentTrips($user),
            'emergency_contacts' => $this->buildEmergencyContacts($user),
        ]);
    }

    // =========================================================================
    //  PATCH /api/passenger/profile
    // =========================================================================

    #[OA\Patch(
        path: '/api/passenger/profile',
        operationId: 'passengerProfileUpdate',
        summary: 'Mettre à jour le profil passager',
        description: 'Met à jour les informations personnelles du passager (prénom, nom, email, ville, quartier).',
        tags: ['👤 Passenger — Profil'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'first_name',    type: 'string', example: 'Koffi'),
                    new OA\Property(property: 'last_name',     type: 'string', example: 'Mensah'),
                    new OA\Property(property: 'email',         type: 'string', format: 'email', example: 'koffi@example.com'),
                    new OA\Property(property: 'city',          type: 'string', example: 'Cotonou'),
                    new OA\Property(property: 'neighborhood',  type: 'string', example: 'Cadjehoun'),
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
        $validated = $request->validate([
            'first_name'   => 'sometimes|string|max:60',
            'last_name'    => 'sometimes|string|max:60',
            'email'        => 'sometimes|email|max:120',
            'city'         => 'sometimes|string|max:80',
            'neighborhood' => 'sometimes|string|max:80',
        ]);

        $user = $request->user();

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return $this->apiResponse(true, 'Profil mis à jour.');
    }

    // =========================================================================
    //  OA SCHEMAS
    // =========================================================================

    #[OA\Schema(
        schema: 'PassengerPaymentMethod',
        properties: [
            new OA\Property(property: 'provider',     type: 'string',  example: 'MTN',           description: 'Label court affiché dans le badge'),
            new OA\Property(property: 'title',        type: 'string',  example: 'MTN MoMo'),
            new OA\Property(property: 'subtitle',     type: 'string',  example: '+229 97 ●● ●● ●●', description: 'Numéro partiellement masqué'),
            new OA\Property(property: 'selected',     type: 'boolean', example: true),
            new OA\Property(property: 'accent_start', type: 'integer', example: 4294965248, description: 'Couleur ARGB début dégradé'),
            new OA\Property(property: 'accent_end',   type: 'integer', example: 4294944000, description: 'Couleur ARGB fin dégradé'),
        ]
    )]
    #[OA\Schema(
        schema: 'PassengerRecentTrip',
        properties: [
            new OA\Property(property: 'booking_uuid', type: 'string',  format: 'uuid'),
            new OA\Property(property: 'title',        type: 'string',  example: 'Cotonou → Abomey-Calavi'),
            new OA\Property(property: 'time',         type: 'string',  example: 'Hier, 14h30'),
            new OA\Property(property: 'price',        type: 'string',  example: '1 500 FCFA'),
            new OA\Property(property: 'rating',       type: 'string',  example: '4.8'),
            new OA\Property(property: 'driver',       type: 'string',  example: 'Moussa A.'),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function buildSummary(\App\Models\User $user, ?Profile $profile): array
    {
        $avatarUrl = '';
        if ($profile?->selfie_front) {
            $avatarUrl = Storage::disk('public')->url($profile->selfie_front);
        }

        $name = $profile
            ? trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? ''))
            : '';

        return [
            'avatar_url'  => $avatarUrl,
            'name'        => $name ?: $user->phone,
            'phone'       => $user->phone ?? '',
            'is_verified' => (bool) $user->is_verified,
        ];
    }

    private function buildMetrics(\App\Models\User $user): array
    {
        $avgRating = $user->averageRating();

        $completedBookings = Booking::where('passenger_id', $user->id)
            ->whereIn('status', ['accepted'])
            ->whereHas('payment', fn ($q) => $q->whereIn('status', ['success', 'released']))
            ->count();

        return [
            [
                'key'   => 'rating',
                'value' => $avgRating ? number_format(round($avgRating, 1), 1) : '—',
                'label' => 'Note',
            ],
            [
                'key'   => 'trips',
                'value' => (string) $completedBookings,
                'label' => 'Trajets',
            ],
        ];
    }

    private function buildTrust(\App\Models\User $user, ?Profile $profile): array
    {
        $phoneVerified   = ! is_null($user->phone_verified_at);
        $kycVerified     = $profile?->kyc_status === 'approved';
        $emailProvided   = ! empty($profile?->email);
        $verifiedCount   = (int) $phoneVerified + (int) $kycVerified + (int) $emailProvided;

        $level = match (true) {
            $verifiedCount >= 3 => 'Élite',
            $verifiedCount === 2 => 'Vérifié',
            $verifiedCount === 1 => 'Basique',
            default              => 'Non vérifié',
        };

        $items = [
            [
                'key'      => 'phone',
                'title'    => 'Numéro de téléphone',
                'status'   => $phoneVerified ? 'Vérifié' : 'Non vérifié',
                'verified' => $phoneVerified,
            ],
            [
                'key'      => 'identity',
                'title'    => 'Pièce d\'identité',
                'status'   => $kycVerified ? 'Validée' : ($profile?->kyc_status === 'pending' ? 'En attente' : 'Non soumise'),
                'verified' => $kycVerified,
            ],
            [
                'key'      => 'email',
                'title'    => 'Adresse email',
                'status'   => $emailProvided ? 'Renseignée' : 'Non renseignée',
                'verified' => $emailProvided,
            ],
        ];

        return [
            'title'             => 'Espace Confiance',
            'level'             => $level,
            'verified_number'   => $phoneVerified  ? 'Numéro de téléphone vérifié' : 'Numéro non vérifié',
            'identity_document' => $kycVerified     ? 'Pièce d\'identité validée'  : 'Pièce d\'identité non vérifiée',
            'verified_email'    => $emailProvided   ? 'Adresse email renseignée'   : 'Adresse email non renseignée',
            'items'             => $items,
        ];
    }

    private function buildSettings(): array
    {
        return [
            ['icon' => 'edit',          'title' => 'Modifier le profil'],
            ['icon' => 'shield',        'title' => 'Sécurité & confidentialité'],
            ['icon' => 'notifications', 'title' => 'Notifications'],
            ['icon' => 'support',       'title' => 'Support'],
        ];
    }

    private function buildPaymentMethods(\App\Models\User $user): array
    {
        // Providers utilisés par le passager (déduits de l'historique des paiements)
        $usedProviders = Payment::whereHas('booking', fn ($q) =>
                $q->where('passenger_id', $user->id)
            )
            ->whereNotNull('provider')
            ->whereIn('status', ['success', 'released', 'locked'])
            ->orderByDesc('created_at')
            ->pluck('provider')
            ->unique()
            ->values();

        if ($usedProviders->isEmpty()) {
            // Retourne le numéro de l'utilisateur avec MTN comme défaut
            $usedProviders = collect(['mtn']);
        }

        $phone = $user->phone ?? '';

        return $usedProviders->map(function (string $provider, int $index) use ($phone) {
            $colors = self::PROVIDER_COLORS[$provider] ?? self::PROVIDER_COLORS['mtn'];
            $providerLabel = $colors['label'];
            $maskedPhone   = $this->maskPhone($phone);

            return [
                'provider'    => $providerLabel,
                'title'       => $providerLabel . ' MoMo',
                'subtitle'    => $maskedPhone,
                'selected'    => $index === 0,
                'accent_start'=> $colors['start'],
                'accent_end'  => $colors['end'],
            ];
        })->values()->toArray();
    }

    private function buildRecentTrips(\App\Models\User $user, int $limit = 5): array
    {
        return Booking::with(['trip.user.profile', 'payment'])
            ->where('passenger_id', $user->id)
            ->whereIn('status', ['accepted', 'cancelled'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Booking $booking) {
                $trip    = $booking->trip;
                $driver  = $trip?->user;
                $profile = $driver?->profile;

                $driverName = '';
                if ($profile) {
                    $first = $profile->first_name ?? '';
                    $last  = $profile->last_name  ?? '';
                    $driverName = $first . ($last ? ' ' . strtoupper(substr($last, 0, 1)) . '.' : '');
                }

                $price = $booking->payment
                    ? number_format($booking->payment->gross_amount, 0, ',', ' ') . ' FCFA'
                    : ($trip ? number_format($trip->price_per_seat, 0, ',', ' ') . ' FCFA' : '—');

                $avgRating = $driver?->averageRating();

                return [
                    'booking_uuid' => $booking->uuid,
                    'title'        => $trip ? ($trip->departure_city . ' → ' . $trip->arrival_city) : 'Trajet supprimé',
                    'time'         => $booking->created_at->setTimezone('Africa/Porto-Novo')->translatedFormat('d M à H\hi'),
                    'price'        => $price,
                    'rating'       => $avgRating ? number_format(round($avgRating, 1), 1) : '—',
                    'driver'       => $driverName ?: '—',
                ];
            })
            ->toArray();
    }

    private function buildEmergencyContacts(\App\Models\User $user): array
    {
        return $user->emergencyContacts()
            ->orderBy('created_at')
            ->get(['id', 'name', 'relationship', 'phone'])
            ->toArray();
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 8) return $phone;
        // Garde les 4 premiers et 2 derniers chiffres, masque le reste
        return substr($phone, 0, 4) . ' ●● ●● ' . substr($phone, -2);
    }
}
