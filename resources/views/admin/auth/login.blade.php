<div style="min-height:100dvh; display:flex; flex-direction:column; background:var(--color-bg);">

    {{-- Contenu principal centré --}}
    <div style="flex:1; display:flex; align-items:center; justify-content:center; padding:24px 16px;">
        <div style="width:100%; max-width:448px; display:flex; flex-direction:column; gap:24px;">

            {{-- Card formulaire --}}
            <form wire:submit.prevent="login"
                  style="background:var(--color-surface); box-shadow:var(--shadow-card); border-radius:24px;
                         outline:1px solid var(--color-border-faint); outline-offset:-1px;
                         padding:32px; display:flex; flex-direction:column; gap:32px;">

                {{-- Header --}}
                <div style="display:flex; flex-direction:column; align-items:center; gap:8px;">
                    {{-- Logo --}}
                    <div style="width:64px; height:64px; background:var(--color-primary); box-shadow:var(--shadow-logo);
                                border-radius:16px; display:flex; align-items:center; justify-content:center; margin-bottom:8px;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                    </div>
                    <h1 style="color:var(--color-text); font-size:30px; font-weight:700; line-height:36px; text-align:center;">
                        Connexion<br>Administrateur
                    </h1>
                    <p style="color:var(--color-text-secondary); font-size:16px; font-weight:400; line-height:24px; text-align:center;">
                        Accédez au centre de contrôle sécurisé MINIZON
                    </p>
                </div>

                {{-- Champs --}}
                <div style="display:flex; flex-direction:column; gap:24px;">

                    {{-- Email --}}
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label for="email"
                               style="color:#374151; font-size:14px; font-weight:500; line-height:20px;">
                            Email administrateur
                        </label>
                        <div style="position:relative;">
                            <span style="position:absolute; left:14px; top:50%; transform:translateY(-50%); display:flex; align-items:center; pointer-events:none;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                            </span>
                            <input id="email"
                                   type="email"
                                   wire:model="email"
                                   autocomplete="email"
                                   placeholder="Adresse email"
                                   style="width:100%; padding:12px 14px 12px 42px; border:1px solid var(--color-border);
                                          border-radius:8px; font-size:16px; font-family:inherit; color:var(--color-text);
                                          background:var(--color-surface); outline:none; transition:border-color .2s;"
                                   onfocus="this.style.borderColor='var(--color-primary)'"
                                   onblur="this.style.borderColor='var(--color-border)'">
                        </div>
                        @error('email')
                            <p style="color:var(--color-error); font-size:13px; margin-top:2px;">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label for="password"
                               style="color:#374151; font-size:14px; font-weight:500; line-height:20px;">
                            Mot de passe sécurisé
                        </label>
                        <div style="position:relative;">
                            <span style="position:absolute; left:14px; top:50%; transform:translateY(-50%); display:flex; align-items:center; pointer-events:none;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </span>
                            <input id="password"
                                   type="password"
                                   wire:model="password"
                                   autocomplete="current-password"
                                   placeholder="Mot de passe"
                                   style="width:100%; padding:12px 42px 12px 42px; border:1px solid var(--color-border);
                                          border-radius:8px; font-size:16px; font-family:inherit; color:var(--color-text);
                                          background:var(--color-surface); outline:none; transition:border-color .2s;"
                                   onfocus="this.style.borderColor='var(--color-primary)'"
                                   onblur="this.style.borderColor='var(--color-border)'">
                            <button type="button"
                                    style="position:absolute; right:14px; top:50%; transform:translateY(-50%);
                                           background:none; border:none; padding:0; cursor:pointer; display:flex;"
                                    onclick="const i=document.getElementById('password'); i.type=i.type==='password'?'text':'password'; this.querySelector('svg').style.opacity=i.type==='text'?'1':'0.5';"
                                    aria-label="Afficher/masquer le mot de passe">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p style="color:var(--color-error); font-size:13px; margin-top:2px;">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Options --}}
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:14px; color:var(--color-text);">
                                <input type="checkbox" wire:model="remember"
                                       style="width:16px; height:16px; accent-color:var(--color-primary); cursor:pointer;">
                                Se souvenir de moi
                            </label>
                            <a href="#"
                               style="color:var(--color-primary); font-size:14px; font-weight:400; text-decoration:none;">
                                Mot de passe oublié ?
                            </a>
                        </div>
                    </div>

                    {{-- Erreur globale --}}
                    @if($errorMessage)
                        <p style="color:var(--color-error); font-size:14px; text-align:center;">{{ $errorMessage }}</p>
                    @endif

                    {{-- Bouton submit --}}
                    <button type="submit"
                            wire:loading.attr="disabled"
                            style="width:100%; padding:14px; background:var(--color-primary); color:white;
                                   border:none; border-radius:8px; font-size:16px; font-weight:600; font-family:inherit;
                                   cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;
                                   box-shadow:var(--shadow-button); transition:background .2s;"
                            onmouseover="this.style.background='var(--color-primary-dark)'"
                            onmouseout="this.style.background='var(--color-primary)'">
                        <span wire:loading.remove wire:target="login" style="display:flex; align-items:center; gap:6px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                                <polyline points="10 17 15 12 10 7"/>
                                <line x1="15" y1="12" x2="3" y2="12"/>
                            </svg>
                            Se connecter
                        </span>
                        <span wire:loading wire:target="login" style="display:none; align-items:center; gap:8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 style="animation: spin 0.8s linear infinite;">
                                <line x1="12" y1="2" x2="12" y2="6"/>
                                <line x1="12" y1="18" x2="12" y2="22"/>
                                <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/>
                                <line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/>
                                <line x1="2" y1="12" x2="6" y2="12"/>
                                <line x1="18" y1="12" x2="22" y2="12"/>
                                <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/>
                                <line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/>
                            </svg>
                            Connexion en cours…
                        </span>
                    </button>

                    {{-- Liens footer --}}
                    <div style="border-top:1px solid var(--color-border-faint); padding-top:12px;
                                display:flex; justify-content:center; gap:24px;">
                        <a href="#" style="display:flex; align-items:center; gap:4px; color:var(--color-text-secondary); font-size:14px; text-decoration:none;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 18v-6a9 9 0 0 1 18 0v6"/>
                                <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/>
                            </svg>
                            Support technique
                        </a>
                        <a href="#" style="display:flex; align-items:center; gap:4px; color:var(--color-text-secondary); font-size:14px; text-decoration:none;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                            Politique sécurité
                        </a>
                    </div>
                </div>
            </form>

            {{-- Badges sécurité --}}
            <div style="display:flex; justify-content:center; align-items:center; gap:24px; flex-wrap:wrap; padding:0 35px;">
                @foreach([['SSL Sécurisé'],['Protection données'],['Connexion chiffrée']] as $badge)
                    <div style="display:flex; align-items:center; gap:4px; color:var(--color-text-muted); font-size:12px;">
                        <span style="width:8px; height:8px; background:var(--color-success); border-radius:9999px; opacity:0.6; flex-shrink:0;"></span>
                        {{ $badge[0] }}
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <footer style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;
                   gap:8px; padding:16px 24px; border-top:1px solid var(--color-border-faint);">
        <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
            <span style="color:var(--color-text-muted); font-size:12px;">Version 1.0</span>
            <div style="display:flex; align-items:center; gap:4px; color:var(--color-text-muted); font-size:12px;">
                <span style="width:8px; height:8px; background:var(--color-success); border-radius:9999px; flex-shrink:0;"></span>
                Serveurs opérationnels
            </div>
            <span style="color:var(--color-text-muted); font-size:12px;">Disponibilité: 99.9%</span>
        </div>
        <div style="display:flex; align-items:center; gap:12px;">
            <span style="color:var(--color-text-muted); font-size:12px;">© 2024 MINIZON Platform</span>
            <a href="#" style="color:var(--color-primary); font-size:12px; text-decoration:none;">Support 24/7</a>
        </div>
    </footer>
</div>

<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
