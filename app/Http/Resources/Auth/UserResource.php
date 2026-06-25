<?php

namespace App\Http\Resources\Auth;

use App\Support\Verification\VerificationLevelResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'safeeId'            => $this->safee_id,
            'displayName'        => $this->display_name,
            'avatarUrl'          => $this->avatar_url,
            'accountType'        => $this->account_type,
            'authProvider'       => $this->auth_provider,
            'status'             => $this->status,
            'onboardingStatus'   => $this->onboarding_status,
            'kycStatus'          => $this->kyc_status,
            'trustScore'         => $this->trust_score,
            'trustTier'          => VerificationLevelResolver::fromUser($this->kyc_status, $this->trust_tier),
            'isChatEnabled'      => $this->is_chat_enabled,
            'isMeetingEnabled'   => $this->is_meeting_enabled,
            'isSosEnabled'       => $this->is_sos_enabled,
            'emailVerifiedAt'    => $this->email_verified_at,
            'phoneVerifiedAt'    => $this->phone_verified_at,
            'lastLoginAt'        => $this->last_login_at,
            'createdAt'          => $this->created_at,
        ];
    }
}
