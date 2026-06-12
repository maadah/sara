<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxy Admin — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: #0f172a; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { background: #1e293b; border-radius: 16px; padding: 48px 40px; width: 100%; max-width: 400px; box-shadow: 0 25px 50px rgba(0,0,0,0.3); }
        .login-card h1 { color: #f1f5f9; font-size: 1.5rem; text-align: center; margin-bottom: 8px; }
        .login-card p { color: #94a3b8; text-align: center; font-size: 0.85em; margin-bottom: 28px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: #cbd5e1; font-size: 0.85em; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 12px 16px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #f1f5f9; font-family: inherit; font-size: 0.95em; }
        .form-group input:focus { outline: none; border-color: #3b82f6; }
        .btn { display: block; width: 100%; padding: 12px; background: #3b82f6; color: #fff; border: none; border-radius: 8px; font-family: inherit; font-size: 1em; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #2563eb; }
        .alert { background: #7f1d1d; color: #fecaca; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.85em; text-align: center; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>🔐 Proxy Admin</h1>
        <p>This area is restricted. Enter the access password to continue.</p>

        @if(session('error'))
            <div class="alert">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('proxy.admin.login.post') }}">
            @csrf
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" autofocus required>
            </div>
            <button type="submit" class="btn">Enter</button>
        </form>
    </div>
</body>
</html>
