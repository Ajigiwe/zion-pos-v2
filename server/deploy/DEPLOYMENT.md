# Deployment — shared cPanel hosting (Zion POS, Laravel 12)

Target reality: shared cPanel (PHP 8.2+ via MultiPHP, MySQL 5.7/8, Apache +
`.htaccess`, cPanel Cron, AutoSSL, **no VPS-style shell services**).

The app was designed for this: everything runs web-transactionally — no queue
worker, no supervisor, no SSH-dependent features. Two runtime decisions:

- **Cache/queue/session driver: `database`** (no Redis on shared hosts)
- **Backups degrade gracefully**: `mysqldump` when PHP `exec` is allowed;
  automatically falls back to a pure-PHP dump when the host blocks it.

---

## 1. Before you start — build the deployable locally

Laravel needs Composer. Shared cPanel accounts sometimes have it via "Terminal
/ SSH", but the **always-works path** is to build the vendor folder locally and
upload:

```powershell
# On the dev machine (Composer available):
cd C:\xampp\htdocs\POS\POS\server
php C:\Users\USER\AppData\Local\Temp\opencode\composer.phar install --no-dev --optimize-autoloader
# Create the upload zip (vendor + app + database + public …, NOT storage/logs junk)
# → produces zion-pos-upload.zip
```

Or the cPanel "Git™ Version Control" route if your host exposes it: push the
`server/` folder to a private GitHub repo and let cPanel deploy from there —
but Composer still must run; the zip path is simpler.

## 2. Database + PHP (cPanel UI)

1. **MySQL® Databases** → create `cpaneluser_pos` database + `cpaneluser_posuser`
   user, ALL PRIVILEGES. Password — keep it safe.
2. **Select PHP Version / MultiPHP Manager** → PHP **8.2 or 8.3**.
3. **MultiPHP INI Editor** (recommended values):

| Directive | Value | Why |
|---|---|---|
| `max_execution_time` | 120 | import/backup |
| `upload_max_filesize` | 16M | XLSX imports, restore file |
| `post_max_size` | 20M | — |
| `memory_limit` | 256M | PDF/excel work |

## 3. Where the files live — two supported layouts

### Layout A (preferred): subdomain/addon docroot = App's `public/`

1. In **Domains** → the (sub)domain → DocumentRoot →
   `/home/USER/zion-pos/public`.
2. Upload/extract the zip to `/home/USER/zion-pos/` so it looks like:

```
/home/USER/zion-pos/
  app/  bootstrap/  config/  database/  public/  resources/
  routes/  storage/  vendor/  artisan  composer.json  .env
```

### Layout B (fallback): you only control `public_html`
### Layout B (your cPanel: subdomain docroot inside public_html, e.g. `public_html/app.ziongroupsofcompanies.com`)

Deploy the Laravel app outside the web root, then let the subdomain folder
bridge to it — everything needed is already packed:

1. Create folder `/home/USER/zion-pos/` (same level as `public_html`),
   upload `zion-pos-upload.zip` there, extract.
2. Open `public_html/app.ziongroupsofcompanies.com/` (confirm it is empty or
   not in use), upload **`zion-pos-webroot.zip`** (ships a ready-made shim
   `index.php` requiring `../../zion-pos/public/index.php`, all static
   assets + `.htaccess`, and a pure-PHP `deploy/keygen.php`), extract.

And copy `public/.htaccess` + the static assets (`css/`, `js/`, `vendor/`,
`manifest.webmanifest`, `icon.svg`, `sw.js`) into the subdomain folder
(already inside `zion-pos-webroot.zip`) — `keygen.php` also lives there for
the zero-shell key step; delete it after use. Everything else (`.env`,
`storage/`) stays outside the web root — safer.

## 4. `.env` on cPanel

Copy `.env.production.example` → `.env` in File Manager, edit:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pos.yourdomain.com
DB_HOST=127.0.0.1
DB_DATABASE=cpaneluser_pos
DB_USERNAME=cpaneluser_posuser
DB_PASSWORD=•••
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
MYSQLDUMP_PATH=/usr/local/bin/mysqldump   # find with: which mysqldump (or leave default)
MYSQL_PATH=/usr/bin/mysql
```

Then activate + seed the schema — three shell-free paths, in order of ease:

1. **cPanel Terminal** (most hosts have it): `php artisan key:generate && php artisan migrate --force`
2. **phpMyAdmin import** (zero shell): SSL/TLS → copy `deploy/keygen.php` to
   the app root, visit `https://…/keygen.php` → paste `APP_KEY` into `.env` →
   delete the file. Then **phpMyAdmin → the DB → Import** with
   `deploy/schema_seed.sql` — regenerated 2026-10-19 and matches the current
   app schema exactly (username login, latest permission matrix). It ships
   roles, permissions, categories, store settings and **three demo users —
   username login**: `admin` (Superadmin), `owner` (Owner/admin tier),
   `cashier` — all with password `password`. **First login on the subdomain:
   username `admin` + password `password`, then Change password (topbar) and
   disable the demo users you don't need (`owner`, `cashier`) in Staff.**

## 5. Cron (cPanel → Cron Jobs)

```
* * * * *  /usr/local/bin/php /home/USER/zion-pos/artisan schedule:run >> /home/USER/zion-pos/storage/logs/cron.log 2>&1
```

Find the exact `php` binary under **Select PHP Version → Show paths**. This
single line powers the nightly 01:30 backup (`pos:backup --prune=30`).
If cron emails matter, set the notification address in the cron UI.

Optional offsite copies: an extra cron `pos:backup` + File-Manager download,
or a cheap second target via the cPanel "Backup" → "Remote destination"
configurations (SFTP/RSync available on some plans).

## 6. TLS + PWA

- **SSL/TLS Status** → AutoSSL/Let's Encrypt the (sub)domain — required: the
  service worker refuses plain HTTP (localhost excepted).
- Set `APP_URL` to the https form; install the PWA from Chrome on the till
  ("Install app"), keep the tab parked on `/pos`.

## 7. Migration of legacy data

Drop the Flutter host's `instrument_pos.db` anywhere private
(`/home/USER/legacy/instrument_pos.db`), then — only if you have terminal:
`php artisan pos:import-sqlite /home/USER/legacy/instrument_pos.db`
Else ask the host for a one-off PHP CLI run of the same artisan command.

## 8. Verify (post-deploy checklist)

- [ ] `https://…/up` → 200
- [ ] Q1 login as the business superadmin, delete/disable the seeded demo users
- [ ] Till: `/pos` loads, sells one product, receipt prints (58 mm thermal)
- [ ] Settings → Backup now… (should generate a `.sql`)
- [ ] Offline test: DevTools → Network → Offline → sale → back Online → syncs
- [ ] `audit` shows LOGIN/SALE for the till

## 9. Updates

Zip-swapUpload the new build, then (Terminal if available):

```bash
php artisan migrate --force && php artisan optimize:clear
```

No shell? `optimize:clear` is optional since the zip ships with fresh cache
files; just ensure the `bootstrap/cache/` folder inside the zip has appropriate
owners via File Manager → Permissions 644 for config.php (safe on cPanel since
it runs as the same user).

## 10. Shared-host hard notes

- **No privileged daemons**: never set `QUEUE_CONNECTION=sync` jobs to expect a
  worker; everything ships as web-transactional and stays that way.
- **Storage tokens**: `storage/` folders (`framework`, `logs`, `backups`,
  `app`) need to be writable — cPanel runs the web server as your own user so
  File Manager-level 660/775 usually suffices.
- **No composer on server**: don't run `composer require` on the live host —
  always rebuild locally → upload.
- **phpMyAdmin restore**: if exec/`mysql` is unavailable, generate the backup,
  and paste `.sql` in phpMyAdmin (or split big dumps via phpMyAdmin upload).
- **Mail**: none required in-app (audit trail instead). Cron failure emails
  arrive through cPanel's cron mail.
