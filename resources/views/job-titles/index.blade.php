@extends('layouts.app')

@section('title', 'Job Titles')

@section('content')
@php
    $sortUrl = function (string $column) use ($filters) {
        $direction = ($filters['sort'] ?? null) === $column && ($filters['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
        return route('job-titles.index', array_merge(request()->query(), ['sort' => $column, 'direction' => $direction, 'page' => 1]));
    };
@endphp

<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Job Title Management</h1>
            <p class="mt-1 text-sm text-gray-400">Manage the professions available for user assignments.</p>
        </div>
        <button id="add-job-title" type="button"
            class="self-start rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#b50f16] sm:self-auto">
            <i class="fa-solid fa-plus mr-1"></i> Add Job Title
        </button>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label' => 'Total Job Titles', 'value' => $statistics['total'], 'icon' => 'fa-briefcase', 'tone' => 'text-blue-400 bg-blue-500/15'],
            ['label' => 'Active Job Titles', 'value' => $statistics['active'], 'icon' => 'fa-circle-check', 'tone' => 'text-green-400 bg-green-500/15'],
            ['label' => 'Inactive Job Titles', 'value' => $statistics['inactive'], 'icon' => 'fa-circle-pause', 'tone' => 'text-amber-400 bg-amber-500/15'],
            ['label' => 'Users with Job Titles', 'value' => $statistics['users_with_titles'], 'icon' => 'fa-users', 'tone' => 'text-red-400 bg-red-500/15'],
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

    <form method="GET" action="{{ route('job-titles.index') }}" class="mb-5 rounded-xl border border-[#2a2d3e] bg-black p-4">
        <div class="grid gap-3 md:grid-cols-[minmax(240px,1fr)_200px_auto]">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-xs text-gray-500"></i>
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by job title"
                    class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] py-2 pl-9 pr-3 text-sm text-white outline-none focus:border-[#DC131C]">
            </div>
            <select name="status" class="rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                <option value="">All statuses</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>
            <div class="flex gap-2">
                <button class="rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white hover:bg-[#b50f16]">Filter</button>
                <a href="{{ route('job-titles.index') }}" class="rounded-lg border border-[#343746] px-4 py-2 text-sm font-semibold text-gray-300 hover:text-white">Reset</a>
            </div>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-[#2a2d3e] bg-black">
        <table class="w-full min-w-[1050px] border-collapse text-[13px]">
            <thead>
                <tr class="border-b border-[#2a2d3e] text-left text-xs uppercase tracking-wide text-red-500">
                    @foreach([
                        'name' => 'Job Title',
                        'users_count' => 'Number of Users',
                        'status' => 'Status',
                        'created_at' => 'Created Date',
                        'updated_at' => 'Updated Date',
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
                @forelse($jobTitles as $jobTitle)
                    <tr class="border-b border-[#2a2d3e] last:border-b-0">
                        <td class="px-5 py-4">
                            <div class="font-semibold text-white">{{ $jobTitle['name'] }}</div>
                            @if($jobTitle['description'])
                                <div class="mt-1 max-w-xs truncate text-xs text-gray-500" title="{{ $jobTitle['description'] }}">{{ $jobTitle['description'] }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-gray-300">{{ number_format($jobTitle['users_count']) }}</td>
                        <td class="px-5 py-4">
                            <button type="button" data-action="status" data-id="{{ $jobTitle['id'] }}" data-status="{{ $jobTitle['status'] }}"
                                class="rounded-full px-3 py-1 text-xs font-medium transition {{ $jobTitle['is_active'] ? 'bg-green-500/15 text-green-400 hover:bg-green-500/25' : 'bg-amber-500/15 text-amber-400 hover:bg-amber-500/25' }}">
                                {{ ucfirst($jobTitle['status']) }}
                            </button>
                        </td>
                        <td class="px-5 py-4 text-gray-400">{{ \Illuminate\Support\Carbon::parse($jobTitle['created_at'])->format('d M Y, h:i A') }}</td>
                        <td class="px-5 py-4 text-gray-400">{{ \Illuminate\Support\Carbon::parse($jobTitle['updated_at'])->format('d M Y, h:i A') }}</td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-2">
                                <button type="button" data-action="edit" data-id="{{ $jobTitle['id'] }}"
                                    class="rounded-md border border-[#343746] px-3 py-1.5 text-xs text-gray-300 transition hover:border-blue-400 hover:text-blue-300">
                                    <i class="fa-solid fa-pen mr-1"></i>Edit
                                </button>
                                <button type="button" data-action="delete" data-id="{{ $jobTitle['id'] }}" data-name="{{ $jobTitle['name'] }}" data-users="{{ $jobTitle['users_count'] }}" data-status="{{ $jobTitle['status'] }}"
                                    class="rounded-md border border-[#343746] px-3 py-1.5 text-xs text-gray-300 transition hover:border-red-500 hover:text-red-400">
                                    <i class="fa-solid fa-trash mr-1"></i>Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">No job titles match your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-400">
            @if($jobTitles->total())
                Showing {{ $jobTitles->firstItem() }} to {{ $jobTitles->lastItem() }} of {{ $jobTitles->total() }}
            @else
                Showing 0 job titles
            @endif
        </p>
        {{ $jobTitles->onEachSide(1)->links() }}
    </div>
</div>

<div id="job-title-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/75 p-4">
    <div class="w-full max-w-lg rounded-2xl border border-[#2a2d3e] bg-black p-5 shadow-2xl">
        <div class="mb-5 flex items-center justify-between">
            <h2 id="modal-title" class="text-lg font-semibold text-white">Add Job Title</h2>
            <button id="close-modal" type="button" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="form-errors" class="mb-4 hidden rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400"></div>
        <form id="job-title-form" class="space-y-4">
            <div>
                <label for="job-title-name" class="mb-2 block text-sm text-gray-400">Job Title <span class="text-red-500">*</span></label>
                <input id="job-title-name" name="name" required maxlength="100"
                    class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="e.g. Electrician">
            </div>
            <div>
                <label for="job-title-description" class="mb-2 block text-sm text-gray-400">Description <span class="text-gray-600">(optional)</span></label>
                <textarea id="job-title-description" name="description" rows="4" maxlength="1000"
                    class="w-full resize-y rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="Briefly describe this profession"></textarea>
            </div>
            <div>
                <label for="job-title-status" class="mb-2 block text-sm text-gray-400">Status</label>
                <select id="job-title-status" name="status" required
                    class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <p id="rename-warning" class="hidden rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-xs text-amber-300"></p>
            <div class="flex justify-end gap-3 pt-1">
                <button id="cancel-modal" type="button" class="rounded-lg border border-[#343746] px-4 py-2 text-sm font-semibold text-gray-300 hover:text-white">Cancel</button>
                <button id="save-job-title" type="submit" class="rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white hover:bg-[#b50f16] disabled:opacity-50">Save Job Title</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const baseUrl = @json(url('/job-titles'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const modal = document.getElementById('job-title-modal');
    const form = document.getElementById('job-title-form');
    const title = document.getElementById('modal-title');
    const name = document.getElementById('job-title-name');
    const description = document.getElementById('job-title-description');
    const status = document.getElementById('job-title-status');
    const errors = document.getElementById('form-errors');
    const warning = document.getElementById('rename-warning');
    const save = document.getElementById('save-job-title');
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
        name.focus();
    };
    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };
    const resetForm = () => {
        editingId = null;
        form.reset();
        status.value = 'active';
        errors.classList.add('hidden');
        warning.classList.add('hidden');
        title.textContent = 'Add Job Title';
    };

    document.getElementById('add-job-title').addEventListener('click', () => {
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
                const jobTitle = result.data;
                resetForm();
                editingId = jobTitle.id;
                title.textContent = 'Edit Job Title';
                name.value = jobTitle.name;
                description.value = jobTitle.description || '';
                status.value = jobTitle.status;
                if (jobTitle.users_count > 0) {
                    warning.textContent = `Renaming this title will safely update ${jobTitle.users_count} existing user assignment(s). Deactivating it will not remove those assignments.`;
                    warning.classList.remove('hidden');
                }
                openModal();
            } catch (error) {
                Swal.fire({ title: 'Unable to load job title', text: error.message, icon: 'error', background: '#1a1a1a', color: '#fff', confirmButtonColor: '#DC131C' });
            }
        }

        if (button.dataset.action === 'status') {
            const nextStatus = button.dataset.status === 'active' ? 'inactive' : 'active';
            const confirmation = await Swal.fire({
                title: `${nextStatus === 'active' ? 'Activate' : 'Deactivate'} this job title?`,
                text: nextStatus === 'inactive' ? 'Existing user assignments will remain unchanged.' : 'It will become available for user assignments.',
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
            const assigned = Number(button.dataset.users);
            if (assigned > 0) {
                const result = await Swal.fire({
                    title: 'Job title is in use',
                    text: `This job title is currently assigned to ${assigned} users and cannot be deleted.`,
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
                title: 'Delete job title?', text: `“${button.dataset.name}” will be permanently deleted.`, icon: 'warning',
                showCancelButton: true, confirmButtonText: 'Delete', background: '#1a1a1a', color: '#fff', confirmButtonColor: '#DC131C', cancelButtonColor: '#4B5563',
            });
            if (!confirmation.isConfirmed) return;
            try {
                const result = await request(`${baseUrl}/${button.dataset.id}`, { method: 'DELETE' });
                await Swal.fire({ title: 'Deleted', text: result.message, icon: 'success', background: '#1a1a1a', color: '#fff', confirmButtonColor: '#DC131C' });
                window.location.reload();
            } catch (error) {
                Swal.fire({ title: 'Cannot delete job title', text: error.message, icon: 'warning', background: '#1a1a1a', color: '#fff', confirmButtonColor: '#DC131C' });
            }
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        errors.classList.add('hidden');
        save.disabled = true;
        try {
            const result = await request(editingId ? `${baseUrl}/${editingId}` : baseUrl, {
                method: editingId ? 'PUT' : 'POST',
                body: JSON.stringify({ name: name.value, description: description.value, status: status.value }),
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
