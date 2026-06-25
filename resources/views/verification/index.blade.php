@extends('layouts.app')

@section('title', 'Verification')

@section('content')
<div class="md:p-6">

    {{-- Page Header --}}
    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px; font-weight:700; color:#fff; margin:0 0 4px 0;">Verification Management</h1>
        <p style="font-size:12px; color:#6b7280; margin:0;">234 pending reviews · Average processing 4.2 min</p>
    </div>

    {{-- Stat Cards --}}
    <div class="grid md:grid-cols-3 gap-[15px]" style="margin-bottom:24px;">

        {{-- Card 1 --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:40px; height:40px; border-radius:50%; border:2px solid #22c55e; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#22c55e; font-size:16px;">✓</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">128</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Level 1 Pending</div>
            </div>
        </div>

        {{-- Card 2 --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:40px; height:40px; border-radius:50%; border:2px solid #3b82f6; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#3b82f6; font-size:16px;">◎</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">87</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Level 2 Pending</div>
            </div>
        </div>

        {{-- Card 3 --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px; display:flex; align-items:center; gap:14px;">
            <div style="width:40px; height:40px; border-radius:50%; border:2px solid #f59e0b; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="color:#f59e0b; font-size:16px;">★</span>
            </div>
            <div>
                <div style="font-size:28px; font-weight:700; color:#fff; line-height:1;">19</div>
                <div style="font-size:11px; color:#6b7280; margin-top:3px;">Professional Pending</div>
            </div>
        </div>

    </div>

    {{-- Verification Queue --}}
    <div style="background:#000; border:1px solid #000; border-radius:12px; overflow:hidden;">

        {{-- Queue Header --}}
        <div style="padding:18px 20px; border-bottom:1px solid #1a1a1a;">
            <h2 style="font-size:15px; font-weight:600; color:#fff; margin:0;">Verification Queue</h2>
        </div>

        {{-- Scrollable Table --}}
        <div style="overflow-x:auto; -webkit-overflow-scrolling:touch;">
            <table style="width:100%; min-width:600px; border-collapse:collapse;">
                <tbody>

                    {{-- Row 1 --}}
                    <tr style="border-bottom:1px solid #1a1a1a;">
                        <td style="padding:16px 20px; width:40px;">
                            <div class="p-1" style="width:40px; height:40px; border-radius:50%; background:#374151; display:flex; align-items:center; justify-content:center;">
                               
                                    <img src="images/user.png" class="w-[25px] h-[25px]">
                                
                            </div>
                        </td>
                        <td style="padding:16px 10px;">
                            <div style="font-size:13px; font-weight:600; color:#fff;">Michael Roberts</div>
                            <div style="font-size:11px; color:#6b7280; margin-top:2px;">Driver License + Selfie · Submitted 5 min ago</div>
                        </td>
                        <td style="padding:16px 10px; text-align:right; white-space:nowrap;">
                            <span style="background:rgba(34,197,94,0.15); color:#4ade80; font-size:11px; padding:4px 12px; border-radius:999px; margin-right:8px;">Level 1</span>
                            <button style="background:rgba(34,197,94,0.15); color:#4ade80; border:1px solid #22c55e; font-size:11px; padding:5px 14px; border-radius:6px; cursor:pointer; margin-right:6px;">Approve</button>
                            <button style="background:rgba(239,68,68,0.15); color:#f87171; border:1px solid #ef4444; font-size:11px; padding:5px 14px; border-radius:6px; cursor:pointer;">Reject</button>
                        </td>
                    </tr>

                    {{-- Row 2 --}}
                    <tr style="border-bottom:1px solid #1a1a1a;">
                        <td style="padding:16px 20px; width:40px;">
                            <div class="p-1" style="width:40px; height:40px; border-radius:50%; background:#374151; display:flex; align-items:center; justify-content:center;">
                               
                                    <img src="images/user.png" class="w-[25px] h-[25px]">
                                
                            </div>
                        </td>
                        <td style="padding:16px 10px;">
                            <div style="font-size:13px; font-weight:600; color:#fff;">Rachel Wong</div>
                            <div style="font-size:11px; color:#6b7280; margin-top:2px;">Background Check · Submitted 12 min ago</div>
                        </td>
                        <td style="padding:16px 10px; text-align:right; white-space:nowrap;">
                            <span style="background:rgba(59,130,246,0.15); color:#60a5fa; font-size:11px; padding:4px 12px; border-radius:999px; margin-right:8px;">Level 2</span>
                            <button style="background:rgba(34,197,94,0.15); color:#4ade80; border:1px solid #22c55e; font-size:11px; padding:5px 14px; border-radius:6px; cursor:pointer; margin-right:6px;">Approve</button>
                            <button style="background:rgba(239,68,68,0.15); color:#f87171; border:1px solid #ef4444; font-size:11px; padding:5px 14px; border-radius:6px; cursor:pointer;">Reject</button>
                        </td>
                    </tr>

                    {{-- Row 3 --}}
                    <tr style="border-bottom:1px solid #1a1a1a;">
                        <td style="padding:16px 20px; width:40px;">
                           <div class="p-1" style="width:40px; height:40px; border-radius:50%; background:#374151; display:flex; align-items:center; justify-content:center;">
                               
                                    <img src="images/user.png" class="w-[25px] h-[25px]">
                                
                            </div>
                        </td>
                        <td style="padding:16px 10px;">
                            <div style="font-size:13px; font-weight:600; color:#fff;">Carlos Mendez</div>
                            <div style="font-size:11px; color:#6b7280; margin-top:2px;">Business License · Submitted 28 min ago</div>
                        </td>
                        <td style="padding:16px 10px; text-align:right; white-space:nowrap;">
                            <span style="background:rgba(234,179,8,0.15); color:#facc15; font-size:11px; padding:4px 12px; border-radius:999px; margin-right:8px;">Professional</span>
                            <button style="background:rgba(34,197,94,0.15); color:#4ade80; border:1px solid #22c55e; font-size:11px; padding:5px 14px; border-radius:6px; cursor:pointer; margin-right:6px;">Approve</button>
                            <button style="background:rgba(239,68,68,0.15); color:#f87171; border:1px solid #ef4444; font-size:11px; padding:5px 14px; border-radius:6px; cursor:pointer;">Reject</button>
                        </td>
                    </tr>

                    {{-- Row 4 --}}
                    <tr>
                        <td style="padding:16px 20px; width:40px;">
                            <div class="p-1" style="width:40px; height:40px; border-radius:50%; background:#374151; display:flex; align-items:center; justify-content:center;">
                               
                                    <img src="images/user.png" class="w-[25px] h-[25px]">
                                
                            </div>
                        </td>
                        <td style="padding:16px 10px;">
                            <div style="font-size:13px; font-weight:600; color:#fff;">Jennifer Lee</div>
                            <div style="font-size:11px; color:#6b7280; margin-top:2px;">Driver License + Selfie · Submitted 41 min ago</div>
                        </td>
                        <td style="padding:16px 10px; text-align:right; white-space:nowrap;">
                            <span style="background:rgba(34,197,94,0.15); color:#4ade80; font-size:11px; padding:4px 12px; border-radius:999px; margin-right:8px;">Level 1</span>
                            <button style="background:rgba(34,197,94,0.15); color:#4ade80; border:1px solid #22c55e; font-size:11px; padding:5px 14px; border-radius:6px; cursor:pointer; margin-right:6px;">Approve</button>
                            <button style="background:rgba(239,68,68,0.15); color:#f87171; border:1px solid #ef4444; font-size:11px; padding:5px 14px; border-radius:6px; cursor:pointer;">Reject</button>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>

    </div>

</div>

{{-- Responsive: stack stat cards on mobile --}}
<style>
    @media (max-width: 640px) {
        .stat-grid { grid-template-columns: 1fr !important; }
    }
</style>

@endsection