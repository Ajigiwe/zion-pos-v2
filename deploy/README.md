# Zion POS v2 — deploy engine

Living in `deploy/`. Two moving parts and one rule.

## The golden rule

**The server's `/home/USER/zion-pos/.env` is sacred.** The build never packs a real `.env` into
any zip (verified in-build), so extract-overwrites can never clobber production config — unless
someone deletes the folder manually. Before any folder surgery on the server: **download `.env`
first.**

## 1. Build (dev machine)

```powershell
powershell -ExecutionPolicy Bypass -File deploy\build.ps1
```

- stages `server/` → `%TEMP%\zion-stage-root\zion-pos`
- `composer install --no-dev --optimize-autoloader` (project composer.lock pins versions)
- writes **`zion-pos-upload.zip`** + **`zion-pos-webroot.zip`** to the Desktop
- refuses to finish if entries carry `\` separators (Linux-extract trap of 2026-10-21) or if an
  `.env` snuck in
- `-SkipComposer` reuses a previous stage's vendor (fast iteration on blade/js/css changes)

## 2. Upload (dev machine → cPanel)

`deploy\push.ps1` FTP-uploads both zips to the cPanel home directory.

- cPanel once: **FTP Accounts → Add FTP Account** (user `ziongrou`, custom password, domain dir `/`)
- copy the generated host/port/user into `deploy\deploy.local.json` (gitignored):

```json
{ "ftpsHost": "ftp.ziongroupsofcomputers.com:990", "user": "ziongrou", "useSsl": true }
```

- run `powershell -File deploy\push.ps1` → it prompts once for the FTP password, remembers
  nothing, uploads both zips.

## 3. Extract (cPanel, 2 clicks)

File Manager → `/home/USER/` → `zion-pos-upload.zip` → **Extract** (no manual `zion-pos` folder
creation — the zip contains it at top level).
→ `public_html/app.ziongroupsofcompanies.com/` → delete placeholder `.htaccess` → extract
`zion-pos-webroot.zip` (overwrite).

## 4. Post-deploy checklist

- [ ] `.env` intact (delete only if this is a first deploy: copy `.env.production.example` →
      `.env` → keygen.php once → DB creds)
- [ ] `https://app.ziongroupsofcompaniess.com/up` → 200
- [ ] login works, till sells, schema migrated? (project uses `deploy/schema_seed.sql` for
      baseline; subsequent deploys: run new migrations via cPanel Terminal or phpMiniAdmin if needed)
- [ ] delete `keygen.php`, `debug.php`, dumps from the webroot

## Never commit

`.env`, `deploy\deploy.local.json`, any zip (gitignored).
