# Zion POS v2 - FTP(S) upload lane
# Uploads the two build zips from the Desktop (or -Zips) to the cPanel home dir.
# One-time setup (cPanel):
#   FTP Accounts -> Add FTP Account (user ziongrou, home dir "/") -> use its login string + password
# Config lives in deploy\deploy.local.json (gitignored):
#   { "host": "ftp.ziongroupsofcompanies.com", "port": 21, "user": "ziongrou", "password": "" }
#   password empty = prompt each run (kept local only)
#
# Usage:
#   powershell -ExecutionPolicy Bypass -File deploy\push.ps1
#   powershell -ExecutionPolicy Bypass -File deploy\push.ps1 -Zips "D:\ship\zion-pos-upload.zip","C:\Users\USER\Desktop\zion-pos-webroot.zip"
#   powershell -ExecutionPolicy Bypass -File deploy\push.ps1 -WhatIf     # show plan only

param(
    [string[]]$Zips = @(),
    [string]$Config = (Join-Path $PSScriptRoot 'deploy.local.json'),
    [switch]$WhatIf
)
$ErrorActionPreference = 'Stop'

# ---- ASCII-only: PS 5.1 parser-safe logo ----
Write-Host '=== Zion POS push (FTPS) ===' -ForegroundColor Cyan

# ---- load or scaffold config ----
if (-not (Test-Path $Config)) {
    Write-Host "No config at $Config" -ForegroundColor Yellow
    $cHost = Read-Host 'FTP host          (e.g. ftp.ziongroupsofcompanies.com)'
    $cPort = (Read-Host 'FTP port          [21]') -as [int]; if (-not $cPort) { $cPort = 21 }
    $cUser = Read-Host 'FTP user          (exactly as cPanel shows it, e.g. ziongrou)'
    @{ host = $cHost; port = $cPort; user = $cUser; password = '' } | ConvertTo-Json | Set-Content $Config -Encoding ASCII
    & 'C:\Windows\System32\notepad.exe' $Config   # user pastes password or leaves blank
    Wait-Process -Name notepad -ErrorAction SilentlyContinue
}
$cfg = Get-Content $Config -Raw | ConvertFrom-Json
if (-not $cfg.host -or -not $cfg.user) { throw "deploy.local.json incomplete (need host,user)" }

# ---- zips ----
if (-not $Zips -or $Zips.Count -eq 0) {
    $Zips = @(
        (Join-Path $env:USERPROFILE 'Desktop\zion-pos-upload.zip'),
        (Join-Path $env:USERPROFILE 'Desktop\zion-pos-webroot.zip')
    )
}
foreach ($z in $Zips) { if (-not (Test-Path $z)) { throw "missing zip: $z" } }

# ---- password (prompt if not stored) ----
$pass = $cfg.password
if (-not $pass) {
    $sec = Read-Host -AsSecureString "FTP password ($($cfg.user))"
    $b   = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec)
    $pass = [System.Runtime.InteropServices.Marshal]::PtrToStringBSTR($b)
    [System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b)
}

# prefer explicit TLS on the chosen port (cPanel FTP over TLS = explicit on 21)

# ---- trust the FTPS server cert (shared hosts often lack a hostname cert on FTP) ----
$cb = [System.Net.Security.RemoteCertificateValidationCallback] { param($sndr,$cert,$chain,$err) $true }
[System.Net.ServicePointManager]::ServerCertificateValidationCallback = $cb

function Test-FtpLogin {
    param($h,$p,$u,$pw,$dry)
    if ($dry) { Write-Host "WHATIF login: ftp://$h`:$p as $u" -ForegroundColor Yellow; return $true }
    $cred = New-Object System.Net.NetworkCredential($u,$pw)
    $r = [System.Net.FtpWebRequest]::Create("ftp://${h}:${p}/")
    $r.Method = [System.Net.WebRequestMethods+Ftp]::ListDirectory
    $r.Credentials = $cred; $r.EnableSsl = $true; $r.UseBinary = $true
    $r.UsePassive = $true; $r.KeepAlive = $false
    $r.Timeout = 20000; $r.ReadWriteTimeout = 20000
    $resp = $r.GetResponse()
    Write-Host ("login ok: {0} ({1})" -f $resp.BannerMessage.Trim(), $resp.WelcomeMessage.Trim()) -ForegroundColor Green
    $resp.Close(); return $true
}

function Send-Ftp {
    param($h,$p,$u,$pw,$file,$dry)
    $name = Split-Path $file -Leaf
    $size = (Get-Item $file).Length
    if ($dry) { Write-Host "WHATIF upload: $name ($size bytes)" -ForegroundColor Yellow; return }
    $cred = New-Object System.Net.NetworkCredential($u,$pw)
    # delete leftover so the upload is provably fresh
    try {
        $d = [System.Net.FtpWebRequest]::Create("ftp://${h}:${p}/$name")
        $d.Method = [System.Net.WebRequestMethods+Ftp]::DeleteFile
        $d.Credentials = $cred; $d.UsePassive = $true; $d.KeepAlive = $false
        $d.GetResponse().Close()
        Write-Host "removed old remote copy of $name" -ForegroundColor DarkGray
    } catch { # absent is fine
    }
    $r = [System.Net.FtpWebRequest]::Create("ftp://${h}:${p}/$name")
    $r.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $r.Credentials = $cred
    $r.EnableSsl = $true; $r.UseBinary = $true; $r.UsePassive = $true
    $r.KeepAlive = $false; $r.ContentLength = $size
    $r.Timeout = 300000; $r.ReadWriteTimeout = 300000
    $rs = $r.GetRequestStream()
    $fs = [System.IO.File]::OpenRead($file)
    $fs.CopyTo($rs); $fs.Close(); $rs.Close()
    $resp = $r.GetResponse()
    Write-Host ("uploaded {0} ({1:N0} bytes, server said {2})" -f $name, $size, $resp.StatusDescription.Trim()) -ForegroundColor Green
    $resp.Close()
}

Test-FtpLogin -h $cfg.host -p $cfg.port -u $cfg.user -pw $pass -dry:$WhatIf
foreach ($z in $Zips) { Send-Ftp -h $cfg.host -p $cfg.port -u $cfg.user -pw $pass -file $z -dry:$WhatIf }

Write-Host ''
Write-Host 'Next: File Manager -> /home/<user>/ -> right-click zion-pos-upload.zip -> Extract'
Write-Host 'Then subdomain folder: delete placeholder .htaccess -> extract zion-pos-webroot.zip.'
if ($WhatIf) { Write-Host '(WHATIF dry run - nothing was uploaded)' -ForegroundColor Yellow }
