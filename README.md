# InsuriVault Client Portal

A Laravel 12 web application for managing and viewing documents from the InsuriVault API.

## Features
- API-based Authentication
- **WebAuthn Biometric Authentication** (fingerprint / face unlock on Android and other platforms)
- Document Listing by Account
- In-browser Document Viewing (PDF & Images)
- Secure Document Downloading

## Prerequisites
- Docker installed
- Access to the InsuriVault API (or a mock)

## Installation

### Using Docker (Development)

1. Clone the repository.
2. Setup environment:
   ```bash
   cp .env.example .env
   ```
   Ensure you have the correct API settings in `.env`.
   - **`INSURIVAULT_API_URL`**: Point this to `https://client-api-dev.insuri-vault.com` for testing/development, and `https://client-api.insuri-vault.com` for production.
   - **`INSURIVAULT_ORGANIZATION`**: Should match your client's organization name. You can use `"Demo Organization"` when pointing to the development/test API.
   - **`ORGANIZATION_DISPLAY_NAME`**: How your organization is named to the people signing in — the portal shows it when it has to tell a client who to contact. Presentation only; it is never sent to the API, so it is free to differ from `INSURIVAULT_ORGANIZATION`, which has to keep matching the master account. Left unset, the portal says "your administrator" instead.
3. Build and start the containers:
   ```bash
   docker compose up -d --build
   ```
4. Initialize the application:
   ```bash
   docker compose exec app composer install
   docker compose exec app npm install
   docker compose exec app npm run build
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate
   ```

### Server Installation (Plesk / cPanel / Shared Hosting)

For production installation on shared hosting servers like Plesk or cPanel, follow the [Server Installation Guide](README_SERVER_INSTALL.md). 

**The repository root is what deploys.** There is one tree: the files you develop against are the files a server runs. `public/build` is committed, so a checkout carries the built stylesheet and script and a server needs no Node toolchain to produce them.

This replaces the `upload/` folder that earlier versions carried. That folder was a second copy of every shared file, and keeping the two in step was manual — it was missed twice, on the biometric rewrite and on a round of installer CSS fixes, and on both occasions operators got neither change. A copy that has to be remembered is a copy that eventually is not.

Operators install from one of two places, and both are the same tree:

- **A release archive** — attached to each tagged release, built at release time by `git archive` and named `insurivault-client-portal-<tag>.zip`. It omits development-only files through the `export-ignore` rules in `.gitattributes`: `tests/`, `docker/`, `docker-compose.yml`, `phpunit.xml`, `vite.config.js` and `.github/`.
- **A `git pull`** on the server, which is how `demo.insuri-vault.com` is deployed. This brings those development-only files with it. None is reachable over the web — the document root is `public/` — so their presence is untidiness rather than exposure.

The lean download is therefore still available, but it is now produced when a release is cut rather than stored as a second tree under version control. That is the distinction worth keeping: a generated artefact cannot drift from its source, and a committed copy always can.

The application also includes a web-based **Installer Wizard** to help you configure the environment and database during the first visit.

## Running the Application

The application is automatically started by Docker Compose and available at `http://localhost:8000`.

To view logs:
```bash
docker compose logs -f app
```

## Testing

### Using Docker Compose
The primary environment for testing is the `laravel-phpstorm` container, which is configured with `APP_ENV=testing` and its own test database.

Before running tests, check if the containers are online:
```bash
docker compose ps
```

If `insurivault-client-laravel-phpstorm-1` is not running, start it:
```bash
docker compose up -d laravel-phpstorm
```

Run the full test suite from the CLI:
```bash
docker compose exec laravel-phpstorm ./vendor/bin/phpunit
```

Alternatively, if you prefer using `artisan test`:
```bash
docker compose exec laravel-phpstorm php artisan test
```

### AI and Automation Guidelines
**IMPORTANT:** Any AI agent or automated testing script **MUST** use the `laravel-phpstorm` container for testing. Do not use the `app` container for testing as it uses the local development database and environment.

### Using PhpStorm
The project is pre-configured for testing within PhpStorm using the `laravel-phpstorm` service. Use the remote interpreter configured for this container.

The tests cover:
- Login (Success/Failure)
- Document Listing
- Document Viewing
- Document Downloading
- Authentication Middleware

### Manual Testing
1. **Login**: Use a valid email and password recognized by your InsuriVault API instance.
2. **Dashboard**: After login, you should see a list of accounts and files.
3. **View**: Click "View" on any document to see it in the browser.
4. **Download**: Click "Download" to save the file locally.
5. **Security**: Try accessing `/` without logging in; you should be redirected to `/login`.

---

## WebAuthn Biometric Authentication

The portal supports **WebAuthn** for passwordless, biometric login on devices that have a platform authenticator — including Android phones (fingerprint / face unlock) and desktops (Windows Hello, Touch ID, etc.).

### Requirements

- The application **must be served over HTTPS** (or `localhost`) — browsers block WebAuthn on plain HTTP.
- The `INSURIVAULT_ORIGIN_HOST` environment variable must match the domain the browser sees (e.g. `portal.example.com`). The InsuriVault API uses this as the WebAuthn **Relying Party ID**.
- The InsuriVault API backend must have WebAuthn (`BiometricAuthentication`) endpoints enabled.

### How it works

#### Registering biometrics (one-time setup per device)
1. Log in with your email and password as normal.
2. Click **Register Biometrics** in the top navigation bar.
3. Your device will prompt you for your fingerprint, face, or PIN.
4. On success, a passkey is stored on your device and linked to your account in the InsuriVault API.

> The "Register Biometrics" button is hidden automatically when the browser or device does not support WebAuthn.

#### Logging in with biometrics
1. Navigate to `/login`.
2. Enter your **email address** in the email field.
3. Click **Login with Biometrics**.
4. Approve the biometric prompt on your device.
5. You are redirected to the dashboard — no password required.

> The "Login with Biometrics" button is hidden when the device has no platform authenticator available (e.g. a desktop with no Windows Hello set up).

### Android-specific notes
- Use **Chrome for Android** (version 99+) or any browser that supports the WebAuthn API.
- The site must be opened over **HTTPS** — Android Chrome blocks `navigator.credentials` on insecure origins.
- After registering, the passkey is stored in your Google Password Manager (if enabled) and can be synced across Android devices signed into the same Google account.

### Troubleshooting

| Error | Likely cause |
|-------|--------------|
| "Biometric prompt was dismissed or timed out" | User cancelled or the 60-second timeout elapsed. Try again. |
| "No registered biometric credentials found" | Biometrics have not been registered for this account/device. Log in with password and click **Register Biometrics**. |
| "A security error occurred" | The site is not served over HTTPS. |
| Button not visible | The browser/OS does not expose a platform authenticator (WebAuthn unavailable). |
