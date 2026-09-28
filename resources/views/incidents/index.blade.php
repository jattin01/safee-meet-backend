@extends('layouts.app')

@section('title', 'Incident Reports')

@section('content')
<div class="">

    {{-- Page Header --}}
    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px; font-weight:700; color:#fff; margin:0 0 4px 0;">Incident Reports</h1>
        <p style="font-size:12px; color:#6b7280; margin:0;">{{ $activeSos }} active SOS {{ \Illuminate\Support\Str::plural('event', $activeSos) }} · {{ $resolutionRate }}% resolution rate</p>
    </div>

    {{-- Stat Cards --}}
    <div class="grid md:grid-cols-3 gap-[15px]" style=" margin-bottom:24px;">

        {{-- Active SOS --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:38px; height:38px; border-radius:8px; background:rgba(239,68,68,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#ef4444; font-size:16px;">⚠</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">{{ $activeSos }}</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Active SOS</div>
            </div>
        </div>

        {{-- Resolved Today --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:38px; height:38px; border-radius:8px; background:rgba(34,197,94,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#22c55e; font-size:16px;">✓</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">{{ $resolvedToday }}</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Resolved Today</div>
            </div>
        </div>

        {{-- Under Review --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:38px; height:38px; border-radius:8px; background:rgba(245,158,11,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#f59e0b; font-size:16px;">⏱</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">{{ $underReview }}</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Under Review</div>
            </div>
        </div>

    </div>

    {{-- Active & Recent Incidents --}}
    <div style="background:#000; border:1px solid #000; border-radius:12px; overflow:hidden;">

        {{-- Header and filters --}}
        <div style="padding:18px 20px; border-bottom:1px solid #1a1a1a;">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h2 style="font-size:15px; font-weight:600; color:#fff; margin:0;">Active & Recent Incidents</h2>
                    <p class="mt-1 text-xs text-gray-500">Search reporters or narrow incidents by reported date</p>
                </div>

                <a id="incidents-export" href="{{ route('incidents.export', array_filter($filters, fn ($value) => $value !== null && $value !== '')) }}"
                    class="inline-flex items-center justify-center rounded-lg bg-[#DC131C] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#b50f16]">
                    Export CSV
                </a>
            </div>

            @if($errors->any())
                <div class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="incidents-filter-form" method="GET" action="{{ route('incidents') }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(260px,2fr)_minmax(160px,1fr)_minmax(160px,1fr)_auto] xl:items-end">
                <div>
                    <label for="search" class="mb-2 block text-xs font-medium text-gray-400">Reporter</label>
                    <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}"
                        placeholder="Search name, email or phone..."
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>

                <div>
                    <label for="start_date" class="mb-2 block text-xs font-medium text-gray-400">From Date</label>
                    <input id="start_date" name="start_date" type="date" value="{{ $filters['start_date'] ?? '' }}"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>

                <div>
                    <label for="end_date" class="mb-2 block text-xs font-medium text-gray-400">To Date</label>
                    <input id="end_date" name="end_date" type="date" value="{{ $filters['end_date'] ?? '' }}"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>

                <div class="flex gap-2 md:col-span-2 xl:col-span-1">
                    <button type="submit" class="rounded-lg bg-[#DC131C] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#b50f16]">
                        Filter
                    </button>
                    <a id="incidents-filter-reset" href="{{ route('incidents') }}" class="rounded-lg border border-[#343746] px-4 py-2.5 text-sm font-semibold text-gray-300 transition hover:border-gray-500 hover:text-white">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div id="incidents-results">
            @include('incidents.partials.results')
        </div>

    </div>

</div>

{{-- Responsive --}}
<style>
    @media (max-width: 640px) {
        .incident-grid { grid-template-columns: 1fr !important; }
    }
</style>

<script>
(function () {
    const form = document.getElementById('incidents-filter-form');
    const searchInput = document.getElementById('search');
    const results = document.getElementById('incidents-results');
    const exportLink = document.getElementById('incidents-export');
    const resetLink = document.getElementById('incidents-filter-reset');
    const baseUrl = form.action;
    const exportUrl = '{{ route('incidents.export') }}';
    let debounceTimer;
    let activeRequest;

    function filterParams() {
        const params = new URLSearchParams(new FormData(form));

        for (const [key, value] of [...params.entries()]) {
            if (!value) params.delete(key);
        }

        return params;
    }

    function filteredUrl() {
        const params = filterParams().toString();
        return baseUrl + (params ? `?${params}` : '');
    }

    function syncExportLink() {
        const params = filterParams().toString();
        exportLink.href = exportUrl + (params ? `?${params}` : '');
    }

    async function loadResults(url) {
        activeRequest?.abort();
        activeRequest = new AbortController();
        results.style.opacity = '0.5';

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: activeRequest.signal,
            });

            if (!response.ok) return;

            results.innerHTML = await response.text();
            bindPagination();
            syncExportLink();
            window.history.replaceState({}, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') console.error('Unable to load incidents.', error);
        } finally {
            results.style.opacity = '1';
        }
    }

    function bindPagination() {
        results.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                loadResults(link.href);
            });
        });
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadResults(filteredUrl()), 350);
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(debounceTimer);
        loadResults(filteredUrl());
    });

    ['start_date', 'end_date'].forEach((id) => {
        document.getElementById(id).addEventListener('change', () => loadResults(filteredUrl()));
    });

    resetLink.addEventListener('click', (event) => {
        event.preventDefault();
        form.reset();
        loadResults(baseUrl);
    });

    bindPagination();
})();
</script>

@endsection
