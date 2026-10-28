@if($showConfirm)
@php
    $variantMap = [
        'danger'  => ['bg' => '#FEE2E2', 'icon_bg' => '#FCA5A5', 'icon' => '⚠️', 'btn_bg' => '#EF4444', 'btn_hover' => '#DC2626'],
        'warning' => ['bg' => '#FEF3C7', 'icon_bg' => '#FCD34D', 'icon' => '⚠️', 'btn_bg' => '#F59E0B', 'btn_hover' => '#D97706'],
        'primary' => ['bg' => '#EFF6FF', 'icon_bg' => '#BFDBFE', 'icon' => 'ℹ️', 'btn_bg' => '#1A5FB4', 'btn_hover' => '#1E40AF'],
    ];
    $vc = $variantMap[$confirmVariant] ?? $variantMap['primary'];
@endphp
<div style="position:fixed;inset:0;z-index:2000;display:flex;align-items:center;justify-content:center;padding:16px">
    {{-- Backdrop --}}
    <div style="position:absolute;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(3px)"
         wire:click="cancelConfirm"></div>

    {{-- Card --}}
    <div style="position:relative;z-index:1;background:#fff;border-radius:16px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden"
         wire:ignore.self>

        {{-- Bande couleur en haut --}}
        <div style="height:4px;background:{{ $vc['btn_bg'] }}"></div>

        <div style="padding:28px 28px 24px">

            {{-- Icône --}}
            <div style="width:52px;height:52px;border-radius:14px;background:{{ $vc['bg'] }};display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:18px">
                {{ $vc['icon'] }}
            </div>

            {{-- Titre --}}
            <div style="font-size:17px;font-weight:700;color:#111827;margin-bottom:8px">
                {{ $confirmTitle }}
            </div>

            {{-- Message --}}
            <div style="font-size:13px;color:#6B7280;line-height:1.6;margin-bottom:24px">
                {{ $confirmMessage }}
            </div>

            {{-- Boutons --}}
            <div style="display:flex;gap:10px">
                <button wire:click="cancelConfirm"
                        style="flex:1;padding:11px;background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;font-weight:600;color:#374151;cursor:pointer;transition:background .15s"
                        onmouseover="this.style.background='#F3F4F6'" onmouseout="this.style.background='#F9FAFB'">
                    Annuler
                </button>
                <button wire:click="executeConfirmed"
                        style="flex:1;padding:11px;background:{{ $vc['btn_bg'] }};border:none;border-radius:10px;font-size:13px;font-weight:600;color:#fff;cursor:pointer;transition:background .15s"
                        onmouseover="this.style.background='{{ $vc['btn_hover'] }}'" onmouseout="this.style.background='{{ $vc['btn_bg'] }}'">
                    Confirmer
                </button>
            </div>
        </div>
    </div>
</div>
@endif
