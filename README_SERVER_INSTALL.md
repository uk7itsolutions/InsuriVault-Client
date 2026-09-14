# Server Installation Guide (Plesk / cPanel / Shared Hosting)

This guide provides step-by-step instructions for installing the InsuriVault Client Portal on servers like Plesk or cPanel.

## Prerequisites

- PHP >= 8.2
- MySQL / MariaDB database (Recommended for sessions/cache, but can be configured to use file-based storage)
- Apache (with mod_rewrite) or Nginx
- Composer (optional, if you have SSH access)
- A valid TLS certificate — **required for biometric login only** (see below). The rest of the portal works over plain HTTP.

### Why is a database needed?
While the InsuriVault Client Portal is API-based for its primary business logic, Laravel uses a database to store:
- **Sessions**: To keep you logged in between page requests.
- **Cache**: To speed up the application by storing temporary data.
- **Jobs**: To handle background tasks if needed.

By default, this application is configured to use the `database` driver for sessions and cache to ensure stability and performance in production environments.

### Why HTTPS is required for biometric login

Biometric (WebAuthn) login only works over HTTPS. Browsers expose the WebAuthn API exclusively in a **secure context** — a valid TLS certificate, or `localhost` for local development. On a plain-HTTP deployment `window.PublicKeyCredential` is undefined, and biometric login cannot work at all. A self-signed or expired certificate counts as insecure and fails the same way.

Two further points follow from how WebAuthn binds credentials to a domain:

- **Serve the portal from the hostname registered as your master account hostname.** A WebAuthn credential is bound to the *relying party ID*, which InsuriVault derives from that registered hostname. If you serve the portal from a different domain, the browser refuses the credential before the request reaches the API. Changing the hostname you serve from later means clients must enrol their biometrics again.
- **Credentials do not move between deployments.** A biometric credential enrolled on one portal cannot be used on another, by design — that binding is what makes WebAuthn resistant to phishing, and it is what keeps one deployment's clients out of another's.

Everything else in the portal works over plain HTTP. Only biometric login has this requirement.

---

## Installation Steps

### 1. Get the Files onto the Server

The portal is a single tree: what you download is what the server runs. Pick whichever route your hosting allows.

**Option A — Download the release archive (recommended for Plesk / cPanel).**

1. Go to the [Releases page](https://github.com/uk7itsolutions/InsuriVault-Client/releases) and download `insurivault-client-portal-<version>.zip` from the latest release.
2. Extract it. Everything sits under a single `insurivault-client-portal/` folder.
3. Upload the **contents** of that folder — not the folder itself — to your domain's directory:
    - **In Plesk**: usually `httpdocs` for a main domain, or the subdomain's folder (e.g. `demo.insuri-vault.com`) for a subdomain.
    - **In cPanel**: usually `public_html`.
    - Include hidden files such as `.env.example` and `public/.htaccess`. Most FTP clients hide them by default.

**Option B — Clone with git (needs SSH access).**

```bash
git clone https://github.com/uk7itsolutions/InsuriVault-Client.git .
```

Upgrading later is then `git pull`, which is the advantage of this route. A clone also brings development-only files — `tests/`, `docker/`, `docker-compose.yml`, `phpunit.xml`, `vite.config.js` and `.github/`. None of them is reachable over the web, because the document root is `public/` and they sit outside it. You may delete them; leaving them costs nothing but disk.

The release archive in Option A is the same tree with exactly those files already stripped out.

#### Then, whichever route you took

- **No build step is required.** The compiled stylesheet and script ship with the download, in `public/build`. You do **not** need Node, npm or `npm run build` on the server — that is deliberate, since shared hosting rarely has them.
- **Copy `.env.example` to `.env` in the project root.** It includes a temporary `APP_KEY` so the application can boot far enough to reach the installer; the installer replaces it with a unique, secure key at the final step.
- **Install the PHP dependencies.** The download does **not** include `vendor/`. Choose one:
    - **Option A (Recommended)**: run `composer install --no-dev --optimize-autoloader` over SSH on the server.
    - **Option B**: run `composer install --no-dev` locally and upload the resulting `vendor` folder.
    - **Option C**: use the Plesk/cPanel "Composer" extension.
- **Point the document root at `/public`.** This is the single most common installation mistake — see the Plesk instructions below, and the 403 entry under Troubleshooting.

### 2. Set Permissions

Ensure the following directories are writable by the web server (recursively):
- `storage` (and all subfolders)
- `bootstrap/cache`

On many servers, setting these to `775` or `755` is sufficient. If you are on Plesk, ensure the `psv-app`, `www-data` or your FTP user has write access. You can usually set this via the Plesk File Manager by clicking on the folder permissions.

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

## Branding the Portal

The portal carries your organisation's name and colours. All of it is set in `.env` — you never
edit a view, and you never need Node or a build step on the server. Edit the file, then run
`php artisan config:clear` if you have cached your config, and reload the page.

```dotenv
ORGANIZATION_DISPLAY_NAME="Acme Insurance"

PORTAL_BASE_THEME=dark
PORTAL_COLOR_SCHEME=blue

PORTAL_NAVIGATION_BACKGROUND_COLOR="#111827"
PORTAL_NAVIGATION_TEXT_COLOR="#f9fafb"
PORTAL_ACCENT_COLOR=indigo-700
```

| Setting | What it changes | Default |
|---|---|---|
| `ORGANIZATION_DISPLAY_NAME` | The name in the navigation bar and the browser tab. It is also the name shown to a client when sign-in fails and they need to know who to contact. | `InsuriVault` |
| `PORTAL_BASE_THEME` | Whether the portal is light or dark. See **Base theme** below. | `light` |
| `PORTAL_COLOR_SCHEME` | A hue laid over the base. See **Colour schemes** below. | none — the base untinted |
| `PORTAL_NAVIGATION_BACKGROUND_COLOR` | The navigation bar's background. | `slate-900` |
| `PORTAL_NAVIGATION_TEXT_COLOR` | The navigation bar's text, and the muted tone used for the Logout link and the menu button. | `slate-50` |
| `PORTAL_ACCENT_COLOR` | The login header band, the Login button, the View button, links, and focus outlines on the login form. | `sky-700` |

### Base theme: light or dark

`PORTAL_BASE_THEME` decides whether the portal is light or dark. It repaints everything — the
page, the cards, the borders, the text tones and the accent all move together.

| Name | What you get |
|---|---|
| *(empty)* or `light` | The portal as it ships: a dark slate bar over a near-white page. |
| `dark` | A dark portal throughout — near-black bar, dark page, dark cards, light text, a brighter sky accent so actions still stand out. |

An unrecognised name is ignored and you get the light portal.

### Colour schemes

`PORTAL_COLOR_SCHEME` lays a hue over the base. The surfaces keep the base's weight and take the
scheme's colour, so the same name means something different on each base — which is the point of
it being a separate setting rather than another theme name.

The seven are the rainbow, in order:

| Name | What it suits |
|---|---|
| `red` | Muted and dusty rather than urgent, and the only scheme that leaves the dark background neutral. See below. |
| `orange` | Warm and approachable without being loud. |
| `yellow` | Bright and optimistic. Rendered in a golden amber — see below. |
| `green` | Calm, and the most conventional choice for anything financial. |
| `blue` | The most conservative. Closest to the portal as it ships. |
| `indigo` | Deep blue-violet. The "true" purple of the rainbow. |
| `violet` | The most distinctive of the seven, and the furthest from a default. |

Over **light** each gives a pale tinted page with white cards and a deep bar of the same hue.
Over **dark** each gives a deep portal throughout in that hue, with a brighter shade for actions.
An unrecognised name leaves the base untinted, and a scheme needs no base named — on its own it
is the light version.

> **Three of these are not the literal colour, on purpose.**
>
> **`yellow`** renders in a golden amber. A true screen yellow is close to unreadable as a button
> colour and unpleasant as a page, so you get the colour you meant rather than the one you named.
>
> **`red`** is deliberately not a true red, and behaves differently from the other six.
> The portal says *wrong* in red — a failed sign-in, a validation error, a failure toast — so a
> saturated red theme would leave a client unable to tell the brand from the bad news. Over
> **light** it is desaturated towards grey: a dusty, muted red rather than a bright one. Over
> **dark** it leaves the page and the cards neutral and turns only the bar, the borders and the
> buttons red, because a fully red dark portal reads as one long error message. Sign in with a
> wrong password once to see the theme and an error together.
>
> **`indigo` and `violet`** are neighbours and deliberately both offered: indigo is the deeper,
> bluer one, violet the brighter, pinker one. If in doubt, look at both.

### How the three layers stack

1. **`PORTAL_BASE_THEME`** — light or dark. Decides every surface.
2. **`PORTAL_COLOR_SCHEME`** — tints that base.
3. **The individual colours below** — written last, and they win over both.

Each layer only changes what the one above it leaves alone, so you can go as far down the list as
you need and stop:

```dotenv
PORTAL_BASE_THEME=dark
PORTAL_COLOR_SCHEME=blue
PORTAL_COLOR_SCHEME=blue
PORTAL_ACCENT_COLOR=rose-500
```

That is a dark blue portal with pink actions. Set the base, look at it; add a scheme, look again;
change one colour only if you still want to.

```dotenv
PORTAL_BASE_THEME=dark
PORTAL_COLOR_SCHEME=blue
PORTAL_ACCENT_COLOR=emerald-500
```

That gives you the dark portal with green actions — not a green light portal, and not a dark
portal that ignores your accent. Set the theme first, look at it, then change only what you want
to move.

**What the theme does not repaint:** a document you are previewing. A PDF renders in its own frame
and an image keeps its own background, because those are your client's files rather than part of
the portal. The frame around them follows the theme.

### Writing a colour

A colour is either:

- **A hex value** — `"#0f172b"`, the short form `"#eee"`, or written without the hash: `0f172b`.
- **A standard Tailwind colour name** — a family and a shade, such as `slate-900`, `indigo-700` or
  `rose-500`. Shades run `50`, `100`, `200` … `900`, `950`. `black` and `white` also work.
  The families are `slate`, `gray`, `zinc`, `neutral`, `stone`, `red`, `orange`, `amber`, `yellow`,
  `lime`, `green`, `emerald`, `teal`, `cyan`, `sky`, `blue`, `indigo`, `violet`, `purple`,
  `fuchsia`, `pink` and `rose`.

> **Put quotes around a hex value.** In a `.env` file an unquoted `#` starts a comment, so
> `PORTAL_ACCENT_COLOR=#4338ca` is read as an empty setting and your colour silently does nothing.
> Write `PORTAL_ACCENT_COLOR="#4338ca"` — or drop the hash and write `PORTAL_ACCENT_COLOR=4338ca`,
> which cannot be misread. This catches everybody once.

**Anything else is ignored and the default is used.** A typo will not break the page and will not
show your text on screen — but it will also not tell you it was wrong, so if a colour does not
change, check the spelling and the quotes first.

### Two things worth knowing

**The two navigation colours travel together.** Setting a pale background without also setting a
dark text colour gives you a bar you cannot read. The portal does not second-guess your choice —
if it corrected you, the colour you set would not be the colour you got. Set both, and look at the
result.

**One accent, several places.** `PORTAL_ACCENT_COLOR` is deliberately a single setting: the shades
around it — the button's hover state, the focus ring, the tinted panel on the documents page — are
derived from it so they stay in the same family. The Download buttons stay green on purpose; they
signal an action rather than your brand.

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

1. Move all files from the project's `public` folder (including `.htaccess`, `index.php` and the `build` folder) directly into your subdomain root (e.g., `httpdocs` or your subdomain folder). Ensure hidden files like `.htaccess` are included.
2. Move all other folders (`app`, `bootstrap`, `config`, etc.) into the same subdomain root.
3. Open `index.php` (now in the root) and change lines 14 and 18:
   - Change `require __DIR__.'/../vendor/autoload.php';` to `require __DIR__.'/vendor/autoload.php';`
   - Change `$app = require_once __DIR__.'/../bootstrap/app.php';` to `$app = require_once __DIR__.'/bootstrap/app.php';`
4. **Note**: This method is less secure as it exposes your configuration files to the web if not handled correctly. We strongly recommend setting the Document Root instead.

---

## Troubleshooting

- **403 Forbidden / Directory Listing / AH01276**: This error occurs when Apache looks for an index file (like `index.php`) in the root of the subdomain and doesn't find it.
    - **Cause**: Apache is serving the base folder (e.g., `demo.insuri-vault.com/`) instead of the `public` folder.
    - **Fix 1**: Ensure your domain's document root is set to the `/public` folder (see Step 1 and the "Special Instructions" section above). In Plesk, double-check that the "Document root" field includes the subdomain folder followed by `/public` (e.g., `demo.insuri-vault.com/public`).
    - **Fix 2**: If you cannot change the document root, follow the "If you cannot change the Document Root" section above to move the files from `public/` to your subdomain root.
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
- **No "Login with Biometrics" button on the login page**: This is almost always the HTTPS requirement, not a fault.
    - **Cause**: Browsers expose the WebAuthn API only in a secure context. Over plain HTTP, `window.PublicKeyCredential` is undefined, so the login page's feature detection stops immediately. Note that **nothing at all is shown** in this case — not even the "Biometric login not available on this device" note, which only appears when the browser supports WebAuthn but the device has no usable authenticator. An empty space where the button should be therefore points at the connection, not the device.
    - **Fix**: Install a valid TLS certificate for the domain and serve the portal over `https://`. In Plesk, use **SSL/TLS Certificates → Install a free basic certificate provided by Let's Encrypt**, then enable **Permanent SEO-safe 301 redirect from HTTP to HTTPS** in Hosting Settings. A self-signed or expired certificate is not enough — the browser treats it as insecure and the API stays hidden.
    - **Verify**: Open the browser console on the login page and enter `window.PublicKeyCredential`. `undefined` confirms the secure-context problem; anything else means WebAuthn is available and the cause is elsewhere.
    - **Also check**: The portal must be served from the hostname registered as your master account hostname. If the certificate is valid and the button appears but authentication fails, confirm that hostname matches — see "Why HTTPS is required for biometric login" above.

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
