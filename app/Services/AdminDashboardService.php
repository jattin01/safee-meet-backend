<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Meeting;
use App\Models\User;
use App\Models\UserVerification;

class AdminDashboardService
{
    /**
     * Data shared by the admin and super-admin dashboard views.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'totalUsers' => User::query()->count(),
            'activeMeetings' => Meeting::query()
                ->whereIn('status', ['scheduled', 'pending_approval', 'active', 'live'])
                ->count(),
            'verificationCount' => UserVerification::query()->count(),
            'openSosCount' => Incident::query()->sos()->open()->count(),
            'meetingCount' => Meeting::query()->count(),
            'incidentCount' => Incident::query()->count(),
            'meetings' => Meeting::query()
                ->with(['host', 'guest'])
                ->latest('created_at')
                ->limit(10)
                ->get(),
            'recentUsers' => User::query()
                ->with(['plan', 'userVerification'])
                ->latest('created_at')
                ->limit(5)
                ->get(),
            'topHosts' => User::query()
                ->withCount('meetings')
                ->whereHas('meetings')
                ->orderByDesc('meetings_count')
                ->orderByDesc('rating')
                ->limit(3)
                ->get(),
        ];
    }
}
