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
        @if($user->verification_level !== 'none')
        <svg class="text-[#358ffc]" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="badge-check" class="lucide lucide-badge-check inline-block text-primary-500 fill-primary-500/20 size-5"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"></path><path d="m9 12 2 2 4-4"></path></svg>
        @endif
        <h6 class="text-[12px]">{{ $user->badge_label }}</h6>
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
                </svg> <span class="align-middle">{{ $user->address ?: 'Unknown location' }}</span></li>
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
                        <img src="https://api.iconify.design/lucide/star.svg?color=%23facc15" class="w-3.5 h-3.5" alt=""> {{ $averageRating !== null ? number_format($averageRating, 1) : '—' }}
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
        </div>

        <div class="mt-5 bg-[#000] rounded-3xl p-5 text-white shadow-lg">
            @if($subscription)
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div class="text-left">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <p class="font-bold text-white">Purchased Plan</p>
                        <span class="px-3 py-1 rounded-full text-[11px] font-medium" style="background:{{ $subscription->status_color }}1a; color:{{ $subscription->status_color }}; border:1px solid {{ $subscription->status_color }}4d;">{{ $subscription->status_label }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0" style="background:{{ $subscriptionPlan->color ?? '#DC131C' }};">
                            <i class="fa-solid fa-crown text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-extrabold text-white">{{ $subscriptionPlan->name ?? $subscription->plan_label }}</h3>
                            <p class="text-xs text-slate-500">{{ $subscription->billing_cycle === 'trial' ? 'Trial period' : 'Monthly subscription' }}</p>
                        </div>
                    </div>
                </div>

                <div class="text-left md:text-right">
                    <p class="text-2xl font-extrabold text-white">${{ number_format((float) $subscription->price, 2) }}</p>
                    <p class="text-xs text-slate-500">{{ $subscription->billing_cycle === 'trial' ? 'trial' : 'per month' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-5">
                <div class="bg-[#1a1a1a] border border-[#1a1a1a] rounded-2xl px-4 py-3 text-left">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500">Started</p>
                    <p class="mt-1 text-sm font-semibold text-white">{{ $subscription->started_at?->format('d M, Y') ?? '—' }}</p>
                </div>
                <div class="bg-[#1a1a1a] border border-[#1a1a1a] rounded-2xl px-4 py-3 text-left">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ $subscription->cancelled_at ? 'Cancelled' : 'Renews' }}</p>
                    <p class="mt-1 text-sm font-semibold text-white">{{ ($subscription->cancelled_at ?? $subscription->renews_at)?->format('d M, Y') ?? '—' }}</p>
                </div>
                <div class="bg-[#1a1a1a] border border-[#1a1a1a] rounded-2xl px-4 py-3 text-left">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500">Billing Cycle</p>
                    <p class="mt-1 text-sm font-semibold text-white">{{ ucfirst($subscription->billing_cycle) }}</p>
                </div>
            </div>

            @if(!empty($subscriptionPlan?->features))
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach($subscriptionPlan->features as $feature)
                <span class="flex items-center gap-2 rounded-full bg-[#1a1a1a] px-3 py-1.5 text-xs text-white">
                    <i class="fa-regular fa-circle-check text-green-400"></i>
                    {{ $feature }}
                </span>
                @endforeach
            </div>
            @endif
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
                <div class="rounded-2xl shadow-md p-4 max-w-xs text-left">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:{{ $review->reviewer?->avatar_color ?? '#6b7280' }};">
                                {{ $review->reviewer?->initials ?? '?' }}
                            </div>
                            <div>
                                <p class="font-semibold text-white text-sm">{{ $review->reviewer?->name ?? 'Anonymous' }}</p>
                                <div class="flex text-yellow-400 text-xs">
                                    {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                                </div>
                            </div>
                        </div>
                        <span class="text-xs text-white">{{ $review->created_at?->format('M j') }}</span>
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
                    <p class="text-sm font-semibold text-white truncate">{{ $otherParty?->name ?? 'Unknown' }}</p>
                    <p class="text-xs text-slate-500">{{ $meeting->meeting_date?->format('M j') ?? '—' }} · {{ $meeting->location ?: 'Location unavailable' }}</p>
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
