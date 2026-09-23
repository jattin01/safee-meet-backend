@extends('layouts.app')

@section('title', 'Revenue Analytics')

@section('content')
<div class="">

    {{-- Page Header --}}
    <div style="margin-bottom:24px; display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; justify-content:space-between;">
        <div>
            <h1 style="font-size:22px; font-weight:700; color:#fff; margin:0 0 4px 0;">Revenue Analytics</h1>
            <p style="font-size:12px; color:#6b7280; margin:0;">Financial overview · {{ \Illuminate\Support\Carbon::parse($rangeStart)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($rangeEnd)->format('d M Y') }}</p>
        </div>

        {{-- Reporting period selector --}}
        <form id="summary-range-form" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="period" class="mb-1 block text-xs font-medium text-gray-400">Reporting Period</label>
                <select id="period" name="period"
                    class="rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                    @foreach([
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'last_7_days' => 'Last 7 Days',
                        'last_30_days' => 'Last 30 Days',
                        'this_month' => 'This Month',
                        'last_month' => 'Last Month',
                        'this_year' => 'This Year',
                        'custom' => 'Custom Range',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div id="custom-range-fields" class="flex items-end gap-2" style="{{ $period === 'custom' ? '' : 'display:none;' }}">
                <div>
                    <label for="range_start" class="mb-1 block text-xs font-medium text-gray-400">From</label>
                    <input id="range_start" name="range_start" type="date" value="{{ $rangeStart }}"
                        class="rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>
                <div>
                    <label for="range_end" class="mb-1 block text-xs font-medium text-gray-400">To</label>
                    <input id="range_end" name="range_end" type="date" value="{{ $rangeEnd }}"
                        class="rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-[#DC131C] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#b50f16]">
                Apply
            </button>
        </form>
    </div>

    {{-- Stat Cards --}}
    <div class="grid md:grid-cols-3 gap-[15px]" style="margin-bottom:24px;">

        {{-- Total Revenue --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:11px; color:#6b7280; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Total Revenue</div>
            <div style="font-size:26px; font-weight:700; color:#fff; margin-bottom:6px;">${{ number_format($summary['total_revenue'], 2) }}</div>
            <div style="font-size:11px; color:#6b7280;">Collected in selected period</div>
        </div>

        {{-- MRR --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:11px; color:#6b7280; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">MRR</div>
            <div style="font-size:26px; font-weight:700; color:#fff; margin-bottom:6px;">${{ number_format($summary['mrr'], 2) }}</div>
            <div style="font-size:11px; color:#6b7280;">From active subscriptions (yearly normalized to monthly)</div>
        </div>

        {{-- ARR --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:11px; color:#6b7280; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">ARR</div>
            <div style="font-size:26px; font-weight:700; color:#fff; margin-bottom:6px;">${{ number_format($summary['arr'], 2) }}</div>
            <div style="font-size:11px; color:#6b7280;">MRR × 12</div>
        </div>

        {{-- Active Subscriptions --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:11px; color:#6b7280; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Active Subscriptions</div>
            <div style="font-size:26px; font-weight:700; color:#fff; margin-bottom:6px;">{{ number_format($summary['active_subscriptions']) }}</div>
            <div style="font-size:11px; color:#6b7280;">Currently paid &amp; active</div>
        </div>

        {{-- Succeeded Payments --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:11px; color:#6b7280; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Successful Payments</div>
            <div style="font-size:26px; font-weight:700; color:#22c55e; margin-bottom:6px;">{{ number_format($summary['payments']['succeeded']) }}</div>
            <div style="font-size:11px; color:#6b7280;">In selected period</div>
        </div>

        {{-- Pending / Failed Payments --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:11px; color:#6b7280; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Pending / Failed</div>
            <div style="font-size:26px; font-weight:700; color:#fff; margin-bottom:6px;">
                <span style="color:#facc15;">{{ number_format($summary['payments']['pending']) }}</span>
                <span style="color:#6b7280; font-size:16px;">/</span>
                <span style="color:#ef4444;">{{ number_format($summary['payments']['failed']) }}</span>
            </div>
            <div style="font-size:11px; color:#6b7280;">Pending / Failed in selected period{{ $summary['payments']['refunded'] > 0 ? ' · '.$summary['payments']['refunded'].' refunded' : '' }}</div>
        </div>

    </div>

    {{-- Revenue Trend Chart --}}
    <div style="background:#000; border:1px solid #000; border-radius:12px; padding:24px;">

        <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px;">
            <h2 style="font-size:15px; font-weight:600; color:#fff; margin:0;">Revenue Trend</h2>

            <div class="inline-flex rounded-lg border border-[#2a2d3e] p-1" id="trend-granularity">
                @foreach(['daily' => 'Daily', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $value => $label)
                    <button type="button" data-granularity="{{ $value }}"
                        class="trend-granularity-btn rounded-md px-3 py-1.5 text-xs font-medium text-gray-400 transition"
                        style="{{ $value === 'daily' ? 'background:#DC131C;color:#fff;' : '' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <div style="position:relative; width:100%; min-height:260px;">
            <canvas id="revenueTrendChart" height="90"></canvas>
            <div id="trend-empty-state" class="hidden" style="position:absolute; inset:0; display:none; align-items:center; justify-content:center; color:#6b7280; font-size:13px;">
                No revenue data for this period.
            </div>
            <div id="trend-error-state" class="hidden" style="position:absolute; inset:0; display:none; align-items:center; justify-content:center; color:#ef4444; font-size:13px;">
                Couldn't load revenue trend. Please try again.
            </div>
            <div id="trend-loading-state" style="position:absolute; inset:0; display:none; align-items:center; justify-content:center; color:#6b7280; font-size:13px;">
                Loading…
            </div>
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

            <form id="transactions-filter-form" method="GET" action="{{ route('revenue') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="search" class="mb-2 block text-xs font-medium text-gray-400">Search (Username / Mobile / Plan)</label>
                    <input id="search" name="search" type="text" autocomplete="off" placeholder="Search by username, mobile or plan"
                        list="username-suggestions"
                        value="{{ $filters['search'] ?? '' }}"
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
                    <label for="subscription_status" class="mb-2 block text-xs font-medium text-gray-400">Subscription Status</label>
                    <select id="subscription_status" name="subscription_status"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2.5 text-sm text-white outline-none focus:border-[#DC131C]">
                        <option value="">All</option>
                        @foreach(['incomplete', 'trial', 'active', 'expired', 'cancelled'] as $status)
                            <option value="{{ $status }}" @selected(($filters['subscription_status'] ?? '') === $status)>
                                {{ \Illuminate\Support\Str::headline($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="billing_cycle" class="mb-2 block text-xs font-medium text-gray-400">Billing Cycle</label>
                    <select id="billing_cycle" name="billing_cycle"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2.5 text-sm text-white outline-none focus:border-[#DC131C]">
                        <option value="">All</option>
                        @foreach(['trial', 'monthly', 'yearly'] as $cycle)
                            <option value="{{ $cycle }}" @selected(($filters['billing_cycle'] ?? '') === $cycle)>
                                {{ \Illuminate\Support\Str::headline($cycle) }}
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

                <div class="flex gap-2 md:col-span-2 xl:col-span-4">
                    <button type="submit"
                        class="rounded-lg bg-[#DC131C] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#b50f16]">
                        Filter
                    </button>
                    <button type="button" id="transactions-filter-reset"
                        class="rounded-lg border border-[#343746] px-4 py-2.5 text-sm font-semibold text-gray-300 transition hover:border-gray-500 hover:text-white">
                        Clear Filters
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

{{-- Reporting period: toggle custom range fields, submit as query string --}}
<script>
(function () {
    const periodSelect = document.getElementById('period');
    const customFields = document.getElementById('custom-range-fields');
    const form = document.getElementById('summary-range-form');

    periodSelect.addEventListener('change', () => {
        customFields.style.display = periodSelect.value === 'custom' ? 'flex' : 'none';
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const params = new URLSearchParams(new FormData(form)).toString();
        window.location.href = '{{ route('revenue') }}' + (params ? '?' + params : '');
    });
})();
</script>

{{-- Revenue trend chart: Chart.js, fed by /revenue/trend, period selector --}}
<script>
(function () {
    const chartGridColor = '#252b3b';
    const chartLabelColor = '#8f98ad';
    const canvas = document.getElementById('revenueTrendChart');
    const emptyState = document.getElementById('trend-empty-state');
    const errorState = document.getElementById('trend-error-state');
    const loadingState = document.getElementById('trend-loading-state');
    const trendUrl = '{{ route('revenue.trend') }}';
    const currentPeriod = @json($period);
    const currentRangeStart = @json($rangeStart);
    const currentRangeEnd = @json($rangeEnd);
    let granularity = 'daily';
    let chart = null;

    function setState(state) {
        canvas.style.display = state === 'ready' ? 'block' : 'none';
        emptyState.style.display = state === 'empty' ? 'flex' : 'none';
        errorState.style.display = state === 'error' ? 'flex' : 'none';
        loadingState.style.display = state === 'loading' ? 'flex' : 'none';
    }

    async function loadTrend() {
        setState('loading');

        const params = new URLSearchParams({
            granularity,
            period: currentPeriod,
            range_start: currentRangeStart,
            range_end: currentRangeEnd,
        });

        try {
            const response = await fetch(`${trendUrl}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                setState('error');
                return;
            }

            const data = await response.json();
            const points = data.points || [];
            const hasRevenue = points.some((point) => point.value > 0);

            if (!points.length || !hasRevenue) {
                setState('empty');
                if (chart) chart.destroy();
                chart = null;
                return;
            }

            const labels = points.map((point) => point.label);
            const values = points.map((point) => point.value);

            if (chart) {
                chart.data.labels = labels;
                chart.data.datasets[0].data = values;
                chart.update();
            } else {
                chart = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Revenue',
                            data: values,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239,68,68,0.15)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 4,
                            pointBackgroundColor: '#ef4444',
                            borderWidth: 2.5,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => '$' + Number(ctx.parsed.y).toLocaleString(undefined, { minimumFractionDigits: 2 }),
                                },
                            },
                        },
                        scales: {
                            x: { grid: { color: chartGridColor }, ticks: { color: chartLabelColor, font: { size: 10 } } },
                            y: {
                                grid: { color: chartGridColor },
                                ticks: {
                                    color: chartLabelColor,
                                    font: { size: 10 },
                                    callback: (value) => '$' + Number(value).toLocaleString(),
                                },
                                beginAtZero: true,
                            },
                        },
                    },
                });
            }

            setState('ready');
        } catch (e) {
            setState('error');
        }
    }

    document.querySelectorAll('.trend-granularity-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            granularity = btn.dataset.granularity;
            document.querySelectorAll('.trend-granularity-btn').forEach((b) => {
                b.style.background = '';
                b.style.color = '';
            });
            btn.style.background = '#DC131C';
            btn.style.color = '#fff';
            loadTrend();
        });
    });

    loadTrend();
})();
</script>

{{-- Transactions table: AJAX filtering + pagination --}}
<script>
(function () {
    const wrap = document.getElementById('transactions-table');
    const form = document.getElementById('transactions-filter-form');
    const resetBtn = document.getElementById('transactions-filter-reset');
    const exportBtn = document.getElementById('transactions-export');
    const usernameInput = document.getElementById('search');
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
