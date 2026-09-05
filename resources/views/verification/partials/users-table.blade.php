{{-- Registered Users table + pagination. Rendered server-side both on the
     initial page load and via AJAX from VerificationController@usersData, so
     search/date-range filtering never duplicates this markup. --}}
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
                            <button type="button" class="document-view-button" data-verification-id="{{ $uv->id }}" title="View documents" aria-label="View documents for {{ $registeredUser->name ?: 'user' }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c-5.5 0-9.5 5.2-9.7 5.4a2.5 2.5 0 0 0 0 3.2C2.5 13.8 6.5 19 12 19s9.5-5.2 9.7-5.4a2.5 2.5 0 0 0 0-3.2C21.5 10.2 17.5 5 12 5Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                            </button>
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
    <div style="padding:16px 20px;" class="verification-users-pagination">
        {{ $users->appends(request()->query())->links() }}
    </div>
@endif
