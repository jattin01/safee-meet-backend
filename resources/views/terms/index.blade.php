@extends('layouts.app')

@section('title', 'Terms & Conditions')

@section('content')
<div class="p-4">
    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-[#000] border border-[#000] rounded-lg p-4">
        <h2 class="text-white font-semibold mb-4">Terms & Conditions</h2>

        <form method="POST" action="{{ route('terms.update') }}">
            @csrf

            <!-- Editor Container -->
            <div id="editor" style="min-height: 200px;">{!! $terms->content ?? '' !!}</div>

            <!-- Hidden input to store value on form submit -->
            <input type="hidden" name="content" id="content">

            <div class="mt-4 flex justify-end">
                <button type="submit" class="rounded-lg bg-[#DC131C] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#b50f16]">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    var quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Write something...',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'link'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                ['clean']
            ]
        }
    });

    // On form submit, copy editor content to hidden input
    document.querySelector('form').addEventListener('submit', function() {
        document.querySelector('#content').value = quill.root.innerHTML;
    });
</script>
@endsection
