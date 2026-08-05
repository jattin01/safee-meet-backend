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
                                <button type="button" class="document-view-button" data-verification-id="{{ $verification->id }}" title="View documents" aria-label="View documents for {{ $user?->name ?: 'user' }}">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c-5.5 0-9.5 5.2-9.7 5.4a2.5 2.5 0 0 0 0 3.2C2.5 13.8 6.5 19 12 19s9.5-5.2 9.7-5.4a2.5 2.5 0 0 0 0-3.2C21.5 10.2 17.5 5 12 5Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                                </button>
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
            <div style="padding:16px 20px;">
                {{ $users->links() }}
            </div>
        @endif

    </div>

</div>

<div id="verification-documents-modal" class="documents-modal" hidden role="dialog" aria-modal="true" aria-labelledby="documents-modal-title">
    <div class="documents-modal__backdrop" data-close-documents></div>
    <section class="documents-modal__panel">
        <header class="documents-modal__header">
            <div>
                <h2 id="documents-modal-title">Verification Documents</h2>
                <div id="documents-overall-status"></div>
            </div>
            <button type="button" class="documents-modal__close" data-close-documents aria-label="Close">&times;</button>
        </header>
        <div id="documents-grid" class="documents-grid"></div>
    </section>
</div>

<script id="verification-document-data" type="application/json">@json($documentDetails)</script>

{{-- Responsive: stack stat cards on mobile --}}
<style>
    .document-view-button { width:30px; height:30px; margin-right:6px; vertical-align:middle; display:inline-flex; align-items:center; justify-content:center; border-radius:6px; border:1px solid #374151; background:#111827; color:#d1d5db; cursor:pointer; }
    .document-view-button:hover { color:#fff; border-color:#6b7280; }
    .document-view-button svg { width:16px; height:16px; fill:currentColor; }
    .documents-modal { position:fixed; inset:0; z-index:1000; }
    .documents-modal[hidden] { display:none; }
    .documents-modal__backdrop { position:absolute; inset:0; background:rgba(0,0,0,.78); }
    .documents-modal__panel { position:relative; width:min(960px, calc(100% - 32px)); max-height:calc(100vh - 48px); overflow:auto; margin:24px auto; padding:22px; border:1px solid #262626; border-radius:14px; background:#090909; box-shadow:0 24px 70px rgba(0,0,0,.55); }
    .documents-modal__header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:20px; }
    .documents-modal__header h2 { color:#fff; font-size:19px; font-weight:700; margin:0 0 8px; }
    .documents-modal__close { color:#9ca3af; background:transparent; border:0; font-size:30px; line-height:1; cursor:pointer; }
    .documents-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; }
    .document-card { overflow:hidden; border:1px solid #242424; border-radius:10px; background:#111; }
    .document-card__top { display:flex; justify-content:space-between; align-items:center; gap:8px; padding:11px 13px; }
    .document-card__label { color:#fff; font-size:12px; font-weight:600; }
    .document-card__preview { height:230px; display:flex; align-items:center; justify-content:center; background:#050505; color:#6b7280; font-size:13px; }
    .document-card__preview img, .document-card__preview video { width:100%; height:100%; object-fit:contain; }
    .document-status { display:inline-flex; padding:4px 9px; border-radius:999px; font-size:10px; font-weight:700; white-space:nowrap; }
    .status-verified { color:#4ade80; background:rgba(34,197,94,.15); }
    .status-rejected { color:#f87171; background:rgba(239,68,68,.15); }
    .status-expired { color:#fbbf24; background:rgba(245,158,11,.15); }
    .status-under-review, .status-pending { color:#facc15; background:rgba(234,179,8,.15); }
    @media (max-width: 640px) {
        .stat-grid { grid-template-columns: 1fr !important; }
        .documents-grid { grid-template-columns:1fr; }
    }
</style>

<script>
    (() => {
        const modal = document.getElementById('verification-documents-modal');
        const grid = document.getElementById('documents-grid');
        const overall = document.getElementById('documents-overall-status');
        const details = JSON.parse(document.getElementById('verification-document-data').textContent || '{}');
        const statusClass = status => `status-${String(status).toLowerCase().replace(/\s+/g, '-')}`;
        const badge = status => {
            const element = document.createElement('span');
            element.className = `document-status ${statusClass(status)}`;
            element.textContent = ({Verified: '✓ ', Pending: '⏳ ', Rejected: '✕ ', Expired: '⚠ ', 'Under Review': '⏳ '})[status] + status;
            return element;
        };

        document.querySelectorAll('.document-view-button').forEach(button => button.addEventListener('click', () => {
            const detail = details[button.dataset.verificationId] || {overallStatus: 'Pending', documents: []};
            grid.replaceChildren();
            overall.replaceChildren(badge(detail.overallStatus));

            detail.documents.forEach(documentDetail => {
                const card = document.createElement('article');
                card.className = 'document-card';
                const top = document.createElement('div');
                top.className = 'document-card__top';
                const label = document.createElement('span');
                label.className = 'document-card__label';
                label.textContent = documentDetail.label;
                top.append(label, badge(documentDetail.status));
                const preview = document.createElement('div');
                preview.className = 'document-card__preview';

                if (!documentDetail.url) {
                    preview.textContent = 'Not Available';
                } else if (documentDetail.type === 'video') {
                    const video = document.createElement('video');
                    video.src = documentDetail.url;
                    video.controls = true;
                    video.preload = 'metadata';
                    preview.append(video);
                } else if (documentDetail.type === 'file') {
                    const link = document.createElement('a');
                    link.href = documentDetail.url;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.textContent = 'Open document';
                    preview.append(link);
                } else {
                    const link = document.createElement('a');
                    link.href = documentDetail.url;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    const image = document.createElement('img');
                    image.src = documentDetail.url;
                    image.alt = documentDetail.label;
                    image.loading = 'lazy';
                    image.addEventListener('error', () => { preview.textContent = 'Not Available'; });
                    link.append(image);
                    preview.append(link);
                }
                card.append(top, preview);
                grid.append(card);
            });

            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        }));

        const close = () => { modal.hidden = true; document.body.style.overflow = ''; };
        modal.querySelectorAll('[data-close-documents]').forEach(button => button.addEventListener('click', close));
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.hidden) close(); });
    })();
</script>

@endsection
