<?php

namespace App\Http\Controllers;

use App\Models\PrivacyPolicy;
use Illuminate\Http\Request;

class PrivacyPolicyController extends Controller
{
    public function index()
    {
        $privacyPolicy = PrivacyPolicy::first();

        return view('privacy-policy.index', ['privacyPolicy' => $privacyPolicy]);
    }

    public function public()
    {
        $privacyPolicy = PrivacyPolicy::first();

        return view('privacy-policy.public', ['privacyPolicy' => $privacyPolicy]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'content' => ['nullable', 'string'],
        ]);

        $privacyPolicy = PrivacyPolicy::first() ?? new PrivacyPolicy();
        $privacyPolicy->content = $validated['content'] ?? '';
        $privacyPolicy->updated_by = auth('admin')->id();
        $privacyPolicy->save();

        return redirect()->route('privacy-policy.index')->with('success', 'Privacy Policy updated successfully.');
    }
}
