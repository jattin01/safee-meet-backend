@extends('layouts.app')

@section('title', 'Meetings')

@section('content')
<div class="">

    {{-- Page Header --}}
    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px; font-weight:700; color:#fff; margin:0 0 4px 0;">Meetings</h1>
        <p style="font-size:12px; color:#6b7280; margin:0;">All meetings booked across the platform</p>
    </div>

    {{-- Meetings --}}
    <div class="overflow-hidden rounded-xl border border-[#2a2d3e] bg-black">
        <div class="border-b border-[#2a2d3e] px-5 py-5">
            <div class="mb-5">
                <h2 class="text-[15px] font-semibold text-white">All Meetings</h2>
                <p class="mt-1 text-xs text-gray-500">Meeting bookings, hosts and guests</p>
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

            <form id="meetings-filter-form" method="GET" action="{{ route('meetings') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(160px,1fr)_minmax(180px,1fr)_minmax(160px,1fr)_minmax(160px,1fr)_auto] xl:items-end">
                <div>
                    <label for="username" class="mb-2 block text-xs font-medium text-gray-400">Username</label>
                    <input id="username" name="username" type="text" autocomplete="off" placeholder="Search username..."
                        list="username-suggestions"
                        value="{{ $filters['username'] ?? '' }}"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                    <datalist id="username-suggestions"></datalist>
                </div>

                <div>
                    <label for="status" class="mb-2 block text-xs font-medium text-gray-400">Meeting Status</label>
                    <select id="status" name="status"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2.5 text-sm text-white outline-none focus:border-[#DC131C]">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                                {{ \Illuminate\Support\Str::headline($status) }}
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
                    <button type="button" id="meetings-filter-reset"
                        class="rounded-lg border border-[#343746] px-4 py-2.5 text-sm font-semibold text-gray-300 transition hover:border-gray-500 hover:text-white">
                        Reset
                    </button>
                </div>
            </form>
        </div>

        <div id="meetings-table" data-base-url="{{ route('meetings') }}">
            @include('meetings.partials.meetings-table')
        </div>
    </div>

</div>

{{-- Meetings table: AJAX filtering + pagination (mirrors revenue.index) --}}
<script>
(function () {
    const wrap = document.getElementById('meetings-table');
    const form = document.getElementById('meetings-filter-form');
    const resetBtn = document.getElementById('meetings-filter-reset');
    const usernameInput = document.getElementById('username');
    const usernameSuggestions = document.getElementById('username-suggestions');
    const baseUrl = wrap.dataset.baseUrl;
    const usernamesUrl = '{{ route('meetings.usernamefilter') }}';

    function debounce(fn, delay) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    }

    async function loadMeetings(url) {
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
                loadMeetings(link.getAttribute('href'));
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

    // Explicit submit (Filter button / Enter / status change).
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        loadMeetings(currentFilterUrl());
    });

    document.getElementById('status').addEventListener('change', () => {
        loadMeetings(currentFilterUrl());
    });

    document.getElementById('start_date').addEventListener('change', () => {
        loadMeetings(currentFilterUrl());
    });

    document.getElementById('end_date').addEventListener('change', () => {
        loadMeetings(currentFilterUrl());
    });

    resetBtn.addEventListener('click', () => {
        form.reset();
        loadMeetings(baseUrl);
    });

    // Username: live search as you type, no need to click Filter.
    const runLiveUsernameSearch = debounce(() => {
        loadMeetings(currentFilterUrl());
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
