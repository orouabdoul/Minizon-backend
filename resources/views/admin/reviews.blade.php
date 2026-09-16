<div>
<style>
.rv-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh}
.rv-header{margin-bottom:24px}
.rv-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.rv-header p{font-size:13px;color:#6B7280;margin:0}

.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;gap:12px}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-value{font-size:22px;font-weight:700;color:#111827}
.stat-label{font-size:11px;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px}

.filter-bar{background:#fff;border-radius:12px;padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:18px}
.filter-input{flex:1;min-width:200px;padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none}
.filter-input:focus{border-color:#1A5FB4}
.filter-select{padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;background:#fff;cursor:pointer}
.filter-select:focus{border-color:#1A5FB4}

.table-wrap{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);overflow:hidden}
.table-head{padding:14px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between}
.table-head h2{font-size:14px;font-weight:600;color:#374151;margin:0}
.table-count{font-size:12px;color:#9CA3AF}
.data-table{width:100%;border-collapse:collapse}
.data-table th{padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #F3F4F6;background:#FAFAFA}
.data-table td{padding:12px 16px;font-size:13px;color:#374151;border-bottom:1px solid #F9FAFB;vertical-align:middle}
.data-table tr:hover td{background:#F9FAFB}
.data-table tr:last-child td{border-bottom:none}

.stars{display:flex;gap:1px;align-items:center}
.star{font-size:14px}
.rating-num{font-size:13px;font-weight:700;margin-left:4px}
.comment-cell{max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#6B7280;font-style:italic}

.user-cell{display:flex;align-items:center;gap:8px}
.user-av{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0}
.user-name{font-size:12px;font-weight:600;color:#374151}
.user-phone{font-size:11px;color:#9CA3AF}

.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}

.action-group{display:flex;gap:5px}
.btn-action{width:28px;height:28px;border-radius:6px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:all .15s}
.btn-action:hover{border-color:#1A5FB4;background:#EFF6FF}
.btn-action.warn:hover{border-color:#F59E0B;background:#FEF3C7}
.btn-action.danger:hover{border-color:#EF4444;background:#FEE2E2}

.empty-state{padding:60px;text-align:center;color:#9CA3AF}
.empty-state .icon{font-size:40px;margin-bottom:12px}

.report-badge{background:#FEE2E2;color:#991B1B;border-radius:20px;padding:1px 7px;font-size:10px;font-weight:700}

/* ── Rating bar chart ───────── */
.rating-bars{display:flex;flex-direction:column;gap:6px;margin-bottom:20px}
.rating-row{display:flex;align-items:center;gap:8px;font-size:12px}
.rating-row-label{width:24px;text-align:right;color:#374151;font-weight:600}
.rating-track{flex:1;height:10px;background:#F3F4F6;border-radius:5px;overflow:hidden}
.rating-fill{height:100%;border-radius:5px}
.rating-count-val{min-width:28px;text-align:right;color:#9CA3AF;font-size:11px}

/* ── Panel ───────── */
.panel-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1000;backdrop-filter:blur(2px)}
.panel-drawer{position:fixed;top:0;right:0;bottom:0;width:480px;max-width:95vw;background:#fff;z-index:1001;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.panel-head{padding:20px 24px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-head h2{font-size:16px;font-weight:700;color:#111827;margin:0}
.panel-close{width:32px;height:32px;border-radius:8px;border:1.5px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:#6B7280;transition:all .15s}
.panel-close:hover{background:#FEE2E2;border-color:#EF4444;color:#EF4444}
.panel-body{flex:1;overflow-y:auto;padding:24px}
.panel-actions{padding:14px 24px;border-top:1px solid #F3F4F6;display:flex;gap:10px;flex-shrink:0}
.btn-panel{flex:1;padding:10px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .15s}
.btn-panel-success{background:#D1FAE5;color:#065F46;border:1.5px solid #6EE7B7}
.btn-panel-success:hover{background:#10B981;color:#fff}
.btn-panel-warn{background:#FEF3C7;color:#D97706;border:1.5px solid #FCD34D}
.btn-panel-warn:hover{background:#D97706;color:#fff}
.btn-panel-danger{background:#FEE2E2;color:#EF4444;border:1.5px solid #FCA5A5}
.btn-panel-danger:hover{background:#EF4444;color:#fff}
.btn-panel-secondary{background:#F3F4F6;color:#374151;border:1.5px solid #E5E7EB}
.btn-panel-secondary:hover{background:#E5E7EB}

.panel-section{margin-bottom:20px}
.panel-section__title{font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px}
.panel-card{background:#F9FAFB;border-radius:10px;padding:14px 16px}
.info-row{display:flex;align-items:flex-start;gap:12px;padding:8px 0;border-bottom:1px solid #F3F4F6}
.info-row:last-child{border-bottom:none}
.info-label{font-size:11px;color:#9CA3AF;min-width:110px;flex-shrink:0;padding-top:1px}
.info-value{font-size:13px;color:#374151;font-weight:500}
</style>

@php
function stars(int $rating): string {
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}
@endphp

<div class="rv-wrap">

    <div class="rv-header">
        <h1>Évaluations</h1>
        <p>Avis et notes laissés par les passagers et conducteurs</p>
    </div>

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEF3C7">⭐</div>
            <div>
                <div class="stat-value" style="color:#D97706">{{ number_format($stats['avg'], 1) }}/5</div>
                <div class="stat-label">Note moyenne</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#EFF6FF">📊</div>
            <div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-label">Total évaluations</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEE2E2">🚩</div>
            <div>
                <div class="stat-value" style="{{ $stats['flagged'] > 0 ? 'color:#EF4444' : '' }}">{{ number_format($stats['flagged']) }}</div>
                <div class="stat-label">Signalées</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#F3F4F6">🙈</div>
            <div>
                <div class="stat-value">{{ number_format($stats['hidden']) }}</div>
                <div class="stat-label">Masquées</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-bar">
        <input type="text" class="filter-input" placeholder="🔍  Rechercher commentaire, utilisateur…"
               wire:model.live.debounce.300ms="search">
        <select class="filter-select" wire:model.live="ratingFilter">
            <option value="">Toutes notes</option>
            @for($i = 5; $i >= 1; $i--)
            <option value="{{ $i }}">{{ $i }} étoile{{ $i > 1 ? 's' : '' }}</option>
            @endfor
        </select>
        <select class="filter-select" wire:model.live="statusFilter">
            <option value="">Tous statuts</option>
            <option value="pending">En attente</option>
            <option value="approved">Approuvée</option>
            <option value="hidden">Masquée</option>
        </select>
    </div>

    {{-- Table --}}
    <div class="table-wrap">
        <div class="table-head">
            <h2>Liste des évaluations</h2>
            <span class="table-count">{{ $reviews->total() }} évaluation(s)</span>
        </div>

        @if($reviews->isEmpty())
        <div class="empty-state">
            <div class="icon">⭐</div>
            <p>Aucune évaluation trouvée</p>
        </div>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Note</th>
                    <th>Auteur</th>
                    <th>Évalué</th>
                    <th>Commentaire</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($reviews as $review)
            @php
                $colors = ['#1A5FB4','#10B981','#F59E0B','#EF4444','#6366F1','#EC4899'];
                $reviewer = $review->reviewer;
                $reviewee = $review->reviewee;

                $rrName = trim(($reviewer?->profile?->first_name??'').(' '.($reviewer?->profile?->last_name??''))) ?: ($reviewer?->phone ?? '—');
                $rrInit = strtoupper(substr($reviewer?->profile?->first_name??'U',0,1).substr($reviewer?->profile?->last_name??'',0,1));
                $rrBg   = $colors[abs(crc32($rrName))%count($colors)];

                $reName = trim(($reviewee?->profile?->first_name??'').(' '.($reviewee?->profile?->last_name??''))) ?: ($reviewee?->phone ?? '—');
                $reInit = strtoupper(substr($reviewee?->profile?->first_name??'U',0,1).substr($reviewee?->profile?->last_name??'',0,1));
                $reBg   = $colors[abs(crc32($reName))%count($colors)];

                $stColors = ['pending'=>['bg'=>'#FEF3C7','c'=>'#92400E'],'approved'=>['bg'=>'#D1FAE5','c'=>'#065F46'],'hidden'=>['bg'=>'#F3F4F6','c'=>'#6B7280']];
                $stD = $stColors[$review->status ?? 'pending'] ?? ['bg'=>'#F3F4F6','c'=>'#374151'];
                $stLabels = ['pending'=>'En attente','approved'=>'Approuvée','hidden'=>'Masquée'];

                $ratingColor = $review->rating >= 4 ? '#D97706' : ($review->rating <= 2 ? '#EF4444' : '#6B7280');
            @endphp
            <tr>
                <td>
                    <div style="color:{{ $ratingColor }};font-size:15px">{{ str_repeat('★',$review->rating).str_repeat('☆',5-$review->rating) }}</div>
                    <div style="font-size:11px;color:#9CA3AF">{{ $review->rating }}/5</div>
                </td>
                <td>
                    <div class="user-cell">
                        <div class="user-av" style="background:{{ $rrBg }}">{{ $rrInit }}</div>
                        <div>
                            <div class="user-name">{{ $rrName }}</div>
                            <div class="user-phone">{{ $reviewer?->phone ?? '—' }}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="user-cell">
                        <div class="user-av" style="background:{{ $reBg }}">{{ $reInit }}</div>
                        <div>
                            <div class="user-name">{{ $reName }}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="comment-cell" title="{{ $review->comment }}">
                        {{ $review->comment ?: '—' }}
                    </div>
                    @if($review->report_count > 0)
                    <span class="report-badge">🚩 {{ $review->report_count }} signalement(s)</span>
                    @endif
                </td>
                <td>
                    <span class="badge" style="background:{{ $stD['bg'] }};color:{{ $stD['c'] }}">
                        {{ $stLabels[$review->status ?? 'pending'] ?? $review->status }}
                    </span>
                </td>
                <td style="font-size:12px;color:#9CA3AF">{{ $review->created_at->format('d/m/Y') }}</td>
                <td>
                    <div class="action-group">
                        <button class="btn-action" title="Détail" wire:click="view({{ $review->id }})">👁</button>
                        @if($review->status !== 'approved')
                        <button class="btn-action" title="Approuver" wire:click="approve({{ $review->id }})">✓</button>
                        @endif
                        @if($review->status !== 'hidden')
                        <button class="btn-action warn" title="Masquer" wire:click="hide({{ $review->id }})">🙈</button>
                        @endif
                        <button class="btn-action danger" title="Supprimer"
                                wire:click="delete({{ $review->id }})"
                                wire:confirm="Supprimer définitivement cet avis ?">✕</button>
                    </div>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>

        <div style="padding:12px 20px;border-top:1px solid #F3F4F6">
            {{ $reviews->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Detail panel --}}
@if($selectedReview)
@php
    $rv = $selectedReview;
    $colors2 = ['#1A5FB4','#10B981','#F59E0B','#EF4444','#6366F1','#EC4899'];
    $rr2 = $rv->reviewer;
    $re2 = $rv->reviewee;
    $rrName2 = trim(($rr2?->profile?->first_name??'').(' '.($rr2?->profile?->last_name??''))) ?: ($rr2?->phone ?? 'Inconnu');
    $reName2 = trim(($re2?->profile?->first_name??'').(' '.($re2?->profile?->last_name??''))) ?: ($re2?->phone ?? 'Inconnu');
    $rrInit2 = strtoupper(substr($rr2?->profile?->first_name??'U',0,1).substr($rr2?->profile?->last_name??'',0,1));
    $reInit2 = strtoupper(substr($re2?->profile?->first_name??'U',0,1).substr($re2?->profile?->last_name??'',0,1));
    $rrBg2 = $colors2[abs(crc32($rrName2))%count($colors2)];
    $reBg2 = $colors2[abs(crc32($reName2))%count($colors2)];
    $stLabels2 = ['pending'=>'En attente','approved'=>'Approuvée','hidden'=>'Masquée'];
    $ratingColor2 = $rv->rating >= 4 ? '#D97706' : ($rv->rating <= 2 ? '#EF4444' : '#6B7280');
@endphp
<div class="panel-overlay" wire:click="closeView"></div>
<div class="panel-drawer">
    <div class="panel-head">
        <h2>Détail de l'évaluation</h2>
        <button class="panel-close" wire:click="closeView">✕</button>
    </div>
    <div class="panel-body">

        {{-- Rating hero --}}
        <div style="background:#FFFBEB;border-radius:12px;padding:20px;text-align:center;margin-bottom:20px;border:1.5px solid #FDE68A">
            <div style="font-size:36px;color:{{ $ratingColor2 }};letter-spacing:4px;margin-bottom:6px">
                {{ str_repeat('★',$rv->rating).str_repeat('☆',5-$rv->rating) }}
            </div>
            <div style="font-size:24px;font-weight:700;color:{{ $ratingColor2 }}">{{ $rv->rating }} / 5</div>
            @if($rv->tags && count($rv->tags))
            <div style="margin-top:10px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
                @foreach($rv->tags as $tag)
                <span class="badge" style="background:#FEF3C7;color:#92400E">{{ $tag }}</span>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Participants --}}
        <div class="panel-section">
            <div class="panel-section__title">Participants</div>
            <div class="panel-card" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div style="text-align:center">
                    <div style="width:44px;height:44px;border-radius:50%;background:{{ $rrBg2 }};color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;margin:0 auto 6px">{{ $rrInit2 }}</div>
                    <div style="font-size:12px;font-weight:600;color:#111827">{{ $rrName2 }}</div>
                    <div style="font-size:10px;color:#9CA3AF">Auteur</div>
                </div>
                <div style="text-align:center">
                    <div style="width:44px;height:44px;border-radius:50%;background:{{ $reBg2 }};color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;margin:0 auto 6px">{{ $reInit2 }}</div>
                    <div style="font-size:12px;font-weight:600;color:#111827">{{ $reName2 }}</div>
                    <div style="font-size:10px;color:#9CA3AF">Évalué</div>
                </div>
            </div>
        </div>

        {{-- Commentaire --}}
        @if($rv->comment)
        <div class="panel-section">
            <div class="panel-section__title">Commentaire</div>
            <div class="panel-card" style="font-size:14px;color:#374151;line-height:1.7;font-style:italic">
                "{{ $rv->comment }}"
            </div>
        </div>
        @endif

        {{-- Réponse conducteur --}}
        @if($rv->driver_reply)
        <div class="panel-section">
            <div class="panel-section__title">Réponse du conducteur</div>
            <div class="panel-card" style="font-size:13px;color:#374151;line-height:1.6;border-left:3px solid #1A5FB4;padding-left:14px">
                {{ $rv->driver_reply }}
            </div>
        </div>
        @endif

        {{-- Infos --}}
        <div class="panel-section">
            <div class="panel-section__title">Informations</div>
            <div class="panel-card">
                <div class="info-row">
                    <div class="info-label">Statut</div>
                    <div class="info-value">{{ $stLabels2[$rv->status ?? 'pending'] ?? $rv->status }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Date</div>
                    <div class="info-value">{{ $rv->created_at?->format('d/m/Y à H:i') }}</div>
                </div>
                @if($rv->report_count > 0)
                <div class="info-row">
                    <div class="info-label">Signalements</div>
                    <div class="info-value" style="color:#EF4444;font-weight:700">🚩 {{ $rv->report_count }} signalement(s)</div>
                </div>
                @endif
                @if($rv->trip)
                <div class="info-row">
                    <div class="info-label">Trajet</div>
                    <div class="info-value">{{ $rv->trip->departure_city }} → {{ $rv->trip->arrival_city }}</div>
                </div>
                @endif
            </div>
        </div>

    </div>
    <div class="panel-actions">
        @if($rv->status !== 'approved')
        <button class="btn-panel btn-panel-success" wire:click="approve({{ $rv->id }})">✓ Approuver</button>
        @endif
        @if($rv->status !== 'hidden')
        <button class="btn-panel btn-panel-warn" wire:click="hide({{ $rv->id }})">🙈 Masquer</button>
        @endif
        <button class="btn-panel btn-panel-danger" wire:click="delete({{ $rv->id }})"
                wire:confirm="Supprimer définitivement cet avis ?">Supprimer</button>
        <button class="btn-panel btn-panel-secondary" wire:click="closeView">Fermer</button>
    </div>
</div>
@endif
</div>
