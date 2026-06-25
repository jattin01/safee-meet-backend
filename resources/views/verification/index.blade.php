@extends('layouts.app')

@section('title', 'Verification')

@section('content')
<div class="md:p-6">

    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px; font-weight:700; color:#fff; margin:0 0 4px 0;">Verification Management</h1>
        <p style="font-size:12px; color:#6b7280; margin:0;">
            {{ $verifications->count() }} active reviews in queue
        </p>
    </div>

    @if (session('success'))
        <div style="margin-bottom:16px; padding:12px 14px; border-radius:12px; background:rgba(34,197,94,0.12); border:1px solid rgba(34,197,94,0.35); color:#86efac; font-size:13px;">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid md:grid-cols-3 gap-[15px]" style="margin-bottom:24px;">
        <div style="background:#000; border:1px solid #111827; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:40px; height:40px; border-radius:50%; border:2px solid #22c55e; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#22c55e; font-size:16px;">✓</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">{{ $counts['pendingLevel1'] }}</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Pending Level 1 Reviews</div>
            </div>
        </div>

        <div style="background:#000; border:1px solid #111827; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:40px; height:40px; border-radius:50%; border:2px solid #3b82f6; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#3b82f6; font-size:16px;">◎</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">{{ $counts['pendingManualReview'] }}</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Manual Review Cases</div>
            </div>
        </div>

        <div style="background:#000; border:1px solid #111827; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:40px; height:40px; border-radius:50%; border:2px solid #f59e0b; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#f59e0b; font-size:16px;">★</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">{{ $counts['approvedToday'] }}</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Approved Today</div>
            </div>
        </div>
    </div>

    <div style="background:#000; border:1px solid #111827; border-radius:12px; overflow:hidden;">
        <div style="padding:18px 20px; border-bottom:1px solid #1a1a1a;">
            <h2 style="font-size:15px; font-weight:600; color:#fff; margin:0;">Verification Queue</h2>
        </div>

        @if ($verifications->isEmpty())
            <div style="padding:28px 20px; color:#9ca3af; font-size:13px;">
                No verification cases are waiting for review right now.
            </div>
        @else
            <div style="overflow-x:auto; -webkit-overflow-scrolling:touch;">
                <table style="width:100%; min-width:900px; border-collapse:collapse;">
                    <tbody>
                    @foreach ($verifications as $verification)
                        @php
                            $document = $verification->documents->sortByDesc('created_at')->first();
                            $selfie = $verification->selfieVerifications->sortByDesc('created_at')->first();
                            $user = $verification->user;
                        @endphp
                        <tr style="border-bottom:1px solid #1a1a1a; vertical-align:top;">
                            <td style="padding:16px 20px; width:56px;">
                                <div class="p-1" style="width:40px; height:40px; border-radius:50%; background:#374151; display:flex; align-items:center; justify-content:center;">
                                    <img src="{{ asset('images/user.png') }}" class="w-[25px] h-[25px]" alt="user">
                                </div>
                            </td>
                            <td style="padding:16px 10px; width:280px;">
                                <div style="font-size:13px; font-weight:600; color:#fff;">{{ $user?->display_name ?? 'SAFEE User' }}</div>
                                <div style="font-size:11px; color:#6b7280; margin-top:2px;">
                                    {{ strtoupper($document?->document_type ?? 'other') }} + Selfie
                                    @if ($verification->submitted_at)
                                        · Submitted {{ $verification->submitted_at->diffForHumans() }}
                                    @endif
                                </div>
                                <div style="font-size:11px; color:#6b7280; margin-top:6px;">
                                    SAFEE ID: {{ $user?->safee_id ?? '—' }}
                                </div>
                            </td>
                            <td style="padding:16px 10px; width:250px;">
                                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                    @if ($document?->front_file_url)
                                        <button
                                            type="button"
                                            class="preview-trigger"
                                            data-preview-url="{{ route('verification.files.show', ['verification' => $verification->id, 'asset' => 'front']) }}"
                                            data-preview-title="ID Front · {{ $user?->display_name ?? 'SAFEE User' }}"
                                            style="background:rgba(59,130,246,0.15); color:#93c5fd; border:1px solid rgba(59,130,246,0.45); font-size:11px; padding:5px 10px; border-radius:999px; cursor:pointer;"
                                        >
                                            View ID Front
                                        </button>
                                    @endif
                                    @if ($document?->back_file_url)
                                        <button
                                            type="button"
                                            class="preview-trigger"
                                            data-preview-url="{{ route('verification.files.show', ['verification' => $verification->id, 'asset' => 'back']) }}"
                                            data-preview-title="ID Back · {{ $user?->display_name ?? 'SAFEE User' }}"
                                            style="background:rgba(59,130,246,0.15); color:#93c5fd; border:1px solid rgba(59,130,246,0.45); font-size:11px; padding:5px 10px; border-radius:999px; cursor:pointer;"
                                        >
                                            View ID Back
                                        </button>
                                    @endif
                                    @if ($selfie?->selfie_file_url)
                                        <button
                                            type="button"
                                            class="preview-trigger"
                                            data-preview-url="{{ route('verification.files.show', ['verification' => $verification->id, 'asset' => 'selfie']) }}"
                                            data-preview-title="Selfie · {{ $user?->display_name ?? 'SAFEE User' }}"
                                            style="background:rgba(34,197,94,0.15); color:#86efac; border:1px solid rgba(34,197,94,0.45); font-size:11px; padding:5px 10px; border-radius:999px; cursor:pointer;"
                                        >
                                            View Selfie
                                        </button>
                                    @endif
                                </div>
                                <div style="font-size:11px; color:#6b7280; margin-top:8px;">
                                    Country: {{ $document?->issuing_country_code ?? 'ZZ' }}
                                </div>
                            </td>
                            <td style="padding:16px 10px; width:120px;">
                                <span style="background:rgba(34,197,94,0.15); color:#4ade80; font-size:11px; padding:4px 12px; border-radius:999px;">
                                    {{ strtoupper($verification->status) }}
                                </span>
                            </td>
                            <td style="padding:16px 10px; width:240px;">
                                <form method="POST" action="{{ route('verification.approve', $verification) }}" style="display:inline-block;">
                                    @csrf
                                    <button type="submit" style="background:rgba(34,197,94,0.15); color:#4ade80; border:1px solid #22c55e; font-size:11px; padding:6px 14px; border-radius:6px; cursor:pointer;">Approve</button>
                                </form>

                                <form method="POST" action="{{ route('verification.reject', $verification) }}" style="margin-top:10px;">
                                    @csrf
                                    <input
                                        type="text"
                                        name="reason"
                                        value="{{ old('reason') }}"
                                        placeholder="Reason for rejection"
                                        style="width:100%; background:#0b1220; color:#fff; border:1px solid #374151; border-radius:8px; padding:8px 10px; font-size:11px;"
                                    >
                                    @error('reason')
                                        <div style="color:#fca5a5; font-size:11px; margin-top:6px;">{{ $message }}</div>
                                    @enderror
                                    <button type="submit" style="margin-top:8px; background:rgba(239,68,68,0.15); color:#f87171; border:1px solid #ef4444; font-size:11px; padding:6px 14px; border-radius:6px; cursor:pointer;">Reject</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div
    id="verification-preview-modal"
    style="display:none; position:fixed; inset:0; background:rgba(2,6,23,0.92); z-index:9999; align-items:center; justify-content:center; padding:24px;"
>
    <button
        type="button"
        id="verification-preview-close"
        aria-label="Close preview"
        style="position:absolute; top:18px; right:18px; width:42px; height:42px; border:none; border-radius:999px; background:rgba(255,255,255,0.12); color:#fff; font-size:22px; cursor:pointer;"
    >
        ×
    </button>
    <div style="width:100%; max-width:1200px; max-height:100%; display:flex; flex-direction:column; gap:14px;">
        <div id="verification-preview-title" style="color:#fff; font-size:16px; font-weight:600;"></div>
        <div style="flex:1; min-height:0; display:flex; align-items:center; justify-content:center;">
            <img
                id="verification-preview-image"
                src=""
                alt="Verification preview"
                style="max-width:100%; max-height:78vh; border-radius:18px; box-shadow:0 20px 60px rgba(0,0,0,0.45); background:#111827;"
            >
        </div>
    </div>
</div>

<script>
    (() => {
        const modal = document.getElementById('verification-preview-modal');
        const image = document.getElementById('verification-preview-image');
        const title = document.getElementById('verification-preview-title');
        const closeButton = document.getElementById('verification-preview-close');
        const triggers = document.querySelectorAll('.preview-trigger');

        const closeModal = () => {
            modal.style.display = 'none';
            image.src = '';
            title.textContent = '';
            document.body.style.overflow = '';
        };

        triggers.forEach((trigger) => {
            trigger.addEventListener('click', () => {
                image.src = trigger.dataset.previewUrl;
                title.textContent = trigger.dataset.previewTitle || 'Image Preview';
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
        });

        closeButton.addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.style.display === 'flex') {
                closeModal();
            }
        });
    })();
</script>
@endsection
