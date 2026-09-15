<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>MINIZON Admin — Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #F2F4F7; color: #1F2933; min-height: 100vh; }
        .header { background: #1A5FB4; color: white; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 20px; font-weight: 700; }
        .logout-btn { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-family: inherit; font-size: 14px; }
        .content { padding: 32px; }
        .welcome { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .sub { color: #6B7684; font-size: 16px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>MINIZON Admin Platform</h1>
        <form method="POST" action="{{ route('panel.logout') }}">
            @csrf
            <button class="logout-btn">Déconnexion</button>
        </form>
    </div>
    <div class="content">
        <p class="welcome">Bienvenue 👋</p>
        <p class="sub">Le dashboard complet arrive bientôt.</p>
    </div>
</body>
</html>
