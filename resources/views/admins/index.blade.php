@extends('layouts.app')

@section('title', 'Admins')

@section('content')
<div class="md:p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Admin Management</h1>
        <p id="admin-total" class="mt-1 text-sm text-gray-400">Loading admins...</p>
    </div>

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
</div>
@endsection

@section('scripts')
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
                        <span class="rounded-full px-3 py-1 text-xs ${active ? 'bg-green-500/15 text-green-400' : 'bg-red-500/15 text-red-400'}">
                            ${active ? 'Active' : 'Inactive'}
                        </span>
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

    loadPage(1);
});
</script>
@endsection
