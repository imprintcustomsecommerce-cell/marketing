# IC Marketing Hub — Implementation Plan

Imprint Customs Marketing, Events, Sponsorship & PR Management System.
Plan date: Aug 28, 2026. Status: pre-Phase-1.

## 0. Environment baseline (verified, not assumed)

| Item | Detected | Action |
|---|---|---|
| PHP | 8.2.12 (XAMPP, ZTS, Win) | Spec requested 8.3+. Laravel 12 + Filament 4 support 8.2. Building on 8.2; `composer.json` declares `^8.2` so an 8.3 upgrade is a no-op. |
| `ext-intl` | Missing | **Must enable** `extension=intl` in `php.ini` before Filament install. |
| Composer | 2.10.1 | OK |
| DB | MariaDB 10.4.32 | OK. `Schema::defaultStringLength(191)` set explicitly for index-length safety. |
| Node / npm | 24.16 / 11.13 | OK (Vite + Tailwind build) |
| Git | 2.54, no repo | `git init` in Phase 1 |

Defaults chosen where the brief was silent are marked **[ASSUMPTION]**.

---

## 1. Proposed folder architecture

Standard Laravel skeleton plus domain subfolders. No custom `src/` — staying idiomatic keeps it maintainable.

```
app/
  Actions/                    # single-purpose write operations, called by Filament + tests
    Events/GenerateEventCode.php
    FunctionHall/DetectBookingConflict.php
    Sponsorship/ApproveApplication.php
    PrKit/ReleasePrKit.php
    Design/TransitionDesignStatus.php
  Enums/                      # every status/type set in the brief -> backed PHP enum
    EventStatus.php  Priority.php  BookingStatus.php  PaymentStatus.php
    RacerStatus.php  ApplicationStatus.php  DesignStatus.php  PrKitStatus.php
    ContactType.php  OrganizationType.php  RequirementStatus.php ...
    Concerns/HasLabelAndColor.php   # shared label()/color()/icon() contract
  Models/                     # flat; ~28 Eloquent models
    Concerns/HasCode.php            # EVT-2026-0001 style generators
    Concerns/TracksBlameable.php    # created_by / updated_by
  Observers/                  # code generation, blameable, cascading status updates
  Policies/                   # one per model, Spatie-permission backed
  Services/
    CodeGenerator.php  ConflictChecker.php  DuplicateDetector.php
    PhoneNormalizer.php  CalendarAggregator.php  DashboardMetrics.php
  Support/Reports/            # one query class per report
  Filament/
    Resources/<Domain>/       # grouped: Contacts, Events, FunctionHall, Sponsorship, PrKit, Design, Settings
    Pages/                    # Dashboard, Calendar, Reports/*
    Widgets/                  # stat cards, upcoming schedule, pending tasks, recent activity
    Clusters/                 # sidebar grouping
  Notifications/
  Console/Commands/           # reminder dispatchers (scheduled)
config/imprint.php            # system settings defaults
database/migrations|seeders|factories
tests/Feature|Unit
docs/
```

Rule enforced throughout: **Filament Resources contain form/table/schema definitions only.** Business logic lives in Actions/Services; validation in Rules; side effects in Observers.

---

## 2. Entity relationship plan

**Hub-and-spoke around three master tables: `contacts`, `organizations`, `events`.** Nothing stores a name+phone+email that already exists on a contact.

```
organizations 1--* contacts                 (contacts.organization_id)
organizations 1--1 contacts                 (organizations.contact_person_id -> primary)
organizations 1--* events, sponsorship_applications, pr_kit_recipients

events (MASTER)
  *--1 event_types, organizations, contacts (primary_contact_id), users (assigned_to)
  1--1 tambike_event_details        \
  1--1 sponsorship_events            >  type-specific "detail" extension tables
  1--1 function_hall_bookings       /
  1--* event_requirements, raffle_prizes, design_requests, attachments

racing_teams 1--* racers
contacts 1--1 racers                        (racers.contact_id — a racer IS a contact)
racers 1--* racer_achievements, sponsorship_applications, pr_kit_recipients

sponsorship_applications  *--1 racer|team|organization|event  (nullable FKs + applicant_type)
                          *--1 users (reviewed_by, approved_by)

pr_kit_campaigns 1--* pr_kit_recipients
pr_kit_recipients 1--* pr_kit_recipient_items *--1 giveaway_items
pr_kit_recipients 1--* pr_kit_content_requirements

design_requests *--1 events, organizations, contacts, users (assigned_designer_id)
design_requests 1--* design_files, design_comments

attachments  (polymorphic) --> events, racers, applications, pr kits, designs, orgs, contacts
activity_log (Spatie) --> everything
```

Key modelling decisions:

- **[ASSUMPTION]** Type-specific tables *extend* `events` rather than duplicate it. A Function Hall booking also creates a master `events` row, so it lands on the master calendar automatically; `function_hall_bookings.event_id` is nullable per the brief but will always be populated.
- **[ASSUMPTION]** A Racer always gets a `contacts` row (created silently if absent) so racers appear in global search and client history. Racer-local name fields stay as the brief specifies, synced from the contact.
- `event_types`, `design_categories`, `sponsorship_types`, `pr_kit_items` are **lookup tables** (seeded from the brief's lists), not enums — they are editable under Settings. Status/priority sets that drive logic stay as PHP enums.
- Money as `decimal(12,2)`. Times as `time`. Dates as `date`. App timezone Asia/Manila.

---

## 3. Migrations (dependency order)

- **Phase 1** — `users`, Spatie `permission_tables`, Spatie `activity_log`, `settings`, `organizations`, `contacts`, `attachments` (polymorphic).
- **Phase 2** — `event_types`, `events`, `tambike_event_details`, `event_requirements`.
- **Phase 3** — `function_hall_bookings`.
- **Phase 4** — `racing_teams`, `racers`, `racer_achievements`, `sponsorship_types`, `sponsorship_applications`, `sponsorship_events`, `raffle_prizes`, `giveaway_items`.
- **Phase 5** — `pr_kit_campaigns`, `pr_kit_recipients`, `pr_kit_recipient_items`, `pr_kit_content_requirements`.
- **Phase 6** — `design_categories`, `design_requests`, `design_files`, `design_comments`.
- **Phase 8** — `notifications`, `notification_preferences`, `reminders`.

Indexes created inline on: `event_date`, `end_date`, `booking_date`, `due_date`, `status`, `email`, `mobile_number`, `event_type_id`, `organization_id`, `contact_id`, `assigned_to`, `assigned_designer_id`, `campaign_id`, and every code column (unique). Composite `(booking_date, start_time, end_time)` for conflict detection.

Soft deletes on: events, contacts, organizations, racers, design_requests, sponsorship_applications, function_hall_bookings, pr_kit_recipients.

---

## 4. Models

`User, Setting, Attachment, Contact, Organization, EventType, Event, TambikeEventDetail, EventRequirement, FunctionHallBooking, RacingTeam, Racer, RacerAchievement, SponsorshipType, SponsorshipApplication, SponsorshipEvent, RafflePrize, GiveawayItem, PrKitCampaign, PrKitRecipient, PrKitRecipientItem, PrKitContentRequirement, DesignCategory, DesignRequest, DesignFile, DesignComment, NotificationPreference, Reminder`

Each carries: enum casts, `LogsActivity`, `TracksBlameable`, relationships, and query scopes (`upcoming()`, `overdue()`, `active()`).

---

## 5. Filament resources and pages

Resources: ContactResource, OrganizationResource, EventResource, FunctionHallBookingResource, EventTypeResource, RacerResource, RacingTeamResource, SponsorshipApplicationResource, SponsorshipEventResource, PrKitCampaignResource, PrKitRecipientResource, GiveawayItemResource, RafflePrizeResource, DesignRequestResource, DesignCategoryResource, DesignFileResource, UserResource, RoleResource, SponsorshipTypeResource, PrKitItemResource, ActivityLogResource.

Tambike / External Event / Sponsorship Event sidebar entries are **pre-scoped views over `EventResource`**, not duplicate resources — this avoids two divergent copies of event logic.

Custom pages: `Dashboard`, `CalendarPage` (FullCalendar), `Reports\*` (6 reports). Filament's global search is extended to every searchable resource.

Relation managers deliver the "client history" tabs on Contact / Organization / Racer / Event detail pages.

---

## 6. Packages

| Package | Purpose |
|---|---|
| `laravel/framework` ^12 | core |
| `filament/filament` ^4 | admin panel |
| `spatie/laravel-permission` | roles / permissions |
| `spatie/laravel-activitylog` | audit trail |
| `saade/filament-fullcalendar` | master calendar + drag-drop |
| `pxlrbt/filament-excel` | CSV / Excel export of filtered tables |
| `barryvdh/laravel-dompdf` | PDF reports |
| `propaganistas/laravel-phone` | PH phone validation / normalisation |
| `spatie/laravel-backup` | DB + file backup architecture |
| dev: `pestphp/pest`, `laravel/pint`, `larastan/larastan` | tests, formatting, static analysis |

Package versions are pinned at install time to whatever is current and compatible; any substitution (e.g. if a calendar package lags Filament v4) is recorded here.

---

## 7. Role and permission matrix

Permissions generated as `<action>_<resource>` (`view_any`, `view`, `create`, `update`, `delete`, `restore`, `force_delete`) plus specials: `approve_design`, `approve_sponsorship`, `override_booking_conflict`, `assign_designer`, `release_pr_kit`, `manage_settings`, `view_activity_log`, `export_data`.

| Role | Scope |
|---|---|
| Super Admin | Everything, including `override_booking_conflict`, users, roles, settings, restore, force delete |
| Marketing Head | Full CRUD on events, design, sponsorship, PR kits, contacts + `approve_design`, `assign_designer`, `export_data`, reports |
| Marketing Staff | Create/update events, design requests, contacts, PR kits; read-only sponsorship. **No delete, no user/role management.** |
| Events Head | Full CRUD events / tambike / function hall, approve bookings, `override_booking_conflict`, calendar drag-drop |
| Events Staff | Create/update events and bookings, update attendance. **Cannot override conflicts.** |
| Designer | `view_any` / `update` on design requests **scoped to `assigned_designer_id = self`**, upload files, comment. No `approve_design` unless explicitly granted. |
| Sponsorship Head | Full CRUD racers, teams, applications, sponsorship events, PR kits + `approve_sponsorship` |
| Sponsorship Staff | Create/update racers, achievements, applications (cannot approve) |
| Management Viewer | `view_any` / `view` only, on dashboard, calendar, reports, events, sponsorship, PR kits |

Enforced twice: Policies (server-side) and Filament resource authorization (UI). Designer scoping is applied as a global query modifier on the resource, so it cannot be bypassed by editing the URL.

---

## 8. Implementation order

Follows the brief's 8 phases. Every phase ends with: `migrate:fresh --seed` → `pest` → `pint` → boot check → Filament page walk → **its own commit**, before the next phase begins.

1. **Foundation** — Laravel, Filament, auth, Spatie packages, enum scaffold, settings, branding (black / white / gray / red), sidebar shell, Contacts, Organizations, duplicate detection, PH phone normalisation, seeders, default admin.
2. **Events** — event types, master events, event codes, Tambike module and form, event detail page, calendar v1.
3. **Function Hall** — bookings, `DetectBookingConflict` (the critical rule), payments, calendar integration, override permission.
4. **Sponsorship** — teams, racers, achievements, racer profile page, applications, approval workflow, sponsorship events, requirements, raffle prizes, giveaway items.
5. **PR Kits** — campaigns, recipients, items, content requirements, release tracking with proof of release.
6. **Design** — requests, assignment, status workflow, versioned files, comments, approvals, deadlines.
7. **Reports** — the 6 reports, filters, CSV / Excel / PDF export.
8. **Polish** — notifications, reminder scheduler, activity log UI, responsive pass, N+1 and caching audit, full test suite, security review.
9. **Public client surface** — isolated host middleware and layout, UUID-token event review links, explicit field allowlist, confirmation/change requests, five inquiry forms, private uploads, throttling/honeypot/validation, local backups, tunnel health, local QR generation, and Turnstile only after a stable hostname exists. The host boundary and core public intake are implemented in the current foundation.

---

## Open items to confirm with the client

1. PHP 8.3 upgrade — building on 8.2 meanwhile; no code change required later.
2. Function Hall default rate per hour (needed for `venue_fee` auto-calc). **[ASSUMPTION]** settings-driven, defaulting to ₱0 until supplied.
3. Whether a *Pencil Booked* slot blocks a conflicting booking. **[ASSUMPTION]** it warns but does not block; only `Confirmed` and `Ongoing` hard-block.
4. Whether Designers may approve their own final designs. **[ASSUMPTION]** no, per the brief; grantable per-user via the `approve_design` permission.
