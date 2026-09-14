<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Support\PortalTheme::name() }} Client</title>
    <!-- PWA -->
    <meta name="theme-color" content="#0f172b">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon.svg">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-portal-theme/>
</head>
<body class="bg-[var(--portal-page)] font-sans text-[var(--portal-text)]">
    <nav class="mb-8 bg-[var(--portal-nav-surface)]">
        <div class="page-container lg:flex lg:h-16 lg:items-center lg:justify-between">
            <div class="flex h-16 items-center justify-between lg:h-auto">
                <a class="text-xl font-semibold text-[var(--portal-nav-text)] no-underline" href="{{ route('documents.index') }}">{{ \App\Support\PortalTheme::name() }}</a>
                @if(Session::has('api_token'))
                    <button id="navbarToggle" type="button"
                            class="inline-flex items-center justify-center rounded-md border-[1px] border-[var(--portal-nav-border)] p-[0.5rem] text-[var(--portal-nav-text-muted)] lg:hidden"
                            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                @endif
            </div>
            @if(Session::has('api_token'))
                <div id="navbarNav"
                     class="hidden flex-col items-start gap-[0.5rem] pb-[0.75rem] lg:flex lg:flex-row lg:items-center lg:gap-6 lg:pb-0">
                    <span class="text-sm text-[var(--portal-nav-text)]">{{ Session::get('user_email') }}</span>
                    <button id="registerBiometricsBtn"
                            class="hidden items-center rounded-md border-[1px] border-[var(--portal-accent-bright)] px-[0.75rem] py-[0.375rem] text-sm font-medium text-[var(--portal-accent-bright-text)] transition hover:bg-[var(--portal-accent-bright)] hover:text-[var(--portal-nav-surface)]">
                        <x-icon.fingerprint class="mr-1 h-4 w-4"/>Register Biometrics
                    </button>
                    <a class="text-sm text-[var(--portal-nav-text-muted)] no-underline transition hover:text-[var(--portal-nav-text)]" href="{{ route('logout') }}">Logout</a>
                </div>
            @endif
        </div>
    </nav>

    <div class="page-container pb-12">
        @yield('content')
    </div>

    <div id="toastContainer"
         class="pointer-events-none fixed right-0 bottom-0 z-[1100] flex flex-col items-end gap-2 p-4">
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', async function () {
        wireNavbarToggle();

        const registerBtn = document.getElementById('registerBiometricsBtn');
        if (!registerBtn) return; // not logged in

        // ── Feature detection ─────────────────────────────────────────────────
        if (!window.PublicKeyCredential) return;

        let platformAvailable = false;
        try {
            platformAvailable = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
        } catch (_) { /* ignore */ }

        if (platformAvailable) {
            registerBtn.classList.remove('hidden');
            registerBtn.classList.add('inline-flex');
        }

        // ── Biometric registration ────────────────────────────────────────────
        registerBtn.addEventListener('click', () => performRegistration());

        async function performRegistration() {
            setButtonLoading(registerBtn, true, 'Registering…');

            try {
                // Step 1 – get registration options (challenge) from server
                const optRes = await fetch('/biometric/register-options', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                });

                if (!optRes.ok) {
                    const body = await optRes.json().catch(() => ({}));
                    throw new Error(body.error || 'Failed to get registration options');
                }

                const options = await optRes.json();

                // Decode base64url strings → ArrayBuffer
                options.challenge  = base64ToBuffer(options.challenge);
                options.user.id    = base64ToBuffer(options.user.id);
                if (Array.isArray(options.excludeCredentials)) {
                    options.excludeCredentials = options.excludeCredentials.map(c => ({
                        ...c,
                        id: base64ToBuffer(c.id),
                    }));
                }

                // Step 2 – invoke platform biometric authenticator (triggers device prompt)
                const credential = await navigator.credentials.create({ publicKey: options });

                // Step 3 – encode result and complete registration on the server
                const attestation = {
                    id:    credential.id,
                    rawId: bufferToBase64(credential.rawId),
                    type:  credential.type,
                    response: {
                        attestationObject: bufferToBase64(credential.response.attestationObject),
                        clientDataJSON:    bufferToBase64(credential.response.clientDataJSON),
                    },
                    clientExtensionResults: credential.getClientExtensionResults
                        ? credential.getClientExtensionResults()
                        : {},
                };

                // Include transports if available (helps server route future assertions)
                if (typeof credential.response.getTransports === 'function') {
                    attestation.response.transports = credential.response.getTransports();
                }

                const completeRes = await fetch('/biometric/complete-registration', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify(attestation),
                });

                if (!completeRes.ok) {
                    const body = await completeRes.json().catch(() => ({}));
                    throw new Error(body.error || 'Registration failed');
                }

                showToast('Biometrics registered successfully! You can now log in with your fingerprint or face.', 'success');

            } catch (err) {
                handleWebAuthnError(err);
            } finally {
                setButtonLoading(registerBtn, false);
            }
        }

        // ── Shared helpers ────────────────────────────────────────────────────

        // Swaps `hidden` for `flex` rather than toggling one class, because the menu carries
        // `lg:flex` for desktop: leaving `hidden` on it would be harmless there but leaving
        // `flex` on it would break the stacked layout the next time the window narrows.
        function wireNavbarToggle() {
            const toggle = document.getElementById('navbarToggle');
            const menu   = document.getElementById('navbarNav');
            if (!toggle || !menu) return;

            toggle.addEventListener('click', () => {
                const opening = menu.classList.contains('hidden');
                menu.classList.toggle('hidden', !opening);
                menu.classList.toggle('flex', opening);
                toggle.setAttribute('aria-expanded', String(opening));
            });
        }

        function handleWebAuthnError(err) {
            console.error('WebAuthn error:', err);
            const messages = {
                NotAllowedError:   'Biometric prompt was dismissed or timed out.',
                InvalidStateError: 'This device is already registered for biometrics.',
                NotSupportedError: 'This device or browser does not support biometric registration.',
                SecurityError:     'A security error occurred. Ensure the site is served over HTTPS.',
                AbortError:        'The biometric operation was aborted.',
            };
            const msg  = messages[err.name] || err.message || 'Biometric registration failed.';
            const type = err.name === 'NotAllowedError' ? 'warning' : 'danger';
            showToast(msg, type);
        }

        // Restores the button's own markup rather than a caller-supplied label, so the icon it was
        // rendered with survives without the ceremony code having to repeat it as a string.
        function setButtonLoading(button, loading, loadingLabel) {
            if (loading) {
                button.dataset.originalHtml = button.innerHTML;
                button.innerHTML = `<span class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" role="status" aria-hidden="true"></span>${loadingLabel}`;
                button.disabled  = true;
            } else {
                button.innerHTML = button.dataset.originalHtml || button.innerHTML;
                button.disabled  = false;
            }
        }

        function base64ToBuffer(base64) {
            const binary = window.atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
            const bytes  = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
            return bytes.buffer;
        }

        function bufferToBase64(buffer) {
            const bytes = new Uint8Array(buffer);
            let binary  = '';
            for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
            return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        }
    });
    </script>
</body>
</html>
