<?php

namespace App\Livewire\Admin;

use App\Models\PlatformSetting;
use Livewire\Component;

class Settings extends Component
{
    // ── Commission ────────────────────────────────────────────────
    public string $commission_rate     = '10';
    public string $min_commission      = '0';
    public string $max_commission      = '0';
    public string $commission_type     = 'percentage'; // percentage | fixed

    // ── Paiement ──────────────────────────────────────────────────
    public string $payment_timeout_minutes = '15';
    public string $escrow_release_hours    = '24';
    public bool   $payment_enabled         = true;

    // ── Plateforme ────────────────────────────────────────────────
    public string $platform_name    = 'MINIZON';
    public string $support_phone    = '';
    public string $support_email    = '';
    public bool   $maintenance_mode = false;

    // ── Notifications ─────────────────────────────────────────────
    public bool   $sms_enabled      = true;
    public bool   $push_enabled     = true;
    public string $otp_expiry_min   = '5';

    // ── Limites ───────────────────────────────────────────────────
    public string $max_seats_per_booking  = '4';
    public string $max_active_trips_driver = '3';
    public string $penalty_threshold      = '5';

    public bool $saved = false;

    public function mount(): void
    {
        $defaults = [
            'commission_rate'          => '10',
            'min_commission'           => '0',
            'max_commission'           => '0',
            'commission_type'          => 'percentage',
            'payment_timeout_minutes'  => '15',
            'escrow_release_hours'     => '24',
            'payment_enabled'          => '1',
            'platform_name'            => 'MINIZON',
            'support_phone'            => '',
            'support_email'            => '',
            'maintenance_mode'         => '0',
            'sms_enabled'              => '1',
            'push_enabled'             => '1',
            'otp_expiry_min'           => '5',
            'max_seats_per_booking'    => '4',
            'max_active_trips_driver'  => '3',
            'penalty_threshold'        => '5',
        ];

        foreach ($defaults as $key => $default) {
            $value = PlatformSetting::get($key, $default);
            if (property_exists($this, $key)) {
                $this->$key = in_array($key, ['payment_enabled', 'maintenance_mode', 'sms_enabled', 'push_enabled'])
                    ? (bool)(int) $value
                    : (string) $value;
            }
        }
    }

    public function save(): void
    {
        $settings = [
            'commission_rate'          => ['value' => $this->commission_rate,          'group' => 'commission'],
            'min_commission'           => ['value' => $this->min_commission,           'group' => 'commission'],
            'max_commission'           => ['value' => $this->max_commission,           'group' => 'commission'],
            'commission_type'          => ['value' => $this->commission_type,          'group' => 'commission'],
            'payment_timeout_minutes'  => ['value' => $this->payment_timeout_minutes,  'group' => 'payment'],
            'escrow_release_hours'     => ['value' => $this->escrow_release_hours,     'group' => 'payment'],
            'payment_enabled'          => ['value' => $this->payment_enabled ? '1' : '0', 'group' => 'payment'],
            'platform_name'            => ['value' => $this->platform_name,            'group' => 'general'],
            'support_phone'            => ['value' => $this->support_phone,            'group' => 'general'],
            'support_email'            => ['value' => $this->support_email,            'group' => 'general'],
            'maintenance_mode'         => ['value' => $this->maintenance_mode ? '1' : '0', 'group' => 'general'],
            'sms_enabled'              => ['value' => $this->sms_enabled ? '1' : '0',  'group' => 'notifications'],
            'push_enabled'             => ['value' => $this->push_enabled ? '1' : '0', 'group' => 'notifications'],
            'otp_expiry_min'           => ['value' => $this->otp_expiry_min,           'group' => 'notifications'],
            'max_seats_per_booking'    => ['value' => $this->max_seats_per_booking,    'group' => 'limits'],
            'max_active_trips_driver'  => ['value' => $this->max_active_trips_driver,  'group' => 'limits'],
            'penalty_threshold'        => ['value' => $this->penalty_threshold,        'group' => 'limits'],
        ];

        foreach ($settings as $key => $data) {
            PlatformSetting::set($key, $data['value'], $data['group']);
        }

        $this->saved = true;
        $this->dispatch('settings-saved');
    }

    public function render()
    {
        return view('admin.settings')
            ->layout('admin.layouts.app', ['title' => 'Paramètres']);
    }
}
