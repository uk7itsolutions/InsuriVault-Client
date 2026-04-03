<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InsuriVault Client</title>
    <!-- PWA -->
    <meta name="theme-color" content="#0d6efd">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon.svg">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar { margin-bottom: 2rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="{{ route('documents.index') }}">InsuriVault</a>
            @if(Session::has('api_token'))
                <div class="navbar-nav ms-auto align-items-center">
                    <span class="nav-item nav-link text-light me-3 mb-0">{{ Session::get('user_email') }}</span>
                    <button id="registerBiometricsBtn" class="btn btn-sm btn-outline-info me-3">
                        Register Biometrics
                    </button>
                    <a class="nav-link" href="{{ route('logout') }}">Logout</a>
                </div>
            @endif
        </div>
    </nav>

    <div class="container">
        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const registerBtn = document.getElementById('registerBiometricsBtn');
            if (registerBtn) {
                registerBtn.addEventListener('click', async () => {
                    try {
                        const optionsResponse = await fetch('/biometric/register-options', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });

                        if (!optionsResponse.ok) throw new Error('Failed to get registration options');
                        const options = await optionsResponse.json();

                        // Convert base64 string to ArrayBuffer where needed
                        options.challenge = base64ToArrayBuffer(options.challenge);
                        options.user.id = base64ToArrayBuffer(options.user.id);
                        if (options.excludeCredentials) {
                            options.excludeCredentials.forEach(cred => {
                                cred.id = base64ToArrayBuffer(cred.id);
                            });
                        }

                        // Create credential
                        const credential = await navigator.credentials.create({ publicKey: options });

                        // Prepare response for backend
                        const response = {
                            id: credential.id,
                            rawId: arrayBufferToBase64(credential.rawId),
                            type: credential.type,
                            response: {
                                attestationObject: arrayBufferToBase64(credential.response.attestationObject),
                                clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON)
                            }
                        };

                        // Complete Registration
                        const completeResponse = await fetch('/biometric/complete-registration', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(response)
                        });

                        if (completeResponse.ok) {
                            alert('Biometrics registered successfully!');
                        } else {
                            alert('Biometrics registration failed.');
                        }
                    } catch (error) {
                        console.error(error);
                        alert('Biometrics registration failed: ' + error.message);
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
</body>
</html>
