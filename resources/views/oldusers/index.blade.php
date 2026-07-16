@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="md:p-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">User Management</h1>
            <p class="text-sm text-gray-400 mt-1">47,292 total users</p>
        </div>
        <button class="bg-red-500 hover:bg-red-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
            Export CSV
        </button>
    </div>

    {{-- Table Wrapper --}}
    <div class="bg-[#000] rounded-xl border border-[#000]" style="overflow-x:auto; -webkit-overflow-scrolling:touch; width:100%;">
        <table style="min-width:750px; width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="border-bottom:1px solid #2a2d3e; color:#6b7280; font-size:11px; text-transform:uppercase; letter-spacing:0.05em;">
                    <th class="text-[#ef4444] text-[12px]" style="text-align:left; padding:14px 20px; ">User</th>
                    <th class="text-[#ef4444] text-[12px]" style="text-align:left; padding:14px 20px;">Safee Pin</th>
                    <th class="text-[#ef4444] text-[12px]" style="text-align:left; padding:14px 20px;">Verification</th>
                    <th class="text-[#ef4444] text-[12px]" style="text-align:left; padding:14px 20px;">Plan</th>
                    <th class="text-[#ef4444] text-[12px]" style="text-align:left; padding:14px 20px;">Trust Score</th>
                    <th class="text-[#ef4444] text-[12px]" style="text-align:left; padding:14px 20px;">Joined</th>
                    <th class="text-[#ef4444] text-[12px]" style="text-align:left; padding:14px 20px;">Status</th>
                    <th class="text-[#ef4444] text-[12px]" style="padding:14px 20px;"></th>
                </tr>
            </thead>
            <tbody>

                {{-- Row 1 --}}
                <tr style="border-bottom:1px solid #2a2d3e;">
                    <td style="padding:14px 20px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:32px; height:32px; border-radius:50%; background:#3b82f6; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">AJ</div>
                            <div>
                                <div style="color:#fff; font-weight:600;">Alex Johnson</div>
                                <div style="color:#6b7280; font-size:11px;">alex@mail.com</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:14px 20px; color:#9ca3af;">#SM-7821</td>
                    <td style="padding:14px 20px;">
                        <span style="background:rgba(59,130,246,0.15); color:#60a5fa; font-size:11px; padding:3px 10px; border-radius:999px;">● L2</span>
                    </td>
                    <td style="padding:14px 20px; color:#fff; font-weight:600;">Premium</td>
                    <td style="padding:14px 20px; color:#f87171; font-weight:700;">94</td>
                    <td style="padding:14px 20px; color:#9ca3af;">May 2024</td>
                    <td style="padding:14px 20px;">
                        <span style="background:rgba(34,197,94,0.15); color:#4ade80; font-size:11px; padding:3px 12px; border-radius:999px;">Active</span>
                    </td>
                    <td style="padding:14px 20px; color:#6b7280;">···</td>
                </tr>

                {{-- Row 2 --}}
                <tr style="border-bottom:1px solid #2a2d3e;">
                    <td style="padding:14px 20px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:32px; height:32px; border-radius:50%; background:#f97316; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">SM</div>
                            <div>
                                <div style="color:#fff; font-weight:600;">Sarah Mitchell</div>
                                <div style="color:#6b7280; font-size:11px;">sarah@mail.com</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:14px 20px; color:#9ca3af;">#SM-4291</td>
                    <td style="padding:14px 20px;">
                        <span style="background:rgba(59,130,246,0.15); color:#60a5fa; font-size:11px; padding:3px 10px; border-radius:999px;">● L2</span>
                    </td>
                    <td style="padding:14px 20px; color:#fff; font-weight:600;">Premium</td>
                    <td style="padding:14px 20px; color:#f87171; font-weight:700;">98</td>
                    <td style="padding:14px 20px; color:#9ca3af;">Mar 2024</td>
                    <td style="padding:14px 20px;">
                        <span style="background:rgba(34,197,94,0.15); color:#4ade80; font-size:11px; padding:3px 12px; border-radius:999px;">Active</span>
                    </td>
                    <td style="padding:14px 20px; color:#6b7280;">···</td>
                </tr>

                {{-- Row 3 --}}
                <tr style="border-bottom:1px solid #2a2d3e;">
                    <td style="padding:14px 20px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:32px; height:32px; border-radius:50%; background:#22c55e; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">JC</div>
                            <div>
                                <div style="color:#fff; font-weight:600;">James Carter</div>
                                <div style="color:#6b7280; font-size:11px;">james@mail.com</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:14px 20px; color:#9ca3af;">#JC-8834</td>
                    <td style="padding:14px 20px;">
                        <span style="background:rgba(34,197,94,0.15); color:#4ade80; font-size:11px; padding:3px 10px; border-radius:999px;">● L3</span>
                    </td>
                    <td style="padding:14px 20px; color:#fff; font-weight:600;">Basic</td>
                    <td style="padding:14px 20px; color:#f87171; font-weight:700;">87</td>
                    <td style="padding:14px 20px; color:#9ca3af;">Jun 2024</td>
                    <td style="padding:14px 20px;">
                        <span style="background:rgba(34,197,94,0.15); color:#4ade80; font-size:11px; padding:3px 12px; border-radius:999px;">Active</span>
                    </td>
                    <td style="padding:14px 20px; color:#6b7280;">···</td>
                </tr>

                {{-- Row 4 --}}
                <tr style="border-bottom:1px solid #2a2d3e;">
                    <td style="padding:14px 20px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:32px; height:32px; border-radius:50%; background:#a855f7; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">ET</div>
                            <div>
                                <div style="color:#fff; font-weight:600;">Emily Torres</div>
                                <div style="color:#6b7280; font-size:11px;">emily@mail.com</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:14px 20px; color:#9ca3af;">#ET-2217</td>
                    <td style="padding:14px 20px;">
                        <span style="background:rgba(234,179,8,0.15); color:#facc15; font-size:11px; padding:3px 10px; border-radius:999px;">● Pro</span>
                    </td>
                    <td style="padding:14px 20px; color:#fff; font-weight:600;">Professional</td>
                    <td style="padding:14px 20px; color:#f87171; font-weight:700;">99</td>
                    <td style="padding:14px 20px; color:#9ca3af;">Jan 2024</td>
                    <td style="padding:14px 20px;">
                        <span style="background:rgba(34,197,94,0.15); color:#4ade80; font-size:11px; padding:3px 12px; border-radius:999px;">Active</span>
                    </td>
                    <td style="padding:14px 20px; color:#6b7280;">···</td>
                </tr>

                {{-- Row 5 --}}
                <tr>
                    <td style="padding:14px 20px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:32px; height:32px; border-radius:50%; background:#6b7280; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">MR</div>
                            <div>
                                <div style="color:#fff; font-weight:600;">Michael Roberts</div>
                                <div style="color:#6b7280; font-size:11px;">mike@mail.com</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:14px 20px; color:#9ca3af;">#MR-5512</td>
                    <td style="padding:14px 20px;">
                        <span style="background:rgba(107,114,128,0.15); color:#9ca3af; font-size:11px; padding:3px 10px; border-radius:999px;">● Pend</span>
                    </td>
                    <td style="padding:14px 20px; color:#fff; font-weight:600;">Free</td>
                    <td style="padding:14px 20px; color:#f87171; font-weight:700;">0</td>
                    <td style="padding:14px 20px; color:#9ca3af;">Jun 2025</td>
                    <td style="padding:14px 20px;">
                        <span style="background:rgba(239,68,68,0.15); color:#f87171; font-size:11px; padding:3px 12px; border-radius:999px;">Inactive</span>
                    </td>
                    <td style="padding:14px 20px; color:#6b7280;">···</td>
                </tr>

            </tbody>
        </table>
    </div>

</div>
@endsection