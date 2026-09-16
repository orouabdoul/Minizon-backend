<div>
<style>
.set-wrap{padding:28px 32px;background:#F2F4F7;min-height:100vh;max-width:900px}
.set-header{margin-bottom:24px}
.set-header h1{font-size:22px;font-weight:700;color:#111827;margin:0 0 4px}
.set-header p{font-size:13px;color:#6B7280;margin:0}

.success-banner{background:#D1FAE5;border:1.5px solid #6EE7B7;border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:8px;font-size:13px;color:#065F46;font-weight:500}

.set-section{background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
.set-section-title{display:flex;align-items:center;gap:10px;font-size:15px;font-weight:700;color:#111827;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid #F3F4F6}
.set-section-title .icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}

.set-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.set-field{display:flex;flex-direction:column;gap:5px}
.set-field.full{grid-column:span 2}
.set-label{font-size:12px;font-weight:600;color:#374151}
.set-hint{font-size:11px;color:#9CA3AF;margin-top:2px}
.set-input{padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;transition:border-color .15s;width:100%;box-sizing:border-box;background:#fff}
.set-input:focus{border-color:#1A5FB4}
.set-select{padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#374151;outline:none;background:#fff;width:100%;cursor:pointer}
.set-select:focus{border-color:#1A5FB4}

.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #F9FAFB}
.toggle-row:last-child{border-bottom:none}
.toggle-info{flex:1}
.toggle-label{font-size:13px;font-weight:600;color:#374151}
.toggle-desc{font-size:11px;color:#9CA3AF;margin-top:2px}
.toggle-switch{position:relative;width:42px;height:24px;flex-shrink:0}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:#D1D5DB;border-radius:12px;cursor:pointer;transition:.25s}
.toggle-slider:before{content:'';position:absolute;width:18px;height:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.25s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
input:checked+.toggle-slider{background:#1A5FB4}
input:checked+.toggle-slider:before{transform:translateX(18px)}

.save-bar{position:sticky;bottom:0;background:#fff;border-top:1px solid #E5E7EB;padding:16px 32px;display:flex;align-items:center;justify-content:flex-end;gap:12px;margin:-28px -32px 0;box-shadow:0 -4px 16px rgba(0,0,0,.06)}
.btn-save{padding:11px 28px;background:#1A5FB4;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;transition:.15s}
.btn-save:hover{background:#1550A0}
.btn-save:active{transform:scale(.98)}
</style>

<div class="set-wrap">

    <div class="set-header">
        <h1>Paramètres de la plateforme</h1>
        <p>Configuration globale de MINIZON</p>
    </div>

    @if($saved)
    <div class="success-banner">
        ✅ Paramètres enregistrés avec succès.
    </div>
    @endif

    {{-- Général --}}
    <div class="set-section">
        <div class="set-section-title">
            <div class="icon" style="background:#EFF6FF">⚙️</div>
            Informations générales
        </div>
        <div class="set-grid">
            <div class="set-field">
                <label class="set-label">Nom de la plateforme</label>
                <input class="set-input" type="text" wire:model="platform_name">
            </div>
            <div class="set-field">
                <label class="set-label">Téléphone support</label>
                <input class="set-input" type="tel" wire:model="support_phone" placeholder="+229 …">
            </div>
            <div class="set-field full">
                <label class="set-label">Email support</label>
                <input class="set-input" type="email" wire:model="support_email" placeholder="support@…">
            </div>
        </div>
        <div style="margin-top:16px">
            <div class="toggle-row">
                <div class="toggle-info">
                    <div class="toggle-label">🔧 Mode maintenance</div>
                    <div class="toggle-desc">Si activé, les utilisateurs voient une page de maintenance. Les admins peuvent toujours se connecter.</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" wire:model="maintenance_mode">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>
    </div>

    {{-- Commission --}}
    <div class="set-section">
        <div class="set-section-title">
            <div class="icon" style="background:#D1FAE5">💰</div>
            Commission & Tarification
        </div>
        <div class="set-grid">
            <div class="set-field">
                <label class="set-label">Type de commission</label>
                <select class="set-select" wire:model="commission_type">
                    <option value="percentage">Pourcentage (%)</option>
                    <option value="fixed">Montant fixe (F)</option>
                </select>
            </div>
            <div class="set-field">
                <label class="set-label">Taux de commission {{ $commission_type === 'percentage' ? '(%)' : '(F)' }}</label>
                <input class="set-input" type="number" min="0" max="100" step="0.1" wire:model="commission_rate">
                <span class="set-hint">{{ $commission_type === 'percentage' ? 'Ex: 10 = 10% du montant de la course' : 'Montant fixe par réservation' }}</span>
            </div>
            @if($commission_type === 'percentage')
            <div class="set-field">
                <label class="set-label">Commission minimale (F)</label>
                <input class="set-input" type="number" min="0" wire:model="min_commission">
                <span class="set-hint">0 = pas de minimum</span>
            </div>
            <div class="set-field">
                <label class="set-label">Commission maximale (F)</label>
                <input class="set-input" type="number" min="0" wire:model="max_commission">
                <span class="set-hint">0 = pas de maximum</span>
            </div>
            @endif
        </div>
    </div>

    {{-- Paiement --}}
    <div class="set-section">
        <div class="set-section-title">
            <div class="icon" style="background:#EDE9FE">💳</div>
            Paiements & Escrow
        </div>
        <div class="set-grid">
            <div class="set-field">
                <label class="set-label">Délai de paiement (minutes)</label>
                <input class="set-input" type="number" min="5" max="60" wire:model="payment_timeout_minutes">
                <span class="set-hint">Temps maximal pour compléter un paiement</span>
            </div>
            <div class="set-field">
                <label class="set-label">Délai libération escrow (heures)</label>
                <input class="set-input" type="number" min="1" max="168" wire:model="escrow_release_hours">
                <span class="set-hint">Délai avant libération automatique des fonds</span>
            </div>
        </div>
        <div style="margin-top:16px">
            <div class="toggle-row">
                <div class="toggle-info">
                    <div class="toggle-label">💳 Paiements activés</div>
                    <div class="toggle-desc">Si désactivé, aucun nouveau paiement ne peut être initié.</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" wire:model="payment_enabled">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>
    </div>

    {{-- Notifications --}}
    <div class="set-section">
        <div class="set-section-title">
            <div class="icon" style="background:#FEF3C7">🔔</div>
            Notifications
        </div>
        <div class="set-grid">
            <div class="set-field">
                <label class="set-label">Expiration OTP (minutes)</label>
                <input class="set-input" type="number" min="2" max="30" wire:model="otp_expiry_min">
            </div>
        </div>
        <div style="margin-top:16px">
            <div class="toggle-row">
                <div class="toggle-info">
                    <div class="toggle-label">📱 SMS activés</div>
                    <div class="toggle-desc">Envoi de SMS (OTP, notifications) aux utilisateurs.</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" wire:model="sms_enabled">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="toggle-row">
                <div class="toggle-info">
                    <div class="toggle-label">🔔 Push notifications activées</div>
                    <div class="toggle-desc">Notifications push sur l'application mobile.</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" wire:model="push_enabled">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>
    </div>

    {{-- Limites --}}
    <div class="set-section">
        <div class="set-section-title">
            <div class="icon" style="background:#FEE2E2">🚦</div>
            Limites & Règles
        </div>
        <div class="set-grid">
            <div class="set-field">
                <label class="set-label">Places max par réservation</label>
                <input class="set-input" type="number" min="1" max="20" wire:model="max_seats_per_booking">
            </div>
            <div class="set-field">
                <label class="set-label">Trajets actifs max par conducteur</label>
                <input class="set-input" type="number" min="1" max="10" wire:model="max_active_trips_driver">
            </div>
            <div class="set-field">
                <label class="set-label">Seuil de pénalités (suspension)</label>
                <input class="set-input" type="number" min="1" wire:model="penalty_threshold">
                <span class="set-hint">Points de pénalité avant suspension automatique</span>
            </div>
        </div>
    </div>

    {{-- Save bar --}}
    <div class="save-bar">
        <button class="btn-save" wire:click="save" wire:loading.attr="disabled">
            <span wire:loading.remove>Enregistrer les paramètres</span>
            <span wire:loading>Enregistrement…</span>
        </button>
    </div>

</div>
</div>
