# Zion POS v2 - production build engine
# Rebuilds the two cPanel go-live zips exactly as DEPLOYMENT.md prescribes:
#   1. zion-pos-upload.zip  -> extracts to /home/USER/zion-pos  (app code + --no-dev vendor)
#   2. zion-pos-webroot.zip -> extracts into the subdomain folder (shim bridge)
# ZIP entries are written with FORWARD SLASHES via Windows built-in bsdtar
# (Compress-Archive and .NET ZipFile in PS 5.1 both emit backslashes, which
# Linux unzip misreads as flat filenames - the go-live outage of 2026-10-21!)
#
# Usage (from repo root):
#   powershell -ExecutionPolicy Bypass -File deploy\build.ps1
#   powershell -ExecutionPolicy Bypass -File deploy\build.ps1 -SkipComposer
#   powershell -ExecutionPolicy Bypass -File deploy\build.ps1 -OutDir D:\ship

param(
    [string]$PhpPath   = 'C:\xampp\php\php.exe',
    [string]$ComposerPhar = "$env:LOCALAPPDATA\Temp\opencode\composer.phar",
    [string]$OutDir    = "$env:USERPROFILE\Desktop",
    [switch]$SkipComposer
)
$ErrorActionPreference = 'Stop'

$src   = Join-Path $PSScriptRoot '..\server'
$stageRoot = Join-Path $env:TEMP 'zion-stage-root'
$stage = Join-Path $stageRoot 'zion-pos'
$stamp = Get-Date -Format 'yyyy-MM-dd HH:mm'

Write-Host "=== Zion POS build engine - $stamp ===" -ForegroundColor Cyan

# ---------- 0. sanity ----------
if (-not (Test-Path $src))          { throw "Missing source: $src" }
if (-not (Test-Path $PhpPath))      { throw "php.exe not found at $PhpPath (edit -PhpPath)" }
if (-not (Test-Path $ComposerPhar)) { throw "composer.phar not found at $ComposerPhar (edit -ComposerPhar)" }

# ---------- 1. stage ----------
if (Test-Path $stageRoot) { Remove-Item $stageRoot -Recurse -Force }
New-Item -ItemType Directory -Path $stage -Force | Out-Null

foreach ($d in 'app','config','database','resources','routes','public','deploy') {
    robocopy (Join-Path $src $d) (Join-Path $stage $d) /E /NFL /NDL /NJH /NJS /NP | Out-Null
}
foreach ($f in 'artisan','composer.json','composer.lock','.env.production.example') {
    Copy-Item (Join-Path $src $f) $stage -Force
}
New-Item -ItemType Directory -Path "$stage\bootstrap"           -Force | Out-Null
New-Item -ItemType Directory -Path "$stage\bootstrap\cache"     -Force | Out-Null
Copy-Item (Join-Path $src 'bootstrap\app.php')       "$stage\bootstrap\app.php" -Force -ErrorAction SilentlyContinue
Copy-Item (Join-Path $src 'bootstrap\providers.php') "$stage\bootstrap\providers.php" -Force -ErrorAction SilentlyContinue
Set-Content "$stage\bootstrap\cache\.gitignore" "*`r`n!.gitignore"
foreach ($d in 'storage\framework\cache','storage\framework\sessions','storage\framework\views','storage\logs','storage\app\public') {
    New-Item -ItemType Directory -Path (Join-Path $stage $d) -Force | Out-Null
    Set-Content (Join-Path (Join-Path $stage $d) '.gitkeep') ''
}
# a spare .env (from the example) lets artisan package:discover run during the composer hook;
# it is deleted before zipping so the SERVER's real .env always survives an extract-overwrite
if (-not (Test-Path "$stage\.env")) { Copy-Item "$stage\.env.production.example" "$stage\.env" }
Write-Host "[1/4] code staged" -ForegroundColor Green

# ---------- 2. composer (no dev) ----------
if (-not $SkipComposer) {
    $env:COMPOSER_HOME = "$env:USERPROFILE\.composer"
    Push-Location $stage
    & cmd /c "`"$PhpPath`" `"$ComposerPhar`" install --no-dev --optimize-autoloader --no-interaction --prefer-dist 2>&1" | Select-Object -Last 3
    $code = $LASTEXITCODE
    Pop-Location
    if ($code -ne 0) { throw "composer install failed (exit $code)" }
} else {
    if (-not (Test-Path "$stage\vendor\autoload.php")) { throw "-SkipComposer but the stage has no vendor/" }
    Write-Host '[2/4] composer skipped (reusing staged vendor)' -ForegroundColor Yellow
}
Write-Host "[2/4] vendor tree ready" -ForegroundColor Green

# ---------- 3. safety: no real .env may ever ship ----------
if (Test-Path "$stage\.env") { Remove-Item "$stage\.env" -Force }

# ---------- 4. zip (bsdtar = forward slashes) ----------
if (-not (Get-Command tar.exe -ErrorAction SilentlyContinue)) { throw 'tar.exe (bsdtar) required - present since Windows 10 1803' }
$zipApp = Join-Path $OutDir 'zion-pos-upload.zip'
if (Test-Path $zipApp) { Remove-Item $zipApp -Force }
tar.exe -a -cf $zipApp -C $stageRoot zion-pos
if ($LASTEXITCODE -ne 0) { throw "tar failed for app zip" }

# webroot pack comes from its template folder in the repo
$webStage = Join-Path $stageRoot 'webroot'
Copy-Item (Join-Path $PSScriptRoot 'webroot-template') $webStage -Recurse
$zipWeb = Join-Path $OutDir 'zion-pos-webroot.zip'
if (Test-Path $zipWeb) { Remove-Item $zipWeb -Force }
tar.exe -a -cf $zipWeb -C $stageRoot webroot
if ($LASTEXITCODE -ne 0) { throw "tar failed for webroot zip" }

# ---------- verify ----------
Add-Type -AssemblyName System.IO.Compression.FileSystem
foreach ($z in @($zipApp, $zipWeb)) {
    $a = [System.IO.Compression.ZipFile]::OpenRead($z)
    $bs  = @($a.Entries | Where-Object { $_.FullName -like '*\*' }).Count
    $envCount = @($a.Entries | Where-Object { $_.FullName -match '/(\.env)$' }).Count
    $size = (Get-Item $z).Length
    $leaf = Split-Path $z -Leaf
    Write-Host ("{0}: {1} entries, {2:N0} bytes, backslash-entries={3}, env-leaks={4}" -f $leaf, $a.Entries.Count, $size, $bs, $envCount) -ForegroundColor Cyan
    if ($bs -gt 0)  { throw "BACKSLASH entries found in $z - build engine bug" }
    if ($envCount -gt 0) { throw "REAL .env would ship inside $z - abort" }
    $a.Dispose()
}
$sym = "$stage\vendor\symfony"
if (Test-Path $sym) { Write-Host ("symfony packages: " + (Get-ChildItem $sym -Directory).Count) -ForegroundColor Green } else { Write-Host "WARNING: symfony vendor dir missing" -ForegroundColor Red }
Write-Host "=== Build complete: $zipApp + $zipWeb -> $OutDir ===" -ForegroundColor Cyan
Write-Host "Server: upload both, extract app zip at /home/USER/, webroot zip in subdomain folder, delete the old .htaccess placeholder first." -ForegroundColor DarkCyan
