@extends('layouts.app')

@section('title', 'Verification Levels')

@section('content')
<style>[x-cloak] { display: none !important; }</style>
<div class="md:p-6"
     x-data="{
        showCreate: {{ $errors->any() ? 'true' : 'false' }},
        showEdit: false,
        badgePreview: null,
        editing: { id: null, name: '', description: '', icon_url: null, is_active: 1 },
        openEdit(l) { this.editing = l; this.showEdit = true; },
        openBadgePreview(url, name) { this.badgePreview = { url, name }; },
        closeBadgePreview() { this.badgePreview = null; }
     }"
     @keydown.escape.window="closeBadgePreview()">

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Verification Levels</h1>
            <p class="mt-1 text-sm text-gray-400">{{ $levels->count() }} levels</p>
        </div>
        <button type="button" @click="showCreate = true"
            class="self-start rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#b50f16] sm:self-auto">
            + Add Verification Level
        </button>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-400">
            {{ session('success') }}
        </div>
    @endif

    {{-- ── Verification level catalog ──────────────────────────────────── --}}
    <div class="mb-8 overflow-x-auto rounded-xl border border-[#2a2d3e] bg-black">
        <table class="w-full min-w-[700px] border-collapse text-[13px]">
            <thead>
                <tr class="border-b border-[#2a2d3e] text-left text-xs uppercase tracking-wide text-red-500">
                    <th class="px-5 py-4">Verification Level</th>
                    <th class="px-5 py-4">Description</th>
                    <th class="px-5 py-4">Status</th>
                    <th class="px-5 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($levels as $level)
                    <tr class="border-b border-[#2a2d3e] last:border-b-0">
                        <td class="px-5 py-3 font-semibold text-white">
                            <div class="flex items-center gap-3">
                                @if($level->badge_icon)
                                    <button type="button"
                                        @click="openBadgePreview(@js('/storage/'.$level->badge_icon), @js($level->name))"
                                        class="w-8 flex-none cursor-zoom-in rounded focus:outline-none focus:ring-2 focus:ring-red-500"
                                        aria-label="View {{ $level->name }} badge image">
                                        <img src="{{ '/storage/'.$level->badge_icon }}" alt="{{ $level->name }} badge" width="32" height="32" class=" w-8 rounded object-cover">
                                    </button>
                                @endif
                                <div class="min-w-0">
                                    <div class="truncate">{{ $level->name }}</div>
                                    <div class="truncate text-[11px] font-normal text-gray-600">{{ $level->slug }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-300">{{ $level->description ?: '—' }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-3 py-1 text-xs {{ $level->is_active ? 'bg-green-500/15 text-green-400' : 'bg-red-500/15 text-red-400' }}">
                                {{ $level->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button"
                                    @click="openEdit({ id: {{ $level->id }}, name: @js($level->name), description: @js($level->description), icon_url: @js($level->badge_icon ? '/storage/'.$level->badge_icon : null), is_active: {{ $level->is_active ? 1 : 0 }} })"
                                    class="rounded-lg border border-blue-400 px-2.5 py-1 text-xs text-blue-400 transition hover:bg-blue-400 hover:text-white">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </button>
                                <form method="POST" action="{{ route('verification-levels.destroy', $level) }}"
                                      onsubmit="return confirm('Delete this verification level? Already-awarded levels keep their history.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-red-500 px-2.5 py-1 text-xs text-red-400 transition hover:bg-red-500 hover:text-white">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-gray-500">No verification levels yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Badge image preview --}}
    <div x-show="badgePreview" x-cloak @click.self="closeBadgePreview()"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4"
        role="dialog" aria-modal="true" aria-label="Badge image preview">
        <div class="relative w-full max-w-lg rounded-2xl border border-[#2a2d3e] bg-[#111] p-6">
            <button type="button" @click="closeBadgePreview()"
                class="absolute right-3 top-3 flex size-8 items-center justify-center rounded-full bg-white text-black shadow transition hover:bg-gray-200"
                aria-label="Close badge image preview">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <img :src="badgePreview?.url" :alt="`${badgePreview?.name ?? ''} badge`"
                class="mx-auto max-h-[70vh] w-full rounded-xl bg-white object-contain p-2">
            <p class="mt-3 text-center text-sm font-semibold text-white" x-text="badgePreview?.name"></p>
        </div>
    </div>

    {{-- ── Create Verification Level Modal ─────────────────────────────── --}}
    <div x-show="showCreate" x-cloak @click.self="showCreate = false"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-lg rounded-2xl border border-[#212529] bg-[#000] p-5">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white">Add a verification level</h3>
                <button type="button" @click="showCreate = false" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>

            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('verification-levels.store') }}" enctype="multipart/form-data" class="grid gap-4">
                @csrf
                <div>
                    <label class="mb-2 block text-sm text-gray-400">Name</label>
                    <input name="name" value="{{ old('name') }}" required class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="Level 1 Verified">
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400">Badge <span class="text-gray-600">(optional)</span></label>
                    <input type="file" name="icon" accept="image/*" class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none file:mr-3 file:rounded-md file:border-0 file:bg-[#DC131C] file:px-3 file:py-1.5 file:text-white focus:border-[#DC131C]">
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400">Description <span class="text-gray-600">(optional)</span></label>
                    <input name="description" value="{{ old('description') }}" class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400">Status</label>
                    <select name="is_active" class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                        <option value="1" @selected(old('is_active', '1') === '1')>Active</option>
                        <option value="0" @selected(old('is_active') === '0')>Inactive</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="showCreate = false" class="rounded-lg border border-[#2a2d3e] px-4 py-2 text-sm font-semibold text-gray-300 transition hover:bg-[#2a2d3e]">Cancel</button>
                    <button type="submit" class="rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#b50f16]">Add Level</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Edit Verification Level Modal ───────────────────────────────── --}}
    <div x-show="showEdit" x-cloak @click.self="showEdit = false"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-lg rounded-2xl border border-[#212529] bg-[#000] p-5">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white">Edit verification level</h3>
                <button type="button" @click="showEdit = false" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" :action="`{{ url('verification-levels') }}/${editing.id}`" enctype="multipart/form-data" class="grid gap-4">
                @csrf @method('PUT')
                <div>
                    <label class="mb-2 block text-sm text-gray-400">Name</label>
                    <input name="name" x-model="editing.name" required class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400">Badge</label>
                    <template x-if="editing.icon_url">
                        <img :src="editing.icon_url" alt="" class="mb-2 h-10 w-10 rounded object-cover">
                    </template>
                    <input type="file" name="icon" accept="image/*" class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none file:mr-3 file:rounded-md file:border-0 file:bg-[#DC131C] file:px-3 file:py-1.5 file:text-white focus:border-[#DC131C]">
                    <p class="mt-1 text-xs text-gray-600">Leave blank to keep the current image.</p>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400">Status</label>
                    <select name="is_active" x-model="editing.is_active" required class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400">Description</label>
                    <input name="description" x-model="editing.description" class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="showEdit = false" class="rounded-lg border border-[#2a2d3e] px-4 py-2 text-sm font-semibold text-gray-300 transition hover:bg-[#2a2d3e]">Cancel</button>
                    <button type="submit" class="rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#b50f16]">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
