# Verification — September 6, 2026

- Full automated suite: 203 tests, 978 assertions passed.
- Added an isolated workflow test covering a submitted client inquiry, conversion to event, automatic coverage and four Multimedia tasks, crew acceptance, completed checklist/media, and recorded final delivery.
- Fixed reminder filtering: archived/done tasks, archived/cancelled events, completed/declined coverage, finished/unneeded media, future media, and other editors' deadlines are excluded. Daily duplicate suppression is tested.
- Restored the fresh `imprint-hub_2026-09-06_112836.zip` into a separate retained folder under `temp_app/storage/app/restore-rehearsals/20260906-112916-8c93e95a`. Database integrity and foreign-key checks passed: 8 users, 7 events, 4 inquiries, 11 tasks, 6 coverage records. Live data was not replaced.
- The real backup contained zero uploaded files. An automated backup/restore test separately verified a synthetic uploaded brief. This is a database/files rehearsal, not a complete restored-app login rehearsal.
- Browser reviewed the public Function Hall form at phone width and a synthetic dashboard at 390px and 320px. Dashboard document width stayed within the viewport. Improved mobile touch targets, form-control sizing, and action wrapping. Synthetic preview uses test records; its absent logo and offline-link state do not describe the live dashboard.
- Gmail sender/host settings prepared for imprintcustoms.ecommerce@gmail.com. MAIL_MAILER remains log pending a locally entered Gmail app password. Real SMTP acceptance and inbox receipt have not been tested.

Next required user step: follow the Gmail section in OPERATIONS.md, enter the app password locally, change MAIL_MAILER to smtp, restart, and run an admin test to the intended recipient.
