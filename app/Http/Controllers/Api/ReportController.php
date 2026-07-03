<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    /**
     * POST /api/reports
     * Module 11 — Safety Reports: Fake User / Fraud / Harassment / General Incident
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['fake_user', 'fraud', 'harassment', 'general_incident'])],
            'meeting_id' => ['nullable', 'exists:meetings,id'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $incident = Incident::create($validated + [
            'reporter_user_id' => $request->user()->id,
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Report submitted. Our safety team will review it shortly.',
            'incident' => $incident,
        ], 201);
    }

    /**
     * GET /api/reports — the user's own report history
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->incidentsReported()->latest()->get()
        );
    }
}
