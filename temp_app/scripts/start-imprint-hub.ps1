$ErrorActionPreference = 'Stop'

$appPath = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $appPath

function Stop-WithMessage([string] $Message) {
    Write-Host ""
    Write-Host "ERROR: $Message" -ForegroundColor Red
    exit 1
}

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Stop-WithMessage 'PHP was not found in PATH. Start XAMPP or add its PHP folder to PATH.'
}

$cloudflared = Get-Command cloudflared -ErrorAction SilentlyContinue
if (-not $cloudflared) {
    Stop-WithMessage 'cloudflared was not found. Install Cloudflare Tunnel and try again.'
}

if (-not (Test-Path -LiteralPath 'artisan')) {
    Stop-WithMessage "Laravel was not found in $appPath."
}

if (-not (Test-Path -LiteralPath 'vendor\autoload.php')) {
    Stop-WithMessage 'PHP dependencies are not installed. Run composer install first.'
}

if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
}

if (-not (Test-Path -LiteralPath 'database\database.sqlite')) {
    New-Item -ItemType File -Path 'database\database.sqlite' | Out-Null
}

if (-not (Select-String -LiteralPath '.env' -Pattern '^APP_KEY=base64:' -Quiet)) {
    Write-Host 'Creating the application key...'
    & php artisan key:generate --force
    if ($LASTEXITCODE -ne 0) { Stop-WithMessage 'The application key could not be created.' }
}

Write-Host 'Preparing the local database...'
& php artisan migrate --force
if ($LASTEXITCODE -ne 0) { Stop-WithMessage 'The database could not be prepared.' }

Write-Host 'Creating the administrator account if needed...'
& php artisan db:seed --force
if ($LASTEXITCODE -ne 0) { Stop-WithMessage 'The administrator account could not be prepared.' }

& php artisan optimize:clear | Out-Null

# optimize:clear empties bootstrap\cache. Rebuild the package manifest here, in
# one process, before the scheduler and the server start together below. Left
# cold, both boot at once and race to rename() their temp file onto
# packages.php; on Windows the loser dies with "Access is denied (code: 5)".
& php artisan package:discover --ansi | Out-Null
if ($LASTEXITCODE -ne 0) { Stop-WithMessage 'The package manifest could not be rebuilt.' }
Get-ChildItem -LiteralPath 'bootstrap\cache' -Filter '*.tmp' -ErrorAction SilentlyContinue |
    Remove-Item -Force -ErrorAction SilentlyContinue

$port = 8081

# Clear out anything left from a previous run before starting.
#
# Closing the window with the X skips the cleanup at the bottom of this script,
# so the tunnel and scheduler outlive it. The next launch then finds port 8081
# still held, "artisan serve" fails to bind, and the launcher exits — leaving a
# tunnel pointing at nothing and a public address that answers 502.
#
# Matched on this app's own port, never on the process name: other projects on
# this machine run their own cloudflared on other ports and must not be touched.
$stale = @(Get-CimInstance Win32_Process -Filter "Name='cloudflared.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -like "*127.0.0.1:$port*" })

foreach ($listener in @(Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue)) {
    $owner = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.OwningProcess)" -ErrorAction SilentlyContinue
    # Only our own server. Anything else on this port is somebody else's and is
    # reported rather than killed.
    if ($owner -and $owner.CommandLine -like "*$appPath*") { $stale += $owner }
    elseif ($owner) {
        Stop-WithMessage "Port $port is in use by $($owner.Name) (PID $($owner.ProcessId)), which is not Imprint Hub. Close it and try again."
    }
}

# Deliberately not named $scheduler: that name holds the running scheduler
# later on, and the cleanup block at the bottom calls .HasExited on it.
$staleSchedulers = @(Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -like '*artisan*schedule:work*' -and $_.CommandLine -notlike '*ImprintProduction*' })
$stale += $staleSchedulers

$stale = $stale | Sort-Object ProcessId -Unique
if ($stale) {
    Write-Host "Clearing $($stale.Count) leftover $(if ($stale.Count -eq 1) { 'process' } else { 'processes' }) from a previous run..."
    foreach ($process in $stale) {
        Stop-Process -Id $process.ProcessId -Force -ErrorAction SilentlyContinue
    }
    Start-Sleep -Seconds 2
}

# Like ImprintProduction's start-all.bat: one Laravel bound to 0.0.0.0 serves the
# office network and the tunnel at the same time (0.0.0.0 covers 127.0.0.1, which
# is what cloudflared points at).
#
# Prefer the adapter that owns a default gateway, so VirtualBox and host-only
# adapters do not get handed out as the staff address.
$lanIp = Get-NetIPConfiguration -ErrorAction SilentlyContinue |
    Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } |
    Select-Object -First 1 -ExpandProperty IPv4Address |
    Select-Object -ExpandProperty IPAddress
if (-not $lanIp) {
    $lanIp = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notmatch '^(127\.|169\.254\.)' } |
        Select-Object -First 1).IPAddress
}

# RequireInternalHost answers any host outside this list with a 404, so serving
# the network is not enough on its own - the addresses staff actually type have
# to be named here. Set as a real environment variable: Laravel's .env loader
# leaves existing process variables alone, so .env needs no edit.
# Macs and phones reach a Windows machine over Bonjour/mDNS, which appends
# .local - "ic-server.local", never the bare "IC-SERVER" a Windows PC would use.
# Both spellings have to be listed or half the office gets a 404.
$hostNames = @('localhost', 'imprint-hub', $env:COMPUTERNAME) | Where-Object { $_ }
$hostNames += $hostNames | ForEach-Object { "$_.local" }

$internalHosts = @('127.0.0.1') + $hostNames +
    @(Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notmatch '^(127\.|169\.254\.)' } |
        Select-Object -ExpandProperty IPAddress)
$env:INTERNAL_HOSTS = ($internalHosts | Where-Object { $_ } | Select-Object -Unique) -join ','

# Needs administrator once. Without it Windows drops the connections silently and
# the app looks unreachable from every other PC.
$ruleName = "Imprint HUB LAN $port"
& netsh advfirewall firewall show rule name="$ruleName" *> $null
if ($LASTEXITCODE -ne 0) {
    & netsh advfirewall firewall add rule name="$ruleName" dir=in action=allow protocol=TCP localport=$port *> $null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "[!] Could not open the firewall for port $port automatically." -ForegroundColor Yellow
        Write-Host '    Right-click RUN_IMPRINT_HUB.bat and "Run as administrator" once,' -ForegroundColor Yellow
        Write-Host '    otherwise other computers cannot connect.' -ForegroundColor Yellow
    } else {
        Write-Host "Firewall opened for port $port."
    }
}

$tunnelLog = Join-Path $env:TEMP ("imprint-cloudflare-{0}.log" -f ([guid]::NewGuid().ToString('N')))
$tunnelState = Join-Path $appPath 'storage\app\public-tunnel-url.txt'
Remove-Item -LiteralPath $tunnelState -Force -ErrorAction SilentlyContinue
$tunnel = $null
$scheduler = $null
$publicUrl = $null

try {
    # PowerShell does not load Laravel's .env itself. Read only the two tunnel
    # settings needed by the launcher, while preserving real process variables.
    if ([string]::IsNullOrWhiteSpace($env:CLOUDFLARE_TUNNEL_TOKEN) -or [string]::IsNullOrWhiteSpace($env:PUBLIC_CLIENT_URL)) {
        foreach ($line in Get-Content -LiteralPath '.env') {
            if ($line -match '^CLOUDFLARE_TUNNEL_TOKEN=(.+)$' -and [string]::IsNullOrWhiteSpace($env:CLOUDFLARE_TUNNEL_TOKEN)) { $env:CLOUDFLARE_TUNNEL_TOKEN = $Matches[1].Trim().Trim('"').Trim("'") }
            if ($line -match '^PUBLIC_CLIENT_URL=(.+)$' -and [string]::IsNullOrWhiteSpace($env:PUBLIC_CLIENT_URL)) { $env:PUBLIC_CLIENT_URL = $Matches[1].Trim().Trim('"').Trim("'") }
        }
    }
    $namedTunnel = -not [string]::IsNullOrWhiteSpace($env:CLOUDFLARE_TUNNEL_TOKEN)
    if ($namedTunnel) { Write-Host 'Connecting the permanent Cloudflare address...' } else { Write-Host 'Creating a secure temporary Cloudflare link...' }
    $arguments = if ($namedTunnel) {
        @('tunnel', '--no-autoupdate', '--loglevel', 'info', '--logfile', $tunnelLog, 'run', '--token', $env:CLOUDFLARE_TUNNEL_TOKEN)
    } else {
        @('tunnel', '--url', 'http://127.0.0.1:8081', '--no-autoupdate', '--loglevel', 'info', '--logfile', $tunnelLog)
    }
    if ($namedTunnel) {
        if ([string]::IsNullOrWhiteSpace($env:PUBLIC_CLIENT_URL)) { Stop-WithMessage 'Set PUBLIC_CLIENT_URL to the permanent Cloudflare hostname.' }
        $namedPublicUrl = $env:PUBLIC_CLIENT_URL.TrimEnd('/')
    }
    for ($tunnelAttempt = 1; $tunnelAttempt -le 3 -and -not $publicUrl; $tunnelAttempt++) {
        Remove-Item -LiteralPath $tunnelLog -Force -ErrorAction SilentlyContinue
        $tunnel = Start-Process -FilePath $cloudflared.Source -ArgumentList $arguments -WindowStyle Hidden -PassThru

        if ($namedTunnel) {
            Start-Sleep -Seconds 2
            if (-not $tunnel.HasExited) { $publicUrl = $namedPublicUrl }
        }

        for ($poll = 0; $poll -lt 45 -and -not $publicUrl; $poll++) {
            Start-Sleep -Milliseconds 500
            if ($tunnel.HasExited) { break }
            if (-not $namedTunnel -and (Test-Path -LiteralPath $tunnelLog)) {
                $match = Select-String -LiteralPath $tunnelLog -Pattern 'https://[a-z0-9-]+\.trycloudflare\.com' | Select-Object -First 1
                if ($match) {
                    $publicUrl = $match.Matches[0].Value
                }
            }
        }

        if (-not $publicUrl) {
            if (-not $tunnel.HasExited) {
                Stop-Process -Id $tunnel.Id -Force -ErrorAction SilentlyContinue
            }
            if ($tunnelAttempt -lt 3) {
                Write-Host "Cloudflare attempt $tunnelAttempt failed; retrying..." -ForegroundColor Yellow
                Start-Sleep -Seconds 2
            }
        }
    }

    if (-not $publicUrl) {
        if (Test-Path -LiteralPath $tunnelLog) {
            Write-Host ''
            Write-Host 'Cloudflare details:' -ForegroundColor Yellow
            Get-Content -LiteralPath $tunnelLog -Tail 8 | ForEach-Object { Write-Host $_ }
        }
        Stop-WithMessage 'Cloudflare did not provide a public link. Check the details above and try again.'
    }

    $env:PUBLIC_CLIENT_URL = $publicUrl
    $env:PUBLIC_CLIENT_HOST = ([uri] $publicUrl).Host
    Set-Content -LiteralPath $tunnelState -Value $publicUrl -Encoding ascii

    Write-Host ''
    Write-Host 'Imprint Hub is ready.' -ForegroundColor Green
    if ($lanIp) {
        Write-Host "On the office network: http://${lanIp}:${port}/login"
    } else {
        Write-Host 'Network address could not be detected. Run ipconfig and use http://YOUR-IP:8081/login'
    }
    Write-Host 'This computer only:     http://127.0.0.1:8081/login'
    Write-Host "Client forms: $publicUrl/client"
    Write-Host ''
    Write-Host 'Default login on first run:'
    Write-Host 'Email:    admin@imprintcustoms.ph'
    Write-Host 'Password: imprint123'
    Write-Host ''
    Write-Host 'Keep this window open. The public link stops when this window closes.'
    Write-Host 'Press Ctrl+C to stop the app and tunnel.'
    Write-Host ''

    $scheduler = Start-Process -FilePath 'php' -ArgumentList @('artisan', 'schedule:work') -WindowStyle Hidden -PassThru
    Start-Process 'http://127.0.0.1:8081/login'
    & php artisan serve --host=0.0.0.0 --port=$port
    exit $LASTEXITCODE
}
finally {
    if ($scheduler -and -not $scheduler.HasExited) {
        Stop-Process -Id $scheduler.Id -Force -ErrorAction SilentlyContinue
    }
    if ($tunnel -and -not $tunnel.HasExited) {
        Stop-Process -Id $tunnel.Id -Force -ErrorAction SilentlyContinue
    }
    if (Test-Path -LiteralPath $tunnelLog) {
        Remove-Item -LiteralPath $tunnelLog -Force -ErrorAction SilentlyContinue
    }
    if ($publicUrl -and (Test-Path -LiteralPath $tunnelState)) {
        $activeUrl = (Get-Content -LiteralPath $tunnelState -Raw).Trim()
        if ($activeUrl -eq $publicUrl) {
            Remove-Item -LiteralPath $tunnelState -Force -ErrorAction SilentlyContinue
        }
    }
}
