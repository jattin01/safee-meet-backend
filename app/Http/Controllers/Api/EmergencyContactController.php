<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Sos\SendSosAlertSms;
use App\Models\EmergencyContact;
use App\Services\Sms\TelnyxSmsService;
use App\Support\Phone\PhoneNumberNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class EmergencyContactController extends Controller
{



    public function store(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'phone_number' => 'required|string|max:20',
        ]);

        $user = $request->user();
        $userId = $user->id;

        // The number is stored exactly as typed so the app sees no change.
        // The country code is only resolved (from the user's registered
        // phone) when an SMS is sent.
        $typedNumber = (string) $request->phone_number;
        $smsNumber = PhoneNumberNormalizer::forSms($typedNumber, $user->phone);

        // The same number typed another way ("098765 43210" vs
        // "+91 98765 43210") is still a duplicate.
        $exists = EmergencyContact::where('user_id', $userId)
            ->pluck('phone_number')
            ->contains(fn ($saved) => $saved === $typedNumber
                || ($smsNumber && PhoneNumberNormalizer::forSms((string) $saved, $user->phone) === $smsNumber));

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'This phone number already exists.'
            ], 409);
        }

        $contact = EmergencyContact::create([
            'user_id' => $userId,
            'full_name' => $request->full_name,
            'relationship' => $request->relationship,
            'phone_number' => $typedNumber,
        ]);

        // Let the contact know they were added. Runs after the response is
        // sent so a slow/failed Telnyx call never blocks adding the contact.
        if ($smsNumber) {
            $userName = SendSosAlertSms::personName($user);
            $text = "Hi {$contact->full_name}, {$userName} has added you as their emergency/trusted contact on "
                . config('app.name', 'SafeeMeet')
                . ". You may receive alerts if they need help.";

            dispatch(function () use ($smsNumber, $text) {
                app(TelnyxSmsService::class)->send($smsNumber, $text);
            })->afterResponse();
        } else {
            Log::warning('Emergency contact added with a number SMS cannot reach', [
                'user_id' => $userId,
                'contact_id' => $contact->id,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Emergency contact added successfully.',
            'data' => $contact,
        ]);

    }

    public function destroy(Request $request, $id)
    {
        $contact = EmergencyContact::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$contact) {
            return response()->json([
                'status' => false,
                'message' => 'Emergency contact not found.'
            ], 404);
        }

        $contact->delete();

        return response()->json([
            'status' => true,
            'message' => 'Emergency contact deleted successfully.'
        ]);
    }


    public function index(Request $request)
    {
        $contacts = EmergencyContact::where('user_id', $request->user()->id)->get();

        if ($contacts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No emergency contacts found.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $contacts
        ]);
    }
    // public function index(Request $request): JsonResponse
    // {
    //     $contacts = $request->user()
    //         ->emergencyContacts()
    //         ->latest()
    //         ->get();

    //     return response()->json([
    //         'status' => true,
    //         'data' => $contacts,
    //     ]);
    // }

    // public function store(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'full_name' => ['required', 'string', 'max:255'],
    //         'relationship' => ['required', 'string', 'max:100'],
    //         'phone_number' => [
    //             'required',
    //             'string',
    //             'max:20',
    //             Rule::unique('emergency_contacts')->where(
    //                 fn ($query) => $query->where('user_id', $request->user()->id)
    //             ),
    //         ],
    //     ]);

    //     $contact = $request->user()->emergencyContacts()->create($validated);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Emergency contact added successfully.',
    //         'data' => $contact,
    //     ], 201);
    // }

    // public function destroy(Request $request, EmergencyContact $emergencyContact): JsonResponse
    // {
    //     abort_unless(
    //         (int) $emergencyContact->user_id === (int) $request->user()->id,
    //         403,
    //         'You cannot delete another user\'s emergency contact.'
    //     );

    //     $emergencyContact->delete();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Emergency contact deleted successfully.',
    //     ]);
    // }
}
