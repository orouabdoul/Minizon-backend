<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Support" — FAQ statique et création de tickets de support.
 */
class DriverSupportController extends Controller
{
    // =========================================================================
    //  GET /api/driver/support/faq
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/support/faq',
        operationId: 'driverFaq',
        summary: 'Sujets et questions fréquentes (FAQ)',
        tags: ['🎧 Driver — Support'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'FAQ récupérée'),
        ]
    )]
    public function faq(Request $request): JsonResponse
    {
        return $this->apiResponse(true, 'FAQ conducteur.', [
            'topics' => self::FAQ_TOPICS,
        ]);
    }

    // =========================================================================
    //  POST /api/driver/support/tickets
    // =========================================================================

    #[OA\Post(
        path: '/api/driver/support/tickets',
        operationId: 'driverCreateTicket',
        summary: 'Créer un ticket de support',
        tags: ['🎧 Driver — Support'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['subject', 'description'],
                properties: [
                    new OA\Property(property: 'subject',     type: 'string', maxLength: 255),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'priority',    type: 'string',
                        enum: ['low', 'medium', 'high'], default: 'medium'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ticket créé'),
            new OA\Response(response: 422, description: 'Validation'),
        ]
    )]
    public function createTicket(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'subject'     => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority'    => ['nullable', 'string', 'in:low,medium,high'],
        ]);

        $ticket = SupportTicket::create([
            'user_id'     => $user->id,
            'subject'     => $validated['subject'],
            'description' => $validated['description'],
            'priority'    => $validated['priority'] ?? 'medium',
            'channel'     => 'app',
            'status'      => 'new',
        ]);

        return $this->apiResponse(true, 'Ticket de support créé. Nous vous répondons sous 24h.', [
            'uuid'   => $ticket->uuid,
            'status' => $ticket->status,
        ]);
    }

    // =========================================================================
    //  GET /api/driver/support/tickets
    // =========================================================================

    #[OA\Get(
        path: '/api/driver/support/tickets',
        operationId: 'driverTickets',
        summary: 'Historique des tickets du conducteur',
        tags: ['🎧 Driver — Support'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Tickets récupérés'),
        ]
    )]
    public function tickets(Request $request): JsonResponse
    {
        $user    = $request->user();
        $tickets = SupportTicket::where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'uuid'       => $t->uuid,
                'subject'    => $t->subject,
                'status'     => $t->status,
                'priority'   => $t->priority,
                'created_at' => $t->created_at->setTimezone('Africa/Porto-Novo')->translatedFormat('d F Y à H:i'),
            ]);

        return $this->apiResponse(true, 'Tickets de support.', [
            'tickets' => $tickets,
        ]);
    }

    // =========================================================================
    //  FAQ — contenu statique
    // =========================================================================

    private const FAQ_TOPICS = [
        [
            'icon_name' => 'payments_rounded',
            'label'     => 'Problème de paiement',
            'color'     => 0xFF00A86B,
            'items'     => [
                [
                    'question' => 'Quand vais-je recevoir mon paiement ?',
                    'answer'   => 'Les paiements sont disponibles dans votre portefeuille MINIZON immédiatement après confirmation par le passager (ou 24h après la course). Le virement vers votre compte se fait sous 1-3 jours ouvrés.',
                ],
                [
                    'question' => "Mon paiement n'est pas arrivé. Que faire ?",
                    'answer'   => 'Vérifiez d\'abord votre portefeuille dans l\'application. Si le solde est disponible mais non retiré, lancez un retrait. En cas de problème persistant, contactez le support au +229 21 31 00 00.',
                ],
                [
                    'question' => 'Comment retirer mes gains ?',
                    'answer'   => 'Allez dans Portefeuille → Retirer. Vous pouvez retirer via MTN Money, Moov Money ou virement bancaire. Le minimum est de 1 000 FCFA.',
                ],
                [
                    'question' => 'Pourquoi y a-t-il une commission sur mes gains ?',
                    'answer'   => 'MINIZON prélève une commission de 10% pour couvrir les frais de plateforme, le support client et la garantie assurance. Exemple : course à 5 000 FCFA → vous recevez 4 500 FCFA.',
                ],
            ],
        ],
        [
            'icon_name' => 'route_rounded',
            'label'     => 'Problème pendant un trajet',
            'color'     => 0xFF3B82F6,
            'items'     => [
                [
                    'question' => 'Un passager refuse de payer. Que faire ?',
                    'answer'   => "Ne démarrez jamais un trajet sans confirmation de paiement dans l'app. Si le passager tente de payer en dehors de l'app, refusez. Signalez le passager depuis le menu du trajet.",
                ],
                [
                    'question' => "J'ai eu un accident. Quelles étapes ?",
                    'answer'   => "1. Assurez votre sécurité et celle des passagers. 2. Appelez le 118 (SAMU) si blessures. 3. Utilisez le bouton SOS dans l'app. 4. Appelez le support MINIZON au +229 21 31 00 00. 5. Photographiez les dommages.",
                ],
                [
                    'question' => 'Un passager a oublié ses affaires dans mon véhicule.',
                    'answer'   => "Contactez le passager via la messagerie de l'app. Si impossible, signalez l'objet trouvé au support. Conservez l'objet en lieu sûr pendant 30 jours.",
                ],
            ],
        ],
        [
            'icon_name' => 'person_off_rounded',
            'label'     => 'Signaler un passager',
            'color'     => 0xFFEF4444,
            'items'     => [
                [
                    'question' => 'Comment signaler un passager problématique ?',
                    'answer'   => "Allez dans Historique → Trajet concerné → Signaler un problème. Sélectionnez la catégorie et décrivez l'incident. Notre équipe examine chaque signalement sous 24h.",
                ],
                [
                    'question' => 'Un passager a été violent. Que faire ?',
                    'answer'   => "Votre sécurité passe avant tout. Arrêtez le véhicule en lieu sûr. Appelez le 117 (Police) ou utilisez le bouton SOS. Signalez immédiatement sur l'app après.",
                ],
            ],
        ],
        [
            'icon_name' => 'badge_rounded',
            'label'     => 'Mes documents',
            'color'     => 0xFF6366F1,
            'items'     => [
                [
                    'question' => 'Quels documents sont requis pour conduire ?',
                    'answer'   => "Permis de conduire valide, carte grise du véhicule, assurance en cours de validité, carte d'identité ou passeport. Tous doivent être téléchargés dans Mon profil → Documents.",
                ],
                [
                    'question' => 'Mon document est expiré. Que faire ?',
                    'answer'   => "Renouvelez votre document et téléchargez la nouvelle version dans l'app. Votre compte sera suspendu temporairement jusqu'à validation (1-2 jours ouvrés).",
                ],
            ],
        ],
        [
            'icon_name' => 'directions_car_rounded',
            'label'     => 'Mon véhicule',
            'color'     => 0xFFF4B400,
            'items'     => [
                [
                    'question' => 'Comment modifier les informations de mon véhicule ?',
                    'answer'   => "Allez dans Mon profil → Mes véhicules → Sélectionnez le véhicule → Modifier. Les modifications sont soumises à validation avant prise d'effet.",
                ],
                [
                    'question' => 'Puis-je avoir plusieurs véhicules ?',
                    'answer'   => "Oui, vous pouvez enregistrer jusqu'à 3 véhicules. Seul le véhicule sélectionné est actif pour vos courses. Changez de véhicule actif dans Mon profil → Mes véhicules.",
                ],
            ],
        ],
        [
            'icon_name' => 'account_circle_rounded',
            'label'     => 'Mon compte et profil',
            'color'     => 0xFFA855F7,
            'items'     => [
                [
                    'question' => 'Comment modifier mon numéro de téléphone ?',
                    'answer'   => 'Par mesure de sécurité, le changement de numéro nécessite une vérification d\'identité. Contactez le support au +229 21 31 00 00 avec votre pièce d\'identité.',
                ],
                [
                    'question' => 'Mon compte a été suspendu. Pourquoi ?',
                    'answer'   => "Les causes courantes : documents expirés, signalements répétés de passagers, non-respect des conditions d'utilisation. Contactez le support pour connaître la raison et la procédure de réactivation.",
                ],
            ],
        ],
    ];
}
