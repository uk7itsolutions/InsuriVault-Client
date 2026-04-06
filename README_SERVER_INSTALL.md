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
- Upload all files from the `upload` folder to your server's web root:
    - **In Plesk**: This is usually `httpdocs` for a main domain or the subdomain's folder (e.g., `demo.insuri-vault.com`) for a subdomain.
    - **In cPanel**: This is usually `public_html`.
- **IMPORTANT**: Ensure you copy `.env.example` to `.env` in your project root. This file now includes a temporary `APP_KEY` that allows the application to boot so you can reach the installer. (The installer will replace this with a secure, unique key during the final step).
- **IMPORTANT**: The `upload` folder does **not** include the `vendor` directory. You must either:
    - **Option A (Recommended)**: Run `composer install` via SSH on your server.
    - **Option B**: Run `composer install` locally and upload the generated `vendor` folder to your server.
    - **Option C**: Use the Plesk/cPanel "Composer" extension to install dependencies.
- **IMPORTANT**: Ensure the document root of your domain/subdomain is pointed to the `/public` directory of the project.

### 2. Set Permissions

Ensure the following directories are writable by the web server (recursively):
- `storage` (and all subfolders)
- `bootstrap/cache`

On many servers, setting these to `775` or `755` is sufficient. If you are on Plesk, ensure the `psv-app`, `www-data` or your FTP user has write access. You can usually set this via the Plesk File Manager by clicking on the folder permissions.

---

## Special Instructions for Plesk Obsidian

### Setting the Document Root for a Subdomain

If you are using a subdomain (e.g., `portal.yourdomain.com`), Plesk might default the document root to the subdomain's folder. You MUST change it to point to the `public` subfolder:

1. Log in to Plesk.
2. Go to **Websites & Domains**.
3. Find your subdomain and click **Hosting Settings**.
4. Locate the **Document root** field. It will likely show something like `subdomain.yourdomain.com`.
5. Change it to `subdomain.yourdomain.com/public`.
6. Click **OK** to save.

### If you cannot change the Document Root

If your hosting provider does not allow changing the document root, you can move the contents of the `public` folder to the subdomain root:

1. Move all files from `upload/public` (including `.htaccess` and `index.php`) directly into your subdomain root (e.g., `httpdocs` or your subdomain folder). Ensure hidden files like `.htaccess` are included.
2. Move all other folders (`app`, `bootstrap`, `config`, etc.) into the same subdomain root.
3. Open `index.php` (now in the root) and change lines 14 and 18:
   - Change `require __DIR__.'/../vendor/autoload.php';` to `require __DIR__.'/vendor/autoload.php';`
   - Change `$app = require_once __DIR__.'/../bootstrap/app.php';` to `$app = require_once __DIR__.'/bootstrap/app.php';`
4. **Note**: This method is less secure as it exposes your configuration files to the web if not handled correctly. We strongly recommend setting the Document Root instead.

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

- **403 Forbidden / Directory Listing / AH01276**: This error occurs when Apache looks for an index file (like `index.php`) in the root of the subdomain and doesn't find it.
    - **Cause**: Apache is serving the base folder (e.g., `demo.insuri-vault.com/`) instead of the `public` folder.
    - **Fix 1**: Ensure your domain's document root is set to the `/public` folder (see Step 1 and the "Special Instructions" below). In Plesk, double-check that the "Document root" field includes the subdomain folder followed by `/public` (e.g., `demo.insuri-vault.com/public`).
    - **Fix 2**: If you cannot change the document root, follow the "If you cannot change the Document Root" section below to move the files from `public/` to your subdomain root.
    - **Verify**: Use the Plesk File Manager to check that `index.php` exists in the exact folder specified as the "Document root" in Hosting Settings.
- **500 Internal Server Error / MissingAppKeyException**: This generic error can have several causes:
    - **Missing .env file**: Ensure you have a `.env` file in your root folder. Copy `.env.example` to `.env`.
    - **Missing Application Key (MissingAppKeyException)**: If you haven't run the installer yet, you might see this error.
        - **Fix 1 (Plesk Composer)**: Go to your domain's **Composer** settings and run the "Artisan" command: `key:generate`.
        - **Fix 2 (Manual)**: Open your `.env` file and set `APP_KEY` to a temporary value to let the application boot and reach the installer:
          `APP_KEY=base64:PlceHolderKeyForInstallerToBoot123456789012=`
          *(Note: The installer will automatically generate a unique, secure key for you during the final step.)*
    - **Incorrect PHP Version**: Ensure your domain is set to use PHP 8.2 or higher in Plesk "PHP Settings".
    - **Missing PHP Extensions**: Ensure `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `curl`, and **`pdo_mysql`** extensions are enabled.
    - **Permissions**: Double-check that `storage` and `bootstrap/cache` are writable recursively. Also ensure the root project folder is writable so the `.env` file can be updated by the installer.
    - **Check Logs**: In Plesk, go to **Logs** for your domain and check the "Apache error" or "nginx error" logs. Also check `storage/logs/laravel.log` if it exists. (See "How to find detailed logs" below).
- **Error on /install/database (Step 5)**: This step runs the database migrations. If it fails:
    - **Cause 1: Missing Extension**: Ensure the `pdo_mysql` PHP extension is enabled in your PHP settings.
    - **Cause 2: Invalid Credentials**: Double-check the database host, name, username, and password you entered in the previous step. Note that some hosts require `127.0.0.1` instead of `localhost`.
    - **Cause 3: Pre-existing Tables**: If you are re-installing, ensure the database is empty.
    - **Cause 4: ModSecurity**: Check the ModSecurity logs (as described above). The migration output can sometimes trigger WAF rules.
    - **Cause 5: .env Not Writable**: If the installer couldn't save your database settings to the `.env` file, the migration step will use the default settings (which will fail). Check that your `.env` file has write permissions.
    - **Cause 6: Call to undefined function fake()**: This occurs if `db:seed` runs in a production environment without dev dependencies. The included `DatabaseSeeder` has been updated to avoid this, but it can still happen with custom seeders.
    - **Workaround**: If this step continues to fail with a 500 error, see "Manual Migration" below.
- **403 Forbidden (ModSecurity / WAF)**: If you see a 403 error in your browser, but your permissions are correct, your server's Web Application Firewall (like ModSecurity with Comodo rules) might be blocking the response.
    - **Cause**: Some WAF rules are overly sensitive and block common words or patterns in the HTML output, suspecting "PHP source code leakage".
    - **Fix (Plesk)**:
        1. Go to **Web Application Firewall (ModSecurity)** for your domain.
        2. Look for the blocked request in the logs. Note the **Rule ID** (e.g., `214620`).
        3. Add this ID to the **Switch off rules** list.
        4. Alternatively, set the WAF mode to **Detection only** temporarily to confirm it's the cause.
    - **Fix (Generic)**: Contact your hosting provider and ask them to whitelist the specific rule ID for your domain.
- **Database Connection Failed**: Double-check your database credentials in the Environment settings step of the wizard.
- **Missing API Settings**: If you need to change your API settings later, you can edit the `.env` file in the project root.

---

### How to find detailed logs

If you encounter a 500 error or other issues where you need more information:

1. **Plesk Log Viewer**: 
   - Log in to Plesk.
   - Go to **Websites & Domains** > **Logs**.
   - Filter by **Apache error** and **nginx error**. Look for entries at the time of the error. This is the most reliable place to see fatal PHP errors.
2. **Laravel Log File**:
   - Check the file `storage/logs/laravel.log` in your project folder.
   - If this file doesn't exist, ensure the `storage/logs` directory is writable (775 or 777).
3. **Enable Display Errors**:
   - In Plesk, go to **PHP Settings** for your domain.
   - Set `display_errors` to `on`.
   - Set `error_reporting` to `E_ALL`.
   - Remember to turn these off once you've fixed the issue.

---

### Manual Migration (Workaround)

If the installer's "Database" step (Step 5) fails with a 500 error, you can run the migrations manually:

1. Ensure your `.env` file contains the correct database credentials.
2. **In Plesk**:
   - Go to your domain's **Laravel** or **Composer** extension.
   - Click **Artisan Command**.
   - Run: `migrate --force`.
3. After the migrations complete successfully, create a file named `installed` in the `storage/` directory to tell the application that installation is finished. 
   *(Note: This empty file prevents the application from redirecting you back to the installer.)*
4. Access your application. If you haven't yet generated an application key, the app will prompt you for one. You can run `key:generate --force` via the Artisan command in Plesk.
