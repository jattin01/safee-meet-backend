@extends('layouts.app')

@section('title', 'Subscription Management')

@section('content')
<div class="md:p-6">

    {{-- Page Header --}}
    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px; font-weight:700; color:#fff; margin:0 0 4px 0;">Subscription Management</h1>
        <p style="font-size:12px; color:#6b7280; margin:0;">$284,721 MRR · 23% growth this month</p>
    </div>

    {{-- Stat Cards --}}
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-[15px]" style=" margin-bottom:24px;">

        {{-- Free Users --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:26px; font-weight:700; color:#fff; margin-bottom:4px;">12,847</div>
            <div style="font-size:11px; color:#9ca3af; margin-bottom:6px;">Free Users</div>
            <div style="font-size:11px; color:#22c55e;">+8% this month</div>
        </div>

        {{-- Basic --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:26px; font-weight:700; color:#3b82f6; margin-bottom:4px;">18,204</div>
            <div style="font-size:11px; color:#9ca3af; margin-bottom:6px;">Basic</div>
            <div style="font-size:11px; color:#22c55e;">+14% this month</div>
        </div>

        {{-- Premium --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:26px; font-weight:700; color:#ef4444; margin-bottom:4px;">13,892</div>
            <div style="font-size:11px; color:#9ca3af; margin-bottom:6px;">Premium</div>
            <div style="font-size:11px; color:#22c55e;">+31% this month</div>
        </div>

        {{-- Professional --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:26px; font-weight:700; color:#f59e0b; margin-bottom:4px;">2,348</div>
            <div style="font-size:11px; color:#9ca3af; margin-bottom:6px;">Professional</div>
            <div style="font-size:11px; color:#22c55e;">+22% this month</div>
        </div>

    </div>

    {{-- Revenue by Plan Chart --}}
    <!--div style="background:#000; border:1px solid #000; border-radius:12px; padding:24px;">

        <h2 style="font-size:15px; font-weight:600; color:#fff; margin:0 0 24px 0;">Revenue by Plan</h2>

        {{-- Chart --}}
        <div style="display:flex; flex-direction:column; gap:16px;">

            {{-- Professional --}}
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:90px; font-size:12px; color:#9ca3af; text-align:right; flex-shrink:0;">Professional</div>
                <div style="flex:1; background:#2a2d3e; border-radius:4px; height:28px; overflow:hidden;">
                    <div style="width:72%; height:100%; background:#f59e0b; border-radius:4px; display:flex; align-items:center; padding-left:10px;">
                        <span style="font-size:11px; color:#fff; font-weight:600;">$102k</span>
                    </div>
                </div>
            </div>

            {{-- Premium --}}
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:90px; font-size:12px; color:#9ca3af; text-align:right; flex-shrink:0;">Premium</div>
                <div style="flex:1; background:#2a2d3e; border-radius:4px; height:28px; overflow:hidden;">
                    <div style="width:95%; height:100%; background:#ef4444; border-radius:4px; display:flex; align-items:center; padding-left:10px;">
                        <span style="font-size:11px; color:#fff; font-weight:600;">$134k</span>
                    </div>
                </div>
            </div>

            {{-- Basic --}}
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:90px; font-size:12px; color:#9ca3af; text-align:right; flex-shrink:0;">Basic</div>
                <div style="flex:1; background:#2a2d3e; border-radius:4px; height:28px; overflow:hidden;">
                    <div style="width:38%; height:100%; background:#3b82f6; border-radius:4px; display:flex; align-items:center; padding-left:10px;">
                        <span style="font-size:11px; color:#fff; font-weight:600;">$54k</span>
                    </div>
                </div>
            </div>

            {{-- Free --}}
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:90px; font-size:12px; color:#9ca3af; text-align:right; flex-shrink:0;">Free</div>
                <div style="flex:1; background:#2a2d3e; border-radius:4px; height:28px; overflow:hidden;">
                    <div style="width:6%; height:100%; background:#6b7280; border-radius:4px; display:flex; align-items:center; padding-left:10px;">
                        <span style="font-size:11px; color:#fff; font-weight:600;"></span>
                    </div>
                </div>
            </div>

        </div>

        {{-- X Axis Labels --}}
        <div style="display:flex; justify-content:space-between; margin-top:12px; padding-left:102px;">
            <span style="font-size:10px; color:#6b7280;">$0</span>
            <span style="font-size:10px; color:#6b7280;">$35k</span>
            <span style="font-size:10px; color:#6b7280;">$70k</span>
            <span style="font-size:10px; color:#6b7280;">$105k</span>
            <span style="font-size:10px; color:#6b7280;">$140k</span>
        </div>

    </div>

</div-->

<div x-data="{ activeTab: 'monthly', showForm: false, showEditModal: false, editingPlan: { id: null, name: '', monthly_price: '', yearly_price: '', trial_days: '', features: '' } }" class="mt-[30px]">
    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-3 justify-center md:justify-start">
            <button @click="activeTab = 'monthly'"
                :class="activeTab === 'monthly' ? 'bg-[#DC131C] text-white' : 'bg-transparent text-gray-400 hover:text-white'"
                class="text-sm font-semibold px-5 py-2 rounded-lg transition">
                Monthly
            </button>

            <button @click="activeTab = 'yearly'"
                :class="activeTab === 'yearly' ? 'bg-[#DC131C] text-white' : 'bg-transparent text-gray-400 hover:text-white'"
                class="text-sm font-semibold px-5 py-2 rounded-lg flex items-center gap-2 transition">
                Annually
                <span class="bg-green-400 text-white text-xs font-bold px-2 py-0.5 rounded-md">
                    25% Off
                </span>
            </button>
        </div>

        <button type="button" @click="showForm = !showForm" class="self-center rounded-lg border border-[#DC131C] px-4 py-2 text-sm font-semibold text-[#DC131C] transition hover:bg-[#DC131C] hover:text-white md:self-auto">
            Add New Plan
        </button>
    </div>

    <div x-show="showForm" x-cloak class="mt-6 rounded-2xl border border-[#212529] bg-[#000] p-5">
        <h3 class="mb-4 text-lg font-semibold text-white">Create a new subscription plan</h3>
        <form method="POST" action="{{ route('subscription') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            <input type="hidden" name="action" value="store">
            <div>
                <label class="mb-2 block text-sm text-gray-400" for="name">Plan name</label>
                <input id="name" name="name" required class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="Enterprise">
            </div>
            <div>
                <label class="mb-2 block text-sm text-gray-400" for="monthly_price">Monthly price</label>
                <input id="monthly_price" name="monthly_price" type="number" step="0.01" min="0" required class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="29.00">
            </div>
            <div>
                <label class="mb-2 block text-sm text-gray-400" for="yearly_price">Yearly price</label>
                <input id="yearly_price" name="yearly_price" type="number" step="0.01" min="0" required class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="290.00">
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm text-gray-400" for="trial_days">Free trial days <span class="text-gray-600">(leave blank for no trial)</span></label>
                <input id="trial_days" name="trial_days" type="number" min="0" step="1" class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="100">
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm text-gray-400" for="features">Features (one per line)</label>
                <textarea id="features" name="features" rows="4" class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="Unlimited projects&#10;Priority support"></textarea>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#b50f16]">
                    Add Plan
                </button>
            </div>
        </form>
    </div>

  
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 lg:gap-y-[40px] lg:gap-x-[20px] gap-12 mt-[40px]">
        @foreach($plans as $plan)
            <div x-show="activeTab === 'monthly'" class="border border-[10px] border-[#212529] bg-[#1a1a1a] rounded-2xl p-4 relative" x-cloak>
                <span class="border border-[#ef4444] absolute -top-7 left-1/2 -translate-x-1/2 rounded-full flex items-center justify-center outline outline-[12px] outline-black/60 h-[40px] w-[40px] bg-[#22252c]">
                    <i class="fa-solid {{ $plan['icon'] }}"></i>
                </span>
                <button type="button"
                    @click="showEditModal = true; editingPlan = { id: {{ $plan['id'] }}, name: @js($plan['name']), monthly_price: {{ (float) $plan['monthly_price'] }}, yearly_price: {{ (float) $plan['yearly_price'] }}, trial_days: {{ $plan['trial_days'] ?? "''" }}, features: @js(implode(chr(10), $plan['features'])) }"
                    class="absolute top-2 right-12 rounded-lg border border-blue-400 w-[30px] h-[30px] p-[0px] text-[12px] font-semibold text-blue-400 transition hover:bg-blue-400 hover:text-white">
                    <i class="fa-regular fa-pen-to-square"></i>
                </button>
                <form method="POST" action="{{ route('subscription') }}" class="w-full">
                        @csrf
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="{{ $plan['id'] }}">
                        <button type="submit" class="absolute top-2 right-2 rounded-lg border border-red-500 w-[30px] h-[30px] p-[0px] text-[12px] font-semibold text-red-400 transition hover:bg-red-500 hover:text-white">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </form>
                <h2>{{ $plan['name'] }}</h2>
                <h4 class="text-white text-[30px] font-bold mt-3">${{ number_format($plan['monthly_price'], 2) }}</h4>
                <ul class="pricing-list my-3 flex flex-col gap-[10px] text-[14px]">
                    @foreach($plan['features'] as $feature)
                        <li><i class="fa-regular fa-circle-check"></i> {{ $feature }}</li>
                    @endforeach
                </ul>
                <div class="mt-[10px] flex flex-col gap-2">
                    <button class="mt-3 w-full text-white text-sm font-semibold px-4 py-2 rounded-lg transition {{ $plan['name'] === 'Basic' ? 'bg-[#ef4444]' : 'bg-[#ef4444]' }}">
                        {{ $plan['name'] === 'Basic' ? 'Buy Plan' : 'Buy Plan' }}
                    </button>
                    
                </div>
            </div>

            <div x-show="activeTab === 'yearly'" class="border border-[10px] border-[#212529] bg-[#1a1a1a] rounded-2xl p-4 relative" x-cloak>
                <span class="border border-[#ef4444] absolute -top-7 left-1/2 -translate-x-1/2 rounded-full flex items-center justify-center outline outline-[12px] outline-black/60 h-[40px] w-[40px] bg-[#22252c]">
                    <i class="fa-solid {{ $plan['icon'] }}"></i>
                </span>
                <button type="button"
                    @click="showEditModal = true; editingPlan = { id: {{ $plan['id'] }}, name: @js($plan['name']), monthly_price: {{ (float) $plan['monthly_price'] }}, yearly_price: {{ (float) $plan['yearly_price'] }}, trial_days: {{ $plan['trial_days'] ?? "''" }}, features: @js(implode(chr(10), $plan['features'])) }"
                    class="absolute top-2 right-12 rounded-lg border border-blue-400 w-[30px] h-[30px] p-[0px] text-[12px] font-semibold text-blue-400 transition hover:bg-blue-400 hover:text-white">
                    <i class="fa-regular fa-pen-to-square"></i>
                </button>
                <h2>{{ $plan['name'] }}</h2>
                <h4 class="text-white text-[30px] font-bold mt-3">${{ number_format($plan['yearly_price'], 2) }}</h4>
                <p class="text-sm text-[#ef4444] mb-3">Billed yearly</p>
                <ul class="pricing-list my-3 flex flex-col gap-[10px] text-[14px]">
                    @foreach($plan['features'] as $feature)
                        <li><i class="fa-regular fa-circle-check"></i> {{ $feature }}</li>
                    @endforeach
                </ul>
                <div class="mt-[10px] flex flex-col gap-2">
                    <button class="w-full text-white text-sm font-semibold px-4 py-2 rounded-lg transition {{ $plan['name'] === 'Basic' ? 'bg-[#22c55e]' : 'bg-[#ef4444]' }}">
                        {{ $plan['name'] === 'Basic' ? 'Current Plan' : 'Change Plan' }}
                    </button>
                    <form method="POST" action="{{ route('subscription') }}" class="w-full">
                        @csrf
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="{{ $plan['id'] }}">
                        <button type="submit" class="w-full rounded-lg border border-red-500 px-3 py-2 text-sm font-semibold text-red-400 transition hover:bg-red-500 hover:text-white">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Edit Plan Modal --}}
    <div x-show="showEditModal" x-cloak @click.self="showEditModal = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-lg rounded-2xl border border-[#212529] bg-[#000] p-5">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white">Edit subscription plan</h3>
                <button type="button" @click="showEditModal = false" class="text-gray-400 hover:text-white">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('subscription') }}" class="grid gap-4 md:grid-cols-2">
                @csrf
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" :value="editingPlan.id">
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm text-gray-400" for="edit_name">Plan name</label>
                    <input id="edit_name" name="name" x-model="editingPlan.name" required class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400" for="edit_monthly_price">Monthly price</label>
                    <input id="edit_monthly_price" name="monthly_price" type="number" step="0.01" min="0" x-model="editingPlan.monthly_price" required class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>
                <div>
                    <label class="mb-2 block text-sm text-gray-400" for="edit_yearly_price">Yearly price</label>
                    <input id="edit_yearly_price" name="yearly_price" type="number" step="0.01" min="0" x-model="editingPlan.yearly_price" required class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm text-gray-400" for="edit_trial_days">Free trial days <span class="text-gray-600">(leave blank for no trial)</span></label>
                    <input id="edit_trial_days" name="trial_days" type="number" min="0" step="1" x-model="editingPlan.trial_days" class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]" placeholder="100">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm text-gray-400" for="edit_features">Features (one per line)</label>
                    <textarea id="edit_features" name="features" rows="4" x-model="editingPlan.features" class="w-full rounded-lg border border-[#2a2d3e] bg-[#1a1a1a] px-3 py-2 text-sm text-white outline-none focus:border-[#DC131C]"></textarea>
                </div>
                <div class="md:col-span-2 flex justify-end gap-3">
                    <button type="button" @click="showEditModal = false" class="rounded-lg border border-[#2a2d3e] px-4 py-2 text-sm font-semibold text-gray-300 transition hover:bg-[#2a2d3e]">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#b50f16]">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Responsive --}}
<style>
    [x-cloak] { display: none !important; }
    @media (max-width: 768px) {
        .sub-grid { grid-template-columns: repeat(2, 1fr) !important; }
    }
    @media (max-width: 480px) {
        .sub-grid { grid-template-columns: 1fr !important; }
    }
</style>

@endsection