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
    <div style="background:#000; border:1px solid #000; border-radius:12px; padding:24px;">

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

</div>

{{-- Responsive --}}
<style>
    @media (max-width: 768px) {
        .sub-grid { grid-template-columns: repeat(2, 1fr) !important; }
    }
    @media (max-width: 480px) {
        .sub-grid { grid-template-columns: 1fr !important; }
    }
</style>

@endsection