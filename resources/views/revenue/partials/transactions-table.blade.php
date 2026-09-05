<div class="overflow-x-auto">
    <table class="w-full min-w-[1080px] border-collapse text-left text-[13px]">
        <thead>
            <tr class="border-b border-[#2a2d3e] text-xs uppercase tracking-wide text-red-500">
                <th class="px-5 py-4">S.No.</th>
                <th class="px-5 py-4">Subscription ID</th>
                <th class="px-5 py-4">Username</th>
                <th class="px-5 py-4">Mobile</th>
                <th class="px-5 py-4">Price</th>
                <th class="px-5 py-4">Transaction ID</th>
                <th class="px-5 py-4">Payment Status</th>
                <th class="px-5 py-4">Date</th>
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
                @endphp
                <tr class="border-b border-[#2a2d3e] last:border-b-0">
                    <td class="px-5 py-4 text-gray-400">{{ $transactions->firstItem() + $loop->index }}</td>
                    <td class="px-5 py-4 font-mono text-xs text-gray-300">{{ $transaction->subscription_id }}</td>
                    <td class="px-5 py-4 font-medium text-white">{{ $transaction->user_name ?: '—' }}</td>
                    <td class="px-5 py-4 text-gray-300">{{ $transaction->mobile ?: '—' }}</td>
                    <td class="px-5 py-4 font-medium text-white">{{ $currency === 'USD' ? '$'.$price : $currency.' '.$price }}</td>
                    <td class="max-w-[220px] truncate px-5 py-4 font-mono text-xs text-gray-300" title="{{ $transactionId }}">
                        {{ $transactionId ?: '—' }}
                    </td>
                    <td class="px-5 py-4">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $statusClasses }}">
                            {{ $statusLabel }}
                        </span>
                    </td>
                    <td class="whitespace-nowrap px-5 py-4 text-gray-400">
                        {{ $transaction->transaction_date?->format('d M Y, h:i A') ?? '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-500">No transactions found.</td>
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
