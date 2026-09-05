<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeetingController extends Controller
{
    // Full set of status values ever used on the meetings table (see the
    // meetings migrations) — offered even before any row with that status
    // exists, same approach as RevenueController::filteredTransactions().
    private const CANONICAL_STATUSES = [
        'draft', 'pending_approval', 'scheduled', 'live', 'active',
        'completed', 'cancelled', 'declined', 'expired',
        'emergency', 'incident_reported',
    ];

    public function index(Request $request)
    {
        [$filteredQuery, $statuses, $validated] = $this->filteredMeetings($request);

        $meetings = $filteredQuery
            ->paginate(15)
            ->withQueryString();

        $data = [
            'meetings' => $meetings,
            'statuses' => $statuses,
            'filters' => $validated,
        ];

        // Filters submit via fetch() with this header, so only the
        // table/pagination fragment is needed for a refresh.
        if ($request->ajax()) {
            return view('meetings.partials.meetings-table', $data);
        }

        return view('meetings.index', $data);
    }

    /**
     * Status options for the Status filter dropdown, loaded via AJAX so the
     * list always reflects the statuses actually used by the app.
     */
    public function statuses(Request $request)
    {
        [, $statuses] = $this->filteredMeetings($request);

        return response()->json(['statuses' => $statuses]);
    }

    /**
     * Username suggestions for the live-search dropdown on the Username
     * filter — scoped to users who actually appear on a meeting (as host or
     * guest), so every suggestion is guaranteed to return at least one meeting.
     */
    public function usernames(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $usernames = \App\Models\User::query()
            ->whereIn('id', function ($query) {
                $query->select('host_user_id')->from('meetings')->whereNull('deleted_at')
                    ->unionAll(
                        \Illuminate\Support\Facades\DB::table('meetings')
                            ->select('guest_user_id')
                            ->whereNull('deleted_at')
                    );
            })
            ->when(
                $validated['q'] ?? null,
                fn ($query, $q) => $query->where('name', 'like', '%'.addcslashes($q, '%_\\').'%'),
            )
            ->whereNotNull('name')
            ->distinct()
            ->orderBy('name')
            ->limit(10)
            ->pluck('name');

        return response()->json(['usernames' => $usernames]);
    }

    /**
     * Builds the shared, filtered Meeting query plus the status options and
     * validated filter values — used by index() (paginated view) and
     * statuses() so filtering/options logic never drifts.
     *
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: \Illuminate\Support\Collection, 2: array}
     */
    private function filteredMeetings(Request $request): array
    {
        $statuses = Meeting::query()
            ->select('status')
            ->distinct()
            ->pluck('status')
            ->filter()
            ->merge(self::CANONICAL_STATUSES)
            ->unique()
            ->sort()
            ->values();

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in($statuses->all())],
            'username' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $query = Meeting::query()
            ->with(['host:id,name,email,phone', 'guest:id,name,email,phone'])
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status),
            )
            ->when(
                $validated['username'] ?? null,
                fn ($query, $username) => $query->where(function ($query) use ($username) {
                    $like = '%'.addcslashes($username, '%_\\').'%';
                    $query->whereHas('host', fn ($q) => $q->where('name', 'like', $like))
                        ->orWhereHas('guest', fn ($q) => $q->where('name', 'like', $like));
                }),
            )
            ->when(
                $validated['start_date'] ?? null,
                fn ($query, $date) => $query->where(
                    'meeting_date', '>=',
                    CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay(),
                ),
            )
            ->when(
                $validated['end_date'] ?? null,
                // meeting_date is a date column, but scheduled_start_at carries
                // the time — compare against the end of the selected day so a
                // meeting later on the end date is still included.
                fn ($query, $date) => $query->where(
                    'meeting_date', '<',
                    CarbonImmutable::createFromFormat('Y-m-d', $date)->addDay()->startOfDay(),
                ),
            )
            ->orderByDesc('meeting_date')
            ->orderByDesc('created_at')
            // Tiebreaker so offset-based pagination never skips/repeats a row.
            ->orderByDesc('id');

        return [$query, $statuses, $validated];
    }
}
