@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="md:p-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">User Management</h1>
            <p id="user-total" class="text-sm text-gray-400 mt-1">Loading users...</p>
        </div>
        <a href="{{ route('users.export') }}" class="bg-red-500 hover:bg-red-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition inline-block">
            Export CSV
        </a>
    </div>

    {{-- Table Wrapper --}}
    <div class="bg-[#000] rounded-xl border border-[#000]" style="overflow-x:auto; -webkit-overflow-scrolling:touch; width:100%;">
        <table style="min-width:750px; width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr class="border-b border-[#2a2d3e] text-left text-xs uppercase tracking-wide text-red-500 ">
                    <th class="px-5 py-4 font-semibold">User</th>
                    <th class="px-5 py-4 font-semibold">Safee Pin</th>
                    <th class="px-5 py-4 font-semibold">Verification</th>
                    <th class="px-5 py-4 font-semibold">Plan</th>
                    <th class="px-5 py-4 font-semibold">Trust Score</th>
                    <th class="px-5 py-4 font-semibold">Joined</th>
                    <th class="px-5 py-4 font-semibold">Status</th>
                    <th class="px-5 py-4 font-semibold">More Details</th>
                </tr>
            </thead>
            <tbody id="user-table-body">
                <tr>
                    <td colspan="8" class="px-5 py-8 text-center text-gray-500">Loading...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
        <p id="pagination-summary" class="text-gray-400"></p>
        <div class="flex items-center gap-2">
            <button id="previous-page" type="button"
                class="rounded-md border border-[#343746] px-4 py-2 text-gray-300 transition hover:border-red-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-40">
                Previous
            </button>
            <span id="page-number" class="min-w-20 text-center text-gray-300"></span>
            <button id="next-page" type="button"
                class="rounded-md border border-[#343746] px-4 py-2 text-gray-300 transition hover:border-red-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-40">
                Next
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dataUrl = @json(route('users.data'));
    const body = document.getElementById('user-table-body');
    const total = document.getElementById('user-total');
    const summary = document.getElementById('pagination-summary');
    const pageNumber = document.getElementById('page-number');
    const previous = document.getElementById('previous-page');
    const next = document.getElementById('next-page');
    let currentPage = 1;
    let lastPage = 1;

    const escapeHtml = (value) => {
        const element = document.createElement('div');
        element.textContent = value ?? '';
        return element.innerHTML;
    };

    const joinedLabel = (value) => {
        if (!value) return '—';
        return new Intl.DateTimeFormat('en', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(new Date(value));
    };

    const renderRows = (users) => {
        if (!users.length) {
            body.innerHTML = '<tr><td colspan="8" class="px-5 py-4 text-center" style="color:#6b7280;">No users found.</td></tr>';
            return;
        }

        body.innerHTML = users.map(user => `
            <tr style="border-bottom:1px solid #2a2d3e;">
                <td class="px-5 py-4 text-[#fff] font-medium">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:32px; height:32px; border-radius:50%; background:${escapeHtml(user.avatar_color)}; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">${escapeHtml(user.initials)}</div>
                        <div>
                            <div style="color:#fff; font-weight:600;">${escapeHtml(user.name)}</div>
                            <div style="color:#6b7280; font-size:11px;">${escapeHtml(user.contact)}</div>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-4 text-[#fff] font-medium">${(user.safee_pin ?? user.safee_id)
 ? '#' + escapeHtml(user.safee_pin ?? user.safee_id)   : '—'}</td>
                <td style="padding:14px 20px;">
                    <span style="background:${escapeHtml(user.verification_color)}26; color:${escapeHtml(user.verification_color)}; font-size:11px; padding:3px 10px; border-radius:999px;">● ${escapeHtml(user.verification_label)}</span>
                </td>
                <td class="px-5 py-4 text-[#fff] font-medium">${escapeHtml(user.plan_label)}</td>
                <td class="px-5 py-4 text-[#fff] font-medium">${user.trust_score !== null ? escapeHtml(user.trust_score) : '—'}</td>
                <td class="px-5 py-4 text-[#fff] font-medium">${escapeHtml(joinedLabel(user.created_at))}</td>
                <td class="px-5 py-4 text-[#fff] font-medium">
                    <select data-user-id="${user.id}" class="status-select" style="background:${escapeHtml(user.status_color)}26; color:${escapeHtml(user.status_color)}; font-size:11px; padding:3px 8px; border-radius:999px; border:1px solid ${escapeHtml(user.status_color)}4d; outline:none;">
                        <option value="active" ${user.status === 'active' ? 'selected' : ''}>Active</option>
                        <option value="inactive" ${user.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                        <option value="suspended" ${user.status === 'suspended' ? 'selected' : ''}>Suspended</option>
                        ${user.status === 'deleted' ? '<option value="deleted" selected>Deleted</option>' : ''}
                    </select>
                </td>
                <td class="px-5 py-4 text-[#fff] font-medium">
                    <a href="${user.show_url}" class="see-more-btn inline-flex items-center gap-1 rounded-md border border-red-500/40 bg-red-500/10 px-3 py-1.5 text-xs font-semibold text-red-500 no-underline transition hover:bg-red-500 hover:text-white">See More</a>
                </td>
            </tr>
        `).join('');
    };

    const loadPage = async (page) => {
        body.innerHTML = '<tr><td colspan="8" class="px-5 py-8 text-center text-gray-500">Loading...</td></tr>';
        previous.disabled = true;
        next.disabled = true;

        try {
            const response = await fetch(`${dataUrl}?page=${page}&per_page=10`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Unable to load users.');
            }

            const result = await response.json();
            currentPage = result.current_page;
            lastPage = result.last_page;

            renderRows(result.data);
            total.textContent = `${result.total} total users`;
            summary.textContent = result.total
                ? `Showing ${result.from} to ${result.to} of ${result.total}`
                : 'Showing 0 users';
            pageNumber.textContent = `Page ${currentPage} of ${lastPage}`;
            previous.disabled = currentPage <= 1;
            next.disabled = currentPage >= lastPage;
        } catch (error) {
            body.innerHTML = `<tr><td colspan="8" class="px-5 py-8 text-center text-red-400">${escapeHtml(error.message)}</td></tr>`;
            total.textContent = 'Unable to load users';
        }
    };

    previous.addEventListener('click', () => loadPage(currentPage - 1));
    next.addEventListener('click', () => loadPage(currentPage + 1));

    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    body.addEventListener('change', async (event) => {
        const select = event.target.closest('.status-select');
        if (!select) return;

        const userId = select.dataset.userId;
        const previousValue = select.dataset.previousValue || select.querySelector('option[selected]')?.value;
        const newStatus = select.value;

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
                select.value = previousValue;
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
                select.value = previousValue;
                return;
            }
        }

        select.disabled = true;
        try {
            const response = await fetch(`/users/${userId}/status`, {
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

            const statusColor = result.status_color;
            select.style.background = `${statusColor}26`;
            select.style.color = statusColor;
            select.style.borderColor = `${statusColor}4d`;

            select.dataset.previousValue = newStatus;
        } catch (error) {
            await Swal.fire({
                title: 'Status update failed',
                text: error.message,
                icon: 'error',
                confirmButtonColor: '#DC131C',
                background: '#1a1a1a',
                color: '#ffffff',
            });
            select.value = previousValue;
        } finally {
            select.disabled = false;
        }
    });

    loadPage(1);
});
</script>
@endsection
