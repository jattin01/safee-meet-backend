@extends('layouts.app') @section('content')

<div class="">
    <div class="relative overflow-hidden rounded-md h-44 bg-[#000]">
        <div class="border-[60px] border-t-primary-500 border-l-primary-500 absolute opacity-10 -top-2 left-0 rotate-45 size-96"></div>
        <div class="border-[60px] border-green-500 absolute opacity-10 top-20 left-8 rotate-45 size-80"></div>
        <div class="border-[60px] border-pink-500 absolute opacity-10 top-36 left-28 rotate-45 size-40"></div>
    </div>

    <div class="relative text-center">
        <div class="relative inline-block mx-auto">
            <div class="relative p-1 rounded-full bg-gradient-to-tr from-primary-300 via-red-300 to-green-300 -mt-14">
                @if($user->profile_photo)
                    <img src="{{ asset('storage/'.$user->profile_photo) }}" alt="" class="mx-auto border-4 border-white rounded-full dark:border-dark-900 size-28 object-cover">
                @else
                    <div class="mx-auto border-4 border-white rounded-full dark:border-dark-900 size-28 flex items-center justify-center text-2xl font-bold text-white" style="background:{{ $user->avatar_color }};">{{ $user->initials }}</div>
                @endif
            </div>
            <div class="absolute right-[20px] border-2 border-white dark:border-dark-900 rounded-full size-4 bg-{{ $user->status === 'active' ? 'green-500' : 'gray-500' }} bottom-2.5 ltr:right-2.5 rtl:left-2.5"></div>
        </div>
        <div class="mt-2 flex items-center justify-center gap-2 mb-2">
        <h5 class="">{{ $user->name ?: $user->display_name ?: 'Unnamed User' }}</h5>
        @if($user->badge_icon_url)
            <button type="button" id="verification-badge" data-badge-preview="{{ $user->badge_icon_url }}" data-badge-name="{{ $user->verificationLevel->name ?? $user->badge_label }}" class="inline-flex items-center justify-center size-6 rounded-full bg-white p-0.5 shadow cursor-pointer">
                <img src="{{ $user->badge_icon_url }}" alt="{{ $user->verificationLevel->name ?? 'Verification badge' }}" class="size-full rounded-full object-contain">
            </button>
        @elseif($user->verification_level !== 'none')
            <button type="button" id="verification-badge" class="inline-flex items-center justify-center cursor-pointer">
                <svg class="text-[#358ffc]" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-badge-check inline-block text-primary-500 fill-primary-500/20 size-5"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"></path><path d="m9 12 2 2 4-4"></path></svg>
            </button>
        @endif
        <h6 id="verification-level-name" class="text-[12px] hidden">{{ $user->verificationLevel->name ?? $user->badge_label }}</h6>
        </div>

        <!-- Badge Preview Modal -->
        <div id="badge-preview-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/70 p-4">
            <div class="relative max-w-sm w-full">
                <button type="button" id="badge-preview-close" class="absolute -top-3 -right-3 size-8 rounded-full bg-white text-black flex items-center justify-center shadow cursor-pointer">✕</button>
                <div class="bg-[#111] rounded-2xl p-6 flex flex-col items-center gap-3">
                    <img id="badge-preview-image" src="" alt="Verification badge" class="w-[300px] h-[500px] object-contain rounded-xl bg-white p-2">
                    <p id="badge-preview-name" class="text-white text-sm font-semibold"></p>
                </div>
            </div>
        </div>
        <ul class="mb-2 flex flex-wrap items-center justify-center gap-2 text-gray-500 dark:text-dark-500 text-14">
            <li>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="building-2" class="lucide lucide-building-2 inline-block ltr:mr-1 rtl:ml-1 size-4">
                    <path d="M10 12h4"></path>
                    <path d="M10 8h4"></path>
                    <path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
                    <path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
                    <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
                </svg> <span class="align-middle">{{ $user->account_type_label }}</span></li>
            <li>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="map-pin" class="lucide lucide-map-pin inline-block ltr:mr-1 rtl:ml-1 size-4">
                    <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg> <!-- <span class="align-middle">{{ $user->address ?: 'Unknown location' }}</span> --></li>
            <li>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="calendar-days" class="lucide lucide-calendar-days inline-block ltr:mr-1 rtl:ml-1 size-4">
                    <path d="M8 2v4"></path>
                    <path d="M16 2v4"></path>
                    <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                    <path d="M3 10h18"></path>
                    <path d="M8 14h.01"></path>
                    <path d="M12 14h.01"></path>
                    <path d="M16 14h.01"></path>
                    <path d="M8 18h.01"></path>
                    <path d="M12 18h.01"></path>
                    <path d="M16 18h.01"></path>
                </svg> <span class="align-middle">{{ $user->created_at?->format('d F, Y') ?? '—' }}</span></li>
        </ul>

        <div class="flex items-center gap-3 justify-center mt-3">

  <!-- Verification Level -->
  <span class="flex items-center gap-2 bg-[#1a2235] text-white text-[12px] font-medium px-4 py-1.5 rounded-full">
    <span class="w-2 h-2 rounded-full bg-blue-400"></span>
    {{ $user->verification_level_label }}
  </span>

  <!-- Plan -->
  <span class="flex items-center gap-2 bg-gradient-to-r from-purple-500 to-pink-500 text-white text-[12px] font-semibold px-4 py-1.5 rounded-full">
    {{ $subscription->plan_label ?? $user->plan_label }}
  </span>

  <!-- Verification Documents -->
  @if ($user->userVerification)
    <a href="{{ route('verification.show', $user->userVerification) }}"
       class="group flex items-center gap-2 bg-transparent border border-blue-400/60 text-blue-300 text-[12px] font-medium px-4 py-1.5 rounded-full transition hover:bg-blue-400/10 hover:text-blue-200 hover:border-blue-300">
      <img src="https://api.iconify.design/lucide/file-text.svg?color=%2360a5fa" class="w-3.5 h-3.5" alt="">
      Verification Docs
      <img src="https://api.iconify.design/lucide/arrow-up-right.svg?color=%2360a5fa" class="w-3 h-3 opacity-70 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5" alt="">
    </a>
  @endif

</div>
        <!-- ===== Profile Card ===== -->
        <div class="bg-[#000] rounded-3xl p-5 text-white shadow-lg mt-5">


            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class=" border-r border-[#1a1a1a]  px-4 py-2 text-center">
                    <p class="text-xl font-extrabold text-[#fff]">{{ $user->trust_score !== null ? round($user->trust_score) : '—' }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-[#fff]">Trust</p>
                </div>
                <div class=" md:border-r border-[#1a1a1a]  px-4 py-2 text-center">
                    <p class="text-xl font-extrabold text-[#fff]">{{ $meetingsCount }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-[#fff]">Meetings</p>
                </div>
                <div class=" border-r border-[#1a1a1a]  px-4 py-2 text-center">
                    <p class="font-bold text-base flex items-center justify-center gap-1">
                        <img src="https://api.iconify.design/lucide/shield-check.svg?color=%23facc15" class="w-3.5 h-3.5" alt=""> {{ $user->safety_score !== null ? round($user->safety_score) : '—' }}
                    </p>
                    <p class="text-[10px] uppercase tracking-wide text-[#fff]">Safety</p>
                </div>

                <div class="px-4 py-2 text-center">
                    <p class="font-bold text-base flex items-center justify-center gap-1">
                        <img src="https://api.iconify.design/lucide/key-round.svg?color=%23facc15" class="w-3.5 h-3.5" alt=""> {{ $user->safee_id ? '#'.$user->safee_id : '—' }}
                    </p>
                    <p class="text-[10px] uppercase tracking-wide text-[#fff]">Safee PIN</p>
                </div>

            </div>

            @if($user->firebase_uid)
            <div class="flex items-center justify-between gap-3 bg-[#111] border border-[#1a1a1a] rounded-2xl px-4 py-2.5 mt-3">
                <div class="flex items-center gap-2 min-w-0">
                    <img src="https://api.iconify.design/lucide/fingerprint.svg?color=%23facc15" class="w-4 h-4 shrink-0" alt="">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wide text-gray-400">Firebase UID</p>
                        <p id="firebase-uid-text" class="font-mono text-[12px] text-white truncate">{{ $user->firebase_uid }}</p>
                    </div>
                </div>
                <button type="button" id="copy-firebase-uid" data-copy-value="{{ $user->firebase_uid }}"
                        class="shrink-0 inline-flex items-center gap-1 rounded-full border border-[#2a2d3e] px-3 py-1.5 text-[11px] font-medium text-gray-300 hover:text-white hover:border-primary-500 transition cursor-pointer"
                        title="Click to copy Firebase UID">
                    <img src="https://api.iconify.design/lucide/copy.svg?color=%23d1d5db" class="w-3.5 h-3.5" alt="">
                    Copy
                </button>
            </div>
            @endif
        </div>

        <div class="mt-5 bg-[#000] rounded-3xl p-5 text-white shadow-lg">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-left">
                    <p class="font-bold text-white mb-1">Account Status</p>
                    <p id="status-message" class="text-xs text-slate-500">Current status: <span id="status-current" class="font-semibold" style="color:{{ $user->status_color }};">{{ $user->status_label }}</span></p>
                </div>
                <div class="flex items-center gap-2">
                    <select id="account-status-select" class="rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                        <option value="active" @selected($user->status === 'active')>Active</option>
                        <option value="inactive" @selected($user->status === 'inactive')>Inactive</option>
                        <option value="suspended" @selected($user->status === 'suspended')>Suspended</option>
                    </select>
                    <button type="button" id="account-status-save" class="rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#b50f16]">
                        Save
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-5 bg-[#000] rounded-3xl p-5 text-white shadow-lg">
            @if($subscription)
            <div class="text-left">
                <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-[#181818] via-[#101010] to-[#080808] px-5 py-5 sm:px-6">
                    <div class="pointer-events-none absolute -right-16 -top-20 size-56 rounded-full bg-[#DC131C]/10 blur-3xl"></div>
                    <div class="relative flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="flex size-11 items-center justify-center rounded-2xl bg-[#DC131C]/15 text-[#ff4b52] ring-1 ring-[#DC131C]/25">
                                <i class="fa-solid fa-layer-group"></i>
                            </span>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-[#ff6268]">Subscription timeline</p>
                                <h3 class="mt-1 text-xl font-bold text-white">Plan Journey</h3>
                                <p class="mt-1 text-xs text-slate-400">Every plan's allowance, usage and features are preserved as a snapshot.</p>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-2.5 text-right">
                            <p class="text-2xl font-black text-white">{{ count($subscriptionHistory) }}</p>
                            <p class="text-[9px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ Str::plural('plan', count($subscriptionHistory)) }} recorded</p>
                        </div>
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    @forelse($subscriptionHistory as $history)
                        @php
                            $snapshot = $history['snapshot'];
                            $searchUsage = $history['usage']['searches'];
                            $meetingUsage = $history['usage']['meetings'];
                            $periodEnd = $snapshot->cancelled_at ?? $snapshot->renews_at;
                            $enabledFeatureCount = collect($history['features'])->where('enabled', true)->count();
                            $searchPercent = is_numeric($searchUsage['total']) && (int) $searchUsage['total'] > 0
                                ? min(100, round(((int) $searchUsage['used'] / (int) $searchUsage['total']) * 100))
                                : null;
                            $meetingPercent = is_numeric($meetingUsage['total']) && (int) $meetingUsage['total'] > 0
                                ? min(100, round(((int) $meetingUsage['used'] / (int) $meetingUsage['total']) * 100))
                                : null;
                        @endphp

                        <article class="relative overflow-hidden rounded-3xl border p-1 {{ $history['is_current'] ? 'border-emerald-400/35 bg-emerald-400/[0.07] shadow-[0_18px_50px_rgba(16,185,129,0.08)]' : 'border-white/10 bg-white/[0.025]' }}">
                            <div class="absolute inset-y-6 left-0 w-1 rounded-r-full" style="background:{{ $history['plan']?->color ?? '#DC131C' }};"></div>
                            <div class="rounded-[20px] bg-[#101010]/95 px-5 py-5 sm:px-6">
                                <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-start">
                                    <div class="flex min-w-0 items-start gap-4">
                                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-lg" style="background:{{ $history['plan']?->color ?? '#DC131C' }};">
                                            <i class="fa-solid {{ $history['is_current'] ? 'fa-crown' : 'fa-clock-rotate-left' }}"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="truncate text-lg font-bold text-white">{{ $history['plan']?->name ?? 'Deleted plan' }}</h4>
                                                @if($history['is_current'])
                                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-400/25 bg-emerald-400/10 px-2.5 py-1 text-[9px] font-bold uppercase tracking-wider text-emerald-400">
                                                        <span class="size-1.5 animate-pulse rounded-full bg-emerald-400"></span> Current plan
                                                    </span>
                                                @else
                                                    <span class="rounded-full border border-slate-600/40 bg-slate-500/10 px-2.5 py-1 text-[9px] font-bold uppercase tracking-wider text-slate-400">Previous plan</span>
                                                @endif
                                            </div>
                                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400">
                                                <span class="font-semibold text-white">${{ number_format((float) $snapshot->price, 2) }} / {{ strtolower($snapshot->billing_cycle) }}</span>
                                                <span class="hidden size-1 rounded-full bg-slate-600 sm:block"></span>
                                                <span>Subscription #{{ $snapshot->subscription_id }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider" style="background:{{ $history['status_color'] }}1a; color:{{ $history['status_color'] }}; border:1px solid {{ $history['status_color'] }}40;">
                                            <span class="size-1.5 rounded-full" style="background:{{ $history['status_color'] }};"></span>
                                            {{ $history['status_label'] }}
                                        </span>
                                        <span class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1.5 text-[10px] font-semibold text-slate-300">
                                            {{ $snapshot->started_at?->format('d M Y') ?? '—' }} <span class="mx-1 text-slate-600">→</span> {{ $periodEnd?->format('d M Y') ?? 'Ongoing' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-5 grid gap-3 xl:grid-cols-2">
                                    @foreach([
                                        ['usage' => $searchUsage, 'title' => 'PIN Searches', 'icon' => 'fa-magnifying-glass', 'accent' => '#60a5fa', 'percent' => $searchPercent],
                                        ['usage' => $meetingUsage, 'title' => 'Meetings', 'icon' => 'fa-calendar-check', 'accent' => '#c084fc', 'percent' => $meetingPercent],
                                    ] as $metric)
                                        <div class="rounded-2xl border border-white/[0.08] bg-white/[0.035] p-4">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="flex items-center gap-2.5">
                                                    <span class="flex size-8 items-center justify-center rounded-xl" style="background:{{ $metric['accent'] }}18; color:{{ $metric['accent'] }};">
                                                        <i class="fa-solid {{ $metric['icon'] }} text-xs"></i>
                                                    </span>
                                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-300">{{ $metric['title'] }}</p>
                                                </div>
                                                @if($metric['usage']['unlimited'])
                                                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400">Unlimited access</span>
                                                @elseif($metric['percent'] !== null)
                                                    <span class="text-[10px] font-semibold text-slate-500">{{ $metric['percent'] }}% used</span>
                                                @endif
                                            </div>

                                            <div class="mt-4 grid grid-cols-3 gap-3">
                                                <div class="flex flex-col gap-1.5">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-300">Total</p>
                                                    <p class="text-xl font-extrabold leading-none text-white">{{ $metric['usage']['total'] }}</p>
                                                </div>
                                                <div class="flex flex-col gap-1.5 border-l border-white/[0.12] pl-3.5">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-300">Used</p>
                                                    <p class="text-xl font-extrabold leading-none text-amber-400">{{ $metric['usage']['used'] }}</p>
                                                </div>
                                                <div class="flex flex-col gap-1.5 border-l border-white/[0.12] pl-3.5">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-300">Remaining</p>
                                                    <p class="text-xl font-extrabold leading-none text-emerald-400">{{ $metric['usage']['remaining'] }}</p>
                                                </div>
                                            </div>

                                            @if($metric['percent'] !== null)
                                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/[0.07]">
                                                    <div class="h-full rounded-full transition-all" style="width:{{ $metric['percent'] }}%; background:{{ $metric['accent'] }};"></div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-3 overflow-x-auto rounded-xl border border-white/[0.08] bg-black/25 px-3 py-2">
                                    <div class="flex min-w-max flex-nowrap items-center gap-1.5">
                                        <p class="mr-1 text-[9px] font-bold uppercase tracking-wider text-slate-400">Feature Snapshot</p>
                                        @foreach($history['features'] as $feature)
                                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[8px] font-medium {{ $feature['enabled'] ? 'border-emerald-400/15 bg-emerald-400/[0.06] text-slate-300' : 'border-white/[0.05] bg-white/[0.02] text-slate-600' }}">
                                                <i class="fa-solid {{ $feature['enabled'] ? 'fa-check text-emerald-400' : 'fa-minus text-slate-600' }} text-[6px]"></i>
                                                {{ $feature['label'] }}
                                            </span>
                                        @endforeach
                                        <span class="ml-1.5 whitespace-nowrap border-l border-white/10 pl-2.5 text-[8px] font-semibold text-slate-600">{{ $enabledFeatureCount }}/{{ count($history['features']) }} included</span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-10 text-center">
                            <i class="fa-solid fa-receipt text-2xl text-slate-700"></i>
                            <p class="mt-3 text-sm font-semibold text-slate-400">No subscription history available.</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @else
            <div class="text-left">
                <p class="font-bold text-white mb-1">Purchased Plan</p>
                <p class="text-sm text-slate-500">No active subscription.</p>
            </div>
            @endif
        </div>

        <div class=" mt-5 bg-[#000] p-4 rounded-3xl p-5 text-white shadow-lg">
          <p class="font-bold text-white text-left mb-3">Reviews</p>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            @forelse($reviews as $review)
            <div class="bg-[#1a1a1a] border border-[#1a1a1a] rounded-2xl">
                <div class="rounded-2xl shadow-md p-4 text-left">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0" style="background:{{ $review->reviewer?->avatar_color ?? '#6b7280' }};">
                                {{ $review->reviewer?->initials ?? '?' }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-white text-sm truncate">{{ $review->reviewer?->display_name ?? 'Anonymous' }}</p>
                                <div class="flex text-yellow-400 text-xs mt-0.5">
                                    {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                                </div>
                            </div>
                        </div>
                        <span class="text-[11px] text-slate-400 whitespace-nowrap shrink-0 mt-1">{{ $review->created_at?->format('M j, Y g:i A') }}</span>
                    </div>
                    @if($review->comment)
                    <p class="text-sm text-white leading-relaxed">
                        {{ $review->comment }}
                    </p>
                    @endif
                </div>
            </div>
            @empty
            <p class="text-sm text-slate-500 text-left">No reviews yet.</p>
            @endforelse
        </div>
        </div>


        <div class="grid grid-cols-1  lg:grid-cols-2 gap-3 mt-5">


            <div class="bg-[#000] p-4 rounded-3xl p-5 text-white shadow-lg ">
                <p class="font-bold text-white mb-3 text-left">Emergency Contact</p>
                <div class="space-y-3">
                    @forelse($user->emergencyContacts as $contact)
                    <div class="flex items-center gap-3 bg-[#1a1a1a] border border-[#1a1a1a] rounded-2xl px-4 py-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-phone"></i>
                        </div>
                        <div class="flex-1 text-left">
                            <p class="text-sm font-semibold text-white">{{ $contact->full_name }}</p>
                            <p class="text-xs text-slate-500">{{ $contact->relationship ? $contact->relationship.': ' : '' }}{{ $contact->phone_number }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-slate-500 text-left">No emergency contacts added.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-[#000] p-4 rounded-3xl p-5 text-white shadow-lg ">
                <div class="flex items-center justify-between mb-3">
            <p class="font-bold text-white">Recent Meetings</p>
        </div>

                <div class="space-y-3">

            @forelse($meetings as $meeting)
                @php
                    $otherParty = $meeting->host_user_id === $user->id ? $meeting->guest : $meeting->host;
                    $userRating = $meeting->reviews->firstWhere('reviewee_id', $user->id)?->rating;
                @endphp
            <div class="text-left  flex items-center gap-3 bg-[#1a1a1a] border border-[#1a1a1a] rounded-2xl px-4 py-3 shadow-sm">
                <div class="w-10 h-10 rounded-full bg-amber-50 flex items-center justify-center shrink-0">
                    <img src="https://api.iconify.design/lucide/users.svg?color=%23d97706" class="w-5 h-5" alt="">
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-white truncate">{{ $otherParty?->display_name ?? $otherParty?->name ?? 'Unknown' }}</p>
                    <p class="text-xs text-slate-500">{{ $meeting->meeting_date?->format('M j, Y g:i A') ?? '—' }} · {{ $meeting->location ?: 'Location unavailable' }}</p>
                </div>
                <div class="text-right shrink-0">
                    <span class="text-[11px] font-medium px-2 py-0.5 rounded-full" style="background:{{ $meeting->status_color }}26; color:{{ $meeting->status_color }};">{{ $meeting->status_label }}</span>
                    <p class="text-[11px] text-amber-500 mt-1 flex items-center justify-end gap-0.5">
                        <img src="https://api.iconify.design/lucide/star.svg?color=%23f59e0b" class="w-3 h-3" alt=""> {{ $userRating ?? '—' }}
                    </p>
                </div>
            </div>
            @empty
            <p class="text-sm text-slate-500 text-left">No meetings yet.</p>
            @endforelse

        </div>
            </div>

        </div>




    </div>



</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const copyUidButton = document.getElementById('copy-firebase-uid');
    if (copyUidButton) {
        copyUidButton.addEventListener('click', async () => {
            const value = copyUidButton.dataset.copyValue || '';
            try {
                await navigator.clipboard.writeText(value);
            } catch (e) {
                const temp = document.createElement('textarea');
                temp.value = value;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
            }
            if (window.Swal) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Firebase UID copied', showConfirmButton: false, timer: 1500 });
            } else {
                const textEl = document.getElementById('firebase-uid-text');
                const original = textEl.textContent;
                textEl.textContent = 'Copied!';
                setTimeout(() => { textEl.textContent = original; }, 1200);
            }
        });
    }

    const badgeButton = document.getElementById('verification-badge');
    const badgeName = document.getElementById('verification-level-name');
    const badgePreviewUrl = badgeButton?.dataset.badgePreview;

    if (badgeButton && badgePreviewUrl) {
        const modal = document.getElementById('badge-preview-modal');
        const modalImage = document.getElementById('badge-preview-image');
        const modalName = document.getElementById('badge-preview-name');
        const modalClose = document.getElementById('badge-preview-close');

        const openModal = () => {
            modalImage.src = badgePreviewUrl;
            modalName.textContent = badgeButton.dataset.badgeName || '';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        badgeButton.addEventListener('click', openModal);
        modalClose.addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });
    } else if (badgeButton && badgeName) {
        badgeButton.addEventListener('click', () => {
            badgeName.classList.toggle('hidden');
        });
    }

    const select = document.getElementById('account-status-select');
    const saveButton = document.getElementById('account-status-save');
    const currentLabel = document.getElementById('status-current');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let initialStatus = select.value;

    saveButton.addEventListener('click', async () => {
        const newStatus = select.value;

        if (newStatus === initialStatus) return;

        let reason = null;
        if (newStatus === 'suspended') {
            const result = await Swal.fire({
                title: 'Suspend this user?',
                text: 'You can include an optional reason for the suspension.',
                icon: 'warning',
                input: 'textarea',
                inputLabel: 'Suspension reason',
                inputPlaceholder: 'Enter a reason (optional)',
                showCancelButton: true,
                confirmButtonText: 'Suspend user',
                confirmButtonColor: '#DC131C',
                cancelButtonColor: '#4B5563',
                background: '#1a1a1a',
                color: '#ffffff',
            });

            if (!result.isConfirmed) {
                select.value = initialStatus;
                return;
            }
            reason = result.value?.trim() || null;
        } else {
            const result = await Swal.fire({
                title: 'Change user status?',
                text: `This user's status will be changed to "${newStatus}".`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, change it',
                confirmButtonColor: '#DC131C',
                cancelButtonColor: '#4B5563',
                background: '#1a1a1a',
                color: '#ffffff',
            });

            if (!result.isConfirmed) {
                select.value = initialStatus;
                return;
            }
        }

        saveButton.disabled = true;
        try {
            const response = await fetch(window.location.pathname + '/status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ status: newStatus, reason }),
            });

            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(result.message || 'Unable to update status.');
            }

            currentLabel.textContent = result.status_label;
            currentLabel.style.color = result.status_color;
            initialStatus = newStatus;
        } catch (error) {
            await Swal.fire({
                title: 'Status update failed',
                text: error.message,
                icon: 'error',
                confirmButtonColor: '#DC131C',
                background: '#1a1a1a',
                color: '#ffffff',
            });
            select.value = initialStatus;
        } finally {
            saveButton.disabled = false;
        }
    });
});
</script>
@endsection
