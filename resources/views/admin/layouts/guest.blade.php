<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MINIZON Admin — Connexion</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @livewireStyles
    <style>
        :root {
            --color-primary:        #1A5FB4;
            --color-primary-dark:   #0F4A9E;
            --color-primary-light:  rgba(26, 95, 180, 0.10);
            --color-primary-glow:   rgba(26, 95, 180, 0.31);
            --color-primary-shadow: rgba(26, 95, 180, 0.45);
            --color-accent:         #FF7A45;
            --color-accent-dark:    #E65E2A;
            --color-success:        #17A398;
            --color-warning:        #F5A623;
            --color-error:          #E5484D;
            --color-bg:             #F2F4F7;
            --color-surface:        #FFFFFF;
            --color-text:           #1F2933;
            --color-text-secondary: #6B7684;
            --color-text-muted:     #9CA3AF;
            --color-border:         #D1D5DB;
            --color-border-light:   #E5E7EB;
            --color-border-faint:   #F3F4F6;
            --shadow-card:          0px 25px 50px rgba(0,0,0,0.25);
            --shadow-button:        0px 0px 27.45px rgba(26,95,180,0.45);
            --shadow-logo:          0px 0px 20.56px rgba(26,95,180,0.31);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--color-bg); color: var(--color-text); }
    </style>
</head>
<body>
    {{ $slot }}
    @livewireScripts
</body>
</html>
