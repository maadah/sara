@extends('proxy.admin.layout')
@section('title', $platform->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2 style="font-size: 1.3rem; color: #f1f5f9; margin: 0;">{{ $platform->name }}</h2>
    <div style="display: flex; gap: 8px;">
        <a href="{{ route('proxy.admin.edit', $platform) }}" class="btn btn-primary" style="font-size: .85rem; padding: 6px 14px;">Edit</a>

        <form method="POST" action="{{ route('proxy.admin.toggle', $platform) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn {{ $platform->is_active ? 'btn-secondary' : 'btn-primary' }}" style="font-size: .85rem; padding: 6px 14px;">
                {{ $platform->is_active ? 'Deactivate' : 'Activate' }}
            </button>
        </form>
    </div>
</div>

{{-- Status & Domain --}}
<div class="card" style="margin-bottom: 16px;">
    <table style="width: 100%; font-size: .9rem;">
        <tr>
            <td style="color: #94a3b8; padding: 6px 0; width: 180px;">Status</td>
            <td>
                <span style="display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: .8rem;
                    background: {{ $platform->is_active ? '#16a34a33' : '#dc262633' }};
                    color: {{ $platform->is_active ? '#4ade80' : '#f87171' }};">
                    {{ $platform->is_active ? 'Active' : 'Inactive' }}
                </span>
            </td>
        </tr>
        <tr><td style="color: #94a3b8; padding: 6px 0;">Domain</td><td>{{ $platform->domain }}</td></tr>
        <tr><td style="color: #94a3b8; padding: 6px 0;">Webhook URL</td><td style="word-break: break-all;">{{ $platform->webhook_url }}</td></tr>
        <tr><td style="color: #94a3b8; padding: 6px 0;">OAuth Callback</td><td style="word-break: break-all;">{{ $platform->oauth_callback_url }}</td></tr>
        <tr><td style="color: #94a3b8; padding: 6px 0;">Created</td><td>{{ $platform->created_at->format('Y-m-d H:i') }}</td></tr>
    </table>
</div>

{{-- API Credentials --}}
<div class="card" style="margin-bottom: 16px;">
    <h3 style="font-size: 1rem; color: #e2e8f0; margin-bottom: 14px;">API Credentials</h3>

    <div class="form-group" style="margin-bottom: 14px;">
        <label style="font-size: .8rem; color: #94a3b8;">API Key</label>
        <div style="display: flex; gap: 8px;">
            <input type="text" id="apiKey" value="{{ $platform->api_key }}" readonly
                   style="flex: 1; font-family: monospace; font-size: .85rem; background: #0f172a; border-color: #334155; color: #e2e8f0; padding: 8px 12px; border-radius: 6px;">
            <button onclick="navigator.clipboard.writeText(document.getElementById('apiKey').value)" class="btn btn-secondary" style="font-size: .8rem; padding: 6px 12px;">Copy</button>
        </div>
    </div>

    <div class="form-group" style="margin-bottom: 14px;">
        <label style="font-size: .8rem; color: #94a3b8;">API Secret</label>
        <div style="display: flex; gap: 8px;">
            <input type="text" id="apiSecret" value="{{ $platform->api_secret }}" readonly
                   style="flex: 1; font-family: monospace; font-size: .85rem; background: #0f172a; border-color: #334155; color: #e2e8f0; padding: 8px 12px; border-radius: 6px;">
            <button onclick="navigator.clipboard.writeText(document.getElementById('apiSecret').value)" class="btn btn-secondary" style="font-size: .8rem; padding: 6px 12px;">Copy</button>
        </div>
    </div>

    <form method="POST" action="{{ route('proxy.admin.regenerate', $platform) }}" onsubmit="return confirm('Regenerate API credentials? The old keys will stop working immediately.')">
        @csrf
        <button type="submit" class="btn btn-secondary" style="font-size: .8rem; padding: 6px 14px; border-color: #f87171; color: #f87171;">Regenerate Keys</button>
    </form>
</div>

{{-- OAuth Link --}}
<div class="card" style="margin-bottom: 16px;">
    <h3 style="font-size: 1rem; color: #e2e8f0; margin-bottom: 14px;">OAuth Start URL</h3>
    <p style="font-size: .85rem; color: #94a3b8; margin-bottom: 10px;">Redirect your users to this URL to start the Facebook OAuth flow:</p>
    @php $oauthUrl = url('/proxy/auth/start') . '?api_key=' . $platform->api_key . '&external_user_id=USER_ID_HERE'; @endphp
    <div style="display: flex; gap: 8px;">
        <input type="text" id="oauthUrl" value="{{ $oauthUrl }}" readonly
               style="flex: 1; font-family: monospace; font-size: .8rem; background: #0f172a; border-color: #334155; color: #e2e8f0; padding: 8px 12px; border-radius: 6px;">
        <button onclick="navigator.clipboard.writeText(document.getElementById('oauthUrl').value)" class="btn btn-secondary" style="font-size: .8rem; padding: 6px 12px;">Copy</button>
    </div>
    <p style="font-size: .8rem; color: #64748b; margin-top: 8px;">Replace <code style="color:#38bdf8;">USER_ID_HERE</code> with the user's ID on your platform.</p>
</div>

{{-- Linked Accounts --}}
<div class="card" style="margin-bottom: 16px;">
    <h3 style="font-size: 1rem; color: #e2e8f0; margin-bottom: 14px;">Linked Accounts ({{ $platform->socialAccounts->count() }})</h3>
    @if($platform->socialAccounts->isEmpty())
        <p style="color: #64748b; font-size: .9rem;">No accounts linked yet.</p>
    @else
        <table class="data-table" style="width: 100%;">
            <thead>
                <tr>
                    <th style="text-align: left; padding: 8px; color: #94a3b8; font-size: .8rem; border-bottom: 1px solid #334155;">Name</th>
                    <th style="text-align: left; padding: 8px; color: #94a3b8; font-size: .8rem; border-bottom: 1px solid #334155;">Provider ID</th>
                    <th style="text-align: left; padding: 8px; color: #94a3b8; font-size: .8rem; border-bottom: 1px solid #334155;">Ext. User</th>
                    <th style="text-align: left; padding: 8px; color: #94a3b8; font-size: .8rem; border-bottom: 1px solid #334155;">Linked</th>
                </tr>
            </thead>
            <tbody>
                @foreach($platform->socialAccounts as $acc)
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #1e293b;">{{ $acc->name ?? '—' }}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #1e293b; font-family: monospace; font-size: .8rem;">{{ $acc->provider_id }}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #1e293b;">{{ $acc->external_user_id }}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #1e293b; font-size: .85rem; color: #94a3b8;">{{ $acc->created_at->format('Y-m-d') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- Recent Logs --}}
<div class="card">
    <h3 style="font-size: 1rem; color: #e2e8f0; margin-bottom: 14px;">Recent API Activity</h3>
    @php $logs = $platform->apiLogs()->latest('id')->limit(20)->get(); @endphp
    @if($logs->isEmpty())
        <p style="color: #64748b; font-size: .9rem;">No activity yet.</p>
    @else
        <table class="data-table" style="width: 100%;">
            <thead>
                <tr>
                    <th style="text-align: left; padding: 8px; color: #94a3b8; font-size: .8rem; border-bottom: 1px solid #334155;">Action</th>
                    <th style="text-align: left; padding: 8px; color: #94a3b8; font-size: .8rem; border-bottom: 1px solid #334155;">Page</th>
                    <th style="text-align: left; padding: 8px; color: #94a3b8; font-size: .8rem; border-bottom: 1px solid #334155;">Status</th>
                    <th style="text-align: left; padding: 8px; color: #94a3b8; font-size: .8rem; border-bottom: 1px solid #334155;">Details</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #1e293b; font-size: .85rem;">{{ $log->action }}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #1e293b; font-family: monospace; font-size: .8rem;">{{ $log->provider_id ?? '—' }}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #1e293b;">
                        <span style="color: {{ $log->status === 'success' ? '#4ade80' : '#f87171' }};">{{ $log->status }}</span>
                    </td>
                    <td style="padding: 8px; border-bottom: 1px solid #1e293b; font-size: .8rem; color: #94a3b8; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ Str::limit($log->details, 60) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
