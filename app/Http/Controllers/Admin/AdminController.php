<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('admins.index');
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'in:10,25,50'],
        ]);

        $admins = Admin::query()
            ->select(['id', 'role_id', 'name', 'email', 'phone', 'status', 'created_at'])
            ->with('role:id,name')
            ->latest('id')
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();

        return response()->json($admins);
    }
}
