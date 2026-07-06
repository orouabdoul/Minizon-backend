<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Page "Centre d'aide" (SupportView) — passager.
 *
 * Fournit :
 *   – La FAQ passager organisée par catégorie (statique, adaptée au module passager)
 *   – La création d'un ticket de support (SupportTicket)
 *   – L'historique des tickets du passager
 *
 * Les actions contactByChat / contactByPhone du SupportController Flutter
 * utilisent url_launcher (WhatsApp / dialer) — aucun endpoint nécessaire.
 */
class PassengerSupportController extends Controller
{
    // =========================================================================
    //  GET /api/passenger/support/faq
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/support/faq',
        operationId: 'passengerFaq',
        summary: 'FAQ passager par catégorie',
        description: "Retourne les catégories et questions fréquentes adaptées au module passager. Le contenu est statique côté serveur — aucune table DB requise.",
        tags: ['🎧 Passenger — Support'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'FAQ récupérée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'FAQ passager.'),
                        new OA\Property(
                            property: 'body',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'topics',
                                    type: 'array',
                                    description: 'Catégories de FAQ avec leurs questions/réponses.',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'key',       type: 'string', example: 'reservations'),
                                            new OA\Property(property: 'label',     type: 'string', example: 'Réservations'),
                                            new OA\Property(property: 'icon_name', type: 'string', example: 'confirmation_number_rounded'),
                                            new OA\Property(property: 'color',     type: 'integer', format: 'int64', example: 0xFF00A86B),
                                            new OA\Property(
                                                property: 'items',
                                                type: 'array',
                                                items: new OA\Items(
                                                    properties: [
                                                        new OA\Property(property: 'question', type: 'string'),
                                                        new OA\Property(property: 'answer',   type: 'string'),
                                                    ]
                                                )
                                            ),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function faq(Request $request): JsonResponse
    {
        return $this->apiResponse(true, 'FAQ passager.', [
            'topics' => self::FAQ_TOPICS,
        ]);
    }

    // =========================================================================
    //  POST /api/passenger/support/tickets
    // =========================================================================

    #[OA\Post(
        path: '/api/passenger/support/tickets',
        operationId: 'passengerCreateTicket',
        summary: 'Créer un ticket de support (passager)',
        tags: ['🎧 Passenger — Support'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['subject', 'description'],
                properties: [
                    new OA\Property(property: 'subject',     type: 'string', maxLength: 255, example: 'Remboursement non reçu'),
                    new OA\Property(property: 'description', type: 'string', example: 'Le conducteur ne répond pas...'),
                    new OA\Property(property: 'category',    type: 'string', nullable: true, example: 'Remboursement', description: 'Catégorie sélectionnée dans SupportCenterView. Stockée en préfixe du sujet : "[Remboursement] Mon sujet".'),
                    new OA\Property(property: 'priority',    type: 'string', enum: ['low', 'medium', 'high'], default: 'medium'),
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
            'category'    => ['nullable', 'string', 'max:100'],
            'priority'    => ['nullable', 'string', 'in:low,medium,high'],
        ]);

        // Catégorie encodée en préfixe pour éviter une migration :
        // "[Remboursement] Mon sujet" — extraction dans tickets() via regex.
        $category = $validated['category'] ?? null;
        $subject  = $category
            ? "[{$category}] {$validated['subject']}"
            : $validated['subject'];

        $ticket = SupportTicket::create([
            'user_id'     => $user->id,
            'subject'     => $subject,
            'description' => $validated['description'],
            'priority'    => $validated['priority'] ?? 'medium',
            'channel'     => 'app',
            'status'      => 'new',
        ]);

        return $this->apiResponse(true, 'Message envoyé. Notre équipe vous répondra sous 24h.', [
            'uuid'     => $ticket->uuid,
            'id'       => $this->ticketRef($ticket->id),
            'category' => $category,
            'status'   => $ticket->status,
        ]);
    }

    // =========================================================================
    //  GET /api/passenger/support/tickets
    // =========================================================================

    #[OA\Get(
        path: '/api/passenger/support/tickets',
        operationId: 'passengerTickets',
        summary: 'Historique des tickets du passager',
        tags: ['🎧 Passenger — Support'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Tickets récupérés'),
        ]
    )]
    public function tickets(Request $request): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(function ($t) {
                // Extraction de la catégorie depuis le préfixe "[cat] sujet"
                $category    = null;
                $cleanSubject = $t->subject;
                if (preg_match('/^\[([^\]]+)\]\s*(.+)$/s', $t->subject, $m)) {
                    $category     = $m[1];
                    $cleanSubject = $m[2];
                }

                // Dernier message de l'équipe support (si relation messages disponible)
                $lastMessage = null;
                if (method_exists($t, 'messages')) {
                    $lastMessage = $t->messages()->latest()->value('body');
                }

                return [
                    'uuid'         => $t->uuid,
                    'id'           => $this->ticketRef($t->id),
                    'subject'      => $cleanSubject,
                    'category'     => $category,
                    'status'       => $t->status,
                    'priority'     => $t->priority,
                    'created_at'   => $t->created_at->setTimezone('Africa/Porto-Novo')->translatedFormat('d F Y à H\hi'),
                    'last_message' => $lastMessage,
                ];
            });

        return $this->apiResponse(true, 'Tickets de support.', [
            'tickets' => $tickets,
        ]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function ticketRef(int $id): string
    {
        return '#TKT-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    //  FAQ — contenu statique (passager)
    // =========================================================================

    private const FAQ_TOPICS = [
        [
            'key'       => 'reservations',
            'icon_name' => 'confirmation_number_rounded',
            'label'     => 'Réservations',
            'color'     => 0xFF00A86B,
            'items'     => [
                [
                    'question' => 'Comment réserver une place ?',
                    'answer'   => "Recherchez un trajet depuis l'accueil en saisissant votre ville de départ, d'arrivée et la date. Sélectionnez un trajet disponible et appuyez sur « Réserver ». Choisissez votre mode de paiement Mobile Money et confirmez.",
                ],
                [
                    'question' => 'Puis-je annuler ma réservation ?',
                    'answer'   => "Oui, jusqu'à 30 minutes avant le départ prévu. Allez dans Mes réservations → Trajet concerné → Annuler. Un remboursement intégral est effectué si l'annulation intervient plus de 2h avant le départ.",
                ],
                [
                    'question' => 'Que faire si le conducteur annule le trajet ?',
                    'answer'   => "Vous recevrez une notification et un remboursement intégral automatique sous 24-48h. Vous pouvez chercher un autre trajet immédiatement depuis l'accueil.",
                ],
                [
                    'question' => "Ma réservation est en attente. Combien de temps attendre ?",
                    'answer'   => "Les conducteurs ont 5 minutes pour accepter ou refuser votre demande. Passé ce délai, la réservation est automatiquement annulée et vous pouvez en faire une nouvelle.",
                ],
            ],
        ],
        [
            'key'       => 'paiement',
            'icon_name' => 'payments_rounded',
            'label'     => 'Paiement',
            'color'     => 0xFF3B82F6,
            'items'     => [
                [
                    'question' => 'Quels modes de paiement sont acceptés ?',
                    'answer'   => "MINIZON accepte MTN Mobile Money, Moov Money et Celtiis Money. Le paiement est débité de votre compte Mobile Money et placé en garantie jusqu'à la fin du trajet.",
                ],
                [
                    'question' => 'À quel moment mon compte est-il débité ?',
                    'answer'   => "Votre compte est débité au moment de la confirmation de réservation. Le montant est conservé en garantie et versé au conducteur uniquement après la validation du trajet.",
                ],
                [
                    'question' => 'Pourquoi y a-t-il des frais de service ?',
                    'answer'   => "MINIZON prélève 10% de frais de service pour couvrir le traitement des paiements Mobile Money, la garantie des transactions et le support client. Ces frais sont affichés avant toute confirmation.",
                ],
                [
                    'question' => 'Mon paiement a échoué. Que faire ?',
                    'answer'   => "Vérifiez que votre compte Mobile Money dispose d'un solde suffisant et que le numéro de téléphone est correct. Réessayez ou choisissez un autre opérateur. En cas de débit sans confirmation, contactez le support.",
                ],
            ],
        ],
        [
            'key'       => 'trajet',
            'icon_name' => 'route_rounded',
            'label'     => 'Pendant le trajet',
            'color'     => 0xFFF59E0B,
            'items'     => [
                [
                    'question' => 'Comment contacter mon conducteur ?',
                    'answer'   => "Depuis votre réservation confirmée, appuyez sur « Message » pour envoyer un message via la messagerie intégrée, ou sur « Appeler » pour le contacter directement.",
                ],
                [
                    'question' => "Le conducteur n'est pas au point de rendez-vous.",
                    'answer'   => "Attendez 10 minutes et contactez le conducteur via la messagerie. S'il ne répond pas, annulez la réservation depuis Mes réservations → Annuler. Un remboursement intégral sera effectué.",
                ],
                [
                    'question' => "J'ai oublié un objet dans le véhicule.",
                    'answer'   => "Contactez le conducteur via la messagerie de votre trajet terminé. Si impossible, ouvrez un ticket de support en précisant le trajet concerné et la description de l'objet.",
                ],
            ],
        ],
        [
            'key'       => 'signalement',
            'icon_name' => 'report_rounded',
            'label'     => 'Signaler un problème',
            'color'     => 0xFFEF4444,
            'items'     => [
                [
                    'question' => 'Comment signaler un conducteur ?',
                    'answer'   => "Depuis votre trajet terminé, appuyez sur « Signaler un problème ». Choisissez la catégorie (comportement, sécurité, véhicule…) et décrivez l'incident. Notre équipe examine chaque signalement sous 24h.",
                ],
                [
                    'question' => 'Je me sens en danger pendant le trajet.',
                    'answer'   => "Utilisez immédiatement le bouton SOS dans la section Sécurité de l'application. Vos contacts d'urgence et notre équipe seront alertés. Appelez le 117 (Police) si nécessaire.",
                ],
                [
                    'question' => 'Comment demander un remboursement suite à un problème ?',
                    'answer'   => "Allez dans Mes réservations → Trajet concerné → Demander un remboursement. Sélectionnez le motif, ajoutez des preuves si disponibles, et soumettez. Le traitement prend 2-5 jours ouvrés.",
                ],
            ],
        ],
        [
            'key'       => 'compte',
            'icon_name' => 'account_circle_rounded',
            'label'     => 'Mon compte',
            'color'     => 0xFF8B5CF6,
            'items'     => [
                [
                    'question' => 'Comment modifier mon profil ?',
                    'answer'   => "Allez dans Mon profil → Modifier le profil. Vous pouvez changer votre photo, prénom, nom et adresse e-mail. La modification du numéro de téléphone nécessite de contacter le support.",
                ],
                [
                    'question' => 'Mon compte a été suspendu. Pourquoi ?',
                    'answer'   => "Les causes courantes : signalements de conducteurs, comportement contraire aux conditions d'utilisation, tentative de fraude. Contactez le support au +229 21 31 00 00 pour connaître la raison et la procédure.",
                ],
                [
                    'question' => 'Comment supprimer mon compte ?',
                    'answer'   => "La suppression de compte est définitive. Si vous souhaitez supprimer votre compte, contactez le support en précisant votre demande. Toutes vos données seront effacées sous 30 jours conformément au RGPD.",
                ],
            ],
        ],
    ];
}
