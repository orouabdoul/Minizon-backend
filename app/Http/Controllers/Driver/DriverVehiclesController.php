<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * Page "Mes véhicules" — gestion de la flotte personnelle du conducteur.
 */
class DriverVehiclesController extends Controller
{
    // =========================================================================
    //  GET /api/driver/vehicles
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/vehicles',
        operationId: 'driverVehicles',
        summary: 'Lister les véhicules du conducteur connecté',
        tags: ['🚗 Driver — Véhicules'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Véhicules récupérés',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'vehicles',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/DriverVehicle')
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $vehicles = Vehicle::where('user_id', $user->id)
            ->with('vehicleType')
            ->latest()
            ->get()
            ->map(fn ($v) => $this->formatVehicle($v));

        return $this->apiResponse(true, 'Véhicules du conducteur.', [
            'vehicles' => $vehicles,
        ]);
    }

    // =========================================================================
    //  POST /api/driver/vehicles
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/vehicles',
        operationId: 'driverAddVehicle',
        summary: 'Enregistrer un nouveau véhicule',
        description: 'Crée un véhicule avec ses documents. Utiliser `multipart/form-data`. Le type de véhicule est identifié par son slug (ex: `voiture`, `moto`, `minibus`).',
        tags: ['🚗 Driver — Véhicules'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['vehicle_type', 'brand', 'model', 'color', 'license_plate', 'available_seats'],
                    properties: [
                        new OA\Property(property: 'vehicle_type',           type: 'string',  example: 'voiture',     description: '[driver] Slug du type de véhicule'),
                        new OA\Property(property: 'brand',                  type: 'string',  example: 'Toyota',      description: '[driver]'),
                        new OA\Property(property: 'model',                  type: 'string',  example: 'Corolla',     description: '[driver]'),
                        new OA\Property(property: 'color',                  type: 'string',  example: 'Blanc',       description: '[driver]'),
                        new OA\Property(property: 'year',                   type: 'integer', example: 2019,          description: '[driver] Optionnel'),
                        new OA\Property(property: 'license_plate',          type: 'string',  example: 'BJ-1234-AA', description: '[driver]'),
                        new OA\Property(property: 'available_seats',        type: 'integer', example: 4,             description: '[driver]'),
                        new OA\Property(property: 'vehicle_photo',          type: 'string',  format: 'binary',       description: '[driver]'),
                        new OA\Property(property: 'registration_doc',       type: 'string',  format: 'binary',       description: '[driver] Carte grise'),
                        new OA\Property(property: 'insurance_doc',          type: 'string',  format: 'binary',       description: '[driver] Assurance'),
                        new OA\Property(property: 'tvm_doc',                type: 'string',  format: 'binary',       description: '[driver] TVM (optionnel)'),
                        new OA\Property(property: 'technical_control_doc',  type: 'string',  format: 'binary',       description: '[driver] Visite technique (optionnel)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Véhicule enregistré'),
            new OA\Response(response: 422, description: 'Validation'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (Vehicle::where('user_id', $user->id)->count() >= 3) {
            return $this->apiResponse(false, 'Vous avez atteint la limite de 3 véhicules.', null, 422);
        }

        $validated = $request->validate([
            'vehicle_type'          => ['nullable', 'string', 'exists:vehicle_types,slug'],
            'brand'                 => ['required', 'string', 'max:100'],
            'model'                 => ['required', 'string', 'max:100'],
            'color'                 => ['required', 'string', 'max:50'],
            'year'                  => ['nullable', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'license_plate'         => ['required', 'string', 'max:20'],
            'available_seats'       => ['required', 'integer', 'min:1', 'max:9'],
            'vehicle_photo'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'registration_doc'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'insurance_doc'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'tvm_doc'               => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'technical_control_doc' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
        ]);

        $vehicleTypeId = null;
        if (! empty($validated['vehicle_type'])) {
            $vehicleTypeId = VehicleType::where('slug', $validated['vehicle_type'])->value('id');
        }

        $vehicle = Vehicle::create([
            'user_id'              => $user->id,
            'vehicle_type_id'      => $vehicleTypeId,
            'brand'                => $validated['brand'],
            'model'                => $validated['model'],
            'color'                => $validated['color'],
            'year'                 => $validated['year'] ?? null,
            'license_plate'        => $validated['license_plate'],
            'available_seats'      => $validated['available_seats'],
            'vehicle_photo'        => $request->hasFile('vehicle_photo')         ? $request->file('vehicle_photo')->store('vehicles', 'public')              : null,
            'registration_doc'     => $request->hasFile('registration_doc')      ? $request->file('registration_doc')->store('vehicles/docs', 'public')      : null,
            'insurance_doc'        => $request->hasFile('insurance_doc')         ? $request->file('insurance_doc')->store('vehicles/docs', 'public')         : null,
            'tvm_doc'              => $request->hasFile('tvm_doc')               ? $request->file('tvm_doc')->store('vehicles/docs', 'public')               : null,
            'technical_control_doc'=> $request->hasFile('technical_control_doc') ? $request->file('technical_control_doc')->store('vehicles/docs', 'public') : null,
            'verification_status'  => 'pending',
            'is_approved'          => false,
        ]);

        // Notifier les admins du nouveau véhicule à valider
        $profile     = $user->profile;
        $driverName  = $profile ? trim("{$profile->first_name} {$profile->last_name}") : $user->phone;
        $vehicleName = $vehicle->brand . ' ' . $vehicle->model;

        AdminNotification::notifyAdmins(
            type:        'vehicle',
            priority:    'high',
            title:       'Nouveau véhicule à valider',
            description: "{$driverName} a soumis un nouveau véhicule ({$vehicleName}) en attente de vérification.",
            refType:     'vehicle',
            refId:       (string) $vehicle->id,
            userId:      $user->id,
        );

        return $this->apiResponse(true, 'Véhicule enregistré. En attente de vérification (1-2 jours ouvrés).', [
            'vehicle' => $this->formatVehicle($vehicle->load('vehicleType')),
        ], 201);
    }

    // =========================================================================
    //  PUT /api/driver/vehicles/{uuid}
    // =========================================================================

    #[OA\Put(
        path: '/api/driver/vehicles/{uuid}',
        operationId: 'driverUpdateVehicle',
        summary: 'Mettre à jour un véhicule',
        description: 'Met à jour les infos et/ou les documents d\'un véhicule. Utiliser `multipart/form-data`. Seuls les champs envoyés sont modifiés.',
        tags: ['🚗 Driver — Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'vehicle_type',           type: 'string',  example: 'voiture',     description: 'Slug du type de véhicule'),
                        new OA\Property(property: 'brand',                  type: 'string',  example: 'Toyota'),
                        new OA\Property(property: 'model',                  type: 'string',  example: 'Corolla'),
                        new OA\Property(property: 'color',                  type: 'string',  example: 'Blanc'),
                        new OA\Property(property: 'year',                   type: 'integer', example: 2019),
                        new OA\Property(property: 'license_plate',          type: 'string',  example: 'BJ-1234-AA'),
                        new OA\Property(property: 'available_seats',        type: 'integer', example: 4),
                        new OA\Property(property: 'vehicle_photo',          type: 'string',  format: 'binary'),
                        new OA\Property(property: 'registration_doc',       type: 'string',  format: 'binary',  description: 'Carte grise'),
                        new OA\Property(property: 'insurance_doc',          type: 'string',  format: 'binary',  description: 'Assurance'),
                        new OA\Property(property: 'tvm_doc',                type: 'string',  format: 'binary',  description: 'TVM'),
                        new OA\Property(property: 'technical_control_doc',  type: 'string',  format: 'binary',  description: 'Visite technique'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Véhicule mis à jour'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Véhicule introuvable'),
        ]
    )]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $user    = $request->user();
        $vehicle = Vehicle::where('user_id', $user->id)->where('id', $uuid)->first();

        if (! $vehicle) {
            return $this->apiResponse(false, 'Véhicule introuvable.', [], 404);
        }

        $validated = $request->validate([
            'vehicle_type'          => ['sometimes', 'string', 'exists:vehicle_types,slug'],
            'brand'                 => ['sometimes', 'string', 'max:100'],
            'model'                 => ['sometimes', 'string', 'max:100'],
            'color'                 => ['sometimes', 'string', 'max:50'],
            'year'                  => ['sometimes', 'nullable', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'license_plate'         => ['sometimes', 'string', 'max:20'],
            'available_seats'       => ['sometimes', 'integer', 'min:1', 'max:9'],
            'vehicle_photo'         => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'registration_doc'      => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'insurance_doc'         => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'tvm_doc'               => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'technical_control_doc' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
        ]);

        $updateData = array_filter([
            'brand'           => $validated['brand']           ?? null,
            'model'           => $validated['model']           ?? null,
            'color'           => $validated['color']           ?? null,
            'year'            => $validated['year']            ?? null,
            'license_plate'   => $validated['license_plate']   ?? null,
            'available_seats' => $validated['available_seats'] ?? null,
        ], fn ($v) => $v !== null);

        if (isset($validated['vehicle_type'])) {
            $updateData['vehicle_type_id'] = VehicleType::where('slug', $validated['vehicle_type'])->value('id');
        }

        foreach (['vehicle_photo', 'registration_doc', 'insurance_doc', 'tvm_doc', 'technical_control_doc'] as $field) {
            if ($request->hasFile($field)) {
                if ($vehicle->$field) {
                    Storage::disk('public')->delete($vehicle->$field);
                }
                $folder = $field === 'vehicle_photo' ? 'vehicles' : 'vehicles/docs';
                $updateData[$field] = $request->file($field)->store($folder, 'public');
            }
        }

        $updateData['verification_status'] = 'pending';
        $updateData['is_approved']         = false;

        $vehicle->update($updateData);

        return $this->apiResponse(true, 'Véhicule mis à jour. En attente de re-vérification.', [
            'vehicle' => $this->formatVehicle($vehicle->fresh()->load('vehicleType')),
        ]);
    }

    // =========================================================================
    //  DELETE /api/driver/vehicles/{uuid}
    // =========================================================================

    #[OA\Delete(
        path: '/api/driver/vehicles/{uuid}',
        operationId: 'driverDeleteVehicle',
        summary: 'Supprimer un véhicule',
        tags: ['🚗 Driver — Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Véhicule supprimé'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 422, description: 'Trajet en cours'),
            new OA\Response(response: 404, description: 'Véhicule introuvable'),
        ]
    )]
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user    = $request->user();
        $vehicle = Vehicle::where('user_id', $user->id)->where('id', $uuid)->first();

        if (! $vehicle) {
            return $this->apiResponse(false, 'Véhicule introuvable.', [], 404);
        }

        if ($vehicle->trips()->where('status', 'active')->exists()) {
            return $this->apiResponse(false, 'Impossible de supprimer un véhicule avec un trajet en cours.', null, 422);
        }

        foreach (['vehicle_photo', 'registration_doc', 'insurance_doc', 'tvm_doc', 'technical_control_doc'] as $field) {
            if ($vehicle->$field) {
                Storage::disk('public')->delete($vehicle->$field);
            }
        }

        $vehicle->delete();

        return $this->apiResponse(true, 'Véhicule supprimé.');
    }

    // =========================================================================
    //  OA SCHEMA
    // =========================================================================

    #[OA\Schema(
        schema: 'DriverVehicle',
        properties: [
            new OA\Property(property: 'id',                         type: 'integer', example: 1),
            new OA\Property(property: 'brand',                      type: 'string',  example: 'Toyota'),
            new OA\Property(property: 'model',                      type: 'string',  example: 'Corolla'),
            new OA\Property(property: 'color',                      type: 'string',  example: 'Blanc'),
            new OA\Property(property: 'year',                       type: 'integer', nullable: true),
            new OA\Property(property: 'license_plate',              type: 'string',  example: 'BJ-1234-AA'),
            new OA\Property(property: 'available_seats',            type: 'integer', example: 4),
            new OA\Property(property: 'vehicle_type',               type: 'string',  nullable: true, example: 'Voiture'),
            new OA\Property(property: 'vehicle_type_slug',          type: 'string',  nullable: true, example: 'voiture'),
            new OA\Property(property: 'verification_status',        type: 'string',  example: 'pending'),
            new OA\Property(property: 'is_approved',                type: 'boolean', example: false),
            new OA\Property(property: 'rejection_reason',           type: 'string',  nullable: true),
            new OA\Property(property: 'full_name',                  type: 'string',  example: 'Toyota Corolla — Blanc'),
            new OA\Property(property: 'vehicle_photo_url',          type: 'string',  nullable: true),
            new OA\Property(property: 'registration_doc_url',       type: 'string',  nullable: true),
            new OA\Property(property: 'insurance_doc_url',          type: 'string',  nullable: true),
            new OA\Property(property: 'tvm_doc_url',                type: 'string',  nullable: true),
            new OA\Property(property: 'technical_control_doc_url',  type: 'string',  nullable: true),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPER PRIVÉ
    // =========================================================================

    private function formatVehicle(Vehicle $v): array
    {
        $url = fn (?string $path) => $path ? Storage::disk('public')->url($path) : null;

        return [
            'id'                        => $v->id,
            'brand'                     => $v->brand,
            'model'                     => $v->model,
            'color'                     => $v->color,
            'year'                      => $v->year,
            'license_plate'             => $v->license_plate,
            'available_seats'           => $v->available_seats,
            'vehicle_type'              => $v->vehicleType?->name,
            'vehicle_type_slug'         => $v->vehicleType?->slug,
            'verification_status'       => $v->verification_status ?? 'pending',
            'is_approved'               => (bool) $v->is_approved,
            'rejection_reason'          => $v->rejection_reason,
            'full_name'                 => $v->fullName(),
            'vehicle_photo_url'         => $url($v->vehicle_photo),
            'registration_doc_url'      => $url($v->registration_doc),
            'insurance_doc_url'         => $url($v->insurance_doc),
            'tvm_doc_url'               => $url($v->tvm_doc),
            'technical_control_doc_url' => $url($v->technical_control_doc),
        ];
    }
}
