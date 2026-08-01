<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Didit Identity Verification</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="mx-auto max-w-xl px-6 py-16">
        <div class="rounded-2xl bg-white p-8 shadow-lg ring-1 ring-slate-200">
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Identity verification</p>
            <h1 class="mt-3 text-2xl font-bold">Complete your KYC check</h1>
            <p class="mt-3 text-sm text-slate-600">
                Safee uses Didit’s hosted verification flow to securely collect your identity evidence and return the result to your account automatically.
            </p>

            <button
                id="didit-start"
                class="mt-6 inline-flex items-center rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow hover:bg-blue-700"
            >
                Start verification
            </button>

            <div id="didit-status" class="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                Verification has not started yet.
            </div>
        </div>
    </div>

    <script type="module">
        const statusBox = document.getElementById('didit-status');
        const startButton = document.getElementById('didit-start');

        const startDiditFlow = async () => {
            startButton.disabled = true;
            statusBox.textContent = 'Starting Didit session...';

            const response = await fetch('/api/v1/verification/didit/start', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });

            const payload = await response.json();

            if (!response.ok) {
                statusBox.textContent = payload.message || 'Unable to start verification.';
                startButton.disabled = false;
                return;
            }

            if (!payload.verificationUrl) {
                statusBox.textContent = 'Didit responded without a verification URL.';
                startButton.disabled = false;
                return;
            }

            statusBox.textContent = 'Launching Didit verification UI...';

            try {
                const { DiditSdk } = await import('@didit-protocol/sdk-web');
                const didit = DiditSdk.shared;

                didit.startVerification({
                    url: payload.verificationUrl,
                });

                statusBox.textContent = 'Didit verification has been opened in the SDK modal.';
            } catch (error) {
                window.location.href = payload.verificationUrl;
                statusBox.textContent = 'The SDK bundle was unavailable, so the hosted verification page was opened directly.';
            }

            startButton.disabled = false;
        };

        startButton.addEventListener('click', startDiditFlow);
    </script>
</body>
</html>
