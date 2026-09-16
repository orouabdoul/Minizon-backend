<div>
<style>
.notif-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.notif-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.notif-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.notif-header p{font-size:13px;color:#6B7280;margin:0}

.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;gap:14px}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-value{font-size:22px;font-weight:700;color:#111827}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px}

.filter-bar{background:#fff;border-radius:12px;padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:18px}
.filter-select{padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;background:#fff;cursor:pointer}
.filter-select:focus{border-color:#1A5FB4}
.btn-mark-all{padding:8px 16px;background:#EFF6FF;border:1.5px solid #BFDBFE;border-radius:8px;font-size:12px;font-weight:600;color:#1A5FB4;cursor:pointer;white-space:nowrap;transition:all .15s}
.btn-mark-all:hover{background:#DBEAFE}

.notif-list{display:flex;flex-direction:column;gap:8px}
.notif-item{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:flex-start;gap:14px;border-left:4px solid transparent;transition:all .15s}
.notif-item.unread{border-left-color:#1A5FB4;background:#FAFCFF}
.notif-item.urgent{border-left-color:#EF4444}
.notif-item.high{border-left-color:#F59E0B}
.notif-item.handled{opacity:.7}

.notif-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:5px}
.notif-content{flex:1;min-width:0}
.notif-title{font-size:14px;font-weight:600;color:#111827;margin-bottom:3px}
.notif-desc{font-size:12px;color:#6B7280;line-height:1.5;margin-bottom:6px}
.notif-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}
.notif-time{font-size:11px;color:#9CA3AF}

.notif-actions{display:flex;gap:6px;flex-shrink:0;align-items:flex-start}
.btn-sm{padding:6px 12px;border-radius:7px;font-size:11px;font-weight:600;cursor:pointer;border:1.5px solid #E5E7EB;background:#fff;color:#374151;transition:all .15s;white-space:nowrap}
.btn-sm:hover{border-color:#1A5FB4;background:#EFF6FF;color:#1A5FB4}
.btn-sm.success:hover{border-color:#10B981;background:#D1FAE5;color:#065F46}
.btn-sm.danger:hover{border-color:#EF4444;background:#FEE2E2;color:#EF4444}

.empty-state{background:#fff;border-radius:12px;padding:60px;text-align:center;color:#9CA3AF;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.empty-state .icon{font-size:40px;margin-bottom:12px}
.empty-state p{font-size:14px;margin:0}

.pagination-wrap{padding:12px 0;display:flex;justify-content:flex-end}
</style>

<div class="notif-wrap">

    {{-- Header --}}
    <div class="notif-header">
        <div>
            <h1>Notifications</h1>
            <p>Alertes et notifications système de MINIZON</p>
        </div>
        @if($stats['unread'] > 0)
        <button class="btn-mark-all" wire:click="markAllRead" wire:confirm="Marquer toutes les notifications comme lues ?">
            ✓ Tout marquer comme lu ({{ $stats['unread'] }})
        </button>
        @endif
    </div>

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:#EFF6FF">🔔</div>
            <div>
                <div class="stat-value" style="{{ $stats['unread'] > 0 ? 'color:#1A5FB4' : '' }}">{{ number_format($stats['unread']) }}</div>
                <div class="stat-label">Non lues</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEE2E2">🚨</div>
            <div>
                <div class="stat-value" style="{{ $stats['urgent'] > 0 ? 'color:#EF4444' : '' }}">{{ number_format($stats['urgent']) }}</div>
                <div class="stat-label">Urgentes non lues</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEF3C7">⚠️</div>
            <div>
                <div class="stat-value" style="{{ $stats['high'] > 0 ? 'color:#D97706' : '' }}">{{ number_format($stats['high']) }}</div>
                <div class="stat-label">Haute priorité non lues</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#D1FAE5">✅</div>
            <div>
                <div class="stat-value">{{ number_format($stats['handled']) }}</div>
                <div class="stat-label">Traitées</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-bar">
        <select class="filter-select" wire:model.live="typeFilter">
            <option value="">Tous types</option>
            <option value="system">Système</option>
            <option value="user">Utilisateur</option>
            <option value="payment">Paiement</option>
            <option value="dispute">Litige</option>
            <option value="driver">Conducteur</option>
        </select>
        <select class="filter-select" wire:model.live="priorityFilter">
            <option value="">Toutes priorités</option>
            <option value="urgent">Urgente</option>
            <option value="high">Haute</option>
            <option value="normal">Normale</option>
            <option value="low">Basse</option>
        </select>
        <select class="filter-select" wire:model.live="statusFilter">
            <option value="">Tous statuts</option>
            <option value="unread">Non lue</option>
            <option value="read">Lue</option>
            <option value="handled">Traitée</option>
        </select>
    </div>

    {{-- Notification list --}}
    @if($notifications->isEmpty())
    <div class="empty-state">
        <div class="icon">🔔</div>
        <p>Aucune notification</p>
    </div>
    @else
    <div class="notif-list">
        @foreach($notifications as $notif)
        @php
            $priorityColors = [
                'urgent' => '#EF4444',
                'high'   => '#F59E0B',
                'normal' => '#1A5FB4',
                'low'    => '#9CA3AF',
            ];
            $typeLabels = [
                'system'  => ['label'=>'Système',     'bg'=>'#EFF6FF','color'=>'#1A5FB4'],
                'user'    => ['label'=>'Utilisateur', 'bg'=>'#F3F4F6','color'=>'#374151'],
                'payment' => ['label'=>'Paiement',    'bg'=>'#D1FAE5','color'=>'#065F46'],
                'dispute' => ['label'=>'Litige',      'bg'=>'#FEE2E2','color'=>'#991B1B'],
                'driver'  => ['label'=>'Conducteur',  'bg'=>'#EDE9FE','color'=>'#4C1D95'],
            ];
            $tData = $typeLabels[$notif->type] ?? ['label'=>$notif->type,'bg'=>'#F3F4F6','color'=>'#374151'];
            $dotColor = $priorityColors[$notif->priority] ?? '#9CA3AF';
            $isUnread = $notif->status === 'unread';
        @endphp
        <div class="notif-item {{ $isUnread ? 'unread' : '' }} {{ $isUnread ? $notif->priority : '' }} {{ $notif->status === 'handled' ? 'handled' : '' }}">
            <div class="notif-dot" style="background:{{ $dotColor }}"></div>
            <div class="notif-content">
                <div class="notif-title">{{ $notif->title }}</div>
                <div class="notif-desc">{{ $notif->description }}</div>
                <div class="notif-meta">
                    <span class="badge" style="background:{{ $tData['bg'] }};color:{{ $tData['color'] }}">{{ $tData['label'] }}</span>
                    @php
                        $prioLabels = ['urgent'=>'🚨 Urgent','high'=>'⚠️ Haute','normal'=>'Normal','low'=>'Basse'];
                        $prioBgs = ['urgent'=>'#FEE2E2','high'=>'#FEF3C7','normal'=>'#EFF6FF','low'=>'#F3F4F6'];
                        $prioColors = ['urgent'=>'#991B1B','high'=>'#92400E','normal'=>'#1A5FB4','low'=>'#9CA3AF'];
                    @endphp
                    <span class="badge" style="background:{{ $prioBgs[$notif->priority]??'#F3F4F6' }};color:{{ $prioColors[$notif->priority]??'#374151' }}">
                        {{ $prioLabels[$notif->priority] ?? $notif->priority }}
                    </span>
                    @if($notif->user)
                    <span class="notif-time">👤 {{ trim(($notif->user->profile?->first_name??'').(' '.($notif->user->profile?->last_name??''))) ?: $notif->user->phone }}</span>
                    @endif
                    <span class="notif-time">{{ $notif->created_at->diffForHumans() }}</span>
                    @if($notif->status === 'read')
                        <span class="badge" style="background:#F3F4F6;color:#6B7280">Lu</span>
                    @elseif($notif->status === 'handled')
                        <span class="badge" style="background:#D1FAE5;color:#065F46">✓ Traité</span>
                    @endif
                </div>
            </div>
            <div class="notif-actions">
                @if($notif->status === 'unread')
                <button class="btn-sm success" wire:click="markRead({{ $notif->id }})">Lu</button>
                @endif
                @if($notif->status !== 'handled')
                <button class="btn-sm" wire:click="markHandled({{ $notif->id }})">Traité</button>
                @endif
                <button class="btn-sm danger" wire:click="delete({{ $notif->id }})"
                        wire:confirm="Supprimer cette notification ?">✕</button>
            </div>
        </div>
        @endforeach
    </div>

    <div class="pagination-wrap">
        {{ $notifications->links() }}
    </div>
    @endif

</div>
</div>
