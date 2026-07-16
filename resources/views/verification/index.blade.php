@extends('layouts.app')

@section('title', 'Verification')

@section('content')
<div class="md:p-6">

    {{-- Page Header --}}
    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px; font-weight:700; color:#fff; margin:0 0 4px 0;">Verification Management</h1>
        <p style="font-size:12px; color:#6b7280; margin:0;">{{ $verifications->count() }} pending reviews</p>
    </div>

    @if(session('success'))
        <div style="margin-bottom:20px; border:1px solid rgba(34,197,94,0.3); background:rgba(34,197,94,0.1); color:#4ade80; font-size:13px; padding:12px 16px; border-radius:8px;">
            {{ session('success') }}
        </div>
    @endif

    {{-- Stat Cards --}}
    <div class="grid md:grid-cols-3 gap-[15px]" style="margin-bottom:24px;">

        {{-- Card 1 --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:40px; height:40px; border-radius:50%; border:2px solid #f59e0b; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#f59e0b; font-size:16px;">◎</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">{{ $counts['pending'] }}</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Pending Review</div>
            </div>
        </div>

        {{-- Card 2 --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:40px; height:40px; border-radius:50%; border:2px solid #22c55e; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#22c55e; font-size:16px;">✓</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">{{ $counts['approvedToday'] }}</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Approved Today</div>
            </div>
        </div>

        {{-- Card 3 --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:40px; height:40px; border-radius:50%; border:2px solid #ef4444; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#ef4444; font-size:16px;">✕</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">{{ $counts['rejected'] }}</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Rejected</div>
            </div>
        </div>

    </div>

    {{-- Verification Queue --}}
    <div style="background:#000; border:1px solid #000; border-radius:12px; overflow:hidden;">

        {{-- Queue Header --}}
        <div style="padding:18px 20px; border-bottom:1px solid #1a1a1a;">
            <h2 style="font-size:15px; font-weight:600; color:#fff; margin:0;">Verification Queue</h2>
        </div>

        {{-- Scrollable Table --}}
        <div style="overflow-x:auto; -webkit-overflow-scrolling:touch;">
            <table style="width:100%; min-width:600px; border-collapse:collapse;">
                <tbody>

                    @forelse($verifications as $verification)
                        @php
                            $user = $verification->user;
                            $hasSelfie = (bool) $verification->face_id_image;
                            $hasId = (bool) ($verification->national_id_front_image && $verification->national_id_back_image);
                        @endphp
                        <tr style="border-bottom:1px solid #1a1a1a;">
                            <td style="padding:16px 20px; width:40px;">
                                <div style="width:40px; height:40px; border-radius:50%; background:{{ $user?->avatar_color ?? '#374151' }}; display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px; font-weight:600;">
                                    {{ $user?->initials ?? '?' }}
                                </div>
                            </td>
                            <td style="padding:16px 10px;">
                                <div style="font-size:13px; font-weight:600; color:#fff;">{{ $user?->name ?: $user?->display_name ?: 'Unknown user' }}</div>
                                <div style="font-size:11px; color:#6b7280; margin-top:2px;">
                                    {{ $hasId ? 'Govt ID' : 'No ID' }}{{ $hasSelfie ? ' + Selfie' : '' }}
                                    · Submitted {{ $verification->submitted_at?->format('d M Y, h:i A') ?? '—' }}
                                    @if($verification->submitted_at)
                                        <span style="color:#4b5563;">({{ $verification->submitted_at->diffForHumans() }})</span>
                                    @endif
                                </div>
                            </td>
                            <td style="padding:16px 10px; text-align:right; white-space:nowrap;">
                                <span style="background:rgba(234,179,8,0.15); color:#facc15; font-size:11px; padding:4px 12px; border-radius:999px; margin-right:8px;">Pending</span>
                                <a href="{{ route('verification.show', $verification) }}" style="background:rgba(220,19,28,0.15); color:#f87171; border:1px solid #DC131C; font-size:11px; padding:5px 14px; border-radius:6px; text-decoration:none; display:inline-block;">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="padding:32px 20px; text-align:center; color:#6b7280; font-size:13px;">No verifications waiting for review.</td>
                        </tr>
                    @endforelse

                </tbody>
            </table>
        </div>

    </div>

    {{-- Registered Users --}}
    <div style="background:#000; border:1px solid #000; border-radius:12px; overflow:hidden; margin-top:24px;">

        <div style="padding:18px 20px; border-bottom:1px solid #1a1a1a;">
            <h2 style="font-size:15px; font-weight:600; color:#fff; margin:0;">Registered Users</h2>
            <p style="font-size:12px; color:#6b7280; margin:4px 0 0 0;">{{ number_format($users->total()) }} total users</p>
        </div>

        <div style="overflow-x:auto; -webkit-overflow-scrolling:touch;">
            <table style="width:100%; min-width:750px; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="border-bottom:1px solid #1a1a1a; text-align:left;">
                        <th style="padding:12px 20px; font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#DC131C; font-weight:600;">User</th>
                        <th style="padding:12px 10px; font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#DC131C; font-weight:600;">Registered On</th>
                        <th style="padding:12px 10px; font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#DC131C; font-weight:600;">Level</th>
                        <th style="padding:12px 10px; font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#DC131C; font-weight:600;">KYC Status</th>
                        <th style="padding:12px 10px; font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#DC131C; font-weight:600;">Submitted</th>
                        <th style="padding:12px 20px; font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#DC131C; font-weight:600; text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>

                    @forelse($users as $registeredUser)
                        @php
                            $uv = $registeredUser->userVerification;
                            $kycColors = [
                                'not_started' => ['bg' => 'rgba(107,114,128,0.15)', 'text' => '#9ca3af'],
                                'pending' => ['bg' => 'rgba(234,179,8,0.15)', 'text' => '#facc15'],
                                'approved' => ['bg' => 'rgba(34,197,94,0.15)', 'text' => '#4ade80'],
                                'rejected' => ['bg' => 'rgba(239,68,68,0.15)', 'text' => '#f87171'],
                            ];
                            $kycColor = $kycColors[$registeredUser->kyc_status] ?? $kycColors['not_started'];
                        @endphp
                        <tr style="border-bottom:1px solid #1a1a1a;">
                            <td style="padding:14px 20px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:32px; height:32px; border-radius:50%; background:{{ $registeredUser->avatar_color }}; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">{{ $registeredUser->initials }}</div>
                                    <div>
                                        <div style="color:#fff; font-weight:600;">{{ $registeredUser->name ?: $registeredUser->display_name ?: 'Unnamed User' }}</div>
                                        <div style="color:#6b7280; font-size:11px;">{{ $registeredUser->email ?: $registeredUser->phone ?: '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding:14px 10px; color:#fff;">{{ $registeredUser->created_at?->format('d M Y') ?? '—' }}</td>
                            <td style="padding:14px 10px;">
                                <span style="background:{{ $registeredUser->verification_color }}26; color:{{ $registeredUser->verification_color }}; font-size:11px; padding:3px 10px; border-radius:999px;">{{ $registeredUser->verification_level_label }}</span>
                            </td>
                            <td style="padding:14px 10px;">
                                <span style="background:{{ $kycColor['bg'] }}; color:{{ $kycColor['text'] }}; font-size:11px; padding:3px 10px; border-radius:999px;">{{ ucfirst(str_replace('_', ' ', $registeredUser->kyc_status ?? 'not_started')) }}</span>
                            </td>
                            <td style="padding:14px 10px; color:#9ca3af;">
                                @if($uv?->submitted_at)
                                    <div style="color:#fff;">{{ $uv->submitted_at->format('d M Y, h:i A') }}</div>
                                    <div style="font-size:11px; color:#6b7280;">{{ $uv->submitted_at->diffForHumans() }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td style="padding:14px 20px; text-align:right;">
                                @if($uv)
                                    <a href="{{ route('verification.show', $uv) }}" style="background:rgba(220,19,28,0.15); color:#f87171; border:1px solid #DC131C; font-size:11px; padding:5px 14px; border-radius:6px; text-decoration:none; display:inline-block;">View</a>
                                @else
                                    <span style="color:#4b5563; font-size:11px;">Not submitted</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:32px 20px; text-align:center; color:#6b7280; font-size:13px;">No registered users found.</td>
                        </tr>
                    @endforelse

                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div style="padding:16px 20px;">
                {{ $users->links() }}
            </div>
        @endif

    </div>

</div>

{{-- Responsive: stack stat cards on mobile --}}
<style>
    @media (max-width: 640px) {
        .stat-grid { grid-template-columns: 1fr !important; }
    }
</style>

@endsection
