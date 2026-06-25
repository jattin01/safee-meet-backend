<?php

namespace App\Http\Controllers;

use App\Http\Requests\Verification\RejectIdentityVerificationRequest;
use App\Models\IdentityVerification;
use App\Services\Verification\IdentityVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    public function __construct(
        private readonly IdentityVerificationService $verificationService,
    ) {}

    public function index()
    {
        return view('verification.index', [
            'counts' => $this->verificationService->counts(),
            'verifications' => $this->verificationService->pendingVerifications(),
        ]);
    }

    public function approve(IdentityVerification $verification): RedirectResponse
    {
        $this->verificationService->approve($verification);

        return redirect()
            ->route('verification')
            ->with('success', 'Verification approved successfully.');
    }

    public function reject(
        RejectIdentityVerificationRequest $request,
        IdentityVerification $verification,
    ): RedirectResponse {
        $this->verificationService->reject($verification, $request->string('reason')->toString());

        return redirect()
            ->route('verification')
            ->with('success', 'Verification rejected.');
    }

    public function showAsset(IdentityVerification $verification, string $asset): StreamedResponse
    {
        $path = $this->verificationService->verificationAssetPath($verification, $asset);
        abort_if(!$path, 404);

        $disk = Storage::disk(config('filesystems.default'));
        $mimeType = $disk->mimeType($path) ?? 'application/octet-stream';
        $filename = basename($path);

        return $disk->response($path, $filename, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
