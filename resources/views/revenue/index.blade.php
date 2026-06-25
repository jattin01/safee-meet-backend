@extends('layouts.app')

@section('title', 'Revenue Analytics')

@section('content')
<div class="">

    {{-- Page Header --}}
    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px; font-weight:700; color:#fff; margin:0 0 4px 0;">Revenue Analytics</h1>
        <p style="font-size:12px; color:#6b7280; margin:0;">Financial overview · June 2026</p>
    </div>

    {{-- Stat Cards --}}
   <div class="grid md:grid-cols-3 gap-[15px]" style=" margin-bottom:24px;">

        {{-- MRR --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:11px; color:#6b7280; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">MRR</div>
            <div style="font-size:26px; font-weight:700; color:#fff; margin-bottom:6px;">$284,721</div>
            <div style="font-size:11px; color:#22c55e;">+23% from last month</div>
        </div>

        {{-- ARR --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:11px; color:#6b7280; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">ARR</div>
            <div style="font-size:26px; font-weight:700; color:#fff; margin-bottom:6px;">$3.4M</div>
            <div style="font-size:11px; color:#22c55e;">+31% from last month</div>
        </div>

        {{-- Churn Rate --}}
        <div style="background:#000; border:1px solid #000; border-radius:12px; padding:20px;">
            <div style="font-size:11px; color:#6b7280; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em;">Churn Rate</div>
            <div style="font-size:26px; font-weight:700; color:#fff; margin-bottom:6px;">2.1%</div>
            <div style="font-size:11px; color:#22c55e;">-0.4% from last month</div>
        </div>

    </div>

    {{-- Revenue Trend Chart --}}
    <div style="background:#000; border:1px solid #000; border-radius:12px; padding:24px;">

        <h2 style="font-size:15px; font-weight:600; color:#fff; margin:0 0 20px 0;">Revenue Trend</h2>

        {{-- Chart Container --}}
        <div style="position:relative; width:100%; overflow-x:auto;">
            <svg viewBox="0 0 700 220" xmlns="http://www.w3.org/2000/svg" style="width:100%; min-width:400px; display:block;">

                {{-- Y Axis Labels --}}
                <text x="38" y="20" font-size="10" fill="#6b7280" text-anchor="end">$300k</text>
                <text x="38" y="62" font-size="10" fill="#6b7280" text-anchor="end">$225k</text>
                <text x="38" y="104" font-size="10" fill="#6b7280" text-anchor="end">$150k</text>
                <text x="38" y="146" font-size="10" fill="#6b7280" text-anchor="end">$75k</text>
                <text x="38" y="188" font-size="10" fill="#6b7280" text-anchor="end">$0</text>

                {{-- Horizontal Grid Lines --}}
                <line x1="45" y1="16" x2="690" y2="16" stroke="#2a2d3e" stroke-width="1"/>
                <line x1="45" y1="58" x2="690" y2="58" stroke="#2a2d3e" stroke-width="1"/>
                <line x1="45" y1="100" x2="690" y2="100" stroke="#2a2d3e" stroke-width="1"/>
                <line x1="45" y1="142" x2="690" y2="142" stroke="#2a2d3e" stroke-width="1"/>
                <line x1="45" y1="184" x2="690" y2="184" stroke="#2a2d3e" stroke-width="1"/>

                {{-- Filled area under line (gradient) --}}
                <defs>
                    <linearGradient id="redGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#ef4444" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#ef4444" stop-opacity="0.02"/>
                    </linearGradient>
                </defs>

                {{-- Area fill: Jan=150, Feb=155, Mar=162, Apr=172, May=185, Jun=194 mapped to SVG coords --}}
                {{-- Y: value mapped: 0=$0 => y=184, $300k => y=16. Range=168px for $300k --}}
                {{-- Jan: $150k => y=184-(150/300*168)=184-84=100 --}}
                {{-- Feb: $158k => y=184-(158/300*168)=184-88.5=95.5 --}}
                {{-- Mar: $175k => y=184-(175/300*168)=184-98=86 --}}
                {{-- Apr: $205k => y=184-(205/300*168)=184-114.8=69.2 --}}
                {{-- May: $248k => y=184-(248/300*168)=184-138.9=45.1 --}}
                {{-- Jun: $285k => y=184-(285/300*168)=184-159.6=24.4 --}}

                <polygon
                    points="45,100 163,95 281,86 399,69 517,45 690,24 690,184 45,184"
                    fill="url(#redGrad)"
                />

                {{-- Line --}}
                <polyline
                    points="45,100 163,95 281,86 399,69 517,45 690,24"
                    fill="none"
                    stroke="#ef4444"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                {{-- Dots --}}
                <circle cx="45"  cy="100" r="4" fill="#ef4444"/>
                <circle cx="163" cy="95"  r="4" fill="#ef4444"/>
                <circle cx="281" cy="86"  r="4" fill="#ef4444"/>
                <circle cx="399" cy="69"  r="4" fill="#ef4444"/>
                <circle cx="517" cy="45"  r="4" fill="#ef4444"/>
                <circle cx="690" cy="24"  r="4" fill="#ef4444"/>

                {{-- X Axis Labels --}}
                <text x="45"  y="202" font-size="10" fill="#6b7280" text-anchor="middle">Jan</text>
                <text x="163" y="202" font-size="10" fill="#6b7280" text-anchor="middle">Feb</text>
                <text x="281" y="202" font-size="10" fill="#6b7280" text-anchor="middle">Mar</text>
                <text x="399" y="202" font-size="10" fill="#6b7280" text-anchor="middle">Apr</text>
                <text x="517" y="202" font-size="10" fill="#6b7280" text-anchor="middle">May</text>
                <text x="690" y="202" font-size="10" fill="#6b7280" text-anchor="middle">Jun</text>

            </svg>
        </div>

    </div>

</div>

{{-- Responsive --}}
<style>
    @media (max-width: 640px) {
        .rev-grid { grid-template-columns: 1fr !important; }
    }
</style>

@endsection