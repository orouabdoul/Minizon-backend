# Script de remplacement de wire:confirm par askConfirm
# Exécuter depuis la racine du projet Laravel

$basePath = "C:\xampp\htdocs\project-minizon-backend"

# ──────────────────────────────────────────────────────────────────────────────
# Fonction helper : lit, modifie et écrit un fichier
# ──────────────────────────────────────────────────────────────────────────────
function Replace-InFile($filePath, $oldStr, $newStr) {
    $content = [System.IO.File]::ReadAllText($filePath, [System.Text.Encoding]::UTF8)
    if ($content.Contains($oldStr)) {
        $content = $content.Replace($oldStr, $newStr)
        [System.IO.File]::WriteAllText($filePath, $content, [System.Text.Encoding]::UTF8)
        Write-Host "  OK: remplace dans $([System.IO.Path]::GetFileName($filePath))"
    } else {
        Write-Host "  SKIP (non trouvé): dans $([System.IO.Path]::GetFileName($filePath))"
    }
}

# ──────────────────────────────────────────────────────────────────────────────
# Ajouter le trait WithConfirmModal dans les composants PHP
# ──────────────────────────────────────────────────────────────────────────────
$phpComponents = @(
    "app\Livewire\Admin\Drivers.php",
    "app\Livewire\Admin\Passengers.php",
    "app\Livewire\Admin\Users.php",
    "app\Livewire\Admin\Trips.php",
    "app\Livewire\Admin\Reviews.php",
    "app\Livewire\Admin\Support.php",
    "app\Livewire\Admin\Refunds.php",
    "app\Livewire\Admin\Notifications.php",
    "app\Livewire\Admin\Vehicles.php"
)

Write-Host "`n=== Ajout du trait WithConfirmModal dans les composants PHP ==="
foreach ($rel in $phpComponents) {
    $file = Join-Path $basePath $rel
    $content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)

    # Vérifier si le trait est déjà là
    if ($content.Contains("WithConfirmModal")) {
        Write-Host "  DEJA PRESENT: $([System.IO.Path]::GetFileName($file))"
        continue
    }

    # Trouver le use WithPagination; et ajouter le trait
    # Pattern: "use WithPagination;" → "use WithPagination, WithConfirmModal;"
    if ($content.Contains("use WithPagination;")) {
        $content = $content.Replace("use WithPagination;", "use WithPagination, WithConfirmModal;")
        # Ajouter l'import au bon endroit (avant use Livewire\Component)
        $content = $content.Replace("use Livewire\Component;", "use App\Traits\WithConfirmModal;`r`nuse Livewire\Component;")
        [System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
        Write-Host "  OK: $([System.IO.Path]::GetFileName($file))"
    } else {
        Write-Host "  WARN: use WithPagination non trouvé dans $([System.IO.Path]::GetFileName($file))"
    }
}

# ──────────────────────────────────────────────────────────────────────────────
# notifications.blade.php
# ──────────────────────────────────────────────────────────────────────────────
Write-Host "`n=== notifications.blade.php ==="
$file = Join-Path $basePath "resources\views\admin\notifications.blade.php"
$content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)

# Ligne 71: markAllRead sans ID
$content = $content.Replace(
    '<button class="btn-mark-all" wire:click="markAllRead" wire:confirm="Marquer toutes les notifications comme lues ?">',
    '<button class="btn-mark-all" wire:click="askConfirm(''markAllRead'', null, ''Tout marquer comme lu'', ''Toutes les notifications seront marquées comme lues.'', ''primary'')">'
)

# Ligne 195: delete($notif->id)
$content = $content.Replace(
    '<button class="btn-sm danger" wire:click="delete({{ $notif->id }})"' + "`r`n                        wire:confirm=" + '"Supprimer cette notification ?">✕</button>',
    '<button class="btn-sm danger" wire:click="askConfirm(''delete'', {{ $notif->id }}, ''Supprimer la notification'', ''Cette notification sera supprimée définitivement.'', ''danger'')">✕</button>'
)
# Alternative sans CRLF
$content = $content.Replace(
    '<button class="btn-sm danger" wire:click="delete({{ $notif->id }})"' + "`n                        wire:confirm=" + '"Supprimer cette notification ?">✕</button>',
    '<button class="btn-sm danger" wire:click="askConfirm(''delete'', {{ $notif->id }}, ''Supprimer la notification'', ''Cette notification sera supprimée définitivement.'', ''danger'')">✕</button>'
)

# Ajouter @include avant </div></div> final
if (-not $content.Contains("admin.partials.confirm-modal")) {
    $content = $content.Replace(
        "</div>`r`n</div>",
        "@include('admin.partials.confirm-modal')`r`n</div>`r`n</div>"
    )
    if (-not $content.Contains("admin.partials.confirm-modal")) {
        $content = $content.Replace(
            "</div>`n</div>",
            "@include('admin.partials.confirm-modal')`n</div>`n</div>"
        )
    }
}

[System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
Write-Host "  OK: notifications.blade.php"

# ──────────────────────────────────────────────────────────────────────────────
# reviews.blade.php
# ──────────────────────────────────────────────────────────────────────────────
Write-Host "`n=== reviews.blade.php ==="
$file = Join-Path $basePath "resources\views\admin\reviews.blade.php"
$content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)

# Tableau: delete($r->id) avec wire:confirm
$content = $content -replace 'wire:click="delete\(\{\{ \$r->id \}\}\)" wire:confirm="Supprimer définitivement cet avis \?"', 'wire:click="askConfirm(''delete'', {{ $r->id }}, ''Supprimer l''''avis'', ''Cet avis sera supprimé définitivement.'', ''danger'')"'

# Panel: delete($selectedId->id) ou delete($s->id) avec wire:confirm
$content = $content -replace 'wire:confirm="Supprimer définitivement cet avis \?"', 'wire:click="askConfirm(''delete'', {{ $selectedId }}, ''Supprimer l''''avis'', ''Cet avis sera supprimé définitivement.'', ''danger'')"'

# Enlever les wire:confirm résiduels sur delete dans reviews
$content = $content -replace 'wire:click="delete\(\{\{ \$s->id \}\}\)" wire:confirm="Supprimer définitivement cet avis \?"', 'wire:click="askConfirm(''delete'', {{ $s->id }}, ''Supprimer l''''avis'', ''Cet avis sera supprimé définitivement.'', ''danger'')"'

if (-not $content.Contains("admin.partials.confirm-modal")) {
    $content = $content -replace '</div>\s*$', "@include('admin.partials.confirm-modal')`r`n</div>"
}

[System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
Write-Host "  OK: reviews.blade.php"

# ──────────────────────────────────────────────────────────────────────────────
# support.blade.php
# ──────────────────────────────────────────────────────────────────────────────
Write-Host "`n=== support.blade.php ==="
$file = Join-Path $basePath "resources\views\admin\support.blade.php"
$content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)

$content = $content -replace 'wire:click="resolve\(\{\{ \$ticket->id \}\}\)" wire:confirm="Marquer ce ticket comme résolu \?"', 'wire:click="askConfirm(''resolve'', {{ $ticket->id }}, ''Résoudre le ticket'', ''Ce ticket sera marqué comme résolu.'', ''primary'')"'
$content = $content -replace 'wire:click="resolve\(\{\{ \$selectedTicketId \}\}\)" wire:confirm="Marquer comme résolu \?"', 'wire:click="askConfirm(''resolve'', {{ $selectedTicketId }}, ''Résoudre le ticket'', ''Ce ticket sera marqué comme résolu.'', ''primary'')"'
$content = $content -replace 'wire:click="closeTicket\(\{\{ \$selectedTicketId \}\}\)" wire:confirm="Fermer ce ticket \?"', 'wire:click="askConfirm(''closeTicket'', {{ $selectedTicketId }}, ''Fermer le ticket'', ''Ce ticket sera fermé.'', ''warning'')"'
# Variante avec $t->id
$content = $content -replace 'wire:click="resolve\(\{\{ \$t->id \}\}\)" wire:confirm="Marquer comme résolu \?"', 'wire:click="askConfirm(''resolve'', {{ $t->id }}, ''Résoudre le ticket'', ''Ce ticket sera marqué comme résolu.'', ''primary'')"'
$content = $content -replace 'wire:click="closeTicket\(\{\{ \$t->id \}\}\)" wire:confirm="Fermer ce ticket \?"', 'wire:click="askConfirm(''closeTicket'', {{ $t->id }}, ''Fermer le ticket'', ''Ce ticket sera fermé.'', ''warning'')"'

if (-not $content.Contains("admin.partials.confirm-modal")) {
    $content = $content -replace '</div>\s*$', "@include('admin.partials.confirm-modal')`r`n</div>"
}

[System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
Write-Host "  OK: support.blade.php"

# ──────────────────────────────────────────────────────────────────────────────
# users.blade.php
# ──────────────────────────────────────────────────────────────────────────────
Write-Host "`n=== users.blade.php ==="
$file = Join-Path $basePath "resources\views\admin\users.blade.php"
$content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)

# Tableau: toggleBlock - débloquer
$content = $content.Replace(
    'wire:click="toggleBlock({{ $user->id }})" wire:confirm="Débloquer cet utilisateur ?"',
    'wire:click="askConfirm(''toggleBlock'', {{ $user->id }}, ''Débloquer l''''utilisateur'', ''Cet utilisateur sera débloqué et pourra accéder à la plateforme.'', ''primary'')"'
)
# Tableau: toggleBlock - bloquer
$content = $content.Replace(
    'wire:click="toggleBlock({{ $user->id }})" wire:confirm="Bloquer cet utilisateur ?"',
    'wire:click="askConfirm(''toggleBlock'', {{ $user->id }}, ''Bloquer l''''utilisateur'', ''Cet utilisateur sera bloqué et ne pourra plus accéder à la plateforme.'', ''warning'')"'
)
# Tableau: deleteUser
$content = $content.Replace(
    'wire:click="deleteUser({{ $user->id }})" wire:confirm="Supprimer définitivement cet utilisateur et toutes ses données ?"',
    'wire:click="askConfirm(''deleteUser'', {{ $user->id }}, ''Supprimer l''''utilisateur'', ''Cet utilisateur et toutes ses données seront supprimés définitivement. Action irréversible.'', ''danger'')"'
)

# Panel: approveKyc
$content = $content.Replace(
    'wire:click="approveKyc({{ $u->id }})" wire:confirm="Approuver le KYC de {{ $uName ?: $u->phone }} ?"',
    'wire:click="askConfirm(''approveKyc'', {{ $u->id }}, ''Approuver le KYC'', ''Le KYC de {{ $uName ?: $u->phone }} sera approuvé.'', ''primary'')"'
)
# Panel: rejectKyc
$content = $content.Replace(
    'wire:click="rejectKyc({{ $u->id }})" wire:confirm="Rejeter le KYC de {{ $uName ?: $u->phone }} ?"',
    'wire:click="askConfirm(''rejectKyc'', {{ $u->id }}, ''Rejeter le KYC'', ''Le KYC de {{ $uName ?: $u->phone }} sera rejeté.'', ''danger'')"'
)
# Panel: rejectKyc révoquer
$content = $content.Replace(
    'wire:click="rejectKyc({{ $u->id }})" wire:confirm="Révoquer l''approbation KYC ?"',
    'wire:click="askConfirm(''rejectKyc'', {{ $u->id }}, ''Révoquer le KYC'', ''L''''approbation KYC de cet utilisateur sera révoquée.'', ''danger'')"'
)
# Panel: approveKyc ré-approuver
$content = $content.Replace(
    'wire:click="approveKyc({{ $u->id }})" wire:confirm="Ré-approuver le KYC ?"',
    'wire:click="askConfirm(''approveKyc'', {{ $u->id }}, ''Ré-approuver le KYC'', ''Le KYC sera ré-approuvé pour cet utilisateur.'', ''primary'')"'
)
# Panel: toggleBlock débloquer
$content = $content.Replace(
    'wire:click="toggleBlock({{ $u->id }})" wire:confirm="Débloquer cet utilisateur ?"',
    'wire:click="askConfirm(''toggleBlock'', {{ $u->id }}, ''Débloquer l''''utilisateur'', ''Cet utilisateur sera débloqué et pourra accéder à la plateforme.'', ''primary'')"'
)
# Panel: toggleBlock bloquer
$content = $content.Replace(
    'wire:click="toggleBlock({{ $u->id }})" wire:confirm="Bloquer cet utilisateur ?"',
    'wire:click="askConfirm(''toggleBlock'', {{ $u->id }}, ''Bloquer l''''utilisateur'', ''Cet utilisateur sera bloqué et ne pourra plus accéder à la plateforme.'', ''warning'')"'
)
# Panel: deleteUser
$content = $content.Replace(
    'wire:click="deleteUser({{ $u->id }})" wire:confirm="Supprimer définitivement {{ $uName ?: $u->phone }} ?"',
    'wire:click="askConfirm(''deleteUser'', {{ $u->id }}, ''Supprimer l''''utilisateur'', ''Cet utilisateur et toutes ses données seront supprimés définitivement. Action irréversible.'', ''danger'')"'
)

if (-not $content.Contains("admin.partials.confirm-modal")) {
    $content = $content -replace '</div>\s*$', "@include('admin.partials.confirm-modal')`r`n</div>"
}

[System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
Write-Host "  OK: users.blade.php"

# ──────────────────────────────────────────────────────────────────────────────
# trips.blade.php
# ──────────────────────────────────────────────────────────────────────────────
Write-Host "`n=== trips.blade.php ==="
$file = Join-Path $basePath "resources\views\admin\trips.blade.php"
$content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)

# Tableau: toggleFlag (conditionnel $trip->is_flagged)
# Ces deux lignes sont séparées par @if, on ne peut pas faire de remplacement simple
# On fait un remplacement de chaque variant séparément via regex
$content = $content -replace 'wire:click="toggleFlag\(\{\{ \$trip->id \}\}\)"\s+wire:confirm="\{\{ \$trip->is_flagged \? ''Retirer le signalement de ce trajet \?'' : ''Signaler ce trajet \?'' \}\}"', 'wire:click="$trip->is_flagged ? `"askConfirm(''toggleFlag'', {{ $trip->id }}, ''Retirer le signalement'', ''Le signalement de ce trajet sera retiré.'', ''warning'')`" : `"askConfirm(''toggleFlag'', {{ $trip->id }}, ''Signaler le trajet'', ''Ce trajet sera signalé pour examen.'', ''warning'')`""'

# Simplification: on décompose en @if/@else dans la vue via regex impossible,
# donc on utilise un askConfirm générique avec message conditionnel depuis PHP
$content = $content -replace 'wire:click="toggleFlag\(\{\{ \$trip->id \}\}\)"\s+wire:confirm="\{\{[^}]+\}\}"', 'wire:click="askConfirm(''toggleFlag'', {{ $trip->id }}, $trip->is_flagged ? ''Retirer le signalement'' : ''Signaler le trajet'', $trip->is_flagged ? ''Le signalement de ce trajet sera retiré.'' : ''Ce trajet sera signalé pour examen.'', ''warning'')"'

# Tableau: cancelTrip
$content = $content.Replace(
    'wire:confirm="Annuler définitivement ce trajet ? Les passagers seront affectés."',
    'wire:click="askConfirm(''cancelTrip'', {{ $trip->id }}, ''Annuler le trajet'', ''Ce trajet sera annulé définitivement. Les passagers seront affectés.'', ''danger'')"'
)
# Nettoyer le wire:click dupliqué (la ligne cancelTrip a déjà un wire:click)
# On doit plutôt remplacer la combinaison complète
$content = $content -replace 'wire:click="cancelTrip\(\{\{ \$trip->id \}\}\)"\s+wire:confirm="Annuler définitivement ce trajet \? Les passagers seront affectés\."', 'wire:click="askConfirm(''cancelTrip'', {{ $trip->id }}, ''Annuler le trajet'', ''Ce trajet sera annulé définitivement. Les passagers seront affectés.'', ''danger'')"'

# Panel: toggleFlag (retirer)
$content = $content -replace 'wire:click="toggleFlag\(\{\{ \$t->id \}\}\)" wire:confirm="Retirer le signalement \?"', 'wire:click="askConfirm(''toggleFlag'', {{ $t->id }}, ''Retirer le signalement'', ''Le signalement de ce trajet sera retiré.'', ''warning'')"'
# Panel: toggleFlag (signaler)
$content = $content -replace 'wire:click="toggleFlag\(\{\{ \$t->id \}\}\)" wire:confirm="Signaler ce trajet \?"', 'wire:click="askConfirm(''toggleFlag'', {{ $t->id }}, ''Signaler le trajet'', ''Ce trajet sera signalé pour examen.'', ''warning'')"'
# Panel: cancelTrip
$content = $content -replace 'wire:click="cancelTrip\(\{\{ \$t->id \}\}\)" wire:confirm="Annuler ce trajet définitivement \?"', 'wire:click="askConfirm(''cancelTrip'', {{ $t->id }}, ''Annuler le trajet'', ''Ce trajet sera annulé définitivement. Les passagers seront affectés.'', ''danger'')"'

if (-not $content.Contains("admin.partials.confirm-modal")) {
    $content = $content -replace '</div>\s*$', "@include('admin.partials.confirm-modal')`r`n</div>"
}

[System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
Write-Host "  OK: trips.blade.php"

# ──────────────────────────────────────────────────────────────────────────────
# vehicles.blade.php
# ──────────────────────────────────────────────────────────────────────────────
Write-Host "`n=== vehicles.blade.php ==="
$file = Join-Path $basePath "resources\views\admin\vehicles.blade.php"
$content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)

$content = $content -replace 'wire:click="reject\(\{\{ \$v->id \}\}\)" wire:confirm="Confirmer le rejet \?"', 'wire:click="askConfirm(''reject'', {{ $v->id }}, ''Rejeter le véhicule'', ''Ce véhicule sera rejeté. Le conducteur en sera notifié.'', ''danger'')"'
$content = $content -replace 'wire:click="approve\(\{\{ \$v->id \}\}\)" wire:confirm="Approuver ce véhicule \?"', 'wire:click="askConfirm(''approve'', {{ $v->id }}, ''Approuver le véhicule'', ''Ce véhicule sera approuvé. Le conducteur pourra créer des trajets.'', ''primary'')"'
$content = $content -replace 'wire:click="suspend\(\{\{ \$v->id \}\}\)" wire:confirm="Suspendre ce véhicule \?"', 'wire:click="askConfirm(''suspend'', {{ $v->id }}, ''Suspendre le véhicule'', ''Ce véhicule sera suspendu temporairement.'', ''warning'')"'

if (-not $content.Contains("admin.partials.confirm-modal")) {
    $content = $content -replace '</div>\s*$', "@include('admin.partials.confirm-modal')`r`n</div>"
}

[System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
Write-Host "  OK: vehicles.blade.php"

# ──────────────────────────────────────────────────────────────────────────────
# passengers.blade.php
# ──────────────────────────────────────────────────────────────────────────────
Write-Host "`n=== passengers.blade.php ==="
$file = Join-Path $basePath "resources\views\admin\passengers.blade.php"
$content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)

# Tableau: toggleBlock (conditionnel)
$content = $content -replace 'wire:click="toggleBlock\(\{\{ \$pax->id \}\}\)"\s+wire:confirm="\{\{ \$pax->is_blocked \? ''Débloquer ce passager \?'' : ''Bloquer ce passager \?'' \}\}"', 'wire:click="{{ $pax->is_blocked ? `"askConfirm(''toggleBlock'', {{ $pax->id }}, ''Débloquer le passager'', ''Ce passager sera débloqué.'', ''primary'')`" : `"askConfirm(''toggleBlock'', {{ $pax->id }}, ''Bloquer le passager'', ''Ce passager sera bloqué et ne pourra plus utiliser la plateforme.'', ''warning'')`" }}"'

# Panel: approveKyc (pending)
$content = $content.Replace(
    'wire:click="approveKyc({{ $s->id }})" wire:confirm="Approuver le KYC de {{ $nm2 }} ?"',
    'wire:click="askConfirm(''approveKyc'', {{ $s->id }}, ''Approuver le KYC'', ''Le KYC de {{ $nm2 }} sera approuvé.'', ''primary'')"'
)
# Panel: rejectKyc (pending)
$content = $content.Replace(
    'wire:click="rejectKyc({{ $s->id }})" wire:confirm="Rejeter le KYC de {{ $nm2 }} ?"',
    'wire:click="askConfirm(''rejectKyc'', {{ $s->id }}, ''Rejeter le KYC'', ''Le KYC de {{ $nm2 }} sera rejeté.'', ''danger'')"'
)
# Panel: rejectKyc (révoquer)
$content = $content.Replace(
    'wire:click="rejectKyc({{ $s->id }})" wire:confirm="Révoquer l''approbation KYC ?"',
    'wire:click="askConfirm(''rejectKyc'', {{ $s->id }}, ''Révoquer le KYC'', ''L''''approbation KYC de ce passager sera révoquée.'', ''danger'')"'
)
# Panel: approveKyc (ré-approuver)
$content = $content.Replace(
    'wire:click="approveKyc({{ $s->id }})" wire:confirm="Ré-approuver le KYC ?"',
    'wire:click="askConfirm(''approveKyc'', {{ $s->id }}, ''Ré-approuver le KYC'', ''Le KYC de ce passager sera ré-approuvé.'', ''primary'')"'
)
# Panel: toggleBlock (conditionnel)
$content = $content -replace 'wire:click="toggleBlock\(\{\{ \$s->id \}\}\)"\s+wire:confirm="\{\{ \$s->is_blocked \? ''Débloquer ce passager \?'' : ''Bloquer ce passager \?'' \}\}"', 'wire:click="{{ $s->is_blocked ? `"askConfirm(''toggleBlock'', {{ $s->id }}, ''Débloquer le passager'', ''Ce passager sera débloqué.'', ''primary'')`" : `"askConfirm(''toggleBlock'', {{ $s->id }}, ''Bloquer le passager'', ''Ce passager sera bloqué.'', ''warning'')`" }}"'

if (-not $content.Contains("admin.partials.confirm-modal")) {
    $content = $content -replace '</div>\s*$', "@include('admin.partials.confirm-modal')`r`n</div>"
}

[System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
Write-Host "  OK: passengers.blade.php"

# ──────────────────────────────────────────────────────────────────────────────
# drivers.blade.php
# ──────────────────────────────────────────────────────────────────────────────
Write-Host "`n=== drivers.blade.php ==="
$file = Join-Path $basePath "resources\views\admin\drivers.blade.php"
$content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)

# Tableau: approveKyc (pending)
$content = $content.Replace(
    'wire:click="approveKyc({{ $driver->id }})" wire:confirm="Approuver le KYC de {{ $name ?: $driver->phone }} ?"',
    'wire:click="askConfirm(''approveKyc'', {{ $driver->id }}, ''Approuver le KYC'', ''Le KYC de {{ $name ?: $driver->phone }} sera approuvé.'', ''primary'')"'
)
# Tableau: rejectKyc (pending)
$content = $content.Replace(
    'wire:click="rejectKyc({{ $driver->id }})" wire:confirm="Rejeter le KYC de {{ $name ?: $driver->phone }} ?"',
    'wire:click="askConfirm(''rejectKyc'', {{ $driver->id }}, ''Rejeter le KYC'', ''Le KYC de {{ $name ?: $driver->phone }} sera rejeté.'', ''danger'')"'
)
# Tableau: rejectKyc (révoquer)
$content = $content.Replace(
    'wire:click="rejectKyc({{ $driver->id }})" wire:confirm="Révoquer l''approbation KYC ?"',
    'wire:click="askConfirm(''rejectKyc'', {{ $driver->id }}, ''Révoquer le KYC'', ''L''''approbation KYC de ce conducteur sera révoquée.'', ''danger'')"'
)
# Tableau: approveKyc (ré-approuver)
$content = $content.Replace(
    'wire:click="approveKyc({{ $driver->id }})" wire:confirm="Ré-approuver le KYC ?"',
    'wire:click="askConfirm(''approveKyc'', {{ $driver->id }}, ''Ré-approuver le KYC'', ''Le KYC de ce conducteur sera ré-approuvé.'', ''primary'')"'
)
# Tableau: approveVehicle (pending)
$content = $content.Replace(
    'wire:click="approveVehicle({{ $driver->id }})" wire:confirm="Approuver le véhicule de {{ $name ?: $driver->phone }} ?"',
    'wire:click="askConfirm(''approveVehicle'', {{ $driver->id }}, ''Approuver le véhicule'', ''Le véhicule de {{ $name ?: $driver->phone }} sera approuvé.'', ''primary'')"'
)
# Tableau: rejectVehicle (pending)
$content = $content.Replace(
    'wire:click="rejectVehicle({{ $driver->id }})" wire:confirm="Rejeter le véhicule de {{ $name ?: $driver->phone }} ?"',
    'wire:click="askConfirm(''rejectVehicle'', {{ $driver->id }}, ''Rejeter le véhicule'', ''Le véhicule de {{ $name ?: $driver->phone }} sera rejeté.'', ''danger'')"'
)
# Tableau: rejectVehicle (révoquer)
$content = $content.Replace(
    'wire:click="rejectVehicle({{ $driver->id }})" wire:confirm="Révoquer l''approbation du véhicule ?"',
    'wire:click="askConfirm(''rejectVehicle'', {{ $driver->id }}, ''Révoquer le véhicule'', ''L''''approbation du véhicule sera révoquée.'', ''danger'')"'
)
# Tableau: approveVehicle (ré-approuver)
$content = $content.Replace(
    'wire:click="approveVehicle({{ $driver->id }})" wire:confirm="Approuver quand même le véhicule ?"',
    'wire:click="askConfirm(''approveVehicle'', {{ $driver->id }}, ''Approuver le véhicule'', ''Le véhicule de ce conducteur sera approuvé malgré le rejet précédent.'', ''primary'')"'
)

# Panel: approveKyc (pending)
$content = $content.Replace(
    'wire:click="approveKyc({{ $sd->id }})" wire:confirm="Approuver le KYC de {{ $nmD ?: $sd->phone }} ?"',
    'wire:click="askConfirm(''approveKyc'', {{ $sd->id }}, ''Approuver le KYC'', ''Le KYC de {{ $nmD ?: $sd->phone }} sera approuvé.'', ''primary'')"'
)
# Panel: rejectKyc (pending)
$content = $content.Replace(
    'wire:click="rejectKyc({{ $sd->id }})" wire:confirm="Rejeter le KYC de {{ $nmD ?: $sd->phone }} ?"',
    'wire:click="askConfirm(''rejectKyc'', {{ $sd->id }}, ''Rejeter le KYC'', ''Le KYC de {{ $nmD ?: $sd->phone }} sera rejeté.'', ''danger'')"'
)
# Panel: rejectKyc (révoquer)
$content = $content.Replace(
    'wire:click="rejectKyc({{ $sd->id }})" wire:confirm="Révoquer l''approbation KYC ?"',
    'wire:click="askConfirm(''rejectKyc'', {{ $sd->id }}, ''Révoquer le KYC'', ''L''''approbation KYC de ce conducteur sera révoquée.'', ''danger'')"'
)
# Panel: approveKyc (ré-approuver)
$content = $content.Replace(
    'wire:click="approveKyc({{ $sd->id }})" wire:confirm="Ré-approuver le KYC ?"',
    'wire:click="askConfirm(''approveKyc'', {{ $sd->id }}, ''Ré-approuver le KYC'', ''Le KYC de ce conducteur sera ré-approuvé.'', ''primary'')"'
)
# Panel: approveVehicle (pending)
$content = $content.Replace(
    'wire:click="approveVehicle({{ $sd->id }})" wire:confirm="Approuver le véhicule ?"',
    'wire:click="askConfirm(''approveVehicle'', {{ $sd->id }}, ''Approuver le véhicule'', ''Le véhicule de ce conducteur sera approuvé.'', ''primary'')"'
)

if (-not $content.Contains("admin.partials.confirm-modal")) {
    $content = $content -replace '</div>\s*$', "@include('admin.partials.confirm-modal')`r`n</div>"
}

[System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
Write-Host "  OK: drivers.blade.php"

# ──────────────────────────────────────────────────────────────────────────────
# refunds.blade.php  (withdrawals)
# ──────────────────────────────────────────────────────────────────────────────
Write-Host "`n=== refunds.blade.php ==="
$file = Join-Path $basePath "resources\views\admin\refunds.blade.php"
$content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)

# Tableau: approveWithdrawal - ligne 258 (avec $w->amount)
$content = $content -replace 'wire:click="approveWithdrawal\(\{\{ \$w->id \}\}\)"\s+wire:confirm="Approuver ce retrait de \{\{ number_format\(\$w->amount\) \}\} FCFA \?"', 'wire:click="askConfirm(''approveWithdrawal'', {{ $w->id }}, ''Approuver le retrait'', ''Le retrait de {{ number_format($w->amount) }} FCFA sera approuvé.'', ''primary'')"'
# Tableau: rejectWithdrawal - ligne 260
$content = $content -replace 'wire:click="rejectWithdrawal\(\{\{ \$w->id \}\}\)"\s+wire:confirm="Rejeter cette demande \?"', 'wire:click="askConfirm(''rejectWithdrawal'', {{ $w->id }}, ''Rejeter la demande'', ''Cette demande de retrait sera rejetée.'', ''danger'')"'

# Panel: approveWithdrawal - ligne 401 (avec $wd->amount)
$content = $content -replace 'wire:click="approveWithdrawal\(\{\{ \$wd->id \}\}\)"\s+wire:confirm="Approuver ce retrait de \{\{ number_format\(\$wd->amount\) \}\} FCFA \?"', 'wire:click="askConfirm(''approveWithdrawal'', {{ $wd->id }}, ''Approuver le retrait'', ''Le retrait de {{ number_format($wd->amount) }} FCFA sera approuvé et traité.'', ''primary'')"'
# Panel: rejectWithdrawal - ligne 403
$content = $content -replace 'wire:click="rejectWithdrawal\(\{\{ \$wd->id \}\}\)"\s+wire:confirm="Rejeter cette demande \?"', 'wire:click="askConfirm(''rejectWithdrawal'', {{ $wd->id }}, ''Rejeter la demande'', ''Cette demande de retrait sera rejetée.'', ''danger'')"'
# Panel: markFailed - ligne 407
$content = $content -replace 'wire:click="markFailed\(\{\{ \$wd->id \}\}\)"\s+wire:confirm="Marquer ce virement comme échoué \?"', 'wire:click="askConfirm(''markFailed'', {{ $wd->id }}, ''Marquer comme échoué'', ''Ce virement sera marqué comme échoué.'', ''warning'')"'

if (-not $content.Contains("admin.partials.confirm-modal")) {
    $content = $content -replace '</div>\s*$', "@include('admin.partials.confirm-modal')`r`n</div>"
}

[System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
Write-Host "  OK: refunds.blade.php"

Write-Host "`n=== TERMINÉ ==="
