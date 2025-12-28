# InsuriVault Client Portal

A Laravel 12 web application for managing and viewing documents from the InsuriVault API.

## Features
- API-based Authentication
- Document Listing by Account
- In-browser Document Viewing (PDF & Images)
- Secure Document Downloading

## Prerequisites
- Docker installed
- Access to the InsuriVault API (or a mock)

## Installation

1. Clone the repository.
2. Setup environment:
   ```bash
   cp .env.example .env
   ```
   Ensure you have the correct API settings in `.env`.
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

## Running the Application

The application is automatically started by Docker Compose and available at `http://localhost:8000`.

To view logs:
```bash
docker compose logs -f app
```

## Testing

### Using Docker Compose
Run the full test suite:
```bash
docker compose run --rm app php artisan test
```

### Using PhpStorm
The project is pre-configured for testing within PhpStorm using the `laravel-phpstorm` service. See previous setup notes for details on configuring the remote interpreter.

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
