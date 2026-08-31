@extends('layouts.app')

@section('title', 'Admins')

@section('content')
<style>[x-cloak] { display: none !important; }</style>
<div class="md:p-6" x-data="{ showCreate: {{ $errors->any() ? 'true' : 'false' }} }">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Admin Management</h1>
            <p id="admin-total" class="mt-1 text-sm text-gray-400">Loading admins...</p>
        </div>
        <button type="button" @click="showCreate = true"
            class="self-start rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#b50f16] sm:self-auto">
            + Add Admin
        </button>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-[#2a2d3e] bg-black">
        <table class="w-full min-w-[850px] border-collapse text-[13px]">
            <thead>
                <tr class="border-b border-[#2a2d3e] text-left text-xs uppercase tracking-wide text-red-500">
                    <th class="px-5 py-4">Admin</th>
                    <th class="px-5 py-4">Phone</th>
                    <th class="px-5 py-4">Role</th>
                    <th class="px-5 py-4">Joined</th>
                    <th class="px-5 py-4">Status</th>
                </tr>
            </thead>
            <tbody id="admin-table-body">
                <tr>
                    <td colspan="5" class="px-5 py-8 text-center text-gray-500">Loading...</td>
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

    {{-- Create Admin Modal --}}
    <div x-show="showCreate" x-cloak @click.self="showCreate = false"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-lg rounded-2xl border border-[#212529] bg-[#000] p-5">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white">Create a new admin</h3>
                <button type="button" @click="showCreate = false" class="text-gray-400 hover:text-white">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admins.store') }}" class="grid gap-4 md:grid-cols-2">
                @csrf
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm text-gray-400" for="name">Name</label>
                    <input id="name" name="name" value="{{ old('name') }}" required
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="Jane Doe">
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400" for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="jane@example.com">
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400" for="phone">Phone <span class="text-gray-600">(optional)</span></label>
                    <input id="phone" name="phone" value="{{ old('phone') }}"
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="+91...">
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400" for="role_id">Role</label>
                    <select id="role_id" name="role_id" required
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                        <option value="">Select role</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400" for="status">Status</label>
                    <select id="status" name="status" required
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                        <option value="1" @selected(old('status', '1') == '1')>Active</option>
                        <option value="0" @selected(old('status') === '0')>Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400" for="password">Password</label>
                    <input id="password" name="password" type="password" required
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="Min 8 characters">
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400" for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                        class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="Re-enter password">
                </div>
                <div class="md:col-span-2 flex justify-end gap-3">
                    <button type="button" @click="showCreate = false"
                        class="rounded-lg border border-[#2a2d3e] px-4 py-2 text-sm font-semibold text-gray-300 transition hover:bg-[#2a2d3e]">
                        Cancel
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#b50f16]">
                        Create Admin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dataUrl = @json(route('admins.data'));
    const body = document.getElementById('admin-table-body');
    const total = document.getElementById('admin-total');
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

    const initials = (name) => (name || '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map(part => part[0].toUpperCase())
        .join('');

    const renderRows = (admins) => {
        if (!admins.length) {
            body.innerHTML = '<tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">No admins found.</td></tr>';
            return;
        }

        body.innerHTML = admins.map(admin => {
            const active = Boolean(admin.status);
            const joined = new Intl.DateTimeFormat('en', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            }).format(new Date(admin.created_at));

            return `
                <tr class="border-b border-[#2a2d3e] last:border-b-0">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-500/20 text-xs font-bold text-red-400">
                                ${escapeHtml(initials(admin.name))}
                            </div>
                            <div>
                                <div class="font-semibold text-white">${escapeHtml(admin.name)}</div>
                                <div class="text-xs text-gray-500">${escapeHtml(admin.email)}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-gray-300">${escapeHtml(admin.phone || '—')}</td>
                    <td class="px-5 py-4 text-gray-300">${escapeHtml(admin.role?.name || '—')}</td>
                    <td class="px-5 py-4 text-gray-400">${escapeHtml(joined)}</td>
                    <td class="px-5 py-4">
                        <button type="button" class="status-toggle rounded-full px-3 py-1 text-xs transition ${active ? 'bg-green-500/15 text-green-400 hover:bg-green-500/25' : 'bg-red-500/15 text-red-400 hover:bg-red-500/25'}"
                            data-admin-id="${admin.id}" data-active="${active ? '1' : '0'}">
                            ${active ? 'Active' : 'Inactive'}
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    };

    const loadPage = async (page) => {
        body.innerHTML = '<tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">Loading...</td></tr>';
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
                throw new Error('Unable to load admins.');
            }

            const result = await response.json();
            currentPage = result.current_page;
            lastPage = result.last_page;

            renderRows(result.data);
            total.textContent = `${result.total} total admins`;
            summary.textContent = result.total
                ? `Showing ${result.from} to ${result.to} of ${result.total}`
                : 'Showing 0 admins';
            pageNumber.textContent = `Page ${currentPage} of ${lastPage}`;
            previous.disabled = currentPage <= 1;
            next.disabled = currentPage >= lastPage;
        } catch (error) {
            body.innerHTML = `<tr><td colspan="5" class="px-5 py-8 text-center text-red-400">${escapeHtml(error.message)}</td></tr>`;
            total.textContent = 'Unable to load admins';
        }
    };

    previous.addEventListener('click', () => loadPage(currentPage - 1));
    next.addEventListener('click', () => loadPage(currentPage + 1));

    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    body.addEventListener('click', async (event) => {
        const button = event.target.closest('.status-toggle');
        if (!button) return;

        const adminId = button.dataset.adminId;
        const wasActive = button.dataset.active === '1';
        const nextStatus = !wasActive;

        const confirmation = await Swal.fire({
            title: `${nextStatus ? 'Activate' : 'Deactivate'} this admin?`,
            text: nextStatus
                ? 'This admin will regain access to the admin panel.'
                : 'This admin will no longer be able to access the admin panel.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: `Yes, ${nextStatus ? 'activate' : 'deactivate'}`,
            confirmButtonColor: nextStatus ? '#16A34A' : '#DC131C',
            cancelButtonColor: '#4B5563',
            background: '#1a1a1a',
            color: '#ffffff',
        });

        if (!confirmation.isConfirmed) {
            return;
        }

        button.disabled = true;
        try {
            const response = await fetch(`/admins/${adminId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ status: nextStatus }),
            });

            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(result.message || 'Unable to update status.');
            }

            button.dataset.active = nextStatus ? '1' : '0';
            button.textContent = nextStatus ? 'Active' : 'Inactive';
            button.className = `status-toggle rounded-full px-3 py-1 text-xs transition ${nextStatus ? 'bg-green-500/15 text-green-400 hover:bg-green-500/25' : 'bg-red-500/15 text-red-400 hover:bg-red-500/25'}`;
        } catch (error) {
            await Swal.fire({
                title: 'Status update failed',
                text: error.message,
                icon: 'error',
                confirmButtonColor: '#DC131C',
                background: '#1a1a1a',
                color: '#ffffff',
            });
        } finally {
            button.disabled = false;
        }
    });

    loadPage(1);
});
</script>
@endsection
