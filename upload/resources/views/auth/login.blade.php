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
                        <input type="email" name="email" id="email" class="form-control" required value="{{ old('email') }}">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Login</button>
                        <button type="button" id="biometricLoginBtn" class="btn btn-outline-secondary">
                            Login with Biometrics
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const biometricLoginBtn = document.getElementById('biometricLoginBtn');
        const emailInput = document.getElementById('email');

        if (biometricLoginBtn) {
            biometricLoginBtn.addEventListener('click', async () => {
                const email = emailInput.value;
                if (!email) {
                    alert('Please enter your email first.');
                    return;
                }

                try {
                    // Get Assertion Options
                    const optionsResponse = await fetch('/biometric/assertion-options', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                        },
                        body: JSON.stringify({ email })
                    });

                    if (!optionsResponse.ok) throw new Error('Failed to get login options');
                    const options = await optionsResponse.json();

                    // Convert base64 string to ArrayBuffer where needed
                    options.challenge = base64ToArrayBuffer(options.challenge);
                    if (options.allowCredentials) {
                        options.allowCredentials.forEach(cred => {
                            cred.id = base64ToArrayBuffer(cred.id);
                        });
                    }

                    // Get credential from authenticator
                    const credential = await navigator.credentials.get({ publicKey: options });

                    // Prepare response for backend
                    const response = {
                        id: credential.id,
                        rawId: arrayBufferToBase64(credential.rawId),
                        type: credential.type,
                        response: {
                            authenticatorData: arrayBufferToBase64(credential.response.authenticatorData),
                            clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
                            signature: arrayBufferToBase64(credential.response.signature),
                            userHandle: credential.response.userHandle ? arrayBufferToBase64(credential.response.userHandle) : null
                        }
                    };

                    // Complete Assertion
                    const completeResponse = await fetch('/biometric/complete-assertion', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                        },
                        body: JSON.stringify(response)
                    });

                    if (completeResponse.ok) {
                        window.location.href = '/';
                    } else {
                        alert('Biometric login failed.');
                    }
                } catch (error) {
                    console.error(error);
                    alert('Biometric login failed: ' + error.message);
                }
            });
        }

        function base64ToArrayBuffer(base64) {
            const binaryString = window.atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
            const len = binaryString.length;
            const bytes = new Uint8Array(len);
            for (let i = 0; i < len; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }
            return bytes.buffer;
        }

        function arrayBufferToBase64(buffer) {
            let binary = '';
            const bytes = new Uint8Array(buffer);
            const len = bytes.byteLength;
            for (let i = 0; i < len; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
            return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        }
    });
</script>
@endsection
