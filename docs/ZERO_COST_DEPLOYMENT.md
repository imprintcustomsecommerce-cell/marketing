# Zero-cost LAN + public-client deployment

The Laravel application has two route surfaces. Internal routes use the `internal.host` middleware; client routes live only under `/client` and use `public.host`. The public hostname receives a 404 for internal routes. Private local storage serving is disabled.

## Environment

```dotenv
APP_URL=http://192.168.150.20:8080
PUBLIC_CLIENT_URL=https://forms.example.com
PUBLIC_CLIENT_HOST=forms.example.com
INTERNAL_HOSTS=imprint-hub,192.168.150.20,localhost,127.0.0.1
FILESYSTEM_DISK=local
LOCAL_BACKUP_PATH=D:\ImprintBackups
NAS_BACKUP_PATH=\\nas\imprint-backups
```

For a Quick Tunnel, leave `PUBLIC_CLIENT_HOST` empty and update `PUBLIC_CLIENT_URL` whenever the temporary URL changes. Never store the temporary hostname as permanent configuration.

## Processes

Run the LAN endpoint on the server's LAN interface:

```powershell
php artisan serve --host=0.0.0.0 --port=8080
```

Run the tunnel origin on loopback only:

```powershell
php artisan serve --host=127.0.0.1 --port=8081
cloudflared tunnel --url http://localhost:8081
```

For a named tunnel, configure only the public hostname and finish with a catch-all 404:

```yaml
ingress:
  - hostname: forms.example.com
    service: http://127.0.0.1:8081
  - service: http_status:404
```

Do not add ingress rules for the LAN hostname, Filament path, database, SMB, XAMPP administration, or server management tools. The application-level host boundary is defense in depth; the Cloudflare configuration must still contain only the client hostname.

## Scheduler and backups

Use Windows Task Scheduler to run `php artisan schedule:run` every minute under a restricted service account. Laravel schedules a tunnel health check every five minutes and a local database/upload backup daily at 01:30. The NAS copy is optional; failure of the Internet or tunnel never blocks internal work.

The PHP `zip` extension and the MariaDB/MySQL `mysqldump` executable must be available to the scheduled-task account. Test recovery periodically; a backup is not proven until it has been restored.
