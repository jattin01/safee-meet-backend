<?php

namespace App\Http\Controllers;

use App\Models\IdentityVerification;
use App\Models\User;
use App\Models\UserVerification;
use App\Services\Verification\IdentityVerificationService;
use App\Services\Verification\UserVerificationLevelService;
use App\Support\Verification\TrustScoreCalculator;
use App\Support\Verification\VerificationDocumentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    // Kept for the legacy identity_verifications wizard the mobile app
    // still calls (/api/v1/verification/status|progress) — its file URLs
    // route here. Not used by the admin review flow below, which reads
    // from user_verifications instead.
    public function showAsset(IdentityVerification $verification, string $asset): StreamedResponse
    {
        $path = app(IdentityVerificationService::class)->verificationAssetPath($verification, $asset);

        abort_unless($path, 404);

        return Storage::disk(config('filesystems.default'))->response($path);
    }

    public function index()
    {
        $verifications = UserVerification::with('user')
            ->latestPerUser()
            ->where('status', 'pending')
            ->latest('submitted_at')
            ->get();

        $counts = [
            'pending' => UserVerification::latestPerUser()->where('status', 'pending')->count(),
            'approvedToday' => UserVerification::latestPerUser()->where('status', 'approved')
                ->whereDate('approved_at', today())
                ->count(),
            'rejected' => UserVerification::latestPerUser()->where('status', 'rejected')->count(),
        ];

        $users = $this->registeredUsersQuery()->paginate(15, ['*'], 'users_page');

        $documentDetails = $verifications
            ->merge($users->getCollection()->pluck('userVerification')->filter())
            ->unique('id')
            ->mapWithKeys(fn (UserVerification $item) => [$item->id => VerificationDocumentPresenter::make($item)]);

        return view('verification.index', [
            'verifications' => $verifications,
            'counts' => $counts,
            'users' => $users,
            'documentDetails' => $documentDetails,
        ]);
    }

    /**
     * AJAX-driven filtering/pagination for the "Registered Users" table on
     * the verification page. Filters by name/email/mobile and by the
     * verification submission date range shown in the "Submitted" column,
     * reusing the same eager-loaded query as index() to avoid N+1s.
     */
    public function usersData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'users_page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $users = $this->registeredUsersQuery($validated)
            ->paginate(15, ['*'], 'users_page');

        $verifications = $users->getCollection()->pluck('userVerification')->filter();

        $documentDetails = $verifications
            ->unique('id')
            ->mapWithKeys(fn (UserVerification $item) => [$item->id => VerificationDocumentPresenter::make($item)]);

        return response()->json([
            'table' => view('verification.partials.users-table', [
                'users' => $users,
            ])->render(),
            'documentDetails' => $documentDetails,
            'total' => $users->total(),
        ]);
    }

    /**
     * @param  array{search?: ?string, start_date?: ?string, end_date?: ?string}  $filters
     */
    private function registeredUsersQuery(array $filters = []): \Illuminate\Database\Eloquent\Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        return User::query()
            ->with('userVerification')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($startDate || $endDate, function ($query) use ($startDate, $endDate) {
                $rangeStart = Carbon::parse($startDate ?: $endDate)->startOfDay();
                $rangeEnd = Carbon::parse($endDate ?: $startDate)->endOfDay();

                $query->whereHas('userVerification', function ($inner) use ($rangeStart, $rangeEnd) {
                    $inner->whereBetween('submitted_at', [$rangeStart, $rangeEnd]);
                });
            })
            ->latest();
    }

    public function show(UserVerification $verification)
    {
        $verification->loadMissing(['user', 'reviewedByAdmin']);

        return view('verification.show', [
            'verification' => $verification,
        ]);
    }

    public function approve(UserVerification $verification): RedirectResponse
    {
        abort_unless(
            $verification->face_id_image && $verification->national_id_front_image && $verification->national_id_back_image,
            422,
            'All required verification documents are not available.'
        );

        DB::transaction(function () use ($verification) {
            $verification->update([
                'status' => 'approved',
                'verification_level' => 1,
                'reviewed_by_admin_id' => Auth::guard('admin')->id(),
                'rejection_reason' => null,
                'reviewed_at' => now(),
                'approved_at' => now(),
                'rejected_at' => null,
            ]);

            if ($user = $verification->user) {
                app(UserVerificationLevelService::class)->promote(
                    $user,
                    'level1',
                    $verification,
                );
            }
        });

        return redirect()->route('verification')->with('success', 'Verification approved.');
    }

    public function reject(Request $request, UserVerification $verification): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($verification, $validated) {
            $verification->update([
                'status' => 'rejected',
                'reviewed_by_admin_id' => Auth::guard('admin')->id(),
                'rejection_reason' => $validated['reason'],
                'reviewed_at' => now(),
                'rejected_at' => now(),
            ]);

            if ($user = $verification->user) {
                $user->update([
                    'kyc_status' => 'rejected',
                ]);

                TrustScoreCalculator::recalculate($user);
            }
        });

        return redirect()->route('verification')->with('success', 'Verification rejected.');
    }
}
