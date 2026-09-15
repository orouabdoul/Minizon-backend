<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public string $email    = '';
    public string $password = '';
    public bool   $remember = false;

    protected $rules = [
        'email'    => 'required|email',
        'password' => 'required|min:6',
    ];

    protected $messages = [
        'email.required'    => 'L\'email est obligatoire.',
        'email.email'       => 'Adresse email invalide.',
        'password.required' => 'Le mot de passe est obligatoire.',
        'password.min'      => 'Le mot de passe doit contenir au moins 6 caractères.',
    ];

    public string $errorMessage = '';

    public function login()
    {
        $this->validate();
        $this->errorMessage = '';

        $credentials = [
            'email'    => $this->email,
            'password' => $this->password,
        ];

        if (Auth::guard('admin')->attempt($credentials, $this->remember)) {
            session()->regenerate();
            return redirect()->route('panel.dashboard');
        }

        $this->errorMessage = 'Identifiants incorrects. Veuillez réessayer.';
    }

    public function render()
    {
        return view('admin.auth.login')
            ->layout('admin.layouts.guest');
    }
}
