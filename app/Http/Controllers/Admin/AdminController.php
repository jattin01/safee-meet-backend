<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('admins.index', [
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ]);
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role_id' => ['required', Rule::exists('admin_roles', 'id')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', 'boolean'],
        ]);

        // Admin model casts 'password' => 'hashed', so pass it as-is.
        Admin::create($validated);

        return redirect()->route('admins.index')->with('success', 'Admin created successfully.');
    }

    public function updateStatus(Request $request, Admin $admin): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'boolean'],
        ]);

        // An admin can't deactivate their own account and lock themselves out.
        if (! $validated['status'] && $admin->id === $request->user('admin')->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
            ], 422);
        }

        $admin->update(['status' => $validated['status']]);

        return response()->json(['status' => $admin->status]);
    }
}
