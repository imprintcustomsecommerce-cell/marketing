# Imprint Hub operations

## Daily startup

Double-click `D:\GitHub\marketing\RUN_IMPRINT_HUB.bat`. Keep its window and this computer running while clients need the forms. Sign in at `http://127.0.0.1:8081/login`. The launcher starts the application, tunnel, and background scheduler. The scheduler checks backups and reminders hourly while running.

Copy the current All Client Forms link from the dashboard. Free Cloudflare tunnel addresses can change after restarting. Previously shared links, including links in old emails, may stop working. Send clients the new link when that happens. The administration area remains local.

## Email setup

Imprint Hub now reads the promo project's existing SMTP settings through `PROMO_MAIL_ENV="D:/GitHub/promo/.env"`. This setting takes precedence over MAIL_MAILER and the local SMTP fields. It maps promo's SSL/TLS setting to Laravel's SMTP transport, including port 465. No promo files are changed, and no password is displayed in the UI. Keep the promo file available at that path. If it cannot be read or lacks required fields, the app falls back to local log mode and shows a warning.

The latest live test reached Gmail but Gmail rejected the saved credentials with code 535 (username/password not accepted). Update the Gmail app password locally in the promo `.env` SMTP_PASS setting, then restart both apps. Run `php artisan config:clear` in Imprint Hub if configuration has been cached. A new CLI check is available: `php artisan imprint:test-email` sends one test to the configured sender address. Successful provider acceptance still needs inbox verification.

To stop sharing the promo configuration and use independent settings, remove PROMO_MAIL_ENV and follow the standalone setup below.

Enable Google 2-Step Verification if needed, then create an app password at https://myaccount.google.com/apppasswords. Enter it locally as MAIL_PASSWORD in `.env` (not your normal Google password), change MAIL_MAILER to `smtp`, clear config, and restart the launcher. Do not paste the password into chat. Google guidance: https://support.google.com/accounts/answer/185833 and https://support.google.com/mail/answer/7104828.

Use the administrator test button only after checking that the account's email is the intended test recipient. Verify actual receipt before relying on notifications.

Edit `temp_app/.env` locally. Use the SMTP values supplied by your email provider, including its required port and scheme. Keep passwords out of screenshots, chat, and source control.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=your-provider-host
MAIL_PORT=587
MAIL_USERNAME=your-provider-username
MAIL_PASSWORD="your-provider-password"
MAIL_FROM_ADDRESS=your-verified-sender@example.com
MAIL_FROM_NAME="Imprint Customs"
```

These are placeholders, not working credentials. Use your provider's settings rather than assuming port 587 is correct. Check any existing MAIL_URL override too. From `temp_app`, run `php artisan config:clear`, then restart the launcher so the scheduler also reloads settings.

An administrator can open System & Security and click Send test email. It sends only to the administrator's own account email. Check the inbox and spam folder. A provider-accepted message is not proof of inbox delivery. Log/array mailers record local tests only. No real sending is enabled by this guide.

Email history shows the latest 25 attempts for inquiry updates, Multimedia reminders, and admin tests. Failed messages are not automatically retried. Check provider credentials, verified sender, host, port, and connectivity; then run a new test. Pending means no final result was recorded. Failure details deliberately exclude raw transport messages that might contain secrets.

## Backup and restore

To rehearse a trusted SQLite backup without replacing live data, run `php artisan imprint:rehearse-backup "ABSOLUTE_PATH_TO_BACKUP.zip"` from `temp_app`. It retains a separate restored database and uploads under `storage/app/restore-rehearsals/`, checks database integrity and relationships, and compares restored upload hashes against the archive. This is a data recovery check; a complete application rehearsal still includes login and file downloads in a separate app copy.

Create and download a fresh backup from System & Security. Default archives are in `temp_app/storage/app/backups`, unless LOCAL_BACKUP_PATH overrides the location. Copy backups to another device. Verification checks ZIP readability and SQLite database integrity; it does not prove a full restore succeeds.

The ZIP contains `database/database.sqlite` for SQLite and `uploads/` for private uploaded files. It does not include application code, `.env`, or the application key. Keep those separately and securely.

For the current SQLite setup, rehearse restoration into a separate copy of the app first:

1. Stop the launcher and any scheduler using the copy. Preserve its existing database and upload directory before replacing anything.
2. Extract a trusted backup into a new temporary folder. Confirm the expected database and uploads exist.
3. In the copy, restore the database snapshot to the database path configured by DB_DATABASE. Restore `uploads/` into `storage/app/private/`.
4. Preserve the matching `.env` and APP_KEY; do not generate a replacement key. Ensure the copy has its own database path and uses MAIL_MAILER=log during the rehearsal.
5. Run `php artisan migrate --force` and `php artisan config:clear` in the copy. Start it locally without a public tunnel or scheduler, then check login, records, and representative file downloads.
6. Only after successful verification, schedule a live restore with the app stopped and a fresh backup of the current state retained.

MySQL/MariaDB backups require their matching database tools and a separate restore procedure; do not copy a SQL dump over a SQLite file.

## Troubleshooting

- Local page unavailable: check the launcher for startup errors and whether port 8081 is already in use.
- Public tunnel error: first confirm the local staff login opens at port 8081. Client pages are restricted to the public hostname, so a local `/client` 404 is expected. Check internet connectivity and the launcher; after restarting, copy the new dashboard link.
- No reminders: the computer and launcher must remain running. Confirm assigned work is due and the staff email is correct. Reminders are limited to once per day per person.
- Missing email: inspect Email notifications in System & Security, confirm the mailer is not log/array, and check spam and provider delivery logs.
- New staff: use Getting started in the sidebar. Set account roles and teams in Team, and have each staff member change their initial password.

## Updating the app

Take a backup, stop the launcher, apply the intended code update, run `php artisan migrate --force` and `php artisan optimize:clear` from `temp_app`, then restart. Verify login, the dashboard, a public form, and a representative workflow before sharing the current tunnel URL.
