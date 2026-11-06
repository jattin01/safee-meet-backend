<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use App\Services\Sms\TelnyxSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $exists = EmergencyContact::where('user_id', $userId)
            ->where('phone_number', $request->phone_number)
            ->exists();

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
            'phone_number' => $request->phone_number,
        ]);

        // Let the contact know they were added. Runs after the response is
        // sent so a slow/failed Telnyx call never blocks adding the contact.
        $userName = $user->name ?: 'A SafeeMeet user';
        $text = "Hi {$contact->full_name}, {$userName} has added you as their emergency/trusted contact on "
            . config('app.name', 'SafeeMeet')
            . ". You may receive alerts if they need help.";

        dispatch(function () use ($contact, $text) {
            app(TelnyxSmsService::class)->send($contact->phone_number, $text);
        })->afterResponse();

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
