<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmergencyContactController extends Controller
{



    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'full_name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'phone_number' => 'required|string|max:20',
        ]);

        $exists = EmergencyContact::where('user_id', $request->user_id)
            ->where('phone_number', $request->phone_number)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'This phone number already exists.'
            ], 409);
        }

        $contact = EmergencyContact::create([
            'user_id' => $request->user_id,
            'full_name' => $request->full_name,
            'relationship' => $request->relationship,
            'phone_number' => $request->phone_number,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Emergency contact added successfully.',
            'data' => $contact,
        ]);
    
    }

    public function destroy($id)
    {
        $contact = EmergencyContact::find($id);

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
   

    public function index($id)
    {
        $contacts = EmergencyContact::where('user_id', $id)->get();

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
