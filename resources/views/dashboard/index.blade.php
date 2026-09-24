@extends('layouts.app')

@section('title', 'Dashboard')

@section('styles')
<style>
  .dashboard-shell {
    --panel: #000;
    --panel-soft: #111722;
    --panel-border: #000;
    --muted: #8f98ad;
    --heading: #f5f7fb;
    --danger: #f06548;
    --success: #0ab39c;
    --warning: #f7b84b;
    --info: #299cdb;
  }
 

  .dashboard-card {
    background: var(--panel);
    border: 1px solid var(--panel-border);
    border-radius: 8px;
    box-shadow: 0 14px 28px rgba(0, 0, 0, 0.14);
  }

  .dashboard-muted {
    color: var(--muted);
  }

  .dashboard-title {
    color: var(--heading);
  }

  .metric-icon {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    display: grid;
    place-items: center;
  }

  .chart-frame {
    height: 320px;
    min-height: 320px;
  }

  #engagementChartFrame {
    height: 560px !important;
    min-height: 560px !important;
  }

  .mini-chart-frame {
    height: 218px;
    min-height: 218px;
  }

  .progress-track {
    height: 6px;
    border-radius: 999px;
    background: #262c3c;
    overflow: hidden;
  }

  .progress-fill {
    display: block;
    height: 100%;
    border-radius: inherit;
  }

  .table-row {
    border-top: 1px solid #252b3b;
  }

  .avatar-mark {
    width: 36px;
    height: 36px;
    border-radius: 9999px;
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.06);
    flex-shrink: 0;
  }
</style>
@endsection

@section('content')
@php
  $metrics = [
    ['label' => 'Total Users', 'value' => number_format($totalUsers), 'change' => 'Live', 'trend' => 'neutral', 'detail' => 'Registered users', 'icon' => 'fa-users', 'tone' => 'info'],
    ['label' => 'Active Meetings', 'value' => number_format($activeMeetings), 'change' => 'Live', 'trend' => 'neutral', 'detail' => 'Live and scheduled sessions', 'icon' => 'fa-calendar-check', 'tone' => 'success'],
    ['label' => 'Identity Verifications', 'value' => number_format($verificationCount), 'change' => 'Live', 'trend' => 'neutral', 'detail' => 'Submitted identity checks', 'icon' => 'fa-shield-halved', 'tone' => 'warning'],
    ['label' => 'Criminal Verifications', 'value' => number_format($criminalVerificationCount), 'change' => 'Live', 'trend' => 'neutral', 'detail' => 'Level 2 verified users', 'icon' => 'fa-user-shield', 'tone' => 'info'],
    ['label' => 'Total Subscribers', 'value' => number_format($totalSubscribers), 'change' => 'Live', 'trend' => 'neutral', 'detail' => 'Active and past subscriptions', 'icon' => 'fa-crown', 'tone' => 'danger'],
  ];

  $locations = [
    ['name' => 'Bengaluru', 'value' => 82, 'color' => '#0ab39c'],
    ['name' => 'Mumbai', 'value' => 74, 'color' => '#299cdb'],
    ['name' => 'Delhi NCR', 'value' => 61, 'color' => '#f7b84b'],
    ['name' => 'Hyderabad', 'value' => 48, 'color' => '#f06548'],
  ];

@endphp

<div class="dashboard-shell space-y-6">
  <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <h1 class="dashboard-title text-[22px] font-semibold tracking-normal">Good Morning, @yield('dashboard-role', 'Admin')!</h1>
      <p class="dashboard-muted mt-1 text-sm">Here is what is happening with SafeeMeet today.</p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <button class="inline-flex items-center gap-2 rounded-md border border-[#000] bg-[#000] px-3 py-2 text-sm font-medium text-[#cbd2e1] transition">
        <i class="fa-regular fa-calendar"></i>
        {{ now()->format('d M, Y') }}
      </button>
      <button class="inline-flex items-center gap-2 rounded-md bg-[#DC131C] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#d9573e]">
        <i class="fa-solid fa-plus"></i>
        Add User
      </button>
    </div>
  </div>

  <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    @php
      $toneMap = [
        'info' => 'text-blue-400 bg-blue-500/15',
        'success' => 'text-green-400 bg-green-500/15',
        'warning' => 'text-amber-400 bg-amber-500/15',
        'danger' => 'text-red-400 bg-red-500/15',
      ];
    @endphp
    @foreach ($metrics as $metric)
      <div class="rounded-xl border border-[#2a2d3e] bg-black p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-400">{{ $metric['label'] }}</p>
            <p class="mt-2 text-2xl font-bold text-white">{{ $metric['value'] }}</p>
          </div>
          <div class="flex h-11 w-11 items-center justify-center rounded-lg {{ $toneMap[$metric['tone']] }}">
            <i class="fa-solid {{ $metric['icon'] }}"></i>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
    <section id="engagementSection" class="rounded-xl border border-[#2a2d3e] bg-black p-5 xl:col-span-12">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h3 class="text-base font-semibold text-white">Engagement Overview</h3>
          <p class="mt-1 text-sm text-gray-400">Users, meetings, revenue, and safety activity.</p>
        </div>

        <div id="engagementRangeToggle" class="inline-flex w-fit rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] p-1 text-xs font-semibold text-gray-400">
          <button type="button" data-range="day" class="rounded px-3 py-1.5 hover:text-white">Day</button>
          <button type="button" data-range="month" class="rounded bg-[#DC131C] px-3 py-1.5 text-white">Month</button>
          <button type="button" data-range="year" class="rounded px-3 py-1.5 hover:text-white">Year</button>
        </div>
      </div>

      <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] p-3">
          <p class="text-xs text-gray-400">Users</p>
          <p class="mt-1 text-lg font-semibold text-white">{{ number_format($totalUsers) }}</p>
        </div>
        <div class="rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] p-3">
          <p class="text-xs text-gray-400">Meetings</p>
          <p class="mt-1 text-lg font-semibold text-white">{{ number_format($meetingCount) }}</p>
        </div>
        <div class="rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] p-3">
          <p class="text-xs text-gray-400">Revenue</p>
          <p class="mt-1 text-lg font-semibold text-white">${{ number_format(array_sum($engagementTrend['month']['revenue']), 2) }}</p>
        </div>
        <div class="rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] p-3">
          <p class="text-xs text-gray-400">Risk Alerts</p>
          <p class="mt-1 text-lg font-semibold text-white">{{ number_format($incidentCount) }}</p>
        </div>
      </div>

      <div id="engagementChartFrame" class="chart-frame mt-5">
        <canvas id="engagementChart"></canvas>
      </div>
    </section>

    <!-- <section class="rounded-xl border border-[#2a2d3e] bg-black p-5 xl:col-span-4">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h3 class="text-base font-semibold text-white">Safety by Location</h3>
          <p class="mt-1 text-sm text-gray-400">Verified activity coverage.</p>
        </div>
        <button class="rounded-lg border border-[#343746] px-3 py-2 text-xs font-semibold text-gray-300 hover:text-white">
          Export
        </button>
      </div>

      <div class="mini-chart-frame mt-4">
        <canvas id="locationChart"></canvas>
      </div>

      <div class="mt-5 space-y-4">
        @foreach ($locations as $location)
          <div>
            <div class="mb-2 flex items-center justify-between text-sm">
              <span class="text-gray-300">{{ $location['name'] }}</span>
              <span class="text-gray-400">{{ $location['value'] }}%</span>
            </div>
            <div class="progress-track">
              <span class="progress-fill" style="width: {{ $location['value'] }}%; background: {{ $location['color'] }};"></span>
            </div>
          </div>
        @endforeach
      </div>
    </section> -->
  </div>



  <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
    <section class="rounded-xl border border-[#2a2d3e] bg-black overflow-hidden xl:col-span-8">
      <div class="flex flex-col gap-3 p-5 md:flex-row md:items-center md:justify-between">
        <div>
          <h3 class="text-base font-semibold text-white">Recent User List</h3>
          <p class="mt-1 text-sm text-gray-400">Newest registered users and their account status.</p>
        </div>
        <a href="{{ route('users') }}" class="w-fit rounded-lg border border-[#343746] px-3 py-2 text-sm font-semibold text-gray-300 hover:text-white">View All Users</a>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] border-collapse text-[13px]">
          <thead>
            <tr class="border-b border-[#2a2d3e] text-left text-xs uppercase tracking-wide text-red-500">
              <th class="px-5 py-4 font-semibold">User</th>
              <th class="px-5 py-4 font-semibold">Joined</th>
              <th class="px-5 py-4 font-semibold">Verification</th>
              <th class="px-5 py-4 font-semibold">Trust Score</th>
              <th class="px-5 py-4 font-semibold">Status</th>
              <th class="px-5 py-4 text-right font-semibold">Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($recentUsers as $user)
              <tr class="border-b border-[#2a2d3e] last:border-b-0">
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="avatar-mark text-white" style="background: {{ $user->avatar_color }};">
                      {{ $user->initials }}
                    </div>
                    <div>
                      <p class="font-semibold text-white">{{ $user->name ?: $user->display_name ?: 'Unnamed User' }}</p>
                      <p class="mt-1 text-xs text-gray-500">{{ $user->email ?: $user->phone ?: 'No contact details' }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4 text-gray-400">{{ $user->created_at?->format('d M Y') ?? '—' }}</td>
                <td class="px-5 py-4">
                  <span class="rounded-full px-3 py-1 text-xs font-medium" style="background: {{ $user->verification_color }}26; color: {{ $user->verification_color }};">{{ $user->verification_label }}</span>
                </td>
                <td class="px-5 py-4 text-gray-300">{{ $user->trust_score !== null ? round($user->trust_score) : '—' }}</td>
                <td class="px-5 py-4">
                  <span class="rounded-full px-3 py-1 text-xs font-medium" style="background: {{ $user->status_color }}26; color: {{ $user->status_color }};">{{ $user->status_label }}</span>
                </td>
                <td class="px-5 py-4 text-right">
                  <a href="{{ route('users.show', $user->id) }}" class="rounded-md border border-[#343746] px-3 py-1.5 text-xs font-semibold text-gray-300 transition hover:border-blue-400 hover:text-blue-300">
                    <i class="fa-solid fa-eye mr-1"></i>View
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="px-5 py-10 text-center text-gray-500">No users found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <!-- <section class="rounded-xl border border-[#2a2d3e] bg-black p-5 xl:col-span-4">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h3 class="text-base font-semibold text-white">Top Hosts</h3>
          <p class="mt-1 text-sm text-gray-400">Highest performing verified hosts.</p>
        </div>
        <button class="text-sm font-semibold text-red-400 hover:text-red-300">Report</button>
      </div>

      <div class="mt-5 space-y-4">
        @forelse ($topHosts as $host)
          <div class="rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] p-4">
            <div class="flex items-center gap-3">
              <div class="avatar-mark text-white" style="background: {{ $host->avatar_color }};">
                {{ $host->initials }}
              </div>
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-white">{{ $host->name ?: $host->display_name ?: 'Unnamed User' }}</p>
                <p class="truncate text-xs text-gray-400">{{ str($host->account_type ?: 'Member')->replace('_', ' ')->title() }}</p>
              </div>
              <span class="ml-auto rounded-full bg-green-500/15 px-2 py-1 text-xs font-semibold text-green-400">{{ $host->rating !== null ? number_format($host->rating, 1).'/5' : 'Not rated' }}</span>
            </div>
            <div class="mt-4 flex items-center justify-between text-sm">
              <span class="text-gray-400">Meetings hosted</span>
              <span class="font-semibold text-white">{{ number_format($host->meetings_count) }}</span>
            </div>
          </div>
        @empty
          <p class="py-6 text-center text-sm text-gray-500">No hosts found.</p>
        @endforelse
      </div>
    </section> -->
    <section class="rounded-xl border border-[#2a2d3e] bg-black p-5 xl:col-span-4">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h3 class="text-base font-semibold text-white">Subscribers by Plan</h3>
          <p class="mt-1 text-sm text-gray-400">Total users subscribed per plan.</p>
        </div>
        <button class="rounded-lg border border-[#343746] px-3 py-2 text-xs font-semibold text-gray-300 hover:text-white">
          Export
        </button>
      </div>

      <div class="mini-chart-frame mt-4">
        <canvas id="locationChart"></canvas>
      </div>

      <div class="mt-5 space-y-4">
        @forelse ($planSubscriberCounts as $plan)
          <div>
            <div class="mb-2 flex items-center justify-between text-sm">
              <span class="text-gray-300">{{ $plan['name'] }}</span>
              <span class="text-gray-400">{{ number_format($plan['count']) }} ({{ $plan['value'] }}%)</span>
            </div>
            <div class="progress-track">
              <span class="progress-fill" style="width: {{ $plan['value'] }}%; background: {{ $plan['color'] }};"></span>
            </div>
          </div>
        @empty
          <p class="text-sm text-gray-500">No subscription plans found.</p>
        @endforelse
      </div>
    </section>
  </div>

  <section class="rounded-xl border border-[#2a2d3e] bg-black overflow-hidden">
    <div class="flex flex-col gap-3 p-5 md:flex-row md:items-center md:justify-between">
      <div>
        <h3 class="text-base font-semibold text-white">Latest 5 Transactions</h3>
        <p class="mt-1 text-sm text-gray-400">Most recent subscription payments across all users.</p>
      </div>
      <a href="{{ route('revenue') }}" class="w-fit rounded-lg border border-[#343746] px-3 py-2 text-sm font-semibold text-gray-300 hover:text-white">View All Transactions</a>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full min-w-[1180px] border-collapse text-left text-[13px]">
        <thead>
          <tr class="border-b border-[#2a2d3e] text-xs uppercase tracking-wide text-red-500">
            <th class="px-5 py-4">Username</th>
            <th class="px-5 py-4">Plan Name</th>
            <th class="px-5 py-4">Billing Cycle</th>
            <th class="px-5 py-4">Price</th>
            <th class="px-5 py-4">Stripe Customer ID</th>
            <th class="px-5 py-4">Stripe Subscription ID</th>
            <th class="px-5 py-4">Payment Status</th>
            <th class="px-5 py-4">Subscription Status</th>
            <th class="px-5 py-4">Payment Date</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($recentTransactions as $transaction)
            @php
              $statusClasses = match ($transaction->payment_status) {
                'succeeded', 'paid', 'successful', 'completed' => 'bg-green-500/15 text-green-400',
                'pending', 'processing', 'requires_action' => 'bg-yellow-500/15 text-yellow-400',
                'failed', 'declined', 'cancelled', 'canceled' => 'bg-red-500/15 text-red-400',
                'refunded', 'partially_refunded' => 'bg-purple-500/15 text-purple-400',
                default => 'bg-gray-500/15 text-gray-300',
              };
              $statusLabel = match ($transaction->payment_status) {
                'succeeded' => 'Paid / Successful',
                'no_payment' => 'No Payment',
                default => \Illuminate\Support\Str::headline($transaction->payment_status),
              };
              $currency = strtoupper($transaction->currency ?: 'USD');
              $price = number_format((float) $transaction->price, 2);
              $subStatusClasses = match ($transaction->subscription_status) {
                'active' => 'bg-green-500/15 text-green-400',
                'trial', 'incomplete' => 'bg-yellow-500/15 text-yellow-400',
                'expired', 'cancelled' => 'bg-red-500/15 text-red-400',
                default => 'bg-gray-500/15 text-gray-300',
              };
            @endphp
            <tr class="border-b border-[#2a2d3e] last:border-b-0">
              <td class="px-5 py-4 font-medium text-white">{{ $transaction->user_name ?: '—' }}</td>
              <td class="px-5 py-4 text-gray-300">{{ $transaction->plan_name ?: '—' }}</td>
              <td class="px-5 py-4 text-gray-300">{{ \Illuminate\Support\Str::headline($transaction->billing_cycle ?: '—') }}</td>
              <td class="px-5 py-4 font-medium text-white">{{ $currency === 'USD' ? '$'.$price : $currency.' '.$price }}</td>
              <td class="max-w-[180px] truncate px-5 py-4 font-mono text-xs text-gray-300" title="{{ $transaction->stripe_customer_id }}">{{ $transaction->stripe_customer_id ?: '—' }}</td>
              <td class="max-w-[180px] truncate px-5 py-4 font-mono text-xs text-gray-300" title="{{ $transaction->stripe_subscription_id }}">{{ $transaction->stripe_subscription_id ?: '—' }}</td>
              <td class="px-5 py-4">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $statusClasses }}">{{ $statusLabel }}</span>
              </td>
              <td class="px-5 py-4">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $subStatusClasses }}">{{ \Illuminate\Support\Str::headline($transaction->subscription_status ?: '—') }}</span>
              </td>
              <td class="whitespace-nowrap px-5 py-4 text-gray-400">{{ $transaction->transaction_date?->format('d M Y, h:i A') ?? '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="px-5 py-10 text-center text-gray-500">No transactions found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="rounded-xl border border-[#2a2d3e] bg-black overflow-hidden">
    <div class="flex flex-col gap-3 p-5 md:flex-row md:items-center md:justify-between">
      <div>
        <h3 class="text-base font-semibold text-white">Recent Meeting List</h3>
        <p class="mt-1 text-sm text-gray-400">Latest meetings with safety status and trust score.</p>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full min-w-[980px] border-collapse text-[13px]">
        <thead>
          <tr class="border-b border-[#2a2d3e] text-left text-xs uppercase tracking-wide text-red-500">
            <th class="px-5 py-4 font-semibold">Meeting ID</th>
            <th class="px-5 py-4 font-semibold">Host</th>
            <th class="px-5 py-4 font-semibold">Guest</th>
            <th class="px-5 py-4 font-semibold">Location</th>
            <th class="px-5 py-4 font-semibold">Date & Time</th>
            <th class="px-5 py-4 font-semibold">Type</th>
            <th class="px-5 py-4 font-semibold">Trust</th>
            <th class="px-5 py-4 font-semibold">Status</th>
            <th class="px-5 py-4 text-right font-semibold">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($meetings as $meeting)
            @php
              $meetingTone = match ($meeting->status) {
                'completed' => 'success',
                'scheduled', 'pending_approval' => 'warning',
                'active', 'live' => 'info',
                'cancelled', 'declined', 'emergency', 'incident_reported' => 'danger',
                default => 'info',
              };
              $meetingBadgeClass = [
                'success' => 'bg-green-500/15 text-green-400',
                'warning' => 'bg-amber-500/15 text-amber-400',
                'danger' => 'bg-red-500/15 text-red-400',
                'info' => 'bg-blue-500/15 text-blue-400',
              ][$meetingTone];
              $hostName = $meeting->host?->name ?: $meeting->host?->display_name ?: 'Unknown host';
              $guestName = $meeting->guest?->name ?: $meeting->guest?->display_name ?: 'Unknown guest';
              $meetingDate = $meeting->scheduled_start_at ?: $meeting->meeting_date;
              $meetingTime = $meeting->scheduled_start_at
                ? $meeting->scheduled_start_at->format('h:i A')
                : ($meeting->meeting_time ? date('h:i A', strtotime($meeting->meeting_time)) : 'Time not set');
            @endphp
            <tr class="border-b border-[#2a2d3e] last:border-b-0">
              <td class="px-5 py-4 font-semibold text-[#DC131C]">#{{ $meeting->reference ?: $meeting->id }}</td>
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  <div class="avatar-mark bg-[#1a1a1a] text-gray-300">
                    {{ $meeting->host?->initials ?? '?' }}
                  </div>
                  <span class="font-semibold text-white">{{ $hostName }}</span>
                </div>
              </td>
              <td class="px-5 py-4 text-gray-300">{{ $guestName }}</td>
              <td class="px-5 py-4 text-gray-400">{{ $meeting->planned_address ?: $meeting->location ?: 'Not specified' }}</td>
              <td class="px-5 py-4">
                <p class="text-gray-300">{{ $meetingDate?->format('d M Y') ?? 'Date not set' }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $meetingTime }}</p>
              </td>
              <td class="px-5 py-4 text-gray-300">{{ str($meeting->type ?: 'other')->replace('_', ' ')->title() }}</td>
              <td class="px-5 py-4">
                <span class="inline-flex items-center gap-1 text-green-400">
                  <i class="fa-solid fa-shield-halved text-xs"></i>
                  {{ $meeting->trust_score_snapshot !== null ? round($meeting->trust_score_snapshot).'%' : '—' }}
                </span>
              </td>
              <td class="px-5 py-4">
                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $meetingBadgeClass }}">{{ $meeting->status_label }}</span>
              </td>
              <td class="px-5 py-4 text-right whitespace-nowrap">
                @if ($meeting->host)
                  <a href="{{ route('users.show', $meeting->host->id) }}" class="inline-flex items-center whitespace-nowrap rounded-md border border-[#343746] px-3 py-1.5 text-xs font-semibold text-gray-300 transition hover:border-blue-400 hover:text-blue-300">
                    <i class="fa-solid fa-eye mr-1"></i>View Host
                  </a>
                @else
                  <span class="text-gray-500">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="px-5 py-10 text-center text-gray-500">No meetings found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection

@section('scripts')
<script>
  const chartGridColor = '#252b3b';
  const chartLabelColor = '#8f98ad';

  const engagementCanvas = document.getElementById('engagementChart');
  if (engagementCanvas) {
    const engagementTrends = @json($engagementTrend);
    const engagementGradient = engagementCanvas.getContext('2d').createLinearGradient(0, 0, 0, 320);
    engagementGradient.addColorStop(0, 'rgba(10, 179, 156, 0.26)');
    engagementGradient.addColorStop(1, 'rgba(10, 179, 156, 0.02)');

    const initialTrend = engagementTrends.month;

    const engagementChart = new Chart(engagementCanvas, {
      type: 'line',
      data: {
        labels: initialTrend.labels,
        datasets: [
          {
            label: 'Users',
            data: initialTrend.users,
            borderColor: '#0ab39c',
            backgroundColor: engagementGradient,
            borderWidth: 2.5,
            fill: true,
            tension: 0.38,
            pointRadius: 0,
            pointHoverRadius: 5
          },
          {
            label: 'Meetings',
            data: initialTrend.meetings,
            borderColor: '#299cdb',
            backgroundColor: 'transparent',
            borderWidth: 2.5,
            tension: 0.38,
            pointRadius: 0,
            pointHoverRadius: 5
          },
          {
            label: 'Incidents',
            data: initialTrend.incidents,
            borderColor: '#f06548',
            backgroundColor: 'transparent',
            borderWidth: 2,
            tension: 0.38,
            pointRadius: 0,
            pointHoverRadius: 5
          },
          {
            label: 'Revenue',
            data: initialTrend.revenue,
            borderColor: '#f7b84b',
            backgroundColor: 'transparent',
            borderWidth: 2.5,
            borderDash: [4, 3],
            tension: 0.38,
            pointRadius: 0,
            pointHoverRadius: 5,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        maintainAspectRatio: false,
        responsive: true,
        interaction: {
          intersect: false,
          mode: 'index'
        },
        plugins: {
          legend: {
            position: 'top',
            align: 'end',
            labels: {
              color: chartLabelColor,
              boxWidth: 10,
              boxHeight: 10,
              usePointStyle: true
            }
          },
          tooltip: {
            backgroundColor: '#111722',
            borderColor: '#252b3b',
            borderWidth: 1,
            titleColor: '#f5f7fb',
            bodyColor: '#cbd2e1',
            callbacks: {
              label: function (context) {
                if (context.dataset.label === 'Revenue') {
                  return 'Revenue: $' + context.parsed.y.toFixed(2);
                }
                return context.dataset.label + ': ' + context.parsed.y;
              }
            }
          }
        },
        scales: {
          x: {
            grid: { color: 'transparent' },
            ticks: { color: chartLabelColor }
          },
          y: {
            beginAtZero: true,
            grid: { color: chartGridColor },
            ticks: { color: chartLabelColor }
          },
          y1: {
            position: 'right',
            beginAtZero: true,
            grid: { drawOnChartArea: false },
            ticks: {
              color: chartLabelColor,
              callback: function (value) { return '$' + value; }
            }
          }
        }
      }
    });

    const engagementRangeToggle = document.getElementById('engagementRangeToggle');
    if (engagementRangeToggle) {
      engagementRangeToggle.addEventListener('click', function (event) {
        const button = event.target.closest('button[data-range]');
        if (!button) {
          return;
        }

        const range = button.dataset.range;
        const trend = engagementTrends[range];
        if (!trend) {
          return;
        }

        engagementRangeToggle.querySelectorAll('button[data-range]').forEach(function (btn) {
          btn.classList.remove('bg-[#DC131C]', 'text-white');
          btn.classList.add('hover:text-white');
        });
        button.classList.add('bg-[#DC131C]', 'text-white');
        button.classList.remove('hover:text-white');

        engagementChart.data.labels = trend.labels;
        engagementChart.data.datasets[0].data = trend.users;
        engagementChart.data.datasets[1].data = trend.meetings;
        engagementChart.data.datasets[2].data = trend.incidents;
        engagementChart.data.datasets[3].data = trend.revenue;
        engagementChart.update();
      });
    }
  }

  const locationCanvas = document.getElementById('locationChart');
  if (locationCanvas) {
    const planSubscriberCounts = @json($planSubscriberCounts);

    new Chart(locationCanvas, {
      type: 'doughnut',
      data: {
        labels: planSubscriberCounts.map((plan) => plan.name),
        datasets: [
          {
            data: planSubscriberCounts.map((plan) => plan.count),
            backgroundColor: planSubscriberCounts.map((plan) => plan.color),
            borderColor: '#151a25',
            borderWidth: 4,
            hoverOffset: 4
          }
        ]
      },
      options: {
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: chartLabelColor,
              boxWidth: 10,
              boxHeight: 10,
              usePointStyle: true
            }
          },
          tooltip: {
            backgroundColor: '#111722',
            borderColor: '#252b3b',
            borderWidth: 1,
            titleColor: '#f5f7fb',
            bodyColor: '#cbd2e1'
          }
        }
      }
    });
  }
</script>
@endsection
