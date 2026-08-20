<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerificationLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VerificationLevelController extends Controller
{
    public function index(): View
    {
        $levels = VerificationLevel::orderBy('sort_order')->orderBy('name')->get();

        return view('verification-levels.index', [
            'levels' => $levels,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ]);

        $data = collect($validated)->except('icon')->all();

        if ($request->hasFile('icon')) {
            $data['badge_icon'] = $request->file('icon')->store('badges', 'public');
        }

        VerificationLevel::create($data + [
            'slug' => $this->uniqueSlug($validated['name']),
            'sort_order' => (int) VerificationLevel::max('sort_order') + 1,
        ]);

        return redirect()->route('verification-levels.index')->with('success', 'Verification level created successfully.');
    }

    public function update(Request $request, VerificationLevel $verificationLevel): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ]);

        $data = collect($validated)->except('icon')->all();

        if ($request->hasFile('icon')) {
            if ($verificationLevel->badge_icon) {
                Storage::disk('public')->delete($verificationLevel->badge_icon);
            }

            $data['badge_icon'] = $request->file('icon')->store('badges', 'public');
        }

        // slug stays stable once created, same as Feature — anything keying on
        // it (e.g. a future API contract) shouldn't break on a rename.
        $verificationLevel->update($data);

        return redirect()->route('verification-levels.index')->with('success', 'Verification level updated successfully.');
    }

    public function destroy(VerificationLevel $verificationLevel): RedirectResponse
    {
        // Soft delete: keeps the row (and any history referencing it)
        // intact, just hides it from the active catalog going forward.
        // The icon file itself is left on disk — it's still referenced by
        // this (trashed) row until a restore or a future hard-delete flow.
        $verificationLevel->delete();

        return redirect()->route('verification-levels.index')->with('success', 'Verification level deleted successfully.');
    }

    public function restore(int $verificationLevel): RedirectResponse
    {
        VerificationLevel::withTrashed()->findOrFail($verificationLevel)->restore();

        return redirect()->route('verification-levels.index')->with('success', 'Verification level restored successfully.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'verification_level';
        $slug = $base;
        $i = 2;

        while (VerificationLevel::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'_'.$i++;
        }

        return $slug;
    }
}
