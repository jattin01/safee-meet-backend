<div class="overflow-x-auto">
    <table class="w-full min-w-[1600px] border-collapse text-left text-[13px]">
        <thead>
            <tr class="border-b border-[#2a2d3e] text-xs uppercase tracking-wide text-red-500">
                <th class="px-5 py-4">S.No.</th>
                <th class="px-5 py-4">Subscription ID</th>
                <th class="px-5 py-4">Username</th>
                <th class="px-5 py-4">Mobile</th>
                <th class="px-5 py-4">Plan Name</th>
                <th class="px-5 py-4">Billing Cycle</th>
                <th class="px-5 py-4">Price</th>
                <th class="px-5 py-4">Transaction ID</th>
                <th class="px-5 py-4">Payment Status</th>
                <th class="px-5 py-4">Subscription Status</th>
                <th class="px-5 py-4">Payment Date</th>
                <th class="px-5 py-4">Start Date</th>
                <th class="px-5 py-4">End Date</th>
                <th class="px-5 py-4">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $transaction)
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
                    $transactionId = $transaction->stripe_payment_intent_id ?: $transaction->stripe_invoice_id;
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
                    <td class="px-5 py-4 text-gray-400">{{ $transactions->firstItem() + $loop->index }}</td>
                    <td class="px-5 py-4 font-mono text-xs text-gray-300">{{ $transaction->subscription_id }}</td>
                    <td class="px-5 py-4 font-medium text-white">{{ $transaction->user_name ?: '—' }}</td>
                    <td class="px-5 py-4 text-gray-300">{{ $transaction->mobile ?: '—' }}</td>
                    <td class="px-5 py-4 text-gray-300">{{ $transaction->plan_name ?: '—' }}</td>
                    <td class="px-5 py-4 text-gray-300">{{ \Illuminate\Support\Str::headline($transaction->billing_cycle ?: '—') }}</td>
                    <td class="px-5 py-4 font-medium text-white">{{ $currency === 'USD' ? '$'.$price : $currency.' '.$price }}</td>
                    <td class="max-w-[220px] truncate px-5 py-4 font-mono text-xs text-gray-300" title="{{ $transactionId }}">
                        {{ $transactionId ?: '—' }}
                    </td>
                    <td class="px-5 py-4">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $statusClasses }}">
                            {{ $statusLabel }}
                        </span>
                    </td>
                    <td class="px-5 py-4">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $subStatusClasses }}">
                            {{ \Illuminate\Support\Str::headline($transaction->subscription_status ?: '—') }}
                        </span>
                    </td>
                    <td class="whitespace-nowrap px-5 py-4 text-gray-400">
                        {{ $transaction->transaction_date?->format('d M Y, h:i A') ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-5 py-4 text-gray-400">
                        {{ $transaction->started_at?->format('d M Y') ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-5 py-4 text-gray-400">
                        {{ $transaction->renews_at?->format('d M Y') ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-5 py-4">
                        @php
                            $refundableCents = (int) $transaction->payment_amount - (int) $transaction->payment_amount_refunded;
                        @endphp
                        @if($transaction->payment_id && in_array($transaction->payment_status, ['succeeded', 'partially_refunded'], true) && $refundableCents > 0)
                            <button type="button"
                                    class="js-refund rounded-lg border border-purple-500/40 px-3 py-1.5 text-xs font-medium text-purple-300 hover:bg-purple-500/10"
                                    data-url="{{ route('revenue.refund', $transaction->payment_id) }}"
                                    data-refundable="{{ number_format($refundableCents / 100, 2, '.', '') }}"
                                    data-user="{{ $transaction->user_name ?: '—' }}"
                                    data-plan="{{ $transaction->plan_name ?: '—' }}"
                                    data-partial="{{ $transaction->payment_status === 'partially_refunded' ? '1' : '0' }}">
                                Refund
                            </button>
                        @else
                            <span class="text-xs text-gray-600">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="14" class="px-5 py-10 text-center text-sm text-gray-500">No transactions found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($transactions->hasPages())
    <div class="border-t border-[#2a2d3e] px-5 py-4">
        {{ $transactions->links() }}
    </div>
@endif
