{{-- Active / Inactive state + toggle. Deactivating hides the plan from new
     users only; existing subscribers keep it and keep renewing. --}}
<div class="mt-2 flex flex-wrap items-center gap-2">
    @if($plan['is_active'])
        <span class="inline-flex items-center gap-1.5 rounded-md border border-green-500/30 bg-green-500/10 px-2 py-0.5 text-[11px] font-semibold text-green-400">
            <span class="size-1.5 rounded-full bg-green-400"></span> Active
        </span>
    @else
        <span class="inline-flex items-center gap-1.5 rounded-md border border-gray-500/40 bg-gray-500/10 px-2 py-0.5 text-[11px] font-semibold text-gray-400">
            <span class="size-1.5 rounded-full bg-gray-400"></span> Inactive: hidden from new users
        </span>
    @endif

    <form method="POST" action="{{ route('subscription') }}"
          onsubmit="return confirm(@js($plan['is_active']
              ? 'Deactivate "'.$plan['name'].'"? New users will no longer see or buy it. Existing subscribers keep their plan.'
              : 'Activate "'.$plan['name'].'"? It will be available to new users again.'))">
        @csrf
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="id" value="{{ $plan['id'] }}">
        <button type="submit"
                class="rounded-md border px-2 py-0.5 text-[11px] font-semibold transition {{ $plan['is_active'] ? 'border-amber-500/50 text-amber-400 hover:bg-amber-500 hover:text-white' : 'border-green-500/50 text-green-400 hover:bg-green-500 hover:text-white' }}">
            {{ $plan['is_active'] ? 'Deactivate' : 'Activate' }}
        </button>
    </form>

    {{-- Display order (the app's plans and comparison screens follow it). --}}
    <div class="ml-auto flex items-center gap-1" title="Change display order">
        @foreach(['up' => 'fa-arrow-up', 'down' => 'fa-arrow-down'] as $direction => $icon)
            <form method="POST" action="{{ route('subscription') }}">
                @csrf
                <input type="hidden" name="action" value="move">
                <input type="hidden" name="id" value="{{ $plan['id'] }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <button type="submit" aria-label="Move {{ $plan['name'] }} {{ $direction }}"
                        @disabled($direction === 'up' ? $isFirst : $isLast)
                        class="rounded-md border border-[#2a2d3e] w-[24px] h-[22px] text-[10px] text-gray-400 transition hover:border-blue-400 hover:text-blue-400 disabled:opacity-30 disabled:pointer-events-none">
                    <i class="fa-solid {{ $icon }}"></i>
                </button>
            </form>
        @endforeach
    </div>
</div>
