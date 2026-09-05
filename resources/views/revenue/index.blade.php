@extends('layouts.app')

@section('title', 'Revenue Analytics')

@section('content')
<div class="">

    {{-- Page Header --}}
    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px; font-weight:700; color:#fff; margin:0 0 4px 0;">Revenue Analytics</h1>
        <p style="font-size:12px; color:#6b7280; margin:0;">Financial overview · June 2026</p>
    </div>

    {{-- Stat Cards --}}
   <div class="grid md:grid-cols-3 gap-[15px]" style=" margin-bottom:24px;">

        {{-- MRR --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:11px; color:#6b7280; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">MRR</div>
            <div style="font-size:26px; font-weight:700; color:#fff; margin-bottom:6px;">$284,721</div>
            <div style="font-size:11px; color:#22c55e;">+23% from last month</div>
        </div>

        {{-- ARR --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:11px; color:#6b7280; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">ARR</div>
            <div style="font-size:26px; font-weight:700; color:#fff; margin-bottom:6px;">$3.4M</div>
            <div style="font-size:11px; color:#22c55e;">+31% from last month</div>
        </div>

        {{-- Churn Rate --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:11px; color:#6b7280; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Churn Rate</div>
            <div style="font-size:26px; font-weight:700; color:#fff; margin-bottom:6px;">2.1%</div>
            <div style="font-size:11px; color:#22c55e;">-0.4% from last month</div>
        </div>

    </div>

    {{-- Revenue Trend Chart --}}
    <div style="background:#000; border:1px solid #000; border-radius:12px; padding:24px;">

        <h2 style="font-size:15px; font-weight:600; color:#fff; margin:0 0 20px 0;">Revenue Trend</h2>

        {{-- Chart Container --}}
        <div style="position:relative; width:100%; overflow-x:auto;">
            <svg viewBox="0 0 700 220" xmlns="http://www.w3.org/2000/svg" style="width:100%; min-width:400px; display:block;">

                {{-- Y Axis Labels --}}
                <text x="38" y="20" font-size="10" fill="#6b7280" text-anchor="end">$300k</text>
                <text x="38" y="62" font-size="10" fill="#6b7280" text-anchor="end">$225k</text>
                <text x="38" y="104" font-size="10" fill="#6b7280" text-anchor="end">$150k</text>
                <text x="38" y="146" font-size="10" fill="#6b7280" text-anchor="end">$75k</text>
                <text x="38" y="188" font-size="10" fill="#6b7280" text-anchor="end">$0</text>

                {{-- Horizontal Grid Lines --}}
                <line x1="45" y1="16" x2="690" y2="16" stroke="#2a2d3e" stroke-width="1"/>
                <line x1="45" y1="58" x2="690" y2="58" stroke="#2a2d3e" stroke-width="1"/>
                <line x1="45" y1="100" x2="690" y2="100" stroke="#2a2d3e" stroke-width="1"/>
                <line x1="45" y1="142" x2="690" y2="142" stroke="#2a2d3e" stroke-width="1"/>
                <line x1="45" y1="184" x2="690" y2="184" stroke="#2a2d3e" stroke-width="1"/>

                {{-- Filled area under line (gradient) --}}
                <defs>
                    <linearGradient id="redGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#ef4444" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#ef4444" stop-opacity="0.02"/>
                    </linearGradient>
                </defs>

                {{-- Area fill: Jan=150, Feb=155, Mar=162, Apr=172, May=185, Jun=194 mapped to SVG coords --}}
                {{-- Y: value mapped: 0=$0 => y=184, $300k => y=16. Range=168px for $300k --}}
                {{-- Jan: $150k => y=184-(150/300*168)=184-84=100 --}}
                {{-- Feb: $158k => y=184-(158/300*168)=184-88.5=95.5 --}}
                {{-- Mar: $175k => y=184-(175/300*168)=184-98=86 --}}
                {{-- Apr: $205k => y=184-(205/300*168)=184-114.8=69.2 --}}
                {{-- May: $248k => y=184-(248/300*168)=184-138.9=45.1 --}}
                {{-- Jun: $285k => y=184-(285/300*168)=184-159.6=24.4 --}}

                <polygon
                    points="45,100 163,95 281,86 399,69 517,45 690,24 690,184 45,184"
                    fill="url(#redGrad)"
                />

                {{-- Line --}}
                <polyline
                    points="45,100 163,95 281,86 399,69 517,45 690,24"
                    fill="none"
                    stroke="#ef4444"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                {{-- Dots --}}
                <circle cx="45"  cy="100" r="4" fill="#ef4444"/>
                <circle cx="163" cy="95"  r="4" fill="#ef4444"/>
                <circle cx="281" cy="86"  r="4" fill="#ef4444"/>
                <circle cx="399" cy="69"  r="4" fill="#ef4444"/>
                <circle cx="517" cy="45"  r="4" fill="#ef4444"/>
                <circle cx="690" cy="24"  r="4" fill="#ef4444"/>

                {{-- X Axis Labels --}}
                <text x="45"  y="202" font-size="10" fill="#6b7280" text-anchor="middle">Jan</text>
                <text x="163" y="202" font-size="10" fill="#6b7280" text-anchor="middle">Feb</text>
                <text x="281" y="202" font-size="10" fill="#6b7280" text-anchor="middle">Mar</text>
                <text x="399" y="202" font-size="10" fill="#6b7280" text-anchor="middle">Apr</text>
                <text x="517" y="202" font-size="10" fill="#6b7280" text-anchor="middle">May</text>
                <text x="690" y="202" font-size="10" fill="#6b7280" text-anchor="middle">Jun</text>

            </svg>
        </div>

    </div>

    {{-- Transactions --}}
    <div class="mt-6 overflow-hidden rounded-xl border border-[#2a2d3e] bg-black">
        <div class="border-b border-[#2a2d3e] px-5 py-5">
            <div class="mb-5">
                <h2 class="text-[15px] font-semibold text-white">Transactions</h2>
                <p class="mt-1 text-xs text-gray-500">Subscription payment history</p>
            </div>

            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="transactions-filter-form" method="GET" action="{{ route('revenue') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(160px,1fr)_minmax(180px,1fr)_minmax(160px,1fr)_minmax(160px,1fr)_auto] xl:items-end">
                <div>
                    <label for="username" class="mb-2 block text-xs font-medium text-gray-400">Username</label>
                    <input id="username" name="username" type="text" autocomplete="off" placeholder="Search by username"
                        list="username-suggestions"
                        value="{{ $filters['username'] ?? '' }}"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                    <datalist id="username-suggestions"></datalist>
                </div>

                <div>
                    <label for="payment_status" class="mb-2 block text-xs font-medium text-gray-400">Payment Status</label>
                    <select id="payment_status" name="payment_status"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2.5 text-sm text-white outline-none focus:border-[#DC131C]">
                        <option value="">All</option>
                        @foreach($paymentStatuses as $status)
                            <option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>
                                {{ match ($status) {
                                    'succeeded' => 'Paid / Successful',
                                    'pending' => 'Pending',
                                    'failed' => 'Failed',
                                    'refunded' => 'Refunded',
                                    'no_payment' => 'No Payment',
                                    default => \Illuminate\Support\Str::headline($status),
                                } }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="start_date" class="mb-2 block text-xs font-medium text-gray-400">Start Date</label>
                    <input id="start_date" name="start_date" type="date" value="{{ $filters['start_date'] ?? '' }}"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>

                <div>
                    <label for="end_date" class="mb-2 block text-xs font-medium text-gray-400">End Date</label>
                    <input id="end_date" name="end_date" type="date" value="{{ $filters['end_date'] ?? '' }}"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>

                <div class="flex gap-2 md:col-span-2 xl:col-span-1">
                    <button type="submit"
                        class="rounded-lg bg-[#DC131C] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#b50f16]">
                        Filter
                    </button>
                    <button type="button" id="transactions-filter-reset"
                        class="rounded-lg border border-[#343746] px-4 py-2.5 text-sm font-semibold text-gray-300 transition hover:border-gray-500 hover:text-white">
                        Clear
                    </button>
                    <button type="button" id="transactions-export"
                        class="rounded-lg border border-[#343746] px-4 py-2.5 text-sm font-semibold text-gray-300 transition hover:border-gray-500 hover:text-white">
                        Export
                    </button>
                </div>
            </form>
        </div>

        <div id="transactions-table" data-base-url="{{ route('revenue') }}">
            @include('revenue.partials.transactions-table')
        </div>
    </div>

</div>

{{-- Responsive --}}
<style>
    @media (max-width: 640px) {
        .rev-grid { grid-template-columns: 1fr !important; }
    }
</style>

{{-- Transactions table: AJAX filtering + pagination --}}
<script>
(function () {
    const wrap = document.getElementById('transactions-table');
    const form = document.getElementById('transactions-filter-form');
    const resetBtn = document.getElementById('transactions-filter-reset');
    const exportBtn = document.getElementById('transactions-export');
    const usernameInput = document.getElementById('username');
    const usernameSuggestions = document.getElementById('username-suggestions');
    const baseUrl = wrap.dataset.baseUrl;
    const usernamesUrl = '{{ route('revenue.usernamefilter') }}';
    const exportUrl = '{{ route('revenue.export') }}';

    function debounce(fn, delay) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    }

    async function loadTransactions(url) {
        wrap.style.opacity = '0.5';
        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) return;

            wrap.innerHTML = await response.text();
            bindPaginationLinks();
        } finally {
            wrap.style.opacity = '1';
        }
    }

    function bindPaginationLinks() {
        wrap.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                loadTransactions(link.getAttribute('href'));
            });
        });
    }

    function currentFilterParams() {
        return new URLSearchParams(new FormData(form)).toString();
    }

    function currentFilterUrl() {
        const params = currentFilterParams();
        return baseUrl + (params ? '?' + params : '');
    }

    // Explicit submit (Filter button / Enter).
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        loadTransactions(currentFilterUrl());
    });

    resetBtn.addEventListener('click', () => {
        form.reset();
        loadTransactions(baseUrl);
    });

    // Export respects whatever filters are currently applied. Triggering a
    // file download via a plain navigation doesn't add a history entry or
    // change the address bar shown for the app once the download starts, so
    // this stays consistent with the "URL never changes" requirement above.
    exportBtn.addEventListener('click', () => {
        const params = currentFilterParams();
        window.location.href = exportUrl + (params ? '?' + params : '');
    });

    // Username: live search as you type, no need to click Filter.
    const runLiveUsernameSearch = debounce(() => {
        loadTransactions(currentFilterUrl());
    }, 350);

    async function fetchUsernameSuggestions(query) {
        if (!query) {
            usernameSuggestions.innerHTML = '';
            return;
        }

        try {
            const response = await fetch(`${usernamesUrl}?q=${encodeURIComponent(query)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) return;

            const { usernames } = await response.json();
            usernameSuggestions.innerHTML = usernames
                .map((name) => `<option value="${name.replace(/"/g, '&quot;')}"></option>`)
                .join('');
        } catch (e) {
            // Suggestions are a convenience only — a failed lookup shouldn't block search.
        }
    }

    const debouncedSuggestions = debounce(fetchUsernameSuggestions, 250);

    usernameInput.addEventListener('input', () => {
        debouncedSuggestions(usernameInput.value.trim());
        runLiveUsernameSearch();
    });

    bindPaginationLinks();
})();
</script>

@endsection
