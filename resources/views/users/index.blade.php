@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="md:p-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">User Management</h1>
            <p class="text-sm text-gray-400 mt-1">{{ number_format($totalUsers) }} total users</p>
        </div>
        <button class="bg-red-500 hover:bg-red-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
            Export CSV
        </button>
    </div>

    {{-- Table Wrapper --}}
    <div class="bg-[#000] rounded-xl border border-[#000]" style="overflow-x:auto; -webkit-overflow-scrolling:touch; width:100%;">
        <table style="min-width:750px; width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr class="border-b border-[#2a2d3e] text-left text-xs uppercase tracking-wide text-red-500 ">
                    <th class="px-5 py-4 font-semibold">User</th>
                     <th class="px-5 py-4 font-semibold">Safee Pin</th>
                     <th class="px-5 py-4 font-semibold">Verification</th>
                     <th class="px-5 py-4 font-semibold">Plan</th>
                     <th class="px-5 py-4 font-semibold">Trust Score</th>
                     <th class="px-5 py-4 font-semibold">Joined</th>
                     <th class="px-5 py-4 font-semibold">Status</th>
                     <th class="px-5 py-4 font-semibold">More Details</th>
                </tr>
            </thead>
            <tbody>

                @forelse($users as $user)
                    <tr style="border-bottom:1px solid #2a2d3e;">
                        <td class="px-5 py-4 text-[#fff] font-medium">
                            <div style="display:flex; align-items:center; gap:10px;">  
                                <div style="width:32px; height:32px; border-radius:50%; background:{{ $user->avatar_color }}; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; flex-shrink:0;">{{ $user->initials }}</div>
                                <div>
                                    <div style="color:#fff; font-weight:600;">{{ $user->name ?: $user->display_name ?: 'Unnamed User' }}</div>
                                    <div style="color:#6b7280; font-size:11px;">{{ $user->email ?: $user->phone ?: '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-[#fff] font-medium">{{ $user->safee_id ? '#'.$user->safee_id : '—' }}</td>
                        <td style="padding:14px 20px;">
                            <span style="background:{{ $user->verification_color }}26; color:{{ $user->verification_color }}; font-size:11px; padding:3px 10px; border-radius:999px;">● {{ $user->verification_label }}</span>
                        </td>
                        <td class="px-5 py-4 text-[#fff] font-medium">{{ $user->plan_label }}</td>
                        <td class="px-5 py-4 text-[#fff] font-medium">{{ $user->trust_score !== null ? round($user->trust_score) : '—' }}</td>
                        <td class="px-5 py-4 text-[#fff] font-medium">{{ $user->created_at?->format('M Y') ?? '—' }}</td>
                        <td class="px-5 py-4 text-[#fff] font-medium">
                            <span style="background:{{ $user->status_color }}26; color:{{ $user->status_color }}; font-size:11px; padding:3px 12px; border-radius:999px;">{{ $user->status_label }}</span>
                        </td>
                        <td class="px-5 py-4 text-[#fff] font-medium">
                            <a href="{{ route('users.show', $user->id) }}" class="see-more-btn">See More</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-4 text-center" style="color:#6b7280;">No users found.</td>
                    </tr>
                @endforelse

            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div class="mt-4">
            {{ $users->links() }}
        </div>
    @endif

</div>
@endsection
