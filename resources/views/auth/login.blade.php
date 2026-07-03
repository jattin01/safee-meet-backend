<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Safee Meet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="min-h-screen bg-[#1a1a1a] text-white antialiased" style="font-family: Inter, ui-sans-serif, system-ui, sans-serif;">
    <main class="flex min-h-screen items-center justify-center px-5 py-10">
        <section class="w-full max-w-[440px] overflow-hidden rounded-xl border border-[#2a2d3e] bg-black shadow-2xl shadow-black/40">
            <div class="border-b border-[#1a1a1a] px-8 py-7 text-center">
                <img src="{{ asset('images/logo.png') }}" alt="Safee Meet" class="mx-auto h-14 w-auto bg-white object-contain">
                <h1 class="mt-5 text-2xl font-bold tracking-tight">Welcome to Safee Meet</h1>
                <p class="mt-2 text-sm text-[#8f98ad]">Sign in to your administration dashboard</p>
            </div>

            <form action="{{ route('login.submit') }}" method="POST" class="space-y-5 px-8 py-7">
                @csrf

                <div>
                    <label for="email" class="mb-2 block text-sm font-semibold text-[#cbd2e1]">Email address</label>
                    <div class="flex items-center rounded-md border border-[#2a2d3e] bg-[#1a1a1a] px-3 focus-within:border-[#DC131C]">
                        <i class="fa-regular fa-envelope text-sm text-[#697386]"></i>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="admin@safeemeet.com"
                            autocomplete="email"
                            required
                            autofocus
                            class="w-full border-0 bg-transparent px-3 py-3 text-sm text-white outline-none placeholder:text-[#697386]"
                        >
                    </div>
                    @error('email')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-semibold text-[#cbd2e1]">Password</label>
                    <div class="flex items-center rounded-md border border-[#2a2d3e] bg-[#1a1a1a] px-3 focus-within:border-[#DC131C]">
                        <i class="fa-solid fa-lock text-sm text-[#697386]"></i>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                            class="w-full border-0 bg-transparent px-3 py-3 text-sm text-white outline-none placeholder:text-[#697386]"
                        >
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex w-fit cursor-pointer items-center gap-3 text-sm text-[#8f98ad]">
                    <input type="checkbox" name="remember" value="1"
                        class="h-4 w-4 rounded border-[#343746] bg-[#1a1a1a] accent-[#DC131C]">
                    Remember me
                </label>

                <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-md bg-[#DC131C] px-4 py-3 text-sm font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500/40">
                    Sign In
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                </button>
            </form>

            <div class="border-t border-[#1a1a1a] px-8 py-4 text-center text-xs text-[#697386]">
                Safee Meet administration
            </div>
        </section>
    </main>
</body>
</html>
