@extends('layouts.app')

@section('title', 'Incident Reports')

@section('content')
<div class="">

    {{-- Page Header --}}
    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px; font-weight:700; color:#fff; margin:0 0 4px 0;">Incident Reports</h1>
        <p style="font-size:12px; color:#6b7280; margin:0;">3 active SOS events · 98.7% resolution rate</p>
    </div>

    {{-- Stat Cards --}}
    <div class="grid md:grid-cols-3 gap-[15px]" style=" margin-bottom:24px;">

        {{-- Active SOS --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:38px; height:38px; border-radius:8px; background:rgba(239,68,68,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#ef4444; font-size:16px;">⚠</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">3</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Active SOS</div>
            </div>
        </div>

        {{-- Resolved Today --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:38px; height:38px; border-radius:8px; background:rgba(34,197,94,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#22c55e; font-size:16px;">✓</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">12</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Resolved Today</div>
            </div>
        </div>

        {{-- Under Review --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:38px; height:38px; border-radius:8px; background:rgba(245,158,11,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#f59e0b; font-size:16px;">⏱</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">7</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Under Review</div>
            </div>
        </div>

    </div>

    {{-- Active & Recent Incidents --}}
    <div style="background:#000; border:1px solid #000; border-radius:12px; overflow:hidden;">

        {{-- Header --}}
        <div style="padding:18px 20px; border-bottom:1px solid #1a1a1a;">
            <h2 style="font-size:15px; font-weight:600; color:#fff; margin:0;">Active & Recent Incidents</h2>
        </div>

        {{-- Incident List --}}
        <div style="overflow-x:auto; -webkit-overflow-scrolling:touch;">
            <table style="width:100%; min-width:500px; border-collapse:collapse;">
                <tbody>

                    {{-- Row 1 - Active --}}
                    <tr style="border-bottom:1px solid #2a2d3e;">
                        <td style="padding:16px 20px; width:16px;">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#ef4444;"></span>
                        </td>
                        <td style="padding:16px 10px;">
                            <div style="font-size:13px; font-weight:600; color:#fff;">Emily Torres</div>
                            <div style="font-size:11px; color:#6b7280; margin-top:2px;">SOS Emergency · Chicago, IL</div>
                        </td>
                        <td style="padding:16px 10px; text-align:right; white-space:nowrap;">
                            <span style="font-size:11px; color:#6b7280; margin-right:12px;">3 min ago</span>
                            <span style="background:rgba(34,197,94,0.15); color:#4ade80; font-size:11px; padding:4px 12px; border-radius:999px;">Active</span>
                        </td>
                    </tr>

                    {{-- Row 2 - Resolved --}}
                    <tr style="border-bottom:1px solid #1a1a1a;">
                        <td style="padding:16px 20px; width:16px;">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#22c55e;"></span>
                        </td>
                        <td style="padding:16px 10px;">
                            <div style="font-size:13px; font-weight:600; color:#fff;">Anonymous</div>
                            <div style="font-size:11px; color:#6b7280; margin-top:2px;">SOS False Alarm · Miami, FL</div>
                        </td>
                        <td style="padding:16px 10px; text-align:right; white-space:nowrap;">
                            <span style="font-size:11px; color:#6b7280; margin-right:12px;">1h ago</span>
                            <span style="background:rgba(34,197,94,0.15); color:#4ade80; font-size:11px; padding:4px 12px; border-radius:999px;">Resolved</span>
                        </td>
                    </tr>

                    {{-- Row 3 - Review --}}
                    <tr style="border-bottom:1px solid #1a1a1a;">
                        <td style="padding:16px 20px; width:16px;">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#f59e0b;"></span>
                        </td>
                        <td style="padding:16px 10px;">
                            <div style="font-size:13px; font-weight:600; color:#fff;">James Carter</div>
                            <div style="font-size:11px; color:#6b7280; margin-top:2px;">Unverified User Report · Los Angeles, CA</div>
                        </td>
                        <td style="padding:16px 10px; text-align:right; white-space:nowrap;">
                            <span style="font-size:11px; color:#6b7280; margin-right:12px;">2h ago</span>
                            <span style="background:rgba(245,158,11,0.15); color:#fbbf24; font-size:11px; padding:4px 12px; border-radius:999px;">Review</span>
                        </td>
                    </tr>

                    {{-- Row 4 - Resolved --}}
                    <tr>
                        <td style="padding:16px 20px; width:16px;">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#22c55e;"></span>
                        </td>
                        <td style="padding:16px 10px;">
                            <div style="font-size:13px; font-weight:600; color:#fff;">Sarah Mitchell</div>
                            <div style="font-size:11px; color:#6b7280; margin-top:2px;">Meeting Dispute · New York, NY</div>
                        </td>
                        <td style="padding:16px 10px; text-align:right; white-space:nowrap;">
                            <span style="font-size:11px; color:#6b7280; margin-right:12px;">4h ago</span>
                            <span style="background:rgba(34,197,94,0.15); color:#4ade80; font-size:11px; padding:4px 12px; border-radius:999px;">Resolved</span>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>

    </div>

</div>

{{-- Responsive --}}
<style>
    @media (max-width: 640px) {
        .incident-grid { grid-template-columns: 1fr !important; }
    }
</style>

@endsection