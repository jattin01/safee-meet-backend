@extends('layouts.app')

@section('title', 'Coupons')

@section('content')
@php
    $sortUrl = function (string $column) use ($filters) {
        $direction = ($filters['sort'] ?? null) === $column && ($filters['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
        return route('coupons.index', array_merge(request()->query(), ['sort' => $column, 'direction' => $direction, 'page' => 1]));
    };
@endphp

<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Coupon Management</h1>
            <p class="mt-1 text-sm text-gray-400">Create and manage discount coupons for subscription plans.</p>
        </div>
        <button id="add-coupon" type="button"
            class="self-start rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#b50f16] sm:self-auto">
            <i class="fa-solid fa-plus mr-1"></i> Add Coupon
        </button>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label' => 'Total Coupons', 'value' => $statistics['total'], 'icon' => 'fa-tags', 'tone' => 'text-blue-400 bg-blue-500/15'],
            ['label' => 'Active Coupons', 'value' => $statistics['active'], 'icon' => 'fa-circle-check', 'tone' => 'text-green-400 bg-green-500/15'],
            ['label' => 'Inactive Coupons', 'value' => $statistics['inactive'], 'icon' => 'fa-circle-pause', 'tone' => 'text-amber-400 bg-amber-500/15'],
            ['label' => 'Total Redemptions', 'value' => $statistics['redeemed'], 'icon' => 'fa-receipt', 'tone' => 'text-red-400 bg-red-500/15'],
        ] as $card)
            <div class="rounded-xl border border-[#2a2d3e] bg-black p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400">{{ $card['label'] }}</p>
                        <p class="mt-2 text-2xl font-bold text-white">{{ number_format($card['value']) }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-lg {{ $card['tone'] }}">
                        <i class="fa-solid {{ $card['icon'] }}"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('coupons.index') }}" class="mb-5 rounded-xl border border-[#2a2d3e] bg-black p-4">
        <div class="grid gap-3 md:grid-cols-[minmax(240px,1fr)_200px_auto]">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-xs text-gray-500"></i>
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by coupon code"
                    class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] py-2 pl-9 pr-3 text-sm text-white outline-none focus:border-[#DC131C]">
            </div>
            <select name="status" class="rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                <option value="">All statuses</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>
            <div class="flex gap-2">
                <button class="rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white hover:bg-[#b50f16]">Filter</button>
                <a href="{{ route('coupons.index') }}" class="rounded-lg border border-[#343746] px-4 py-2 text-sm font-semibold text-gray-300 hover:text-white">Reset</a>
            </div>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-[#2a2d3e] bg-black">
        <table class="w-full min-w-[1100px] border-collapse text-[13px]">
            <thead>
                <tr class="border-b border-[#2a2d3e] text-left text-xs uppercase tracking-wide text-red-500">
                    @foreach([
                        'code' => 'Code',
                        'discount_value' => 'Discount',
                        'times_redeemed' => 'Redeemed',
                        'status' => 'Status',
                        'expires_at' => 'Expires',
                        'created_at' => 'Created Date',
                    ] as $column => $label)
                        <th class="px-5 py-4">
                            <a href="{{ $sortUrl($column) }}" class="inline-flex items-center gap-1 hover:text-red-300">
                                {{ $label }}
                                <i class="fa-solid fa-sort text-[10px] opacity-60"></i>
                            </a>
                        </th>
                    @endforeach
                    <th class="px-5 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($coupons as $coupon)
                    <tr class="border-b border-[#2a2d3e] last:border-b-0">
                        <td class="px-5 py-4">
                            <div class="font-mono font-semibold text-white">{{ $coupon['code'] }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $coupon['plan_name'] ?? 'All plans' }}@if($coupon['billing_cycle']) &middot; {{ ucfirst($coupon['billing_cycle']) }} @endif</div>
                            @if($coupon['description'])
                                <div class="mt-1 max-w-xs truncate text-xs text-gray-500" title="{{ $coupon['description'] }}">{{ $coupon['description'] }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-gray-300">
                            @if($coupon['discount_type'] === 'percentage')
                                {{ rtrim(rtrim(number_format($coupon['discount_value'], 2), '0'), '.') }}%
                            @else
                                ${{ number_format($coupon['discount_value'], 2) }}
                            @endif
                        </td>
                        <td class="px-5 py-4 text-gray-300">
                            {{ $coupon['times_redeemed'] }}{{ $coupon['max_redemptions'] ? ' / '.$coupon['max_redemptions'] : '' }}
                        </td>
                        <td class="px-5 py-4">
                            <button type="button" data-action="status" data-id="{{ $coupon['id'] }}" data-status="{{ $coupon['status'] }}"
                                class="rounded-full px-3 py-1 text-xs font-medium transition {{ $coupon['is_active'] ? 'bg-green-500/15 text-green-400 hover:bg-green-500/25' : 'bg-amber-500/15 text-amber-400 hover:bg-amber-500/25' }}">
                                {{ ucfirst($coupon['status']) }}
                            </button>
                        </td>
                        <td class="px-5 py-4 text-gray-400">{{ $coupon['expires_at'] ? \Illuminate\Support\Carbon::parse($coupon['expires_at'])->format('d M Y, h:i A') : 'Never' }}</td>
                        <td class="px-5 py-4 text-gray-400">{{ \Illuminate\Support\Carbon::parse($coupon['created_at'])->format('d M Y, h:i A') }}</td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                <button type="button" data-action="edit" data-id="{{ $coupon['id'] }}"
                                    class="rounded-md border border-[#343746] px-3 py-1.5 text-xs text-gray-300 transition hover:border-blue-400 hover:text-blue-300">
                                    <i class="fa-solid fa-pen mr-1"></i>Edit
                                </button>
                                <button type="button" data-action="delete" data-id="{{ $coupon['id'] }}" data-code="{{ $coupon['code'] }}" data-redeemed="{{ $coupon['times_redeemed'] }}" data-status="{{ $coupon['status'] }}"
                                    class="rounded-md border border-[#343746] px-3 py-1.5 text-xs text-gray-300 transition hover:border-red-500 hover:text-red-400">
                                    <i class="fa-solid fa-trash mr-1"></i>Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-gray-500">No coupons match your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-400">
            @if($coupons->total())
                Showing {{ $coupons->firstItem() }} to {{ $coupons->lastItem() }} of {{ $coupons->total() }}
            @else
                Showing 0 coupons
            @endif
        </p>
        {{ $coupons->onEachSide(1)->links() }}
    </div>
</div>

<div id="coupon-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/75 p-4">
    <div class="w-full max-w-lg overflow-y-auto rounded-2xl border border-[#2a2d3e] bg-black p-5 shadow-2xl" style="max-height: 90vh;">
        <div class="mb-5 flex items-center justify-between">
            <h2 id="modal-title" class="text-lg font-semibold text-white">Add Coupon</h2>
            <button id="close-modal" type="button" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="form-errors" class="mb-4 hidden rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400"></div>
        <form id="coupon-form" class="space-y-4">
            <div>
                <label for="coupon-code" class="mb-2 block text-sm text-gray-400">Coupon Code <span class="text-red-500">*</span></label>
                <input id="coupon-code" name="code" required maxlength="40" style="text-transform:uppercase"
                    class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="e.g. WELCOME50">
            </div>
            <div>
                <label for="coupon-description" class="mb-2 block text-sm text-gray-400">Description <span class="text-gray-600">(optional)</span></label>
                <input id="coupon-description" name="description" maxlength="255"
                    class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="Briefly describe this coupon">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="coupon-discount-type" class="mb-2 block text-sm text-gray-400">Discount Type</label>
                    <select id="coupon-discount-type" name="discount_type" required
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed Amount</option>
                    </select>
                </div>
                <div>
                    <label for="coupon-discount-value" class="mb-2 block text-sm text-gray-400">Discount Value <span class="text-red-500">*</span></label>
                    <input id="coupon-discount-value" name="discount_value" type="number" step="0.01" min="0.01" required
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="e.g. 20">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="coupon-plan" class="mb-2 block text-sm text-gray-400">Restrict to Plan</label>
                    <select id="coupon-plan" name="plan_id"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                        <option value="">All plans</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="coupon-billing-cycle" class="mb-2 block text-sm text-gray-400">Billing Cycle</label>
                    <select id="coupon-billing-cycle" name="billing_cycle"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                        <option value="">Monthly &amp; Yearly</option>
                        <option value="monthly">Monthly only</option>
                        <option value="yearly">Yearly only</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="coupon-max-redemptions" class="mb-2 block text-sm text-gray-400">Max Total Redemptions</label>
                    <input id="coupon-max-redemptions" name="max_redemptions" type="number" min="1"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="Unlimited">
                </div>
                <div>
                    <label for="coupon-max-per-user" class="mb-2 block text-sm text-gray-400">Max Uses Per User</label>
                    <input id="coupon-max-per-user" name="max_redemptions_per_user" type="number" min="1" value="1" required
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="coupon-starts-at" class="mb-2 block text-sm text-gray-400">Starts At</label>
                    <input id="coupon-starts-at" name="starts_at" type="datetime-local"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>
                <div>
                    <label for="coupon-expires-at" class="mb-2 block text-sm text-gray-400">Expires At</label>
                    <input id="coupon-expires-at" name="expires_at" type="datetime-local"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>
            </div>
            <div>
                <label for="coupon-status" class="mb-2 block text-sm text-gray-400">Status</label>
                <select id="coupon-status" name="status" required
                    class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="flex justify-end gap-3 pt-1">
                <button id="cancel-modal" type="button" class="rounded-lg border border-[#343746] px-4 py-2 text-sm font-semibold text-gray-300 hover:text-white">Cancel</button>
                <button id="save-coupon" type="submit" class="rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white hover:bg-[#b50f16] disabled:opacity-50">Save Coupon</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const baseUrl = @json(url('/coupons'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const modal = document.getElementById('coupon-modal');
    const form = document.getElementById('coupon-form');
    const title = document.getElementById('modal-title');
    const code = document.getElementById('coupon-code');
    const description = document.getElementById('coupon-description');
    const discountType = document.getElementById('coupon-discount-type');
    const discountValue = document.getElementById('coupon-discount-value');
    const plan = document.getElementById('coupon-plan');
    const billingCycle = document.getElementById('coupon-billing-cycle');
    const maxRedemptions = document.getElementById('coupon-max-redemptions');
    const maxPerUser = document.getElementById('coupon-max-per-user');
    const startsAt = document.getElementById('coupon-starts-at');
    const expiresAt = document.getElementById('coupon-expires-at');
    const status = document.getElementById('coupon-status');
    const errors = document.getElementById('form-errors');
    const save = document.getElementById('save-coupon');
    let editingId = null;

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(result.message || 'The request could not be completed.');
            error.payload = result;
            throw error;
        }
        return result;
    };

    const openModal = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        code.focus();
    };
    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };
    const toLocalInput = (value) => value ? new Date(value).toISOString().slice(0, 16) : '';
    const resetForm = () => {
        editingId = null;
        form.reset();
        discountType.value = 'percentage';
        maxPerUser.value = 1;
        status.value = 'active';
        errors.classList.add('hidden');
        title.textContent = 'Add Coupon';
    };

    document.getElementById('add-coupon').addEventListener('click', () => {
        resetForm();
        openModal();
    });
    document.getElementById('close-modal').addEventListener('click', closeModal);
    document.getElementById('cancel-modal').addEventListener('click', closeModal);
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });

    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-action]');
        if (!button) return;

        if (button.dataset.action === 'edit') {
            try {
                const result = await request(`${baseUrl}/${button.dataset.id}`);
                const coupon = result.data;
                resetForm();
                editingId = coupon.id;
                title.textContent = 'Edit Coupon';
                code.value = coupon.code;
                description.value = coupon.description || '';
                discountType.value = coupon.discount_type;
                discountValue.value = coupon.discount_value;
                plan.value = coupon.plan_id || '';
                billingCycle.value = coupon.billing_cycle || '';
                maxRedemptions.value = coupon.max_redemptions || '';
                maxPerUser.value = coupon.max_redemptions_per_user;
                startsAt.value = toLocalInput(coupon.starts_at);
                expiresAt.value = toLocalInput(coupon.expires_at);
                status.value = coupon.status;
                openModal();
            } catch (error) {
                Swal.fire({ title: 'Unable to load coupon', text: error.message, icon: 'error', background: '#1a1a1a', color: '#fff', confirmButtonColor: '#DC131C' });
            }
        }

        if (button.dataset.action === 'status') {
            const nextStatus = button.dataset.status === 'active' ? 'inactive' : 'active';
            const confirmation = await Swal.fire({
                title: `${nextStatus === 'active' ? 'Activate' : 'Deactivate'} this coupon?`,
                text: nextStatus === 'inactive' ? 'It will immediately stop being redeemable.' : 'It will become redeemable again.',
                icon: 'warning', showCancelButton: true, confirmButtonText: `Yes, ${nextStatus}`,
                background: '#1a1a1a', color: '#fff', confirmButtonColor: '#DC131C', cancelButtonColor: '#4B5563',
            });
            if (!confirmation.isConfirmed) return;
            try {
                const result = await request(`${baseUrl}/${button.dataset.id}/status`, { method: 'PATCH', body: JSON.stringify({ status: nextStatus }) });
                await Swal.fire({ title: 'Updated', text: result.message, icon: 'success', background: '#1a1a1a', color: '#fff', confirmButtonColor: '#DC131C' });
                window.location.reload();
            } catch (error) {
                Swal.fire({ title: 'Status update failed', text: error.message, icon: 'error', background: '#1a1a1a', color: '#fff', confirmButtonColor: '#DC131C' });
            }
        }

        if (button.dataset.action === 'delete') {
            const redeemed = Number(button.dataset.redeemed);
            if (redeemed > 0) {
                const result = await Swal.fire({
                    title: 'Coupon already redeemed',
                    text: `This coupon has been redeemed ${redeemed} time(s) and cannot be deleted.`,
                    icon: 'warning', showCancelButton: button.dataset.status === 'active',
                    confirmButtonText: 'Close', cancelButtonText: 'Deactivate instead',
                    background: '#1a1a1a', color: '#fff', confirmButtonColor: '#4B5563', cancelButtonColor: '#DC131C',
                });
                if (result.dismiss === Swal.DismissReason.cancel) {
                    await request(`${baseUrl}/${button.dataset.id}/status`, { method: 'PATCH', body: JSON.stringify({ status: 'inactive' }) });
                    window.location.reload();
                }
                return;
            }
            const confirmation = await Swal.fire({
                title: 'Delete coupon?', text: `“${button.dataset.code}” will be permanently deleted.`, icon: 'warning',
                showCancelButton: true, confirmButtonText: 'Delete', background: '#1a1a1a', color: '#fff', confirmButtonColor: '#DC131C', cancelButtonColor: '#4B5563',
            });
            if (!confirmation.isConfirmed) return;
            try {
                const result = await request(`${baseUrl}/${button.dataset.id}`, { method: 'DELETE' });
                await Swal.fire({ title: 'Deleted', text: result.message, icon: 'success', background: '#1a1a1a', color: '#fff', confirmButtonColor: '#DC131C' });
                window.location.reload();
            } catch (error) {
                Swal.fire({ title: 'Cannot delete coupon', text: error.message, icon: 'warning', background: '#1a1a1a', color: '#fff', confirmButtonColor: '#DC131C' });
            }
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        errors.classList.add('hidden');
        save.disabled = true;
        try {
            const payload = {
                code: code.value,
                description: description.value,
                discount_type: discountType.value,
                discount_value: discountValue.value,
                plan_id: plan.value,
                billing_cycle: billingCycle.value,
                max_redemptions: maxRedemptions.value,
                max_redemptions_per_user: maxPerUser.value,
                starts_at: startsAt.value,
                expires_at: expiresAt.value,
                status: status.value,
            };
            const result = await request(editingId ? `${baseUrl}/${editingId}` : baseUrl, {
                method: editingId ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            });
            closeModal();
            await Swal.fire({ title: 'Saved', text: result.message, icon: 'success', background: '#1a1a1a', color: '#fff', confirmButtonColor: '#DC131C' });
            window.location.reload();
        } catch (error) {
            const validationErrors = error.payload?.errors;
            errors.innerHTML = validationErrors
                ? Object.values(validationErrors).flat().map(message => `<div>${String(message).replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]))}</div>`).join('')
                : error.message;
            errors.classList.remove('hidden');
        } finally {
            save.disabled = false;
        }
    });
});
</script>
@endsection
