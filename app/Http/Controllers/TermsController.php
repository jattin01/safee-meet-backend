<?php

namespace App\Http\Controllers;

use App\Models\Terms;
use Illuminate\Http\Request;

class TermsController extends Controller
{
    public function index()
    {
        $terms = Terms::first();

        return view('terms.index', ['terms' => $terms]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'content' => ['nullable', 'string'],
        ]);

        $terms = Terms::first() ?? new Terms();
        $terms->content = $validated['content'] ?? '';
        $terms->updated_by = auth('admin')->id();
        $terms->save();

        return redirect()->route('terms.index')->with('success', 'Terms & Conditions updated successfully.');
    }
}
