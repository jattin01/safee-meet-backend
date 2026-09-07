<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobTitle;
use Illuminate\Http\JsonResponse;

class JobTitleController extends Controller
{
    /**
     * GET /api/v1/job-titles — public list of active job titles, for the
     * registration/profile job-title dropdown. No auth required so it can be
     * loaded on the signup screen before the user has a token.
     */
    public function index(): JsonResponse
    {
        $jobTitles = JobTitle::active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $jobTitles,
        ]);
    }
}
