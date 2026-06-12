<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Proxy Admin')</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
        .top-bar { background: #1e293b; border-bottom: 1px solid #334155; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; }
        .top-bar h1 { font-size: 1.1rem; color: #f1f5f9; display: flex; align-items: center; gap: 8px; }
        .top-bar nav { display: flex; gap: 16px; align-items: center; }
        .top-bar nav a { color: #94a3b8; text-decoration: none; font-size: 0.85em; padding: 6px 12px; border-radius: 6px; }
        .top-bar nav a:hover, .top-bar nav a.active { background: #334155; color: #f1f5f9; }
        .top-bar .btn-logout { color: #f87171; font-size: 0.82em; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
        .card h2 { font-size: 1.1rem; color: #f1f5f9; margin-bottom: 16px; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 16px 20px; }
        .stat .label { font-size: 0.78em; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat .value { font-size: 1.8rem; font-weight: 700; color: #f1f5f9; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.88em; }
        th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #334155; }
        th { color: #94a3b8; font-weight: 600; font-size: 0.78em; text-transform: uppercase; letter-spacing: 0.5px; }
        td { color: #e2e8f0; }
        .badge { display: inline-block; font-size: 0.75em; padding: 2px 10px; border-radius: 10px; font-weight: 600; }
        .badge-green { background: #065f46; color: #6ee7b7; }
        .badge-red { background: #7f1d1d; color: #fca5a5; }
        .badge-blue { background: #1e3a5f; color: #93c5fd; }
        .btn { display: inline-block; padding: 8px 16px; border-radius: 8px; font-size: 0.85em; font-weight: 600; text-decoration: none; border: none; cursor: pointer; font-family: inherit; }
        .btn-primary { background: #3b82f6; color: #fff; }
        .btn-primary:hover { background: #2563eb; }
        .btn-secondary { background: #334155; color: #e2e8f0; }
        .btn-secondary:hover { background: #475569; }
        .btn-danger { background: #991b1b; color: #fecaca; }
        .btn-danger:hover { background: #7f1d1d; }
        .btn-sm { padding: 5px 12px; font-size: 0.8em; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.82em; color: #94a3b8; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #f1f5f9; font-family: inherit; font-size: 0.9em; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #3b82f6; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.85em; }
        .alert-success { background: #065f46; color: #6ee7b7; }
        .alert-error { background: #7f1d1d; color: #fecaca; }
        .alert-warning { background: #78350f; color: #fde68a; }
        .mono { font-family: 'Courier New', monospace; font-size: 0.82em; color: #93c5fd; word-break: break-all; }
        .key-box { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 12px 16px; margin-top: 6px; position: relative; }
        .key-box code { color: #93c5fd; font-family: 'Courier New', monospace; font-size: 0.82em; word-break: break-all; }
        .copy-btn { position: absolute; top: 8px; right: 8px; background: #334155; border: none; color: #94a3b8; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.75em; }
        .copy-btn:hover { background: #475569; color: #f1f5f9; }
        .empty-state { text-align: center; padding: 48px 20px; color: #64748b; }
        .empty-state h3 { color: #94a3b8; margin-bottom: 8px; }
    </style>
    @yield('extra-style')
</head>
<body>
    <div class="top-bar">
        <h1>🔒 Proxy Admin</h1>
        <nav>
            <a href="{{ route('proxy.admin.dashboard') }}" class="{{ request()->routeIs('proxy.admin.dashboard') ? 'active' : '' }}">Platforms</a>
            <a href="{{ route('proxy.admin.docs') }}" class="{{ request()->routeIs('proxy.admin.docs') ? 'active' : '' }}">Documentation</a>
            <a href="{{ route('proxy.admin.logout') }}" class="btn-logout">Logout ←</a>
        </nav>
    </div>
    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @yield('content')
    </div>
    @yield('scripts')
</body>
</html>
