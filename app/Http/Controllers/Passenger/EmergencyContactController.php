<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class EmergencyContactController extends Controller
{
    private const MAX_CONTACTS = 5;

    // =========================================================================
    //  GET /api/passenger/emergency-contacts
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/emergency-contacts',
        operationId: 'passengerEmergencyContactsIndex',
        summary: 'Liste des contacts d\'urgence du passager',
        tags: ['👤 Passenger — Profil'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des contacts d\'urgence',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Contacts d\'urgence.'),
                        new OA\Property(
                            property: 'body',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/EmergencyContact')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $contacts = $request->user()
            ->emergencyContacts()
            ->orderBy('created_at')
            ->get(['id', 'name', 'relationship', 'phone']);

        return $this->apiResponse(true, 'Contacts d\'urgence.', $contacts->toArray());
    }

    // =========================================================================
    //  POST /api/passenger/emergency-contacts
    // =========================================================================

    #[OA\Post(
        path: '/api/passenger/emergency-contacts',
        operationId: 'passengerEmergencyContactsStore',
        summary: 'Ajouter un contact d\'urgence',
        tags: ['👤 Passenger — Profil'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'relationship', 'phone'],
                properties: [
                    new OA\Property(property: 'name',         type: 'string', example: 'Mama Adèle'),
                    new OA\Property(property: 'relationship', type: 'string', example: 'maman', description: 'maman, papa, femme, ami, frère, sœur, etc.'),
                    new OA\Property(property: 'phone',        type: 'string', example: '+22997000000'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Contact ajouté'),
            new OA\Response(response: 422, description: 'Données invalides ou limite atteinte'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->emergencyContacts()->count() >= self::MAX_CONTACTS) {
            return $this->apiResponse(false, 'Limite de ' . self::MAX_CONTACTS . ' contacts d\'urgence atteinte.', [], 422);
        }

        $validated = $request->validate([
            'name'         => 'required|string|max:80',
            'relationship' => 'required|string|max:40',
            'phone'        => 'required|string|max:20',
        ]);

        $contact = $user->emergencyContacts()->create($validated);

        return $this->apiResponse(true, 'Contact d\'urgence ajouté.', [
            'id'           => $contact->id,
            'name'         => $contact->name,
            'relationship' => $contact->relationship,
            'phone'        => $contact->phone,
        ], 201);
    }

    // =========================================================================
    //  PUT /api/passenger/emergency-contacts/{id}
    // =========================================================================

    #[OA\Put(
        path: '/api/passenger/emergency-contacts/{id}',
        operationId: 'passengerEmergencyContactsUpdate',
        summary: 'Modifier un contact d\'urgence',
        tags: ['👤 Passenger — Profil'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name',         type: 'string', example: 'Mama Adèle'),
                    new OA\Property(property: 'relationship', type: 'string', example: 'maman'),
                    new OA\Property(property: 'phone',        type: 'string', example: '+22997000000'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Contact mis à jour'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Contact introuvable'),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $contact = $this->findOwnContact($request, $id);
        if ($contact instanceof JsonResponse) return $contact;

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:80',
            'relationship' => 'sometimes|string|max:40',
            'phone'        => 'sometimes|string|max:20',
        ]);

        $contact->update($validated);

        return $this->apiResponse(true, 'Contact d\'urgence mis à jour.', [
            'id'           => $contact->id,
            'name'         => $contact->name,
            'relationship' => $contact->relationship,
            'phone'        => $contact->phone,
        ]);
    }

    // =========================================================================
    //  DELETE /api/passenger/emergency-contacts/{id}
    // =========================================================================

    #[OA\Delete(
        path: '/api/passenger/emergency-contacts/{id}',
        operationId: 'passengerEmergencyContactsDestroy',
        summary: 'Supprimer un contact d\'urgence',
        tags: ['👤 Passenger — Profil'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contact supprimé'),
            new OA\Response(response: 403, description: 'Non autorisé'),
            new OA\Response(response: 404, description: 'Contact introuvable'),
        ]
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $contact = $this->findOwnContact($request, $id);
        if ($contact instanceof JsonResponse) return $contact;

        $contact->delete();

        return $this->apiResponse(true, 'Contact d\'urgence supprimé.');
    }

    // =========================================================================
    //  OA SCHEMAS
    // =========================================================================

    #[OA\Schema(
        schema: 'EmergencyContact',
        properties: [
            new OA\Property(property: 'id',           type: 'integer', example: 1),
            new OA\Property(property: 'name',         type: 'string',  example: 'Mama Adèle'),
            new OA\Property(property: 'relationship', type: 'string',  example: 'maman'),
            new OA\Property(property: 'phone',        type: 'string',  example: '+22997000000'),
        ]
    )]
    private function schemaPlaceholder(): void {}

    // =========================================================================
    //  HELPERS
    // =========================================================================

    private function findOwnContact(Request $request, int $id): EmergencyContact|JsonResponse
    {
        $contact = EmergencyContact::find($id);

        if (! $contact) {
            return $this->apiResponse(false, 'Contact introuvable.', [], 404);
        }

        if ($contact->user_id !== $request->user()->id) {
            return $this->apiResponse(false, 'Non autorisé.', [], 403);
        }

        return $contact;
    }
}
