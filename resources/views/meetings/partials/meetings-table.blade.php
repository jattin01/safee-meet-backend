<div class="overflow-x-auto">
    <table class="w-full min-w-[1180px] border-collapse text-left text-[13px]">
        <thead>
            <tr class="border-b border-[#2a2d3e] text-xs uppercase tracking-wide text-red-500">
                <th class="px-5 py-4">S.No.</th>
                <th class="px-5 py-4">Reference</th>
                <th class="px-5 py-4">Title</th>
                <th class="px-5 py-4">User (Guest)</th>
                <th class="px-5 py-4">User Contact</th>
                <th class="px-5 py-4">Host</th>
                <th class="px-5 py-4">Host Contact</th>
                <th class="px-5 py-4">Meeting Date &amp; Time</th>
                <th class="px-5 py-4">Location</th>
                <th class="px-5 py-4">Status</th>
                <th class="px-5 py-4">Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse($meetings as $meeting)
                @php
                    $statusClasses = match ($meeting->status) {
                        'completed' => 'bg-green-500/15 text-green-400',
                        'scheduled', 'pending_approval' => 'bg-yellow-500/15 text-yellow-400',
                        'active', 'live' => 'bg-blue-500/15 text-blue-400',
                        'cancelled', 'declined', 'emergency', 'incident_reported' => 'bg-red-500/15 text-red-400',
                        'expired' => 'bg-gray-500/15 text-gray-300',
                        default => 'bg-gray-500/15 text-gray-300',
                    };
                @endphp
                <tr class="border-b border-[#2a2d3e] last:border-b-0">
                    <td class="px-5 py-4 text-gray-400">{{ $meetings->firstItem() + $loop->index }}</td>
                    <td class="px-5 py-4 font-mono text-xs text-gray-300">{{ $meeting->reference ?: $meeting->id }}</td>
                    <td class="max-w-[180px] truncate px-5 py-4 font-medium text-white" title="{{ $meeting->title }}">
                        {{ $meeting->title ?: '—' }}
                    </td>
                    <td class="px-5 py-4 text-gray-300">{{ $meeting->guest?->name ?? '—' }}</td>
                    <td class="px-5 py-4 text-gray-400">
                        {{ $meeting->guest?->phone ?: $meeting->guest?->email ?: '—' }}
                    </td>
                    <td class="px-5 py-4 text-gray-300">{{ $meeting->host?->name ?? '—' }}</td>
                    <td class="px-5 py-4 text-gray-400">
                        {{ $meeting->host?->phone ?: $meeting->host?->email ?: '—' }}
                    </td>
                    @php
                        // Prefer the date + time-of-day the meeting was actually
                        // booked for (meeting_date/meeting_time); fall back to
                        // scheduled_start_at when either piece is missing.
                        $meetingDateTime = $meeting->meeting_date && $meeting->meeting_time
                            ? \Illuminate\Support\Carbon::parse($meeting->meeting_date->format('Y-m-d').' '.$meeting->meeting_time)
                            : ($meeting->scheduled_start_at ?? $meeting->meeting_date);
                    @endphp
                    <td class="whitespace-nowrap px-5 py-4 text-gray-400">
                        {{ $meetingDateTime?->format('d M Y, h:i A') ?? '—' }}
                    </td>
                    <td class="max-w-[160px] truncate px-5 py-4 text-gray-400" title="{{ $meeting->location }}">
                        {{ $meeting->location ?: '—' }}
                    </td>
                    <td class="px-5 py-4">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $statusClasses }}">
                            {{ $meeting->status_label }}
                        </span>
                    </td>
                    <td class="whitespace-nowrap px-5 py-4 text-gray-400">
                        {{ $meeting->created_at?->format('d M Y, h:i A') ?? '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="px-5 py-10 text-center text-sm text-gray-500">No meetings found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($meetings->hasPages())
    <div class="border-t border-[#2a2d3e] px-5 py-4">
        {{ $meetings->links() }}
    </div>
@endif
