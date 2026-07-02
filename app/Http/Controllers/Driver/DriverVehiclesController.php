<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            new OA\Response(response: 200, description: 'Véhicules récupérés'),
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
        tags: ['🚗 Driver — Véhicules'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['brand', 'model', 'color', 'year', 'license_plate', 'available_seats'],
                properties: [
                    new OA\Property(property: 'vehicle_type_id',  type: 'integer'),
                    new OA\Property(property: 'brand',            type: 'string', example: 'Toyota'),
                    new OA\Property(property: 'model',            type: 'string', example: 'Corolla'),
                    new OA\Property(property: 'color',            type: 'string', example: 'Blanc'),
                    new OA\Property(property: 'year',             type: 'integer', example: 2019),
                    new OA\Property(property: 'license_plate',    type: 'string', example: 'BJ-1234-AA'),
                    new OA\Property(property: 'available_seats',  type: 'integer', example: 4),
                    new OA\Property(property: 'fuel_type',        type: 'string', example: 'Essence'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Véhicule enregistré'),
            new OA\Response(response: 422, description: 'Validation'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Un conducteur ne peut pas avoir plus de 3 véhicules
        if (Vehicle::where('user_id', $user->id)->count() >= 3) {
            return $this->apiResponse(false, 'Vous avez atteint la limite de 3 véhicules.', null, 422);
        }

        $validated = $request->validate([
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'brand'           => ['required', 'string', 'max:100'],
            'model'           => ['required', 'string', 'max:100'],
            'color'           => ['required', 'string', 'max:50'],
            'year'            => ['required', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'license_plate'   => ['required', 'string', 'max:20'],
            'available_seats' => ['required', 'integer', 'min:1', 'max:9'],
            'fuel_type'       => ['nullable', 'string', 'max:50'],
        ]);

        $vehicle = Vehicle::create([
            'user_id'              => $user->id,
            'vehicle_type_id'      => $validated['vehicle_type_id'] ?? null,
            'brand'                => $validated['brand'],
            'model'                => $validated['model'],
            'color'                => $validated['color'],
            'year'                 => $validated['year'],
            'license_plate'        => $validated['license_plate'],
            'available_seats'      => $validated['available_seats'],
            'verification_status'  => 'pending',
            'is_approved'          => false,
        ]);

        return $this->apiResponse(true, 'Véhicule enregistré. En attente de vérification (1-2 jours ouvrés).', [
            'vehicle' => $this->formatVehicle($vehicle->load('vehicleType')),
        ]);
    }

    // =========================================================================
    //  PUT /api/driver/vehicles/{uuid}
    // =========================================================================

    #[OA\Put(
        path: '/api/driver/vehicles/{uuid}',
        operationId: 'driverUpdateVehicle',
        summary: 'Mettre à jour un véhicule',
        tags: ['🚗 Driver — Véhicules'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true,
                schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Véhicule mis à jour'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Véhicule introuvable'),
        ]
    )]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $user    = $request->user();
        $vehicle = Vehicle::where('user_id', $user->id)->where('id', $uuid)->firstOrFail();

        $validated = $request->validate([
            'brand'           => ['sometimes', 'string', 'max:100'],
            'model'           => ['sometimes', 'string', 'max:100'],
            'color'           => ['sometimes', 'string', 'max:50'],
            'year'            => ['sometimes', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'license_plate'   => ['sometimes', 'string', 'max:20'],
            'available_seats' => ['sometimes', 'integer', 'min:1', 'max:9'],
        ]);

        $vehicle->update(array_merge($validated, [
            'verification_status' => 'pending',
            'is_approved'         => false,
        ]));

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
            new OA\Parameter(name: 'uuid', in: 'path', required: true,
                schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Véhicule supprimé'),
            new OA\Response(response: 403, description: 'Non autorisé'),
        ]
    )]
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user    = $request->user();
        $vehicle = Vehicle::where('user_id', $user->id)->where('id', $uuid)->firstOrFail();

        if ($vehicle->trips()->where('status', 'active')->exists()) {
            return $this->apiResponse(false, 'Impossible de supprimer un véhicule avec un trajet en cours.', null, 422);
        }

        $vehicle->delete();

        return $this->apiResponse(true, 'Véhicule supprimé.');
    }

    // =========================================================================
    //  HELPER PRIVÉ
    // =========================================================================

    private function formatVehicle(Vehicle $v): array
    {
        return [
            'id'                  => $v->id,
            'brand'               => $v->brand,
            'model'               => $v->model,
            'color'               => $v->color,
            'year'                => $v->year,
            'license_plate'       => $v->license_plate,
            'available_seats'     => $v->available_seats,
            'vehicle_type'        => $v->vehicleType?->name,
            'verification_status' => $v->verification_status ?? 'pending',
            'is_approved'         => (bool) $v->is_approved,
            'rejection_reason'    => $v->rejection_reason,
            'full_name'           => $v->fullName(),
        ];
    }
}
