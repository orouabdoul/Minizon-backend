<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * Gestion de l'authentification et du cycle de vie des comptes utilisateurs.
 *
 * Couvre :
 *  - Authentification OTP (passagers & conducteurs)
 *  - Inscription avec soumission KYC conditionnelle
 *  - Connexion Back-Office (email / password)
 *  - Administration des comptes (validation KYC, blocage, suppression)
 */
class AuthController extends Controller
{
    // =========================================================================
    //  PROTOCOLE D'AUTHENTIFICATION PUBLIC (OTP)
    // =========================================================================

    #[OA\Post(
        path: '/api/auth/send-otp',
        operationId: 'sendOtp',
        summary: 'Demande d\'OTP',
        description: 'Génère un code de vérification à 6 chiffres et l\'associe au numéro de téléphone. Le code expire après **10 minutes**. Un délai de 60 secondes s\'applique entre deux demandes.',
        tags: ['🔓 Authentification & KYC'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['phone'],
                properties: [
                    new OA\Property(
                        property: 'phone',
                        type: 'string',
                        example: '+2290161165619',
                        description: 'Numéro de téléphone au format international E.164'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Code OTP généré avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Code OTP généré avec succès.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'phone',                type: 'string',  example: '+2290161165619'),
                                new OA\Property(property: 'otp_code',            type: 'string',  example: '584291', description: 'Code OTP généré (visible pour les tests)'),
                                new OA\Property(property: 'resend_available_in', type: 'integer', example: 60, description: 'Secondes avant de pouvoir renvoyer un OTP'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Format de numéro invalide',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 429,
                description: 'Délai entre deux envois non respecté (60 secondes)',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Format de numéro de téléphone invalide.', $validator->errors(), 422);
        }

        $passengerRole = Role::where('name', 'passenger')->first();

        $user = User::firstOrCreate(
            ['phone' => $request->phone],
            [
                'role_id'     => $passengerRole?->id ?? 2,
                'is_verified' => false,
            ]
        );

        // Cooldown de 60 secondes entre deux envois d'OTP.
        // Protège contre le double-appel (instance Render lente) qui écrasait
        // l'OTP en base pendant que l'ancien était encore en transit vers le client.
        $otpTtl        = 10; // minutes
        $resendCooldown = 60; // secondes
        $cooldownUntil = now()->addMinutes($otpTtl)->subSeconds($resendCooldown);

        if ($user->otp_expires_at && $user->otp_expires_at->gt($cooldownUntil)) {
            $secondsLeft = (int) now()->diffInSeconds($user->otp_expires_at->subMinutes($otpTtl)->addSeconds($resendCooldown), false);
            $secondsLeft = max(1, $secondsLeft);

            return $this->apiResponse(false, 'Un code OTP a déjà été envoyé. Veuillez patienter avant d\'en demander un nouveau.', [
                'phone'               => $user->phone,
                'resend_available_in' => $secondsLeft,
            ], 429);
        }

        $otpCode = (string) random_int(100000, 999999);

        $user->update([
            'otp_code'       => $otpCode,
            'otp_expires_at' => now()->addMinutes($otpTtl),
        ]);

        // TODO : envoyer le SMS via votre provider (ex. Twilio, Orange SMS API)

        return $this->apiResponse(true, 'Code OTP généré avec succès.', [
            'phone'               => $user->phone,
            'otp_code'            => $otpCode,
            'resend_available_in' => $resendCooldown,
        ]);
    }

    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/auth/verify-otp',
        operationId: 'verifyOtp',
        summary: 'Validation de l\'OTP',
        description: 'Vérifie le code OTP. En cas de succès, un token Sanctum est délivré et le code est consommé (usage unique).',
        tags: ['🔓 Authentification & KYC'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['phone', 'otp_code'],
                properties: [
                    new OA\Property(property: 'phone',    type: 'string', example: '+2290161165619'),
                    new OA\Property(property: 'otp_code', type: 'string', example: '584291', description: 'Code à 6 chiffres'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authentification réussie — token Sanctum délivré',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Authentification réussie.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'token',            type: 'string',  example: '1|laravel_sanctum_token...'),
                                new OA\Property(property: 'profile_complete', type: 'boolean', example: false),
                                new OA\Property(property: 'is_verified',      type: 'boolean', example: false),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Code OTP incorrect ou expiré',   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données incomplètes ou invalides', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone'    => ['required', 'string'],
            'otp_code' => ['required', 'string', 'size:6'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données fournies incomplètes.', $validator->errors(), 422);
        }

        $user = User::where('phone', $request->phone)
            ->where('otp_code', $request->otp_code)
            ->where('otp_expires_at', '>', now('UTC'))
            ->first();

        if (! $user) {
            return $this->apiResponse(false, 'Code OTP incorrect ou expiré.', [], 401);
        }

        $user->update([
            'phone_verified_at' => now(),
            'otp_code'          => null,
            'otp_expires_at'    => null,
        ]);

        $profile = Profile::where('user_id', $user->id)->first();
        $token   = $user->createToken('mobile_auth_token')->plainTextToken;

        return $this->apiResponse(true, 'Authentification réussie.', [
            'token'            => $token,
            'profile_complete' => ! is_null($profile),
            'is_verified'      => (bool) $user->is_verified,
            'user'             => $this->getUserWithDetails($user),
        ]);
    }

    // =========================================================================
    //  INSCRIPTION & SOUMISSION KYC
    // =========================================================================

    #[OA\Post(
        path: '/api/auth/register',
        operationId: 'register',
        summary: 'Inscription & Soumission KYC',
        description: 'Enregistre l\'identité civile de l\'utilisateur et soumet le dossier KYC. Si le rôle est `driver`, les pièces du véhicule et les habilitations de transport sont **obligatoires**.',
        tags: ['🔓 Authentification & KYC'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: [
                        'user_uuid', 'role_name', 'first_name', 'last_name',
                        'gender', 'city', 'neighborhood',
                        'selfie_front', 'selfie_left', 'selfie_right',
                        'id_card_front', 'id_card_back',
                    ],
                    properties: [
                        // — Identité
                        new OA\Property(property: 'user_uuid',        type: 'string',  format: 'uuid',   example: '8f3b6c7a-9c2d-4e5f-a1b2-c3d4e5f6a7b8'),
                        new OA\Property(property: 'role_name',        type: 'string',  enum: ['passenger', 'driver'], example: 'passenger'),
                        new OA\Property(property: 'first_name',       type: 'string',  example: 'Jean'),
                        new OA\Property(property: 'last_name',        type: 'string',  example: 'DOSSOU'),
                        new OA\Property(property: 'gender',           type: 'string',  enum: ['M', 'F'], example: 'M'),
                        new OA\Property(property: 'email',            type: 'string',  format: 'email',  example: 'jean.dossou@example.com', nullable: true),
                        new OA\Property(property: 'city',             type: 'string',  example: 'Cotonou'),
                        new OA\Property(property: 'neighborhood',     type: 'string',  example: 'Fidjrossè'),
                        new OA\Property(property: 'address_details',  type: 'string',  example: 'Face pharmacie du centre', nullable: true),
                        // — Selfies & pièces d'identité
                        new OA\Property(property: 'selfie_front',    type: 'string', format: 'binary', description: 'Photo de face'),
                        new OA\Property(property: 'selfie_left',     type: 'string', format: 'binary', description: 'Photo de profil gauche'),
                        new OA\Property(property: 'selfie_right',    type: 'string', format: 'binary', description: 'Photo de profil droit'),
                        new OA\Property(property: 'id_card_front',   type: 'string', format: 'binary', description: 'Recto CNI / passeport'),
                        new OA\Property(property: 'id_card_back',    type: 'string', format: 'binary', description: 'Verso CNI'),
                        // — Conducteur uniquement
                        new OA\Property(property: 'driving_license_number', type: 'string', example: '120452026',   description: '[driver] Numéro permis'),
                        new OA\Property(property: 'driving_license_photo',  type: 'string', format: 'binary',       description: '[driver] Photo permis'),
                        new OA\Property(property: 'vehicle_type',           type: 'string', example: 'voiture',     description: '[driver] Slug du type de véhicule'),
                        new OA\Property(property: 'brand',                  type: 'string', example: 'Toyota',      description: '[driver]'),
                        new OA\Property(property: 'model',                  type: 'string', example: 'Camry',       description: '[driver]'),
                        new OA\Property(property: 'color',                  type: 'string', example: 'Noir',        description: '[driver]'),
                        new OA\Property(property: 'license_plate',          type: 'string', example: 'RB 1234 X',   description: '[driver]'),
                        new OA\Property(property: 'available_seats',        type: 'integer', example: 3,            description: '[driver]'),
                        new OA\Property(property: 'vehicle_photo',          type: 'string', format: 'binary',       description: '[driver]'),
                        new OA\Property(property: 'registration_doc',       type: 'string', format: 'binary',       description: '[driver] Carte grise'),
                        new OA\Property(property: 'insurance_doc',          type: 'string', format: 'binary',       description: '[driver] Assurance'),
                        new OA\Property(property: 'tvm_doc',                type: 'string', format: 'binary',       description: '[driver] TVM (optionnel)', nullable: true),
                        new OA\Property(property: 'technical_control_doc',  type: 'string', format: 'binary',       description: '[driver] Visite technique (optionnel)', nullable: true),
                        // — Contacts d'urgence (optionnel, max 5)
                        new OA\Property(
                            property: 'emergency_contacts',
                            type: 'array',
                            nullable: true,
                            description: 'Contacts d\'urgence (famille, amis). Max 5. Envoyer en JSON encodé ou format indexé emergency_contacts[0][name]=...',
                            items: new OA\Items(
                                required: ['name', 'relationship', 'phone'],
                                properties: [
                                    new OA\Property(property: 'name',         type: 'string', example: 'Mama Adèle'),
                                    new OA\Property(property: 'relationship', type: 'string', example: 'maman', description: 'maman, papa, femme, mari, ami, frère, sœur, etc.'),
                                    new OA\Property(property: 'phone',        type: 'string', example: '+22997000000'),
                                ]
                            )
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Dossier soumis — en attente de validation KYC'),
            new OA\Response(response: 404, description: 'UUID utilisateur introuvable',    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Erreur de validation des champs', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Erreur serveur interne',          content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $rules = [
            'user_uuid'                          => ['required', 'uuid', 'exists:users,uuid'],
            'role_name'                          => ['required', 'string', 'in:passenger,driver'],
            'first_name'                         => ['required', 'string', 'max:100'],
            'last_name'                          => ['required', 'string', 'max:100'],
            'gender'                             => ['required', 'in:M,F'],
            'email'                              => ['nullable', 'email', 'unique:profiles,email'],
            'city'                               => ['required', 'string'],
            'neighborhood'                       => ['required', 'string'],
            'address_details'                    => ['nullable', 'string'],
            'selfie_front'                       => ['required', 'image', 'max:5120'],
            'selfie_left'                        => ['required', 'image', 'max:5120'],
            'selfie_right'                       => ['required', 'image', 'max:5120'],
            'id_card_front'                      => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:7168'],
            'id_card_back'                       => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:7168'],
            'emergency_contacts'                 => ['nullable', 'array', 'max:5'],
            'emergency_contacts.*.name'          => ['required_with:emergency_contacts', 'string', 'max:80'],
            'emergency_contacts.*.relationship'  => ['required_with:emergency_contacts', 'string', 'max:40'],
            'emergency_contacts.*.phone'         => ['required_with:emergency_contacts', 'string', 'max:20'],
        ];

        if ($request->input('role_name') === 'driver') {
            $rules = array_merge($rules, [
                'driving_license_number' => ['required', 'string', 'max:50'],
                'driving_license_photo'  => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:7168'],
                'vehicle_type'           => ['required', 'string', 'exists:vehicle_types,slug'],
                'brand'                  => ['required', 'string'],
                'model'                  => ['required', 'string'],
                'color'                  => ['required', 'string'],
                'license_plate'          => ['required', 'string', 'unique:vehicles,license_plate'],
                'vehicle_photo'          => ['required', 'image', 'max:7168'],
                'registration_doc'       => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:7168'],
                'insurance_doc'          => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:7168'],
                'available_seats'        => ['required', 'integer', 'min:1'],
                'tvm_doc'                => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:7168'],
                'technical_control_doc'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:7168'],
            ]);
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->apiResponse(false, 'Erreur de validation des données.', $validator->errors(), 422);
        }

        $user = User::where('uuid', $request->user_uuid)->first();
        if (! $user) {
            return $this->apiResponse(false, 'Utilisateur introuvable à partir de l\'UUID fourni.', [], 404);
        }

        DB::beginTransaction();
        try {
            $selfieFront = $request->file('selfie_front')->store('kyc/selfies',    'public');
            $selfieLeft  = $request->file('selfie_left')->store('kyc/selfies',     'public');
            $selfieRight = $request->file('selfie_right')->store('kyc/selfies',    'public');
            $idFront     = $request->file('id_card_front')->store('kyc/documents', 'public');
            $idBack      = $request->file('id_card_back')->store('kyc/documents',  'public');

            $profileData = [
                'user_id'         => $user->id,
                'first_name'      => Str::title(strtolower($request->first_name)),
                'last_name'       => strtoupper($request->last_name),
                'gender'          => $request->gender,
                'email'           => $request->email,
                'city'            => ucfirst(strtolower($request->city)),
                'neighborhood'    => ucfirst(strtolower($request->neighborhood)),
                'address_details' => $request->address_details,
                'selfie_front'    => $selfieFront,
                'selfie_left'     => $selfieLeft,
                'selfie_right'    => $selfieRight,
                'id_card_front'   => $idFront,
                'id_card_back'    => $idBack,
                'kyc_status'      => 'pending',
            ];

            if ($request->role_name === 'driver') {
                $licensePhoto                          = $request->file('driving_license_photo')->store('kyc/documents', 'public');
                $profileData['driving_license_number'] = $request->driving_license_number;
                $profileData['driving_license_photo']  = $licensePhoto;
            }

            Profile::create($profileData);

            if ($request->filled('emergency_contacts')) {
                foreach ($request->emergency_contacts as $contact) {
                    EmergencyContact::create([
                        'user_id'      => $user->id,
                        'name'         => $contact['name'],
                        'relationship' => $contact['relationship'],
                        'phone'        => $contact['phone'],
                    ]);
                }
            }

            if ($request->role_name === 'driver') {
                $vType = VehicleType::where('slug', $request->vehicle_type)->firstOrFail();

                Vehicle::create([
                    'user_id'               => $user->id,
                    'vehicle_type_id'       => $vType->id,
                    'brand'                 => ucfirst(strtolower($request->brand)),
                    'model'                 => ucfirst(strtolower($request->model)),
                    'color'                 => ucfirst(strtolower($request->color)),
                    'available_seats'       => $request->available_seats,
                    'license_plate'         => strtoupper($request->license_plate),
                    'vehicle_photo'         => $request->file('vehicle_photo')->store('vehicles', 'public'),
                    'registration_doc'      => $request->file('registration_doc')->store('vehicles/docs', 'public'),
                    'insurance_doc'         => $request->file('insurance_doc')->store('vehicles/docs', 'public'),
                    'tvm_doc'               => $request->hasFile('tvm_doc')               ? $request->file('tvm_doc')->store('vehicles/docs', 'public')               : null,
                    'technical_control_doc' => $request->hasFile('technical_control_doc') ? $request->file('technical_control_doc')->store('vehicles/docs', 'public') : null,
                    'is_approved'           => false,
                ]);
            }

            $chosenRole = Role::where('name', $request->role_name)->first();
            $user->update([
                'role_id'     => $chosenRole?->id ?? $user->role_id,
                'is_verified' => false,
            ]);

            $token = $user->createToken('mobile_auth_token')->plainTextToken;

            DB::commit();

            return $this->apiResponse(true, 'Dossier d\'inscription soumis. En attente de validation.', [
                'token' => $token,
                'user'  => $this->getUserWithDetails($user),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->apiResponse(false, 'Échec lors de l\'enregistrement du dossier.', ['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    //  CONNEXION BACK-OFFICE (ADMIN)
    // =========================================================================

    #[OA\Post(
        path: '/api/auth/admin/login',
        operationId: 'adminLogin',
        summary: 'Connexion Administrateur',
        description: 'Authentification email / mot de passe réservée aux membres de l\'équipe Back-Office. Retourne un token Sanctum avec scope `*`.',
        tags: ['👑 Administration'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email',    type: 'string', format: 'email',    example: 'admin@minizon.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'minizon@229'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Authentification administrative réussie'),
            new OA\Response(response: 401, description: 'Identifiants incorrects',         content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Privilèges administratifs requis', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données invalides',                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function adminLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données d\'accès invalides.', $validator->errors(), 422);
        }

        $profile = Profile::where('email', $request->email)->first();
        if (! $profile) {
            return $this->apiResponse(false, 'Identifiants de sécurité incorrects.', [], 401);
        }

        $user = User::find($profile->user_id);

        if (! $user || ! $this->isAdmin($user)) {
            return $this->apiResponse(false, 'Accès refusé. Privilèges administratifs requis.', [], 403);
        }

        if (! Hash::check($request->password, $user->password)) {
            return $this->apiResponse(false, 'Identifiants de sécurité incorrects.', [], 401);
        }

        $token = $user->createToken('admin_backoffice_token', ['*'])->plainTextToken;

        return $this->apiResponse(true, 'Authentification administrative réussie.', [
            'token' => $token,
            'user'  => $this->getUserWithDetails($user),
        ]);
    }

    // =========================================================================
    //  ZONE SÉCURISÉE (AUTH REQUISE)
    // =========================================================================

    #[OA\Post(
        path: '/api/auth/logout',
        operationId: 'logout',
        summary: 'Déconnexion',
        description: 'Révoque définitivement le token Sanctum de la session courante.',
        tags: ['🔒 Session'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Session fermée — token révoqué'),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->apiResponse(true, 'Session fermée et token révoqué avec succès.');
    }

    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/auth/me',
        operationId: 'me',
        summary: 'Profil de l\'utilisateur connecté',
        description: 'Retourne les données complètes (profil, véhicule, rôle) de l\'utilisateur authentifié.',
        tags: ['🔒 Session'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Profil récupéré avec succès'),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_blocked) {
            return $this->apiResponse(false, 'Votre compte a été suspendu. Contactez l\'assistance.', [
                'account_status' => 'suspended',
                'blocked_until'  => $user->blocked_until?->toIso8601String(),
            ], 403);
        }

        return $this->apiResponse(true, 'Profil récupéré.', $this->getUserWithDetails($user));
    }

    // =========================================================================
    //  GESTION DES COMPTES (CRUD UTILISATEUR)
    // =========================================================================

    #[OA\Get(
        path: '/api/auth/users/{uuid}',
        operationId: 'showUser',
        summary: 'Consulter une fiche profil',
        description: 'Retourne les informations complètes d\'un utilisateur par son UUID.',
        tags: ['👤 Utilisateurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID de l\'utilisateur',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fiche profil récupérée'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Utilisateur introuvable.', [], 404);
        }

        return $this->apiResponse(true, 'Détails de l\'utilisateur récupérés.', $this->getUserWithDetails($user));
    }

    // -------------------------------------------------------------------------

    #[OA\Put(
        path: '/api/auth/users/{uuid}',
        operationId: 'updateUser',
        summary: 'Mettre à jour un profil',
        description: 'Met à jour les informations de profil. Un admin peut également modifier le numéro de téléphone et les pièces d\'identité.',
        tags: ['👤 Utilisateurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name', 'city', 'neighborhood'],
                properties: [
                    new OA\Property(property: 'first_name',      type: 'string', example: 'Jean'),
                    new OA\Property(property: 'last_name',       type: 'string', example: 'DOSSOU'),
                    new OA\Property(property: 'email',           type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'city',            type: 'string', example: 'Cotonou'),
                    new OA\Property(property: 'neighborhood',    type: 'string', example: 'Fidjrossè'),
                    new OA\Property(property: 'address_details', type: 'string', nullable: true),
                    new OA\Property(property: 'brand',           type: 'string', description: '[driver]', nullable: true),
                    new OA\Property(property: 'model',           type: 'string', description: '[driver]', nullable: true),
                    new OA\Property(property: 'color',           type: 'string', description: '[driver]', nullable: true),
                    new OA\Property(property: 'available_seats', type: 'integer', description: '[driver]', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour avec succès'),
            new OA\Response(response: 403, description: 'Action interdite',        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données invalides',       content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Erreur serveur interne',  content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->first();

        if (! $user || $request->user()->id !== $user->id) {
            return $this->apiResponse(false, 'Action interdite ou compte non autorisé.', [], 403);
        }

        $profile = Profile::where('user_id', $user->id)->firstOrFail();
        $vehicle = Vehicle::where('user_id', $user->id)->first();
        $isAdmin = $this->isAdmin($request->user());

        $rules = [
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'email'           => ['nullable', 'email', 'unique:profiles,email,' . $profile->id],
            'city'            => ['required', 'string'],
            'neighborhood'    => ['required', 'string'],
            'address_details' => ['nullable', 'string'],
        ];

        if ($isAdmin) {
            $rules['phone']         = ['required', 'string', 'unique:users,phone,' . $user->id];
            $rules['id_card_front'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:7168'];
            $rules['id_card_back']  = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:7168'];
        }

        if ($vehicle) {
            $rules['brand']           = ['required', 'string'];
            $rules['model']           = ['required', 'string'];
            $rules['color']           = ['required', 'string'];
            $rules['available_seats'] = ['required', 'integer', 'min:1'];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données invalides pour la mise à jour.', $validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            $profileData = [
                'first_name'      => Str::title(strtolower($request->first_name)),
                'last_name'       => strtoupper($request->last_name),
                'email'           => $request->email,
                'city'            => ucfirst(strtolower($request->city)),
                'neighborhood'    => ucfirst(strtolower($request->neighborhood)),
                'address_details' => $request->address_details,
            ];

            if ($isAdmin) {
                $user->update(['phone' => $request->phone]);

                if ($request->hasFile('id_card_front')) {
                    $profileData['id_card_front'] = $request->file('id_card_front')->store('kyc/documents', 'public');
                }
                if ($request->hasFile('id_card_back')) {
                    $profileData['id_card_back'] = $request->file('id_card_back')->store('kyc/documents', 'public');
                }
            }

            Profile::where('user_id', $user->id)->update($profileData);

            if ($vehicle) {
                Vehicle::where('user_id', $user->id)->update([
                    'brand'           => ucfirst(strtolower($request->brand)),
                    'model'           => ucfirst(strtolower($request->model)),
                    'color'           => ucfirst(strtolower($request->color)),
                    'available_seats' => $request->available_seats,
                ]);
            }

            DB::commit();

            return $this->apiResponse(true, 'Informations de profil mises à jour avec succès.', $this->getUserWithDetails($user));

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->apiResponse(false, 'Une erreur est survenue lors de la sauvegarde.', [], 500);
        }
    }

    // -------------------------------------------------------------------------

    #[OA\Delete(
        path: '/api/auth/users/{uuid}',
        operationId: 'deleteUser',
        summary: 'Supprimer un compte',
        description: 'Supprime en **cascade** toutes les données liées au compte (profil, véhicule, tokens). Action irréversible.',
        tags: ['👤 Utilisateurs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Compte et données associées supprimés'),
            new OA\Response(response: 404, description: 'Compte introuvable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Erreur lors de la suppression', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function delete(string $uuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->first();

        if (! $user) {
            return $this->apiResponse(false, 'Compte introuvable.', [], 404);
        }

        DB::beginTransaction();
        try {
            Vehicle::where('user_id', $user->id)->delete();
            Profile::where('user_id', $user->id)->delete();
            $user->tokens()->delete();
            $user->delete();

            DB::commit();

            return $this->apiResponse(true, 'Compte utilisateur et données associées purgés définitivement.');

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->apiResponse(false, 'Échec de l\'opération de suppression en cascade.', [], 500);
        }
    }

    // =========================================================================
    //  PANEL ADMINISTRATIF
    // =========================================================================

    #[OA\Get(
        path: '/api/auth/admin/users',
        operationId: 'adminListUsers',
        summary: '[ADMIN] Liste de tous les utilisateurs',
        description: 'Extrait la liste ordonnée de tous les comptes enregistrés. Filtre optionnel par `role_id`.',
        tags: ['👑 Administration'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'role_id',
                in: 'query',
                required: false,
                description: 'Filtrer par ID de rôle',
                schema: new OA\Schema(type: 'integer', example: 2)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste extraite avec succès'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $this->isAdmin($request->user())) {
            return $this->apiResponse(false, 'Action non autorisée.', [], 403);
        }

        $query = User::query();

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        $users = $query->orderByDesc('created_at')->get()
            ->map(fn (User $user) => $this->getUserWithDetails($user));

        return $this->apiResponse(true, 'Liste des utilisateurs extraite.', $users);
    }

    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/auth/admin/users/{uuid}/validate-kyc',
        operationId: 'validateKyc',
        summary: '[ADMIN] Approbation / Rejet KYC',
        description: 'Enregistre le verdict de vérification d\'identité. En cas d\'approbation, le compte est marqué `is_verified = true` et le véhicule est activé.',
        tags: ['👑 Administration'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['approved', 'rejected'], example: 'approved'),
                    new OA\Property(property: 'score',  type: 'number', format: 'float', example: 95.5, nullable: true, description: 'Score de correspondance biométrique (0–100)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Statut KYC mis à jour'),
            new OA\Response(response: 403, description: 'Action réservée aux administrateurs', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Utilisateur introuvable',             content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Données de validation incorrectes',   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function validateKyc(Request $request, string $uuid): JsonResponse
    {
        if (! $this->isAdmin($request->user())) {
            return $this->apiResponse(false, 'Action non autorisée.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:approved,rejected'],
            'score'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'Données de validation incorrectes.', $validator->errors(), 422);
        }

        $user = User::where('uuid', $uuid)->first();
        if (! $user) {
            return $this->apiResponse(false, 'Utilisateur introuvable.', [], 404);
        }

        DB::beginTransaction();
        try {
            $isApproved = ($request->status === 'approved');

            Profile::where('user_id', $user->id)->update([
                'kyc_status'          => $request->status,
                'kyc_matching_score'  => $request->score,
                'approved_at'         => $isApproved ? now() : null,
            ]);

            $user->update(['is_verified' => $isApproved]);
            Vehicle::where('user_id', $user->id)->update(['is_approved' => $isApproved]);

            DB::commit();

            return $this->apiResponse(true, 'Le statut KYC a été mis à jour avec succès.', $this->getUserWithDetails($user));

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->apiResponse(false, 'Une erreur interne a bloqué la validation administrative.', [], 500);
        }
    }

    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/auth/admin/users/{uuid}/toggle-block',
        operationId: 'toggleBlock',
        summary: '[ADMIN] Suspendre / Lever la suspension',
        description: 'Bascule le statut de blocage d\'un compte. Si le compte est actif, il est suspendu pour 10 ans. Si déjà suspendu, la suspension est levée.',
        tags: ['👑 Administration'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Statut de suspension mis à jour'),
            new OA\Response(response: 403, description: 'Action réservée aux administrateurs', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Compte introuvable',                  content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function toggleBlock(Request $request, string $uuid): JsonResponse
    {
        if (! $this->isAdmin($request->user())) {
            return $this->apiResponse(false, 'Action non autorisée.', [], 403);
        }

        $user = User::where('uuid', $uuid)->first();
        if (! $user) {
            return $this->apiResponse(false, 'Compte introuvable.', [], 404);
        }

        $newStatus = ! $user->is_blocked;

        $user->update([
            'is_blocked'    => $newStatus,
            'blocked_until' => $newStatus ? now()->addYears(10) : null,
        ]);

        $message = $newStatus
            ? 'L\'utilisateur a été suspendu de la plateforme.'
            : 'La suspension de l\'utilisateur a été levée.';

        return $this->apiResponse(true, $message, $this->getUserWithDetails($user));
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    /**
     * Formate une réponse JSON standardisée pour toute l'API.
     *
     * @param  mixed  $body
     */
    protected function apiResponse(bool $success, string $message, mixed $body = [], int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'body'    => $body,
        ], $statusCode);
    }

    /**
     * Vérifie si l'utilisateur possède le rôle administrateur.
     */
    private function isAdmin(User $user): bool
    {
        $adminRole = Role::where('name', 'admin')->first();

        return $adminRole && (int) $user->role_id === (int) $adminRole->id;
    }

    /**
     * Construit un tableau complet des données utilisateur (profil + véhicule + rôle).
     */
    private function getUserWithDetails(User $user): array
    {
        $role              = Role::find($user->role_id);
        $profile           = Profile::where('user_id', $user->id)->first();
        $vehicle           = Vehicle::where('user_id', $user->id)->first();
        $emergencyContacts = EmergencyContact::where('user_id', $user->id)
            ->orderBy('created_at')
            ->get(['id', 'name', 'relationship', 'phone'])
            ->toArray();

        $vehicleDetails = null;
        if ($vehicle) {
            $vType          = VehicleType::find($vehicle->vehicle_type_id);
            $vehicleDetails = array_merge($vehicle->toArray(), [
                'vehicle_type_name' => $vType?->name,
                'vehicle_type_slug' => $vType?->slug,
            ]);
        }

        $accountStatus = match (true) {
            (bool) $user->is_blocked  => 'suspended',
            ! (bool) $user->is_verified => 'pending_approval',
            default                   => 'active',
        };

        return [
            'id'                 => $user->id,
            'uuid'               => $user->uuid,
            'phone'              => $user->phone,
            'is_verified'        => (bool) $user->is_verified,
            'is_blocked'         => (bool) $user->is_blocked,
            'account_status'     => $accountStatus,
            'blocked_until'      => $user->blocked_until?->toIso8601String(),
            'penalty_points'     => (int) $user->penalty_points,
            'role'               => $role?->name ?? 'passenger',
            'profile'            => $profile?->toArray(),
            'vehicle'            => $vehicleDetails,
            'emergency_contacts' => $emergencyContacts,
        ];
    }

    // =========================================================================
    //  POST /api/auth/fcm-token
    // =========================================================================

    #[OA\Post(
        path: '/api/auth/fcm-token',
        operationId: 'registerFcmToken',
        summary: 'Enregistrer / mettre à jour le token FCM',
        description: 'Appelé côté client après authentification pour stocker le token FCM de l\'appareil. Utilisé pour l\'envoi de notifications push.',
        tags: ['🔐 Authentification'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['fcm_token'],
                properties: [
                    new OA\Property(property: 'fcm_token', type: 'string', example: 'dGhpcyBpcyBhIHNhbXBsZSB0b2tlbg=='),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Token FCM enregistré'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 422, description: 'Token manquant'),
        ]
    )]
    public function registerFcmToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => ['required', 'string', 'max:512'],
        ]);

        $request->user()->update([
            'fcm_token' => $request->fcm_token,
        ]);

        return $this->apiResponse(true, 'Token FCM enregistré.');
    }
}