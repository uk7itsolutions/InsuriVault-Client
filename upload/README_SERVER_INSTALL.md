# Server Installation Guide (Plesk / cPanel / Shared Hosting)

This guide provides step-by-step instructions for installing the InsuriVault Client Portal on servers like Plesk or cPanel.

## Prerequisites

- PHP >= 8.2
- MySQL / MariaDB database (Recommended for sessions/cache, but can be configured to use file-based storage)
- Apache (with mod_rewrite) or Nginx
- Composer (optional, if you have SSH access)

### Why is a database needed?
While the InsuriVault Client Portal is API-based for its primary business logic, Laravel uses a database to store:
- **Sessions**: To keep you logged in between page requests.
- **Cache**: To speed up the application by storing temporary data.
- **Jobs**: To handle background tasks if needed.

By default, this application is configured to use the `database` driver for sessions and cache to ensure stability and performance in production environments.

---

## Installation Steps

### 1. Upload Files

- Use the files provided in the `upload` folder of the distribution. This folder excludes development-only files like Docker assets and tests.
- Upload all files from the `upload` folder to your server's web root (e.g., `httpdocs` in Plesk or `public_html` in cPanel).
- **IMPORTANT**: Ensure the document root of your domain is pointed to the `/public` directory of the project.

### 2. Set Permissions

Ensure the following directories are writable by the web server:
- `storage`
- `bootstrap/cache`

On many servers, setting these to `775` or `755` is sufficient.

### 3. Create a Database

- Log in to your control panel (Plesk/cPanel).
- Create a new MySQL/MariaDB database.
- Create a database user and assign it to the database with all privileges.
- Keep the database name, username, and password for the next step.

### 4. Run the Installer Wizard

- Open your browser and navigate to your domain: `http://yourdomain.com`
- If the application is not yet installed, you will be automatically redirected to `http://yourdomain.com/install`.
- Follow the wizard's steps:
    1. **Welcome**: Introduction.
    2. **Requirements**: Verifies your server has the correct PHP version and extensions.
    3. **Permissions**: Verifies that the required folders are writable.
    4. **Environment Settings**:
        - **Database Tab**: Enter your database host (usually `127.0.0.1` or `localhost`), database name, username, and password.
        - **Application Tab**: General settings (App Name, URL, etc.).
        - **InsuriVault API Tab**: Enter your API URL, Organization Name, and Origin Host provided by InsuriVault.
    5. **Database**: The wizard will run the migrations and set up the tables.
    6. **Final**: Installation complete!

### 5. Finalize

Once the installation is complete, the application will be ready to use. Navigating to the root URL will take you to the login page.

---

## Troubleshooting

- **403 Forbidden / Directory Listing**: Ensure your domain's document root is set to the `/public` folder.
- **500 Internal Server Error**: Check the logs in `storage/logs/laravel.log` for more details.
- **Database Connection Failed**: Double-check your database credentials in the Environment settings step of the wizard.
- **Missing API Settings**: If you need to change your API settings later, you can edit the `.env` file in the project root.
