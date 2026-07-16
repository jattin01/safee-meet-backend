@extends('layouts.app')

@section('title', 'Review Verification')

@section('content')
<style>[x-cloak] { display: none !important; }</style>
<div class="md:p-6" x-data="{ showRejectForm: false }">

    {{-- Back link --}}
    <a href="{{ route('verification') }}" style="display:inline-flex; align-items:center; gap:6px; color:#6b7280; font-size:12px; text-decoration:none; margin-bottom:16px;">
        ← Back to queue
    </a>

    @if(session('success'))
        <div style="margin-bottom:20px; border:1px solid rgba(34,197,94,0.3); background:rgba(34,197,94,0.1); color:#4ade80; font-size:13px; padding:12px 16px; border-radius:8px;">
            {{ session('success') }}
        </div>
    @endif

    @php
        $statusColors = [
            'not_submitted' => ['bg' => 'rgba(107,114,128,0.15)', 'text' => '#9ca3af'],
            'pending' => ['bg' => 'rgba(234,179,8,0.15)', 'text' => '#facc15'],
            'approved' => ['bg' => 'rgba(34,197,94,0.15)', 'text' => '#4ade80'],
            'rejected' => ['bg' => 'rgba(239,68,68,0.15)', 'text' => '#f87171'],
        ];
        $statusColor = $statusColors[$verification->status] ?? ['bg' => 'rgba(107,114,128,0.15)', 'text' => '#9ca3af'];
        $isActionable = $verification->status === 'pending';
    @endphp

    {{-- User header --}}
    <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:14px;">
            <div style="width:48px; height:48px; border-radius:50%; background:{{ $verification->user?->avatar_color ?? '#374151' }}; display:flex; align-items:center; justify-content:center; color:#fff; font-size:14px; font-weight:600; flex-shrink:0;">
                {{ $verification->user?->initials ?? '?' }}
            </div>
            <div>
                <div style="font-size:16px; font-weight:700; color:#fff;">{{ $verification->user?->name ?: $verification->user?->display_name ?: 'Unknown user' }}</div>
                <div style="font-size:12px; color:#6b7280; margin-top:2px;">
                    Submitted {{ $verification->submitted_at?->format('d M Y, h:i A') ?? '—' }}
                    @if($verification->submitted_at)
                        <span style="color:#4b5563;">({{ $verification->submitted_at->diffForHumans() }})</span>
                    @endif
                </div>
            </div>
        </div>
        <span style="background:{{ $statusColor['bg'] }}; color:{{ $statusColor['text'] }}; font-size:12px; padding:6px 14px; border-radius:999px; font-weight:600;">{{ ucfirst(str_replace('_', ' ', $verification->status)) }}</span>
    </div>

    @if($verification->rejection_reason)
        <div style="margin-bottom:20px; border:1px solid rgba(239,68,68,0.3); background:rgba(239,68,68,0.1); color:#f87171; font-size:13px; padding:12px 16px; border-radius:8px;">
            <strong>Rejection reason:</strong> {{ $verification->rejection_reason }}
            @if($verification->reviewedByAdmin)
                <span style="color:#9ca3af;">— by {{ $verification->reviewedByAdmin->name }}</span>
            @endif
        </div>
    @endif

    @if($verification->status === 'approved' && $verification->reviewedByAdmin)
        <div style="margin-bottom:20px; border:1px solid rgba(34,197,94,0.3); background:rgba(34,197,94,0.1); color:#4ade80; font-size:13px; padding:12px 16px; border-radius:8px;">
            Approved by {{ $verification->reviewedByAdmin->name }} {{ $verification->approved_at?->diffForHumans() }}
        </div>
    @endif

    {{-- Documents --}}
    <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; margin-bottom:20px;">
        <h2 style="font-size:15px; font-weight:600; color:#fff; margin:0 0 4px 0;">Identity Document</h2>
        <p style="font-size:12px; color:#6b7280; margin:0 0 16px 0;">
            @if($verification->national_id_number)
                ID #{{ $verification->national_id_number }}
            @endif
            @if($verification->national_id_country)
                · Issued in {{ $verification->national_id_country }}
            @endif
        </p>

        <div class="grid md:grid-cols-3 gap-[15px]">
            {{-- Front --}}
            <div style="background:#1a1a1a; border-radius:10px; overflow:hidden;">
                <div style="padding:10px 14px; font-size:11px; color:#9ca3af; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">ID Front</div>
                @if($verification->national_id_front_image)
                    <a href="{{ asset('storage/'.$verification->national_id_front_image) }}" target="_blank">
                        <img src="{{ asset('storage/'.$verification->national_id_front_image) }}" style="width:100%; height:220px; object-fit:cover; display:block; cursor:zoom-in;" alt="ID front">
                    </a>
                @else
                    <div style="height:220px; display:flex; align-items:center; justify-content:center; color:#4b5563; font-size:12px;">Not uploaded</div>
                @endif
            </div>

            {{-- Back --}}
            <div style="background:#1a1a1a; border-radius:10px; overflow:hidden;">
                <div style="padding:10px 14px; font-size:11px; color:#9ca3af; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">ID Back</div>
                @if($verification->national_id_back_image)
                    <a href="{{ asset('storage/'.$verification->national_id_back_image) }}" target="_blank">
                        <img src="{{ asset('storage/'.$verification->national_id_back_image) }}" style="width:100%; height:220px; object-fit:cover; display:block; cursor:zoom-in;" alt="ID back">
                    </a>
                @else
                    <div style="height:220px; display:flex; align-items:center; justify-content:center; color:#4b5563; font-size:12px;">Not uploaded</div>
                @endif
            </div>

            {{-- Selfie --}}
            <div style="background:#1a1a1a; border-radius:10px; overflow:hidden;">
                <div style="padding:10px 14px; font-size:11px; color:#9ca3af; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Selfie</div>
                @if($verification->face_id_image)
                    <a href="{{ asset('storage/'.$verification->face_id_image) }}" target="_blank">
                        <img src="{{ asset('storage/'.$verification->face_id_image) }}" style="width:100%; height:220px; object-fit:cover; display:block; cursor:zoom-in;" alt="Selfie">
                    </a>
                @else
                    <div style="height:220px; display:flex; align-items:center; justify-content:center; color:#4b5563; font-size:12px;">Not uploaded</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Actions --}}
    @if($isActionable)
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <h2 style="font-size:15px; font-weight:600; color:#fff; margin:0 0 16px 0;">Decision</h2>

            <div style="display:flex; gap:10px; flex-wrap:wrap;" x-show="!showRejectForm">
                <form method="POST" action="{{ route('verification.approve', $verification) }}" onsubmit="return confirm('Approve this verification?');">
                    @csrf
                    <button type="submit" style="background:#22c55e; color:#000; border:none; font-size:13px; font-weight:600; padding:10px 24px; border-radius:8px; cursor:pointer;">Approve</button>
                </form>
                <button type="button" @click="showRejectForm = true" style="background:rgba(239,68,68,0.15); color:#f87171; border:1px solid #ef4444; font-size:13px; font-weight:600; padding:10px 24px; border-radius:8px; cursor:pointer;">Reject</button>
            </div>

            <div x-show="showRejectForm" x-cloak>
                <form method="POST" action="{{ route('verification.reject', $verification) }}">
                    @csrf
                    <label style="display:block; font-size:12px; color:#9ca3af; margin-bottom:8px;">Rejection reason (shown to the user)</label>
                    <textarea name="reason" required maxlength="1000" rows="3" style="width:100%; background:#1a1a1a; border:1px solid #374151; border-radius:8px; color:#fff; font-size:13px; padding:10px 12px; resize:vertical;" placeholder="e.g. Selfie does not match ID photo, document photo is blurry..."></textarea>
                    <div style="display:flex; gap:10px; margin-top:12px;">
                        <button type="submit" style="background:#ef4444; color:#fff; border:none; font-size:13px; font-weight:600; padding:10px 24px; border-radius:8px; cursor:pointer;">Confirm Rejection</button>
                        <button type="button" @click="showRejectForm = false" style="background:transparent; color:#9ca3af; border:1px solid #374151; font-size:13px; padding:10px 24px; border-radius:8px; cursor:pointer;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
@endsection
