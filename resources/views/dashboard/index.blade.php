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
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 700;
  }
</style>
@endsection

@section('content')
@php
  $metrics = [
    ['label' => 'Total Users', 'value' => number_format($totalUsers), 'change' => 'Live', 'trend' => 'neutral', 'detail' => 'Registered users', 'icon' => 'fa-users', 'tone' => 'info'],
    ['label' => 'Active Meetings', 'value' => number_format($activeMeetings), 'change' => 'Live', 'trend' => 'neutral', 'detail' => 'Live and scheduled sessions', 'icon' => 'fa-calendar-check', 'tone' => 'success'],
    ['label' => 'Verifications', 'value' => number_format($verificationCount), 'change' => 'Live', 'trend' => 'neutral', 'detail' => 'Submitted identity checks', 'icon' => 'fa-shield-halved', 'tone' => 'warning'],
    ['label' => 'SOS Events', 'value' => number_format($openSosCount), 'change' => 'Live', 'trend' => 'neutral', 'detail' => 'Open safety incidents', 'icon' => 'fa-triangle-exclamation', 'tone' => 'danger'],
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

  <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
    @foreach ($metrics as $metric)
      @php
        $toneMap = [
          'info' => ['icon' => 'rgba(41, 156, 219, .15)', 'text' => '#299cdb'],
          'success' => ['icon' => 'rgba(10, 179, 156, .15)', 'text' => '#0ab39c'],
          'warning' => ['icon' => 'rgba(247, 184, 75, .15)', 'text' => '#f7b84b'],
          'danger' => ['icon' => 'rgba(240, 101, 72, .15)', 'text' => '#f06548'],
        ][$metric['tone']];
        $changeClass = $metric['trend'] === 'neutral'
          ? 'text-[#8f98ad] bg-[#8f98ad]/10'
          : ($metric['trend'] === 'up' ? 'text-[#0ab39c] bg-[#0ab39c]/10' : 'text-[#f06548] bg-[#f06548]/10');
        $trendIcon = $metric['trend'] === 'neutral'
          ? 'fa-database'
          : ($metric['trend'] === 'up' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down');
      @endphp

      <article class="dashboard-card flex h-full flex-col justify-between gap-4 p-5">
        <div class="flex items-center justify-between gap-3">
          <p class="dashboard-muted truncate text-sm font-medium">{{ $metric['label'] }}</p>
          <div class="metric-icon shrink-0" style="background: {{ $toneMap['icon'] }}; color: {{ $toneMap['text'] }};">
            <i class="fa-solid {{ $metric['icon'] }}"></i>
          </div>
        </div>

        <div>
          <h2 class="dashboard-title whitespace-nowrap text-3xl font-semibold tracking-normal">{{ $metric['value'] }}</h2>
          <span class="mt-2 inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-semibold {{ $changeClass }}">
            <i class="fa-solid {{ $trendIcon }}"></i>
            {{ $metric['change'] }}
          </span>
        </div>

        <p class="dashboard-muted line-clamp-2 text-xs leading-relaxed">{{ $metric['detail'] }}</p>
      </article>
    @endforeach
  </div>

  <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
    <section class="dashboard-card p-5 xl:col-span-8">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h3 class="dashboard-title text-base font-semibold">Engagement Overview</h3>
          <p class="dashboard-muted mt-1 text-sm">Users, meetings, revenue, and safety activity.</p>
        </div>

        <div class="inline-flex w-fit rounded-md border border-[#1a1a1a] bg-[#1a1a1a] p-1 text-xs font-semibold text-[#8f98ad]">
          <button class="rounded bg-[#000] px-3 py-1.5 text-white">ALL</button>
          <button class="rounded px-3 py-1.5 hover:text-white">1M</button>
          <button class="rounded px-3 py-1.5 hover:text-white">6M</button>
          <button class="rounded px-3 py-1.5 hover:text-white">1Y</button>
        </div>
      </div>

      <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="rounded-md bg-[#1a1a1a] p-3">
          <p class="dashboard-muted text-xs">Users</p>
          <p class="dashboard-title mt-1 text-lg font-semibold">{{ number_format($totalUsers) }}</p>
        </div>
        <div class="rounded-md bg-[#1a1a1a] p-3">
          <p class="dashboard-muted text-xs">Meetings</p>
          <p class="dashboard-title mt-1 text-lg font-semibold">{{ number_format($meetingCount) }}</p>
        </div>
        <div class="rounded-md bg-[#1a1a1a] p-3">
          <p class="dashboard-muted text-xs">Revenue</p>
          <p class="dashboard-title mt-1 text-lg font-semibold">$42.8k</p>
        </div>
        <div class="rounded-md bg-[#1a1a1a] p-3">
          <p class="dashboard-muted text-xs">Risk Alerts</p>
          <p class="dashboard-title mt-1 text-lg font-semibold">{{ number_format($incidentCount) }}</p>
        </div>
      </div>

      <div class="chart-frame mt-5">
        <canvas id="engagementChart"></canvas>
      </div>
    </section>

    <section class="dashboard-card p-5 xl:col-span-4">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h3 class="dashboard-title text-base font-semibold">Safety by Location</h3>
          <p class="dashboard-muted mt-1 text-sm">Verified activity coverage.</p>
        </div>
        <button class="rounded-md border border-[#252b3b] px-3 py-2 text-xs font-semibold text-[#cbd2e1] hover:text-white">
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
              <span class="text-[#cbd2e1]">{{ $location['name'] }}</span>
              <span class="dashboard-muted">{{ $location['value'] }}%</span>
            </div>
            <div class="progress-track">
              <span class="progress-fill" style="width: {{ $location['value'] }}%; background: {{ $location['color'] }};"></span>
            </div>
          </div>
        @endforeach
      </div>
    </section>
  </div>

  <section class="dashboard-card overflow-hidden">
    <div class="flex flex-col gap-3 p-5 md:flex-row md:items-center md:justify-between">
      <div>
        <h3 class="dashboard-title text-base font-semibold">Recent Meeting List</h3>
        <p class="dashboard-muted mt-1 text-sm">Latest meetings with safety status and trust score.</p>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full min-w-[980px] text-left">
        <thead class="bg-[#1a1a1a] text-xs uppercase text-[#8f98ad]">
          <tr>
            <th class="px-5 py-3 font-semibold">Meeting ID</th>
            <th class="px-5 py-3 font-semibold">Host</th>
            <th class="px-5 py-3 font-semibold">Guest</th>
            <th class="px-5 py-3 font-semibold">Location</th>
            <th class="px-5 py-3 font-semibold">Date & Time</th>
            <th class="px-5 py-3 font-semibold">Type</th>
            <th class="px-5 py-3 font-semibold">Trust</th>
            <th class="px-5 py-3 font-semibold">Status</th>
            <th class="px-5 py-3 text-right font-semibold">Action</th>
          </tr>
        </thead>
        <tbody class="text-sm">
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
                'success' => 'bg-[#0ab39c]/10 text-[#0ab39c]',
                'warning' => 'bg-[#f7b84b]/10 text-[#f7b84b]',
                'danger' => 'bg-[#f06548]/10 text-[#f06548]',
                'info' => 'bg-[#299cdb]/10 text-[#299cdb]',
              ][$meetingTone];
              $hostName = $meeting->host?->name ?: $meeting->host?->display_name ?: 'Unknown host';
              $guestName = $meeting->guest?->name ?: $meeting->guest?->display_name ?: 'Unknown guest';
              $meetingDate = $meeting->scheduled_start_at ?: $meeting->meeting_date;
              $meetingTime = $meeting->scheduled_start_at
                ? $meeting->scheduled_start_at->format('h:i A')
                : ($meeting->meeting_time ? date('h:i A', strtotime($meeting->meeting_time)) : 'Time not set');
            @endphp
            <tr class="table-row">
              <td class="px-5 py-4 font-semibold text-[#DC131C]">#{{ $meeting->reference ?: $meeting->id }}</td>
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  <div class="avatar-mark bg-[#1a1a1a] text-[#cbd2e1]">
                    {{ $meeting->host?->initials ?? '?' }}
                  </div>
                  <span class="dashboard-title font-medium">{{ $hostName }}</span>
                </div>
              </td>
              <td class="px-5 py-4 text-[#cbd2e1]">{{ $guestName }}</td>
              <td class="dashboard-muted px-5 py-4">{{ $meeting->planned_address ?: $meeting->location ?: 'Not specified' }}</td>
              <td class="px-5 py-4">
                <p class="text-[#cbd2e1]">{{ $meetingDate?->format('d M Y') ?? 'Date not set' }}</p>
                <p class="dashboard-muted mt-1 text-xs">{{ $meetingTime }}</p>
              </td>
              <td class="px-5 py-4 text-[#cbd2e1]">{{ str($meeting->type ?: 'other')->replace('_', ' ')->title() }}</td>
              <td class="px-5 py-4">
                <span class="inline-flex items-center gap-1 text-[#0ab39c]">
                  <i class="fa-solid fa-shield-halved text-xs"></i>
                  {{ $meeting->trust_score_snapshot !== null ? round($meeting->trust_score_snapshot).'%' : '—' }}
                </span>
              </td>
              <td class="px-5 py-4">
                <span class="rounded px-2 py-1 text-xs font-semibold {{ $meetingBadgeClass }}">{{ $meeting->status_label }}</span>
              </td>
              <td class="px-5 py-4 text-right whitespace-nowrap">
                @if ($meeting->host)
                  <a href="{{ route('users.show', $meeting->host->id) }}" class="inline-flex items-center whitespace-nowrap rounded-md border border-[#252b3b] px-3 py-1.5 text-xs font-semibold text-[#cbd2e1] hover:text-white">View Host</a>
                @else
                  <span class="dashboard-muted">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr class="table-row">
              <td colspan="9" class="dashboard-muted px-5 py-8 text-center">No meetings found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
    <section class="dashboard-card overflow-hidden xl:col-span-8">
      <div class="flex flex-col gap-3 p-5 md:flex-row md:items-center md:justify-between">
        <div>
          <h3 class="dashboard-title text-base font-semibold">Recent User List</h3>
          <p class="dashboard-muted mt-1 text-sm">Newest registered users and their account status.</p>
        </div>
        <a href="{{ route('users') }}" class="w-fit rounded-md border border-[#252b3b] px-3 py-2 text-sm font-semibold text-[#cbd2e1] hover:text-white">View All Users</a>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-left">
          <thead class="bg-[#1a1a1a] text-xs uppercase text-[#8f98ad]">
            <tr>
              <th class="px-5 py-3 font-semibold">User</th>
              <th class="px-5 py-3 font-semibold">Joined</th>
              <th class="px-5 py-3 font-semibold">Verification</th>
              <th class="px-5 py-3 font-semibold">Trust Score</th>
              <th class="px-5 py-3 font-semibold">Status</th>
              <th class="px-5 py-3 text-right font-semibold">Action</th>
            </tr>
          </thead>
          <tbody class="text-sm">
            @forelse ($recentUsers as $user)
              <tr class="table-row">
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="avatar-mark text-white" style="background: {{ $user->avatar_color }};">
                      {{ $user->initials }}
                    </div>
                    <div>
                      <p class="dashboard-title font-medium">{{ $user->name ?: $user->display_name ?: 'Unnamed User' }}</p>
                      <p class="dashboard-muted mt-1 text-xs">{{ $user->email ?: $user->phone ?: 'No contact details' }}</p>
                    </div>
                  </div>
                </td>
                <td class="dashboard-muted px-5 py-4">{{ $user->created_at?->format('d M Y') ?? '—' }}</td>
                <td class="px-5 py-4">
                  <span class="rounded px-2 py-1 text-xs font-semibold" style="background: {{ $user->verification_color }}1a; color: {{ $user->verification_color }};">{{ $user->verification_label }}</span>
                </td>
                <td class="px-5 py-4 text-[#cbd2e1]">{{ $user->trust_score !== null ? round($user->trust_score) : '—' }}</td>
                <td class="px-5 py-4">
                  <span class="rounded px-2 py-1 text-xs font-semibold" style="background: {{ $user->status_color }}1a; color: {{ $user->status_color }};">{{ $user->status_label }}</span>
                </td>
                <td class="px-5 py-4 text-right">
                  <a href="{{ route('users.show', $user->id) }}" class="rounded-md border border-[#252b3b] px-3 py-1.5 text-xs font-semibold text-[#cbd2e1] hover:text-white">View</a>
                </td>
              </tr>
            @empty
              <tr class="table-row">
                <td colspan="6" class="dashboard-muted px-5 py-8 text-center">No users found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <section class="dashboard-card p-5 xl:col-span-4">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h3 class="dashboard-title text-base font-semibold">Top Hosts</h3>
          <p class="dashboard-muted mt-1 text-sm">Highest performing verified hosts.</p>
        </div>
        <button class="text-sm font-semibold text-[#299cdb] hover:text-[#58b6ea]">Report</button>
      </div>

      <div class="mt-5 space-y-4">
        @forelse ($topHosts as $host)
          <div class="rounded-md border border-[#1a1a1a] bg-[#1a1a1a] p-4">
            <div class="flex items-center gap-3">
              <div class="avatar-mark text-white" style="background: {{ $host->avatar_color }};">
                {{ $host->initials }}
              </div>
              <div class="min-w-0">
                <p class="dashboard-title truncate text-sm font-semibold">{{ $host->name ?: $host->display_name ?: 'Unnamed User' }}</p>
                <p class="dashboard-muted truncate text-xs">{{ str($host->account_type ?: 'Member')->replace('_', ' ')->title() }}</p>
              </div>
              <span class="ml-auto rounded bg-[#0ab39c]/10 px-2 py-1 text-xs font-semibold text-[#0ab39c]">{{ $host->rating !== null ? number_format($host->rating, 1).'/5' : 'Not rated' }}</span>
            </div>
            <div class="mt-4 flex items-center justify-between text-sm">
              <span class="dashboard-muted">Meetings hosted</span>
              <span class="dashboard-title font-semibold">{{ number_format($host->meetings_count) }}</span>
            </div>
          </div>
        @empty
          <p class="dashboard-muted py-6 text-center text-sm">No hosts found.</p>
        @endforelse
      </div>
    </section>
  </div>
</div>
@endsection

@section('scripts')
<script>
  const chartGridColor = '#252b3b';
  const chartLabelColor = '#8f98ad';

  const engagementCanvas = document.getElementById('engagementChart');
  if (engagementCanvas) {
    const engagementGradient = engagementCanvas.getContext('2d').createLinearGradient(0, 0, 0, 320);
    engagementGradient.addColorStop(0, 'rgba(10, 179, 156, 0.26)');
    engagementGradient.addColorStop(1, 'rgba(10, 179, 156, 0.02)');

    new Chart(engagementCanvas, {
      type: 'line',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [
          {
            label: 'Users',
            data: [18, 22, 28, 31, 37, 42, 45, 48, 52, 57, 61, 67],
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
            data: [12, 17, 16, 22, 24, 29, 33, 35, 39, 42, 45, 49],
            borderColor: '#299cdb',
            backgroundColor: 'transparent',
            borderWidth: 2.5,
            tension: 0.38,
            pointRadius: 0,
            pointHoverRadius: 5
          },
          {
            label: 'Incidents',
            data: [6, 5, 7, 6, 4, 5, 3, 4, 2, 3, 2, 1],
            borderColor: '#f06548',
            backgroundColor: 'transparent',
            borderWidth: 2,
            tension: 0.38,
            pointRadius: 0,
            pointHoverRadius: 5
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
            bodyColor: '#cbd2e1'
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
          }
        }
      }
    });
  }

  const locationCanvas = document.getElementById('locationChart');
  if (locationCanvas) {
    new Chart(locationCanvas, {
      type: 'doughnut',
      data: {
        labels: ['Verified', 'Pending', 'Flagged', 'Escalated'],
        datasets: [
          {
            data: [62, 18, 12, 8],
            backgroundColor: ['#0ab39c', '#299cdb', '#f7b84b', '#f06548'],
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
