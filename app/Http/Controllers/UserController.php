<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingReview;
use App\Models\SearchHistory;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
        public function index()
        {
            return view('users.index', [
                'plans' => SubscriptionPlan::query()->orderBy('sort_order')->get(['id', 'name']),
            ]);
        }

        /**
         * Paginated user rows for the users-management table (fetched via JS,
         * same pattern as the admins list). `plan` is eager-loaded because
         * plan_label reads the relation (avoids an N+1 across the page).
         */
        public function data(Request $request): JsonResponse
        {
            $validated = $request->validate([
                'page' => ['sometimes', 'integer', 'min:1'],
                'per_page' => ['sometimes', 'integer', 'in:10,25,50'],
                'search' => ['sometimes', 'nullable', 'string', 'max:255'],
                'status' => ['sometimes', 'nullable', 'in:active,inactive,suspended,deleted'],
                'plan_id' => ['sometimes', 'nullable', 'integer'],
                'date_from' => ['sometimes', 'nullable', 'date'],
                'date_to' => ['sometimes', 'nullable', 'date'],
            ]);

            $users = User::query()
                ->with('plan')
                ->filter($validated)
                ->latest('id')
                ->paginate($validated['per_page'] ?? 10)
                ->withQueryString();

            $users->getCollection()->transform(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name ?: $user->display_name ?: 'Unnamed User',
                'contact' => $user->email ?: $user->phone ?: '—',
                'initials' => $user->initials,
                'avatar_color' => $user->avatar_color,
                'safee_pin' => $user->safee_pin,
                'verification_label' => $user->verification_label,
                'verification_color' => $user->verification_color,
                'plan_label' => $user->plan_label,
                'trust_score' => $user->trust_score !== null ? round($user->trust_score) : null,
                'created_at' => $user->created_at,
                'status' => $user->status,
                'status_label' => $user->status_label,
                'status_color' => $user->status_color,
                'show_url' => route('users.show', $user->id),
            ]);

            return response()->json($users);
        }

        /**
         * CSV export of all users for the users-management table. Streams via
         * chunkById so the full table can be exported without loading every
         * row into memory at once.
         */
        public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
        {
            $validated = $request->validate([
                'search' => ['sometimes', 'nullable', 'string', 'max:255'],
                'status' => ['sometimes', 'nullable', 'in:active,inactive,suspended,deleted'],
                'plan_id' => ['sometimes', 'nullable', 'integer'],
                'date_from' => ['sometimes', 'nullable', 'date'],
                'date_to' => ['sometimes', 'nullable', 'date'],
            ]);

            $filename = 'users-'.now()->format('Y-m-d-His').'.csv';

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($validated) {
                $handle = fopen('php://output', 'w');

                fputcsv($handle, [
                    'ID', 'Name', 'Contact', 'Safee Pin', 'Verification',
                    'Plan', 'Trust Score', 'Status', 'Joined At',
                ]);

                User::query()
                    ->with('plan')
                    ->filter($validated)
                    ->chunkById(500, function ($users) use ($handle) {
                        foreach ($users as $user) {
                            fputcsv($handle, [
                                $user->id,
                                $user->name ?: $user->display_name ?: 'Unnamed User',
                                $user->email ?: $user->phone ?: '—',
                                $user->safee_pin ?? $user->safee_id,
                                $user->verification_label,
                                $user->plan_label,
                                $user->trust_score !== null ? round($user->trust_score) : '',
                                $user->status_label,
                                optional($user->created_at)->format('Y-m-d H:i:s'),
                            ]);
                        }
                    });

                fclose($handle);
            };

            return response()->streamDownload($callback, $filename, $headers);
        }

        /**
         * Quick status change from the users list/profile (active/inactive/
         * suspended). Account deletion is intentionally not offered here —
         * it's a separate, more deliberate action.
         */
        public function updateStatus(Request $request, $id): JsonResponse
        {
            $validated = $request->validate([
                'status' => ['required', 'in:active,inactive,suspended'],
                'reason' => ['nullable', 'string', 'max:500'],
            ]);

            $user = User::findOrFail($id);

            $user->status = $validated['status'];

            if ($validated['status'] === 'suspended') {
                $user->account_suspended_at = now();
                $user->suspended_reason = $validated['reason'] ?? null;
            } else {
                $user->account_suspended_at = null;
                $user->suspended_reason = null;
            }

            $user->save();

            return response()->json([
                'status' => $user->status,
                'status_label' => $user->status_label,
                'status_color' => $user->status_color,
            ]);
        }

        public function show($id)
        {
            $user = User::with(['emergencyContacts', 'verificationLevel', 'userVerification'])->findOrFail($id);

            $meetingsQuery = Meeting::where('host_user_id', $user->id)
                ->orWhere('guest_user_id', $user->id);

            $meetings = (clone $meetingsQuery)
                ->with(['host', 'guest', 'reviews'])
                ->orderByDesc('meeting_date')
                ->limit(5)
                ->get();

            $meetingsCount = (clone $meetingsQuery)->count();

            $reviews = MeetingReview::where('reviewee_id', $user->id)
                ->with('reviewer')
                ->latest()
                ->limit(6)
                ->get();

            $averageRating = MeetingReview::where('reviewee_id', $user->id)->avg('rating');

            $subscription = Subscription::where('user_id', $user->id)
                ->with(['plan', 'userSubscription'])
                ->latest('started_at')
                ->latest('id')
                ->first();

            $subscriptionPlan = $subscription?->plan;
            $subscriptionHistory = $this->subscriptionHistory($user);

            return view('users.show', [
                'user' => $user,
                'meetings' => $meetings,
                'meetingsCount' => $meetingsCount,
                'reviews' => $reviews,
                'averageRating' => $averageRating,
                'subscription' => $subscription,
                'subscriptionPlan' => $subscriptionPlan,
                'subscriptionHistory' => $subscriptionHistory,
            ]);
        }

        /**
         * Keep admin usage rendering catalog-agnostic. Meetings already use
         * the same shape, so adding a persisted meeting balance later will not
         * require changing the Blade component.
         */
        private function subscriptionHistory(User $user): array
        {
            $snapshots = UserSubscription::where('user_id', $user->id)
                ->with(['plan', 'subscription'])
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->get();

            if ($snapshots->isEmpty()) {
                return [];
            }

            $snapshotIds = $snapshots->pluck('id');
            $searchesUsed = SearchHistory::whereIn('user_subscription_id', $snapshotIds)
                ->whereIn('method', ['pin', 'qr'])
                ->selectRaw('user_subscription_id, COUNT(DISTINCT found_user_id) as used')
                ->groupBy('user_subscription_id')
                ->pluck('used', 'user_subscription_id');
            $meetingsUsed = Meeting::withTrashed()
                ->whereIn('user_subscription_id', $snapshotIds)
                ->selectRaw('user_subscription_id, COUNT(*) as used')
                ->groupBy('user_subscription_id')
                ->pluck('used', 'user_subscription_id');
            $currentSnapshotId = $snapshots
                ->first(fn (UserSubscription $snapshot) => in_array($snapshot->status, ['trial', 'active'], true))
                ?->id;

            return $snapshots->map(function (UserSubscription $snapshot) use (
                $searchesUsed,
                $meetingsUsed,
                $currentSnapshotId,
            ) {
                return [
                    'snapshot' => $snapshot,
                    'plan' => $snapshot->plan,
                    'is_current' => $snapshot->id === $currentSnapshotId,
                    'status_label' => ucfirst((string) $snapshot->status),
                    'status_color' => match ($snapshot->status) {
                        'active' => '#4ade80',
                        'trial' => '#facc15',
                        'incomplete' => '#94a3b8',
                        default => '#f87171',
                    },
                    'usage' => $this->subscriptionUsage(
                        $snapshot,
                        (int) ($searchesUsed[$snapshot->id] ?? 0),
                        (int) ($meetingsUsed[$snapshot->id] ?? 0),
                    ),
                    'features' => $this->snapshotFeatures($snapshot),
                ];
            })->all();
        }

        private function snapshotFeatures(UserSubscription $snapshot): array
        {
            $labels = [
                'level_1_verification' => 'Level 1 Verification',
                'level_2_clearance' => 'Level 2 Clearance',
                'verified_badge_display' => 'Verified Badge Display',
                'qr_generation' => 'QR Code Generation',
                'trust_score_calculation' => 'Trust Score Calculation',
                'safety_score_analytics' => 'Safety Score Analytics',
                'trusted_contact_alerts' => 'Trusted Contact Alerts',
                'premium_badge' => 'Premium Badge',
            ];

            return collect($labels)
                ->map(fn (string $label, string $column) => [
                    'label' => $label,
                    'enabled' => (bool) $snapshot->{$column},
                ])
                ->values()
                ->all();
        }

        private function subscriptionUsage(
            UserSubscription $snapshot,
            int $loggedSearches,
            int $meetingsUsed,
        ): array
        {
            return [
                'searches' => $this->usageMetric(
                    'SAFEE PIN Searches',
                    $snapshot->safee_pin_search,
                    $loggedSearches,
                    $snapshot->safee_pin_search_remaining,
                ),
                'meetings' => $this->usageMetric(
                    'Meetings Created',
                    $snapshot->meeting_history,
                    $meetingsUsed,
                ),
            ];
        }

        private function usageMetric(
            string $label,
            mixed $configured,
            int $loggedUsed,
            ?int $persistedRemaining = null,
        ): array {
            $configuredValue = trim((string) $configured);

            if ($configuredValue === '') {
                return [
                    'label' => $label,
                    'total' => '—',
                    'used' => $loggedUsed,
                    'remaining' => '—',
                    'unlimited' => false,
                    'available' => false,
                ];
            }

            if (strcasecmp($configuredValue, 'Unlimited') === 0
                || strcasecmp($configuredValue, 'Full') === 0) {
                return [
                    'label' => $label,
                    'total' => $configuredValue,
                    'used' => $loggedUsed,
                    'remaining' => 'Unlimited',
                    'unlimited' => true,
                    'available' => true,
                ];
            }

            if (is_numeric($configuredValue)) {
                $total = (int) $configuredValue;
                $remaining = $persistedRemaining ?? max(0, $total - $loggedUsed);

                return [
                    'label' => $label,
                    'total' => $total,
                    'used' => max(0, $total - $remaining),
                    'remaining' => $remaining,
                    'unlimited' => false,
                    'available' => true,
                ];
            }

            return [
                'label' => $label,
                'total' => $configuredValue,
                'used' => $loggedUsed,
                'remaining' => '—',
                'unlimited' => false,
                'available' => true,
            ];
        }
}
