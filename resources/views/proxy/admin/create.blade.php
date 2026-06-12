@extends('proxy.admin.layout')
@section('title', 'Add Platform')

@section('content')
<div style="max-width: 600px;">
    <h2 style="font-size: 1.3rem; color: #f1f5f9; margin-bottom: 20px;">Add External Platform</h2>

    <div class="card">
        <form method="POST" action="{{ route('proxy.admin.store') }}">
            @csrf
            <div class="form-group">
                <label for="name">Platform Name</label>
                <input type="text" name="name" id="name" placeholder="e.g. MyShop CRM" value="{{ old('name') }}" required>
                @error('name') <small style="color: #f87171;">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label for="domain">Domain</label>
                <input type="text" name="domain" id="domain" placeholder="e.g. app.myshop.com" value="{{ old('domain') }}" required>
                @error('domain') <small style="color: #f87171;">{{ $message }}</small> @enderror
            </div>

            <div id="same-server-notice" style="display:none; background:#164e63; border:1px solid #0e7490; border-radius:8px; padding:12px 16px; margin-bottom:16px; color:#67e8f9; font-size:0.9rem;">
                ✓ Same server detected — webhook forwarding and OAuth callback are not needed.
            </div>

            <div id="external-urls" style="transition: opacity 0.2s;">
                <div class="form-group">
                    <label for="webhook_url">Webhook URL <span style="color:#9ca3af;font-size:0.85em;">(optional — where we forward Facebook webhooks)</span></label>
                    <input type="url" name="webhook_url" id="webhook_url" placeholder="https://app.myshop.com/webhooks/meta" value="{{ old('webhook_url') }}">
                    @error('webhook_url') <small style="color: #f87171;">{{ $message }}</small> @enderror
                </div>

                <div class="form-group">
                    <label for="oauth_callback_url">OAuth Callback URL <span style="color:#9ca3af;font-size:0.85em;">(optional — where we redirect after login)</span></label>
                    <input type="url" name="oauth_callback_url" id="oauth_callback_url" placeholder="https://app.myshop.com/auth/proxy/callback" value="{{ old('oauth_callback_url') }}">
                    @error('oauth_callback_url') <small style="color: #f87171;">{{ $message }}</small> @enderror
                </div>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="submit" class="btn btn-primary">Create Platform</button>
                <a href="{{ route('proxy.admin.dashboard') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<script>
(function() {
    const appHost = @json(parse_url(config('app.url'), PHP_URL_HOST));
    const domainInput = document.getElementById('domain');
    const externalUrls = document.getElementById('external-urls');
    const notice = document.getElementById('same-server-notice');

    function check() {
        const val = domainInput.value.trim().toLowerCase().replace(/^https?:\/\//, '').replace(/\/.*$/, '');
        const isSame = val && appHost && val === appHost.toLowerCase();
        externalUrls.style.display = isSame ? 'none' : '';
        notice.style.display = isSame ? 'block' : 'none';
    }

    domainInput.addEventListener('input', check);
    check();
})();
</script>
@endsection
