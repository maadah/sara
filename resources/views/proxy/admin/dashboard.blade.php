@extends('proxy.admin.layout')
@section('title', 'Proxy Admin — Platforms')

@section('content')
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
    <h2 style="font-size: 1.3rem; color: #f1f5f9;">External Platforms</h2>
    <a href="{{ route('proxy.admin.create') }}" class="btn btn-primary">+ Add Platform</a>
</div>

<div class="stat-grid">
    <div class="stat">
        <div class="label">Platforms</div>
        <div class="value">{{ $platforms->count() }}</div>
    </div>
    <div class="stat">
        <div class="label">Total Linked Accounts</div>
        <div class="value">{{ $totalAccounts }}</div>
    </div>
    <div class="stat">
        <div class="label">Active Platforms</div>
        <div class="value">{{ $platforms->where('is_active', true)->count() }}</div>
    </div>
</div>

@if($platforms->isEmpty())
    <div class="card">
        <div class="empty-state">
            <h3>No platforms yet</h3>
            <p>Add your first external platform to start proxying Facebook OAuth.</p>
        </div>
    </div>
@else
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Platform</th>
                    <th>Domain</th>
                    <th>Status</th>
                    <th>Accounts</th>
                    <th>API Key</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($platforms as $p)
                <tr>
                    <td style="font-weight: 600;">{{ $p->name }}</td>
                    <td class="mono">{{ $p->domain }}</td>
                    <td>
                        @if($p->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Disabled</span>
                        @endif
                    </td>
                    <td>{{ $p->social_accounts_count }}</td>
                    <td class="mono">{{ substr($p->api_key, 0, 12) }}…</td>
                    <td>
                        <a href="{{ route('proxy.admin.show', $p) }}" class="btn btn-secondary btn-sm">View</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if($recentLogs->isNotEmpty())
<div class="card">
    <h2>Recent API Activity</h2>
    <table>
        <thead>
            <tr><th>Time</th><th>Platform</th><th>Action</th><th>Page</th><th>Status</th></tr>
        </thead>
        <tbody>
            @foreach($recentLogs as $log)
            <tr>
                <td style="font-size: 0.8em; color: #94a3b8;">{{ $log->created_at }}</td>
                <td>{{ $log->platform->name ?? '—' }}</td>
                <td class="mono">{{ $log->action }}</td>
                <td class="mono">{{ $log->provider_id ?? '—' }}</td>
                <td>
                    <span class="badge {{ $log->status === 'success' ? 'badge-green' : 'badge-red' }}">{{ $log->status }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
