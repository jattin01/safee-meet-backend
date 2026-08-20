<?php

namespace App\Http\Controllers;

use App\Models\IdentityVerification;
use App\Models\User;
use App\Models\UserVerification;
use App\Models\VerificationLevel;
use App\Services\Verification\IdentityVerificationService;
use App\Support\Verification\TrustScoreCalculator;
use App\Support\Verification\VerificationDocumentPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $users = User::with('userVerification')
            ->latest()
            ->paginate(15, ['*'], 'users_page');

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
                $user->update([
                    'verification_level' => 'level1',
                    // 'kyc_status' => 'verified',
                    // 'kyc_verified_at' => now(),
                    'verification_level_id' => VerificationLevel::active()
                        ->where('slug', 'level_1_verified')
                        ->value('id'),
                ]);

                TrustScoreCalculator::recalculate($user);
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
