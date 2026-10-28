<?php
/**
 * Script de migration : remplace wire:confirm par le modal de confirmation
 * Exécuter depuis la racine du projet : php fix_wire_confirm.php
 */

$basePath = __DIR__;

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────
function readFile(string $path): string {
    return file_get_contents($path);
}

function writeFile(string $path, string $content): void {
    file_put_contents($path, $content);
    echo "  ✓ " . basename($path) . "\n";
}

function addTraitToPhp(string $filePath): void {
    $content = readFile($filePath);
    if (str_contains($content, 'WithConfirmModal')) {
        echo "  DÉJÀ PRÉSENT: " . basename($filePath) . "\n";
        return;
    }
    $content = str_replace(
        'use WithPagination;',
        'use WithPagination, WithConfirmModal;',
        $content
    );
    $content = str_replace(
        'use Livewire\Component;',
        'use App\Traits\WithConfirmModal;' . "\n" . 'use Livewire\Component;',
        $content
    );
    writeFile($filePath, $content);
}

function addModalInclude(string $content): string {
    if (str_contains($content, 'admin.partials.confirm-modal')) {
        return $content;
    }
    // Chercher le dernier </div> et insérer avant
    $pos = strrpos($content, '</div>');
    if ($pos !== false) {
        return substr($content, 0, $pos)
            . "@include('admin.partials.confirm-modal')\n"
            . substr($content, $pos);
    }
    return $content;
}

// ─────────────────────────────────────────────────────────────
// Étape 5 : Ajouter le trait dans les composants PHP
// ─────────────────────────────────────────────────────────────
echo "\n=== ÉTAPE 5 : Ajout du trait WithConfirmModal ===\n";
$phpComponents = [
    'app/Livewire/Admin/Drivers.php',
    'app/Livewire/Admin/Passengers.php',
    'app/Livewire/Admin/Users.php',
    'app/Livewire/Admin/Trips.php',
    'app/Livewire/Admin/Reviews.php',
    'app/Livewire/Admin/Support.php',
    'app/Livewire/Admin/Refunds.php',
    'app/Livewire/Admin/Notifications.php',
    'app/Livewire/Admin/Vehicles.php',
];
foreach ($phpComponents as $rel) {
    addTraitToPhp($basePath . '/' . $rel);
}

// ─────────────────────────────────────────────────────────────
// Étape 6a : notifications.blade.php
// ─────────────────────────────────────────────────────────────
echo "\n=== ÉTAPE 6 : Remplacement wire:confirm dans les vues ===\n";

$file = $basePath . '/resources/views/admin/notifications.blade.php';
$c = readFile($file);

// markAllRead (sans ID)
$c = str_replace(
    'wire:click="markAllRead" wire:confirm="Marquer toutes les notifications comme lues ?"',
    "wire:click=\"askConfirm('markAllRead', null, 'Tout marquer comme lu', 'Toutes les notifications seront marquées comme lues.', 'primary')\"",
    $c
);

// delete($notif->id) — peut être sur plusieurs lignes
$c = preg_replace(
    '/wire:click="delete\(\{\{ \$notif->id \}\}\)"\s+wire:confirm="Supprimer cette notification \?"/',
    "wire:click=\"askConfirm('delete', {{ \$notif->id }}, 'Supprimer la notification', 'Cette notification sera supprimée définitivement.', 'danger')\"",
    $c
);
// variante même ligne
$c = str_replace(
    'wire:click="delete({{ $notif->id }})" wire:confirm="Supprimer cette notification ?"',
    "wire:click=\"askConfirm('delete', {{ \$notif->id }}, 'Supprimer la notification', 'Cette notification sera supprimée définitivement.', 'danger')\"",
    $c
);

$c = addModalInclude($c);
writeFile($file, $c);

// ─────────────────────────────────────────────────────────────
// Étape 6b : reviews.blade.php
// ─────────────────────────────────────────────────────────────
$file = $basePath . '/resources/views/admin/reviews.blade.php';
$c = readFile($file);

// Toutes occurrences — tableau et panel utilisent la même méthode delete
// On remplace wire:confirm et on garde wire:click existant
$c = preg_replace(
    '/wire:click="delete\(\{\{ \$([a-z]+)->id \}\}\)"\s+wire:confirm="Supprimer définitivement cet avis \?"/',
    "wire:click=\"askConfirm('delete', {{ \$$1->id }}, 'Supprimer l\'avis', 'Cet avis sera supprimé définitivement. Action irréversible.', 'danger')\"",
    $c
);
// Même ligne
$c = preg_replace(
    '/wire:click="delete\(\{\{ \$([a-z]+)->id \}\}\)" wire:confirm="Supprimer définitivement cet avis \?"/',
    "wire:click=\"askConfirm('delete', {{ \$$1->id }}, 'Supprimer l\'avis', 'Cet avis sera supprimé définitivement. Action irréversible.', 'danger')\"",
    $c
);

$c = addModalInclude($c);
writeFile($file, $c);

// ─────────────────────────────────────────────────────────────
// Étape 6c : support.blade.php
// ─────────────────────────────────────────────────────────────
$file = $basePath . '/resources/views/admin/support.blade.php';
$c = readFile($file);

// Dans le tableau : resolve($ticket->id)
$c = preg_replace(
    '/wire:click="resolve\(\{\{ \$ticket->id \}\}\)"\s+wire:confirm="Marquer ce ticket comme résolu \?"/',
    "wire:click=\"askConfirm('resolve', {{ \$ticket->id }}, 'Résoudre le ticket', 'Ce ticket sera marqué comme résolu.', 'primary')\"",
    $c
);
$c = str_replace(
    'wire:click="resolve({{ $ticket->id }})" wire:confirm="Marquer ce ticket comme résolu ?"',
    "wire:click=\"askConfirm('resolve', {{ \$ticket->id }}, 'Résoudre le ticket', 'Ce ticket sera marqué comme résolu.', 'primary')\"",
    $c
);

// Dans le panel : resolve($selectedTicketId) ou resolve($t->id)
$c = preg_replace(
    '/wire:click="resolve\(\{\{ \$selectedTicketId \}\}\)"\s+wire:confirm="Marquer comme résolu \?"/',
    "wire:click=\"askConfirm('resolve', {{ \$selectedTicketId }}, 'Résoudre le ticket', 'Ce ticket sera marqué comme résolu.', 'primary')\"",
    $c
);
$c = str_replace(
    'wire:click="resolve({{ $selectedTicketId }})" wire:confirm="Marquer comme résolu ?"',
    "wire:click=\"askConfirm('resolve', {{ \$selectedTicketId }}, 'Résoudre le ticket', 'Ce ticket sera marqué comme résolu.', 'primary')\"",
    $c
);

// Dans le panel : closeTicket($selectedTicketId) ou closeTicket($t->id)
$c = preg_replace(
    '/wire:click="closeTicket\(\{\{ \$selectedTicketId \}\}\)"\s+wire:confirm="Fermer ce ticket \?"/',
    "wire:click=\"askConfirm('closeTicket', {{ \$selectedTicketId }}, 'Fermer le ticket', 'Ce ticket sera fermé sans autre action.', 'warning')\"",
    $c
);
$c = str_replace(
    'wire:click="closeTicket({{ $selectedTicketId }})" wire:confirm="Fermer ce ticket ?"',
    "wire:click=\"askConfirm('closeTicket', {{ \$selectedTicketId }}, 'Fermer le ticket', 'Ce ticket sera fermé sans autre action.', 'warning')\"",
    $c
);

// Variante avec $t->id
$c = preg_replace(
    '/wire:click="resolve\(\{\{ \$t->id \}\}\)"\s+wire:confirm="Marquer comme résolu \?"/',
    "wire:click=\"askConfirm('resolve', {{ \$t->id }}, 'Résoudre le ticket', 'Ce ticket sera marqué comme résolu.', 'primary')\"",
    $c
);
$c = str_replace(
    'wire:click="resolve({{ $t->id }})" wire:confirm="Marquer comme résolu ?"',
    "wire:click=\"askConfirm('resolve', {{ \$t->id }}, 'Résoudre le ticket', 'Ce ticket sera marqué comme résolu.', 'primary')\"",
    $c
);
$c = preg_replace(
    '/wire:click="closeTicket\(\{\{ \$t->id \}\}\)"\s+wire:confirm="Fermer ce ticket \?"/',
    "wire:click=\"askConfirm('closeTicket', {{ \$t->id }}, 'Fermer le ticket', 'Ce ticket sera fermé sans autre action.', 'warning')\"",
    $c
);
$c = str_replace(
    'wire:click="closeTicket({{ $t->id }})" wire:confirm="Fermer ce ticket ?"',
    "wire:click=\"askConfirm('closeTicket', {{ \$t->id }}, 'Fermer le ticket', 'Ce ticket sera fermé sans autre action.', 'warning')\"",
    $c
);

$c = addModalInclude($c);
writeFile($file, $c);

// ─────────────────────────────────────────────────────────────
// Étape 6d : users.blade.php
// ─────────────────────────────────────────────────────────────
$file = $basePath . '/resources/views/admin/users.blade.php';
$c = readFile($file);

// Tableau: toggleBlock — débloquer
$c = str_replace(
    'wire:click="toggleBlock({{ $user->id }})" wire:confirm="Débloquer cet utilisateur ?"',
    "wire:click=\"askConfirm('toggleBlock', {{ \$user->id }}, 'Débloquer l\'utilisateur', 'Cet utilisateur sera débloqué et pourra accéder à la plateforme.', 'primary')\"",
    $c
);
// Tableau: toggleBlock — bloquer
$c = str_replace(
    'wire:click="toggleBlock({{ $user->id }})" wire:confirm="Bloquer cet utilisateur ?"',
    "wire:click=\"askConfirm('toggleBlock', {{ \$user->id }}, 'Bloquer l\'utilisateur', 'Cet utilisateur sera bloqué et ne pourra plus accéder à la plateforme.', 'warning')\"",
    $c
);
// Tableau: deleteUser
$c = str_replace(
    'wire:click="deleteUser({{ $user->id }})" wire:confirm="Supprimer définitivement cet utilisateur et toutes ses données ?"',
    "wire:click=\"askConfirm('deleteUser', {{ \$user->id }}, 'Supprimer l\'utilisateur', 'Cet utilisateur et toutes ses données seront supprimés définitivement. Action irréversible.', 'danger')\"",
    $c
);

// Panel: approveKyc
$c = str_replace(
    'wire:click="approveKyc({{ $u->id }})" wire:confirm="Approuver le KYC de {{ $uName ?: $u->phone }} ?"',
    "wire:click=\"askConfirm('approveKyc', {{ \$u->id }}, 'Approuver le KYC', 'Le KYC de {{ \$uName ?: \$u->phone }} sera approuvé.', 'primary')\"",
    $c
);
// Panel: rejectKyc
$c = str_replace(
    'wire:click="rejectKyc({{ $u->id }})" wire:confirm="Rejeter le KYC de {{ $uName ?: $u->phone }} ?"',
    "wire:click=\"askConfirm('rejectKyc', {{ \$u->id }}, 'Rejeter le KYC', 'Le KYC de {{ \$uName ?: \$u->phone }} sera rejeté.', 'danger')\"",
    $c
);
// Panel: rejectKyc révoquer — attention aux apostrophes
$c = str_replace(
    "wire:click=\"rejectKyc({{ \$u->id }})\" wire:confirm=\"Révoquer l'approbation KYC ?\"",
    "wire:click=\"askConfirm('rejectKyc', {{ \$u->id }}, 'Révoquer le KYC', 'L\\'approbation KYC de cet utilisateur sera révoquée.', 'danger')\"",
    $c
);
// Panel: approveKyc ré-approuver
$c = str_replace(
    'wire:click="approveKyc({{ $u->id }})" wire:confirm="Ré-approuver le KYC ?"',
    "wire:click=\"askConfirm('approveKyc', {{ \$u->id }}, 'Ré-approuver le KYC', 'Le KYC sera ré-approuvé pour cet utilisateur.', 'primary')\"",
    $c
);
// Panel: toggleBlock débloquer
$c = str_replace(
    'wire:click="toggleBlock({{ $u->id }})" wire:confirm="Débloquer cet utilisateur ?"',
    "wire:click=\"askConfirm('toggleBlock', {{ \$u->id }}, 'Débloquer l\'utilisateur', 'Cet utilisateur sera débloqué.', 'primary')\"",
    $c
);
// Panel: toggleBlock bloquer
$c = str_replace(
    'wire:click="toggleBlock({{ $u->id }})" wire:confirm="Bloquer cet utilisateur ?"',
    "wire:click=\"askConfirm('toggleBlock', {{ \$u->id }}, 'Bloquer l\'utilisateur', 'Cet utilisateur sera bloqué et ne pourra plus accéder à la plateforme.', 'warning')\"",
    $c
);
// Panel: deleteUser
$c = str_replace(
    'wire:click="deleteUser({{ $u->id }})" wire:confirm="Supprimer définitivement {{ $uName ?: $u->phone }} ?"',
    "wire:click=\"askConfirm('deleteUser', {{ \$u->id }}, 'Supprimer l\'utilisateur', 'Cet utilisateur et toutes ses données seront supprimés définitivement.', 'danger')\"",
    $c
);

$c = addModalInclude($c);
writeFile($file, $c);

// ─────────────────────────────────────────────────────────────
// Étape 6e : trips.blade.php
// ─────────────────────────────────────────────────────────────
$file = $basePath . '/resources/views/admin/trips.blade.php';
$c = readFile($file);

// Tableau: toggleFlag conditionnel (multiline)
$c = preg_replace(
    '/wire:click="toggleFlag\(\{\{ \$trip->id \}\}\)"\s+wire:confirm="\{\{ \$trip->is_flagged \? \'Retirer le signalement de ce trajet \?\' : \'Signaler ce trajet \?\' \}\}"/',
    "wire:click=\"askConfirm('toggleFlag', {{ \$trip->id }}, \$trip->is_flagged ? 'Retirer le signalement' : 'Signaler le trajet', \$trip->is_flagged ? 'Le signalement de ce trajet sera retiré.' : 'Ce trajet sera signalé pour examen.', 'warning')\"",
    $c
);
// Tableau: cancelTrip (multiline)
$c = preg_replace(
    '/wire:click="cancelTrip\(\{\{ \$trip->id \}\}\)"\s+wire:confirm="Annuler définitivement ce trajet \? Les passagers seront affectés\."/',
    "wire:click=\"askConfirm('cancelTrip', {{ \$trip->id }}, 'Annuler le trajet', 'Ce trajet sera annulé définitivement. Les passagers seront affectés.', 'danger')\"",
    $c
);
// même ligne
$c = str_replace(
    'wire:click="cancelTrip({{ $trip->id }})" wire:confirm="Annuler définitivement ce trajet ? Les passagers seront affectés."',
    "wire:click=\"askConfirm('cancelTrip', {{ \$trip->id }}, 'Annuler le trajet', 'Ce trajet sera annulé définitivement. Les passagers seront affectés.', 'danger')\"",
    $c
);

// Panel: toggleFlag retirer
$c = str_replace(
    'wire:click="toggleFlag({{ $t->id }})" wire:confirm="Retirer le signalement ?"',
    "wire:click=\"askConfirm('toggleFlag', {{ \$t->id }}, 'Retirer le signalement', 'Le signalement de ce trajet sera retiré.', 'warning')\"",
    $c
);
// Panel: toggleFlag signaler
$c = str_replace(
    'wire:click="toggleFlag({{ $t->id }})" wire:confirm="Signaler ce trajet ?"',
    "wire:click=\"askConfirm('toggleFlag', {{ \$t->id }}, 'Signaler le trajet', 'Ce trajet sera signalé pour examen.', 'warning')\"",
    $c
);
// Panel: cancelTrip
$c = str_replace(
    'wire:click="cancelTrip({{ $t->id }})" wire:confirm="Annuler ce trajet définitivement ?"',
    "wire:click=\"askConfirm('cancelTrip', {{ \$t->id }}, 'Annuler le trajet', 'Ce trajet sera annulé définitivement. Les passagers seront affectés.', 'danger')\"",
    $c
);

$c = addModalInclude($c);
writeFile($file, $c);

// ─────────────────────────────────────────────────────────────
// Étape 6f : vehicles.blade.php
// ─────────────────────────────────────────────────────────────
$file = $basePath . '/resources/views/admin/vehicles.blade.php';
$c = readFile($file);

// reject (multiline)
$c = preg_replace(
    '/wire:click="reject\(\{\{ \$v->id \}\}\)"\s+wire:confirm="Confirmer le rejet \?"/',
    "wire:click=\"askConfirm('reject', {{ \$v->id }}, 'Rejeter le véhicule', 'Ce véhicule sera rejeté. Le conducteur en sera notifié.', 'danger')\"",
    $c
);
$c = str_replace(
    'wire:click="reject({{ $v->id }})" wire:confirm="Confirmer le rejet ?"',
    "wire:click=\"askConfirm('reject', {{ \$v->id }}, 'Rejeter le véhicule', 'Ce véhicule sera rejeté. Le conducteur en sera notifié.', 'danger')\"",
    $c
);
// approve (multiline)
$c = preg_replace(
    '/wire:click="approve\(\{\{ \$v->id \}\}\)"\s+wire:confirm="Approuver ce véhicule \?"/',
    "wire:click=\"askConfirm('approve', {{ \$v->id }}, 'Approuver le véhicule', 'Ce véhicule sera approuvé. Le conducteur pourra créer des trajets.', 'primary')\"",
    $c
);
$c = str_replace(
    'wire:click="approve({{ $v->id }})" wire:confirm="Approuver ce véhicule ?"',
    "wire:click=\"askConfirm('approve', {{ \$v->id }}, 'Approuver le véhicule', 'Ce véhicule sera approuvé. Le conducteur pourra créer des trajets.', 'primary')\"",
    $c
);
// suspend (multiline)
$c = preg_replace(
    '/wire:click="suspend\(\{\{ \$v->id \}\}\)"\s+wire:confirm="Suspendre ce véhicule \?"/',
    "wire:click=\"askConfirm('suspend', {{ \$v->id }}, 'Suspendre le véhicule', 'Ce véhicule sera suspendu temporairement.', 'warning')\"",
    $c
);
$c = str_replace(
    'wire:click="suspend({{ $v->id }})" wire:confirm="Suspendre ce véhicule ?"',
    "wire:click=\"askConfirm('suspend', {{ \$v->id }}, 'Suspendre le véhicule', 'Ce véhicule sera suspendu temporairement.', 'warning')\"",
    $c
);

$c = addModalInclude($c);
writeFile($file, $c);

// ─────────────────────────────────────────────────────────────
// Étape 6g : passengers.blade.php
// ─────────────────────────────────────────────────────────────
$file = $basePath . '/resources/views/admin/passengers.blade.php';
$c = readFile($file);

// Tableau: toggleBlock conditionnel — garde le @if/@else en décomposant
// La ligne est : wire:click="toggleBlock({{ $pax->id }})" wire:confirm="{{ $pax->is_blocked ? ... : ... }}"
// On doit décomposer car wire:click et wire:confirm sont sur des lignes séparées
$c = preg_replace(
    '/wire:click="toggleBlock\(\{\{ \$pax->id \}\}\)"\s+wire:confirm="\{\{ \$pax->is_blocked \? \'Débloquer ce passager \?\' : \'Bloquer ce passager \?\' \}\}"/',
    "wire:click=\"askConfirm('toggleBlock', {{ \$pax->id }}, \$pax->is_blocked ? 'Débloquer le passager' : 'Bloquer le passager', \$pax->is_blocked ? 'Ce passager sera débloqué.' : 'Ce passager sera bloqué.', \$pax->is_blocked ? 'primary' : 'warning')\"",
    $c
);
// même ligne
$c = str_replace(
    "wire:click=\"toggleBlock({{ \$pax->id }})\" wire:confirm=\"{{ \$pax->is_blocked ? 'Débloquer ce passager ?' : 'Bloquer ce passager ?' }}\"",
    "wire:click=\"askConfirm('toggleBlock', {{ \$pax->id }}, \$pax->is_blocked ? 'Débloquer le passager' : 'Bloquer le passager', \$pax->is_blocked ? 'Ce passager sera débloqué.' : 'Ce passager sera bloqué.', \$pax->is_blocked ? 'primary' : 'warning')\"",
    $c
);

// Panel: approveKyc (pending)
$c = str_replace(
    'wire:click="approveKyc({{ $s->id }})" wire:confirm="Approuver le KYC de {{ $nm2 }} ?"',
    "wire:click=\"askConfirm('approveKyc', {{ \$s->id }}, 'Approuver le KYC', 'Le KYC de {{ \$nm2 }} sera approuvé.', 'primary')\"",
    $c
);
// Panel: rejectKyc (pending)
$c = str_replace(
    'wire:click="rejectKyc({{ $s->id }})" wire:confirm="Rejeter le KYC de {{ $nm2 }} ?"',
    "wire:click=\"askConfirm('rejectKyc', {{ \$s->id }}, 'Rejeter le KYC', 'Le KYC de {{ \$nm2 }} sera rejeté.', 'danger')\"",
    $c
);
// Panel: rejectKyc révoquer
$c = str_replace(
    "wire:click=\"rejectKyc({{ \$s->id }})\" wire:confirm=\"Révoquer l'approbation KYC ?\"",
    "wire:click=\"askConfirm('rejectKyc', {{ \$s->id }}, 'Révoquer le KYC', 'L\\'approbation KYC de ce passager sera révoquée.', 'danger')\"",
    $c
);
// Panel: approveKyc ré-approuver
$c = str_replace(
    'wire:click="approveKyc({{ $s->id }})" wire:confirm="Ré-approuver le KYC ?"',
    "wire:click=\"askConfirm('approveKyc', {{ \$s->id }}, 'Ré-approuver le KYC', 'Le KYC de ce passager sera ré-approuvé.', 'primary')\"",
    $c
);
// Panel: toggleBlock conditionnel
$c = preg_replace(
    '/wire:click="toggleBlock\(\{\{ \$s->id \}\}\)"\s+wire:confirm="\{\{ \$s->is_blocked \? \'Débloquer ce passager \?\' : \'Bloquer ce passager \?\' \}\}"/',
    "wire:click=\"askConfirm('toggleBlock', {{ \$s->id }}, \$s->is_blocked ? 'Débloquer le passager' : 'Bloquer le passager', \$s->is_blocked ? 'Ce passager sera débloqué.' : 'Ce passager sera bloqué.', \$s->is_blocked ? 'primary' : 'warning')\"",
    $c
);
$c = str_replace(
    "wire:click=\"toggleBlock({{ \$s->id }})\" wire:confirm=\"{{ \$s->is_blocked ? 'Débloquer ce passager ?' : 'Bloquer ce passager ?' }}\"",
    "wire:click=\"askConfirm('toggleBlock', {{ \$s->id }}, \$s->is_blocked ? 'Débloquer le passager' : 'Bloquer le passager', \$s->is_blocked ? 'Ce passager sera débloqué.' : 'Ce passager sera bloqué.', \$s->is_blocked ? 'primary' : 'warning')\"",
    $c
);

$c = addModalInclude($c);
writeFile($file, $c);

// ─────────────────────────────────────────────────────────────
// Étape 6h : drivers.blade.php
// ─────────────────────────────────────────────────────────────
$file = $basePath . '/resources/views/admin/drivers.blade.php';
$c = readFile($file);

// Tableau
$c = str_replace(
    'wire:click="approveKyc({{ $driver->id }})" wire:confirm="Approuver le KYC de {{ $name ?: $driver->phone }} ?"',
    "wire:click=\"askConfirm('approveKyc', {{ \$driver->id }}, 'Approuver le KYC', 'Le KYC de {{ \$name ?: \$driver->phone }} sera approuvé.', 'primary')\"",
    $c
);
$c = str_replace(
    'wire:click="rejectKyc({{ $driver->id }})" wire:confirm="Rejeter le KYC de {{ $name ?: $driver->phone }} ?"',
    "wire:click=\"askConfirm('rejectKyc', {{ \$driver->id }}, 'Rejeter le KYC', 'Le KYC de {{ \$name ?: \$driver->phone }} sera rejeté.', 'danger')\"",
    $c
);
$c = str_replace(
    "wire:click=\"rejectKyc({{ \$driver->id }})\" wire:confirm=\"Révoquer l'approbation KYC ?\"",
    "wire:click=\"askConfirm('rejectKyc', {{ \$driver->id }}, 'Révoquer le KYC', 'L\\'approbation KYC de ce conducteur sera révoquée.', 'danger')\"",
    $c
);
$c = str_replace(
    'wire:click="approveKyc({{ $driver->id }})" wire:confirm="Ré-approuver le KYC ?"',
    "wire:click=\"askConfirm('approveKyc', {{ \$driver->id }}, 'Ré-approuver le KYC', 'Le KYC de ce conducteur sera ré-approuvé.', 'primary')\"",
    $c
);
$c = str_replace(
    'wire:click="approveVehicle({{ $driver->id }})" wire:confirm="Approuver le véhicule de {{ $name ?: $driver->phone }} ?"',
    "wire:click=\"askConfirm('approveVehicle', {{ \$driver->id }}, 'Approuver le véhicule', 'Le véhicule de {{ \$name ?: \$driver->phone }} sera approuvé.', 'primary')\"",
    $c
);
$c = str_replace(
    'wire:click="rejectVehicle({{ $driver->id }})" wire:confirm="Rejeter le véhicule de {{ $name ?: $driver->phone }} ?"',
    "wire:click=\"askConfirm('rejectVehicle', {{ \$driver->id }}, 'Rejeter le véhicule', 'Le véhicule de {{ \$name ?: \$driver->phone }} sera rejeté.', 'danger')\"",
    $c
);
$c = str_replace(
    "wire:click=\"rejectVehicle({{ \$driver->id }})\" wire:confirm=\"Révoquer l'approbation du véhicule ?\"",
    "wire:click=\"askConfirm('rejectVehicle', {{ \$driver->id }}, 'Révoquer le véhicule', 'L\\'approbation du véhicule sera révoquée.', 'danger')\"",
    $c
);
$c = str_replace(
    'wire:click="approveVehicle({{ $driver->id }})" wire:confirm="Approuver quand même le véhicule ?"',
    "wire:click=\"askConfirm('approveVehicle', {{ \$driver->id }}, 'Approuver le véhicule', 'Le véhicule sera approuvé malgré le rejet précédent.', 'primary')\"",
    $c
);

// Panel ($sd->id)
$c = str_replace(
    'wire:click="approveKyc({{ $sd->id }})" wire:confirm="Approuver le KYC de {{ $nmD ?: $sd->phone }} ?"',
    "wire:click=\"askConfirm('approveKyc', {{ \$sd->id }}, 'Approuver le KYC', 'Le KYC de {{ \$nmD ?: \$sd->phone }} sera approuvé.', 'primary')\"",
    $c
);
$c = str_replace(
    'wire:click="rejectKyc({{ $sd->id }})" wire:confirm="Rejeter le KYC de {{ $nmD ?: $sd->phone }} ?"',
    "wire:click=\"askConfirm('rejectKyc', {{ \$sd->id }}, 'Rejeter le KYC', 'Le KYC de {{ \$nmD ?: \$sd->phone }} sera rejeté.', 'danger')\"",
    $c
);
$c = str_replace(
    "wire:click=\"rejectKyc({{ \$sd->id }})\" wire:confirm=\"Révoquer l'approbation KYC ?\"",
    "wire:click=\"askConfirm('rejectKyc', {{ \$sd->id }}, 'Révoquer le KYC', 'L\\'approbation KYC de ce conducteur sera révoquée.', 'danger')\"",
    $c
);
$c = str_replace(
    'wire:click="approveKyc({{ $sd->id }})" wire:confirm="Ré-approuver le KYC ?"',
    "wire:click=\"askConfirm('approveKyc', {{ \$sd->id }}, 'Ré-approuver le KYC', 'Le KYC de ce conducteur sera ré-approuvé.', 'primary')\"",
    $c
);
$c = str_replace(
    'wire:click="approveVehicle({{ $sd->id }})" wire:confirm="Approuver le véhicule ?"',
    "wire:click=\"askConfirm('approveVehicle', {{ \$sd->id }}, 'Approuver le véhicule', 'Le véhicule de ce conducteur sera approuvé.', 'primary')\"",
    $c
);

$c = addModalInclude($c);
writeFile($file, $c);

// ─────────────────────────────────────────────────────────────
// Étape 6i : refunds.blade.php
// ─────────────────────────────────────────────────────────────
$file = $basePath . '/resources/views/admin/refunds.blade.php';
$c = readFile($file);

// Tableau: $w->id
$c = preg_replace(
    '/wire:click="approveWithdrawal\(\{\{ \$w->id \}\}\)"\s+wire:confirm="Approuver ce retrait de \{\{ number_format\(\$w->amount\) \}\} FCFA \?"/',
    "wire:click=\"askConfirm('approveWithdrawal', {{ \$w->id }}, 'Approuver le retrait', 'Le retrait de {{ number_format(\$w->amount) }} FCFA sera approuvé.', 'primary')\"",
    $c
);
$c = str_replace(
    'wire:click="approveWithdrawal({{ $w->id }})" wire:confirm="Approuver ce retrait de {{ number_format($w->amount) }} FCFA ?"',
    "wire:click=\"askConfirm('approveWithdrawal', {{ \$w->id }}, 'Approuver le retrait', 'Le retrait de {{ number_format(\$w->amount) }} FCFA sera approuvé.', 'primary')\"",
    $c
);
$c = preg_replace(
    '/wire:click="rejectWithdrawal\(\{\{ \$w->id \}\}\)"\s+wire:confirm="Rejeter cette demande \?"/',
    "wire:click=\"askConfirm('rejectWithdrawal', {{ \$w->id }}, 'Rejeter la demande', 'Cette demande de retrait sera rejetée.', 'danger')\"",
    $c
);
$c = str_replace(
    'wire:click="rejectWithdrawal({{ $w->id }})" wire:confirm="Rejeter cette demande ?"',
    "wire:click=\"askConfirm('rejectWithdrawal', {{ \$w->id }}, 'Rejeter la demande', 'Cette demande de retrait sera rejetée.', 'danger')\"",
    $c
);

// Panel: $wd->id
$c = preg_replace(
    '/wire:click="approveWithdrawal\(\{\{ \$wd->id \}\}\)"\s+wire:confirm="Approuver ce retrait de \{\{ number_format\(\$wd->amount\) \}\} FCFA \?"/',
    "wire:click=\"askConfirm('approveWithdrawal', {{ \$wd->id }}, 'Approuver le retrait', 'Le retrait de {{ number_format(\$wd->amount) }} FCFA sera approuvé et traité.', 'primary')\"",
    $c
);
$c = str_replace(
    'wire:click="approveWithdrawal({{ $wd->id }})" wire:confirm="Approuver ce retrait de {{ number_format($wd->amount) }} FCFA ?"',
    "wire:click=\"askConfirm('approveWithdrawal', {{ \$wd->id }}, 'Approuver le retrait', 'Le retrait de {{ number_format(\$wd->amount) }} FCFA sera approuvé et traité.', 'primary')\"",
    $c
);
$c = preg_replace(
    '/wire:click="rejectWithdrawal\(\{\{ \$wd->id \}\}\)"\s+wire:confirm="Rejeter cette demande \?"/',
    "wire:click=\"askConfirm('rejectWithdrawal', {{ \$wd->id }}, 'Rejeter la demande', 'Cette demande de retrait sera rejetée.', 'danger')\"",
    $c
);
$c = str_replace(
    'wire:click="rejectWithdrawal({{ $wd->id }})" wire:confirm="Rejeter cette demande ?"',
    "wire:click=\"askConfirm('rejectWithdrawal', {{ \$wd->id }}, 'Rejeter la demande', 'Cette demande de retrait sera rejetée.', 'danger')\"",
    $c
);
$c = preg_replace(
    '/wire:click="markFailed\(\{\{ \$wd->id \}\}\)"\s+wire:confirm="Marquer ce virement comme échoué \?"/',
    "wire:click=\"askConfirm('markFailed', {{ \$wd->id }}, 'Marquer comme échoué', 'Ce virement sera marqué comme échoué.', 'warning')\"",
    $c
);
$c = str_replace(
    'wire:click="markFailed({{ $wd->id }})" wire:confirm="Marquer ce virement comme échoué ?"',
    "wire:click=\"askConfirm('markFailed', {{ \$wd->id }}, 'Marquer comme échoué', 'Ce virement sera marqué comme échoué.', 'warning')\"",
    $c
);

$c = addModalInclude($c);
writeFile($file, $c);

// ─────────────────────────────────────────────────────────────
// Résumé
// ─────────────────────────────────────────────────────────────
echo "\n=== VÉRIFICATION FINALE ===\n";
$views = [
    'resources/views/admin/notifications.blade.php',
    'resources/views/admin/reviews.blade.php',
    'resources/views/admin/support.blade.php',
    'resources/views/admin/users.blade.php',
    'resources/views/admin/trips.blade.php',
    'resources/views/admin/vehicles.blade.php',
    'resources/views/admin/passengers.blade.php',
    'resources/views/admin/drivers.blade.php',
    'resources/views/admin/refunds.blade.php',
];
foreach ($views as $rel) {
    $path = $basePath . '/' . $rel;
    $content = readFile($path);
    $remaining = substr_count($content, 'wire:confirm');
    $hasModal = str_contains($content, 'admin.partials.confirm-modal') ? '✓ modal' : '✗ MODAL MANQUANT';
    echo "  " . basename($path) . ": wire:confirm restants=$remaining  $hasModal\n";
}

echo "\n=== TERMINÉ ===\n";
echo "Supprimez ce fichier après utilisation : rm fix_wire_confirm.php\n";
