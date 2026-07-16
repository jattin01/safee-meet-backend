<?php

namespace App\Services\Verification;

use App\Models\IdentityDocument;
use App\Models\IdentityVerification;
use App\Models\SelfieVerification;
use App\Models\User;
use App\Support\Verification\VerificationLevelResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IdentityVerificationService
{
    private const EDITABLE_STATUSES = ['draft', 'rejected', 'expired'];
    private const BLOCKED_STATUSES = ['pending', 'manual_review', 'approved'];

    public function getStatus(User $user): array
    {
        $verification = $this->latestVerificationForUser($user);
        $progress = $this->serializeProgress($verification);
        $publicLevel = VerificationLevelResolver::fromUser($user->kyc_status, $user->trust_tier);
        $level1Complete = $progress['status'] === 'approved';

        return [
            'trustScore' => (int) ($user->trust_score ?? 0),
            'verificationLevel' => $publicLevel,
            'level1Complete' => $level1Complete,
            'level2Complete' => false,
            'professionalComplete' => false,
            'safetyMetricMeetings' => $level1Complete ? 0.7 : 0.25,
            'safetyMetricResponsiveness' => $level1Complete ? 0.8 : 0.2,
            'safetyMetricReviews' => $level1Complete ? 0.6 : 0.1,
            'recentReviews' => [],
            'kycStatus' => $progress['status'],
            'currentStep' => $progress['currentStep'],
            'rejectionReason' => $progress['rejectionReason'],
            'submittedAt' => $verification?->submitted_at?->toIso8601String(),
            'reviewedAt' => $verification?->reviewed_at?->toIso8601String(),
        ];
    }

    public function getProgress(User $user): array
    {
        return $this->serializeProgress($this->latestVerificationForUser($user));
    }

    public function serializeProgress(?IdentityVerification $verification): array
    {
        $document = $verification?->documents->sortByDesc('created_at')->first();
        $selfie = $verification?->selfieVerifications->sortByDesc('created_at')->first();

        return [
            'id' => $verification?->id,
            'currentStep' => $this->determineCurrentStep($verification, $document, $selfie),
            'status' => $this->presentStatus($verification),
            'rejectionReason' => $verification?->rejection_reason ?? $document?->rejection_reason ?? $selfie?->failure_reason,
            'hasIdFront' => !empty($document?->front_file_url),
            'hasIdBack' => !empty($document?->back_file_url),
            'hasSelfie' => !empty($selfie?->selfie_file_url),
            'idFrontUrl' => $document?->front_file_url ? route('verification.files.show', ['verification' => $verification->id, 'asset' => 'front']) : null,
            'idBackUrl' => $document?->back_file_url ? route('verification.files.show', ['verification' => $verification->id, 'asset' => 'back']) : null,
            'selfieUrl' => $selfie?->selfie_file_url ? route('verification.files.show', ['verification' => $verification->id, 'asset' => 'selfie']) : null,
            'submittedAt' => $verification?->submitted_at?->toIso8601String(),
            'reviewedAt' => $verification?->reviewed_at?->toIso8601String(),
        ];
    }

    public function uploadIdDocument(
        User $user,
        UploadedFile $front,
        UploadedFile $back,
        ?string $documentType = null,
        ?string $issuingCountryCode = null,
    ): IdentityVerification {
        $existing = $this->latestVerificationForUser($user);
        $this->guardAgainstLockedVerification($existing);

        return DB::transaction(function () use ($user, $front, $back, $documentType, $issuingCountryCode, $existing) {
            $verification = $this->editableVerificationForUser($user, $existing);
            $disk = Storage::disk(config('filesystems.default'));
            $directory = sprintf('verifications/%s/%s', $user->id, $verification->id);

            $document = $verification->documents()->latest('created_at')->first();
            if (!$document) {
                $document = new IdentityDocument([
                    'user_id' => $user->id,
                ]);
                $document->identity_verification_id = $verification->id;
            }

            $frontPath = $disk->putFile($directory, $front);
            $backPath = $disk->putFile($directory, $back);

            $document->fill([
                'document_type' => $documentType ?? 'other',
                'issuing_country_code' => strtoupper($issuingCountryCode ?? 'ZZ'),
                'front_file_url' => $frontPath,
                'back_file_url' => $backPath,
                'status' => 'pending',
                'rejection_reason' => null,
                'created_by_user_id' => $document->created_by_user_id ?? $user->id,
                'updated_by_user_id' => $user->id,
            ]);
            $document->save();

            $verification->fill([
                'status' => 'draft',
                'rejection_reason' => null,
                'submitted_at' => null,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'created_by_user_id' => $verification->created_by_user_id ?? $user->id,
                'updated_by_user_id' => $user->id,
                'metadata' => array_filter([
                    'frontUploadedAt' => now()->toIso8601String(),
                    'documentType' => $document->document_type,
                    'issuingCountryCode' => $document->issuing_country_code,
                ]),
            ]);
            $verification->save();

            return $verification->fresh(['documents', 'selfieVerifications']);
        });
    }

    public function uploadSelfie(User $user, UploadedFile $selfie): IdentityVerification
    {
        $existing = $this->latestVerificationForUser($user);
        $this->guardAgainstLockedVerification($existing);

        if (!$existing || !$existing->documents()->exists()) {
            throw new HttpException(422, 'Upload your ID document before submitting a selfie.');
        }

        return DB::transaction(function () use ($user, $selfie, $existing) {
            $disk = Storage::disk(config('filesystems.default'));
            $directory = sprintf('verifications/%s/%s', $user->id, $existing->id);
            $selfiePath = $disk->putFile($directory, $selfie);

            $selfieRecord = $existing->selfieVerifications()->latest('created_at')->first();
            if (!$selfieRecord) {
                $selfieRecord = new SelfieVerification([
                    'user_id' => $user->id,
                ]);
                $selfieRecord->identity_verification_id = $existing->id;
            }

            $selfieRecord->fill([
                'selfie_file_url' => $selfiePath,
                'status' => 'pending',
                'failure_reason' => null,
                'created_by_user_id' => $selfieRecord->created_by_user_id ?? $user->id,
                'updated_by_user_id' => $user->id,
            ]);
            $selfieRecord->save();

            $existing->fill([
                'status' => 'pending',
                'submitted_at' => now(),
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'rejection_reason' => null,
                'updated_by_user_id' => $user->id,
                'metadata' => array_merge($existing->metadata ?? [], [
                    'selfieUploadedAt' => now()->toIso8601String(),
                ]),
            ]);
            $existing->save();

            $user->forceFill([
                'kyc_status' => 'pending',
                'updated_at' => now(),
            ])->save();

            return $existing->fresh(['documents', 'selfieVerifications']);
        });
    }

    public function pendingVerifications(): Collection
    {
        return IdentityVerification::query()
            ->with(['user', 'documents', 'selfieVerifications'])
            ->whereIn('status', ['pending', 'manual_review'])
            ->latest('submitted_at')
            ->get();
    }

    public function counts(): array
    {
        return [
            'pendingLevel1' => IdentityVerification::query()->where('status', 'pending')->count(),
            'pendingManualReview' => IdentityVerification::query()->where('status', 'manual_review')->count(),
            'approvedToday' => IdentityVerification::query()
                ->where('status', 'approved')
                ->whereDate('reviewed_at', today())
                ->count(),
        ];
    }

    public function approve(IdentityVerification $verification, ?User $reviewer = null): IdentityVerification
    {
        return DB::transaction(function () use ($verification, $reviewer) {
            $verification->loadMissing(['documents', 'selfieVerifications', 'user']);

            $verification->forceFill([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $reviewer?->id,
                'rejection_reason' => null,
                'expires_at' => now()->addYear(),
            ])->save();

            $verification->documents->each(function (IdentityDocument $document) use ($reviewer) {
                $document->forceFill([
                    'status' => 'approved',
                    'rejection_reason' => null,
                    'updated_by_user_id' => $reviewer?->id,
                ])->save();
            });

            $verification->selfieVerifications->each(function (SelfieVerification $selfie) use ($reviewer) {
                $selfie->forceFill([
                    'status' => 'approved',
                    'failure_reason' => null,
                    'updated_by_user_id' => $reviewer?->id,
                ])->save();
            });

            $verification->user->forceFill([
                'kyc_status' => 'verified',
                'trust_score' => max((int) $verification->user->trust_score, 60),
                'trust_tier' => 'low',
                'updated_at' => now(),
            ])->save();

            return $verification->fresh(['user', 'documents', 'selfieVerifications']);
        });
    }

    public function reject(IdentityVerification $verification, string $reason, ?User $reviewer = null): IdentityVerification
    {
        return DB::transaction(function () use ($verification, $reason, $reviewer) {
            $verification->loadMissing(['documents', 'selfieVerifications', 'user']);

            $verification->forceFill([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $reviewer?->id,
                'rejection_reason' => $reason,
            ])->save();

            $verification->documents->each(function (IdentityDocument $document) use ($reason, $reviewer) {
                $document->forceFill([
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                    'updated_by_user_id' => $reviewer?->id,
                ])->save();
            });

            $verification->selfieVerifications->each(function (SelfieVerification $selfie) use ($reason, $reviewer) {
                $selfie->forceFill([
                    'status' => 'rejected',
                    'failure_reason' => $reason,
                    'updated_by_user_id' => $reviewer?->id,
                ])->save();
            });

            $verification->user->forceFill([
                'kyc_status' => 'rejected',
                'trust_score' => 0,
                'updated_at' => now(),
            ])->save();

            return $verification->fresh(['user', 'documents', 'selfieVerifications']);
        });
    }

    public function verificationAssetPath(IdentityVerification $verification, string $asset): ?string
    {
        $verification->loadMissing(['documents', 'selfieVerifications']);

        $document = $verification->documents->sortByDesc('created_at')->first();
        $selfie = $verification->selfieVerifications->sortByDesc('created_at')->first();

        return match ($asset) {
            'front' => $document?->front_file_url,
            'back' => $document?->back_file_url,
            'selfie' => $selfie?->selfie_file_url,
            default => null,
        };
    }

    private function latestVerificationForUser(User $user): ?IdentityVerification
    {
        return IdentityVerification::query()
            ->with(['documents', 'selfieVerifications'])
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->first();
    }

    private function editableVerificationForUser(User $user, ?IdentityVerification $existing = null): IdentityVerification
    {
        if ($existing && in_array($existing->status, ['draft'], true)) {
            return $existing;
        }

        return IdentityVerification::create([
            'user_id' => $user->id,
            'verification_level' => 'basic',
            'status' => 'draft',
            'provider' => 'manual',
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
            'metadata' => [],
        ]);
    }

    private function guardAgainstLockedVerification(?IdentityVerification $verification): void
    {
        if (!$verification) {
            return;
        }

        if (in_array($verification->status, self::BLOCKED_STATUSES, true)) {
            $message = $verification->status === 'approved'
                ? 'Your identity is already verified.'
                : 'Your verification is already under review.';

            throw new HttpException(409, $message);
        }
    }

    private function determineCurrentStep(
        ?IdentityVerification $verification,
        ?IdentityDocument $document,
        ?SelfieVerification $selfie,
    ): string {
        if (!$verification) {
            return 'uploadId';
        }

        return match ($verification->status) {
            'approved' => 'complete',
            'pending', 'manual_review' => 'processing',
            'rejected', 'expired' => 'uploadId',
            default => $selfie?->selfie_file_url ? 'processing' : ($document?->front_file_url ? 'selfie' : 'uploadId'),
        };
    }

    private function presentStatus(?IdentityVerification $verification): string
    {
        if (!$verification) {
            return 'not_started';
        }

        return $verification->status;
    }
}
