@extends('proxy.admin.layout')
@section('title', 'Edit: ' . $platform->name)

@section('content')
<div style="max-width: 600px;">
    <h2 style="font-size: 1.3rem; color: #f1f5f9; margin-bottom: 20px;">Edit — {{ $platform->name }}</h2>

    <div class="card">
        <form method="POST" action="{{ route('proxy.admin.update', $platform) }}">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="name">Platform Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $platform->name) }}" required>
                @error('name') <small style="color: #f87171;">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label for="domain">Domain</label>
                <input type="text" name="domain" id="domain" value="{{ old('domain', $platform->domain) }}" required>
                @error('domain') <small style="color: #f87171;">{{ $message }}</small> @enderror
            </div>

            <div id="same-server-notice" style="display:none; background:#164e63; border:1px solid #0e7490; border-radius:8px; padding:12px 16px; margin-bottom:16px; color:#67e8f9; font-size:0.9rem;">
                ✓ Same server detected — webhook forwarding and OAuth callback are not needed.
            </div>

            <div id="external-urls" style="transition: opacity 0.2s;">
                <div class="form-group">
                    <label for="webhook_url">Webhook URL <span style="color:#9ca3af;font-size:0.85em;">(optional)</span></label>
                    <input type="url" name="webhook_url" id="webhook_url" value="{{ old('webhook_url', $platform->webhook_url) }}">
                    @error('webhook_url') <small style="color: #f87171;">{{ $message }}</small> @enderror
                </div>

                <div class="form-group">
                    <label for="oauth_callback_url">OAuth Callback URL <span style="color:#9ca3af;font-size:0.85em;">(optional)</span></label>
                    <input type="url" name="oauth_callback_url" id="oauth_callback_url" value="{{ old('oauth_callback_url', $platform->oauth_callback_url) }}">
                    @error('oauth_callback_url') <small style="color: #f87171;">{{ $message }}</small> @enderror
                </div>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('proxy.admin.show', $platform) }}" class="btn btn-secondary">Cancel</a>
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
