@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Login</h4>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('login') }}" method="POST" id="loginForm">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" name="email" id="email" class="form-control" required
                               value="{{ old('email') }}"
                               autocomplete="username webauthn">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required
                               autocomplete="current-password">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Login</button>
                        <button type="button" id="biometricLoginBtn" class="btn btn-outline-secondary d-none">
                            <i class="bi bi-fingerprint me-1"></i>Login with Biometrics
                        </button>
                        <div id="biometricUnavailable" class="text-center text-muted small d-none">
                            <i class="bi bi-fingerprint me-1"></i>Biometric login not available on this device
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
    const biometricBtn  = document.getElementById('biometricLoginBtn');
    const biometricNote = document.getElementById('biometricUnavailable');
    const emailInput    = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const csrfToken     = document.querySelector('input[name="_token"]').value;

    // ── Feature detection ─────────────────────────────────────────────────────
    if (!window.PublicKeyCredential) {
        // Browser does not support WebAuthn at all
        return;
    }

    let platformAvailable = false;
    try {
        platformAvailable = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
    } catch (_) { /* ignore */ }

    if (platformAvailable) {
        biometricBtn.classList.remove('d-none');
    } else {
        biometricNote.classList.remove('d-none');
    }

    // ── Biometric login ───────────────────────────────────────────────────────
    biometricBtn.addEventListener('click', () => performBiometricLogin());

    async function performBiometricLogin() {
        const email = emailInput.value.trim();
        if (!email) {
            showToast('Please enter your email address first.', 'warning');
            emailInput.focus();
            return;
        }

        // A filled password means the client is asking to enrol rather than to assert: there is
        // nothing to assert with until a credential exists, and the registration ceremony needs a
        // token that only a password sign-in can issue.
        if (passwordInput.value) {
            await signInWithPasswordThenEnrol(email, passwordInput.value);
            return;
        }

        await signInWithCredential(email);
    }

    async function signInWithCredential(email) {
        setButtonLoading(biometricBtn, true, 'Authenticating…');

        try {
            const optRes = await fetch('/biometric/assertion-options', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ email }),
            });

            if (!optRes.ok) {
                const body = await optRes.json().catch(() => ({}));
                throw new Error(body.error || 'Failed to get assertion options');
            }

            const options = await optRes.json();

            // An empty allowCredentials means this account has enrolled nothing. Handing that to the
            // browser starts a discoverable-credential flow and opens the passkey picker, which
            // cannot succeed here and explains nothing about why.
            if (!Array.isArray(options.allowCredentials) || options.allowCredentials.length === 0) {
                showToast('No biometrics are registered for this account. Enter your password and press this button again to set them up.', 'warning');
                passwordInput.focus();
                return;
            }

            options.challenge = base64ToBuffer(options.challenge);
            options.allowCredentials = options.allowCredentials.map(c => ({
                ...c,
                id: base64ToBuffer(c.id),
            }));

            const credential = await navigator.credentials.get({ publicKey: options });

            const assertion = {
                id:    credential.id,
                rawId: bufferToBase64(credential.rawId),
                type:  credential.type,
                response: {
                    authenticatorData: bufferToBase64(credential.response.authenticatorData),
                    clientDataJSON:    bufferToBase64(credential.response.clientDataJSON),
                    signature:         bufferToBase64(credential.response.signature),
                    userHandle: credential.response.userHandle
                        ? bufferToBase64(credential.response.userHandle)
                        : null,
                },
                clientExtensionResults: credential.getClientExtensionResults
                    ? credential.getClientExtensionResults()
                    : {},
            };

            const completeRes = await fetch('/biometric/complete-assertion', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(assertion),
            });

            if (!completeRes.ok) {
                const body = await completeRes.json().catch(() => ({}));
                throw new Error(body.error || 'Assertion failed');
            }

            showToast('Login successful! Redirecting…', 'success');
            window.location.href = '/';

        } catch (err) {
            handleWebAuthnError(err);
        } finally {
            setButtonLoading(biometricBtn, false, '<i class="bi bi-fingerprint me-1"></i>Login with Biometrics');
        }
    }

    /**
     * Signs in with the password and enrols a credential in the same press. The password half has
     * to succeed first: the registration ceremony is authenticated, and there is no token until it
     * does.
     */
    async function signInWithPasswordThenEnrol(email, password) {
        setButtonLoading(biometricBtn, true, 'Signing in…');

        try {
            const loginRes = await fetch('/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ email, password }),
            });

            if (!loginRes.ok) {
                const body = await loginRes.json().catch(() => ({}));
                showToast(body.error || body.message || 'The provided credentials do not match our records.', 'danger');
                return;
            }

            setButtonLoading(biometricBtn, true, 'Registering…');
            await enrolCredential();

            showToast('Biometrics registered! Redirecting…', 'success');
            window.location.href = '/';

        } catch (err) {
            // The password already succeeded, so the client holds a valid session whatever happened
            // after it. Say what went wrong with the enrolment, then send them on rather than
            // stranding them on the login page already signed in.
            if (err.name === 'InvalidStateError') {
                showToast('This device is already registered. Signing you in…', 'info');
            } else {
                handleWebAuthnError(err);
            }
            setTimeout(() => { window.location.href = '/'; }, 2500);
        } finally {
            setButtonLoading(biometricBtn, false, '<i class="bi bi-fingerprint me-1"></i>Login with Biometrics');
        }
    }

    async function enrolCredential() {
        const optRes = await fetch('/biometric/register-options', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        if (!optRes.ok) {
            const body = await optRes.json().catch(() => ({}));
            throw new Error(body.error || 'Failed to get registration options');
        }

        const options = await optRes.json();

        options.challenge = base64ToBuffer(options.challenge);
        options.user.id   = base64ToBuffer(options.user.id);
        if (Array.isArray(options.excludeCredentials)) {
            options.excludeCredentials = options.excludeCredentials.map(c => ({
                ...c,
                id: base64ToBuffer(c.id),
            }));
        }

        const credential = await navigator.credentials.create({ publicKey: options });

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

        if (typeof credential.response.getTransports === 'function') {
            attestation.response.transports = credential.response.getTransports();
        }

        const completeRes = await fetch('/biometric/complete-registration', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(attestation),
        });

        if (!completeRes.ok) {
            const body = await completeRes.json().catch(() => ({}));
            throw new Error(body.error || 'Registration failed');
        }
    }

    // ── Shared helpers ────────────────────────────────────────────────────────

    function handleWebAuthnError(err) {
        console.error('WebAuthn error:', err);
        const messages = {
            NotAllowedError:   'Biometric prompt was dismissed or timed out.',
            InvalidStateError: 'No registered biometric credentials found for this account.',
            NotSupportedError: 'This device or browser does not support biometric login.',
            SecurityError:     'A security error occurred. Ensure the site is served over HTTPS.',
            AbortError:        'The biometric operation was aborted.',
        };
        const msg  = messages[err.name] || err.message || 'Biometric login failed.';
        const type = err.name === 'NotAllowedError' ? 'warning' : 'danger';
        showToast(msg, type);
    }

    function setButtonLoading(btn, loading, label) {
        if (loading) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${label}`;
            btn.disabled  = true;
        } else {
            btn.innerHTML = btn.dataset.originalHtml || label;
            btn.disabled  = false;
        }
    }

    function showToast(message, type) {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const id = 'toast-' + Date.now();
        container.insertAdjacentHTML('beforeend', `
            <div id="${id}" class="toast align-items-center text-bg-${type} border-0"
                 role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto"
                            data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>`);
        const el    = document.getElementById(id);
        const toast = new bootstrap.Toast(el, { autohide: true, delay: 5000 });
        toast.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
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
@endsection
