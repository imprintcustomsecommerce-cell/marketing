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
    Write-Host 'Admin login:  http://127.0.0.1:8081/login'
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
    & php artisan serve --host=127.0.0.1 --port=8081
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
