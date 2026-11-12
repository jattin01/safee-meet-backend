{{-- Plan change history from the admin audit log: who changed which plan,
     when, and the before -> after values (price history included). --}}
@php
    $actionLabels = [
        'plan.created' => 'Created',
        'plan.updated' => 'Edited',
        'plan.features_updated' => 'Features changed',
        'plan.activated' => 'Activated',
        'plan.deactivated' => 'Deactivated',
        'plan.reordered' => 'Order changed',
        'plan.deleted' => 'Deleted',
    ];

    $fieldLabels = [
        'name' => 'Name',
        'account_type' => 'Shown to',
        'monthly_price' => 'Monthly price',
        'monthly_original_price' => 'Monthly MRP',
        'yearly_price' => 'Yearly price',
        'yearly_original_price' => 'Yearly MRP',
        'trial_days' => 'Trial days',
        'is_active' => 'Active',
        'sort_order' => 'Position',
        'features' => 'Feature list',
        'plan_features' => 'Feature grid',
    ];

    $money = ['monthly_price', 'monthly_original_price', 'yearly_price', 'yearly_original_price'];

    $show = function (string $field, $value) use ($money) {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (in_array($field, $money, true)) {
            return '$'.number_format((float) $value, 2);
        }
        if (is_array($value)) {
            return count($value).' item'.(count($value) === 1 ? '' : 's');
        }

        return (string) $value;
    };
@endphp

<div class="mt-[40px] rounded-2xl border border-[#212529] bg-[#000] p-5">
    <div class="mb-4">
        <h2 class="text-[15px] font-semibold text-white">Plan change history</h2>
        <p class="text-xs text-gray-500">Last 50 changes to plans: prices, features, status, order and deletions.</p>
    </div>

    @if($planHistory->isEmpty())
        <p class="text-sm text-gray-500">No plan changes recorded yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[13px]">
                <thead class="text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2 pr-4 font-medium">When</th>
                        <th class="py-2 pr-4 font-medium">Admin</th>
                        <th class="py-2 pr-4 font-medium">Plan</th>
                        <th class="py-2 pr-4 font-medium">Action</th>
                        <th class="py-2 font-medium">Change</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#1a1a1a] text-gray-300">
                    @foreach($planHistory as $entry)
                        @php
                            $old = $entry->old_values ?? [];
                            $new = $entry->new_values ?? [];
                            $fields = in_array($entry->action, ['plan.created', 'plan.deleted'], true)
                                ? []
                                : array_keys($new + $old);
                        @endphp
                        <tr class="align-top">
                            <td class="py-2 pr-4 whitespace-nowrap text-gray-400">{{ $entry->occurred_at?->format('d M Y, H:i') }}</td>
                            <td class="py-2 pr-4 whitespace-nowrap">{{ $entry->admin?->name ?? '—' }}</td>
                            <td class="py-2 pr-4 whitespace-nowrap text-white">{{ $planNames[$entry->target_id] ?? ($old['name'] ?? $new['name'] ?? '#'.$entry->target_id) }}</td>
                            <td class="py-2 pr-4 whitespace-nowrap">{{ $actionLabels[$entry->action] ?? $entry->action }}</td>
                            <td class="py-2">
                                @forelse($fields as $field)
                                    <div>
                                        <span class="text-gray-500">{{ $fieldLabels[$field] ?? $field }}:</span>
                                        {{ $show($field, $old[$field] ?? null) }}
                                        <span class="text-gray-500">→</span>
                                        <span class="text-white">{{ $show($field, $new[$field] ?? null) }}</span>
                                    </div>
                                @empty
                                    <span class="text-gray-500">
                                        @if($entry->action === 'plan.created')
                                            {{ $show('monthly_price', $new['monthly_price'] ?? null) }}/mo · {{ $show('yearly_price', $new['yearly_price'] ?? null) }}/yr
                                        @else
                                            —
                                        @endif
                                    </span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
