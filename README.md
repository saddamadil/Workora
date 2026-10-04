# Workora

Run your freelancers and your projects in one place. Companies hire freelancers and manage the work;
freelancers do it, track their time and get paid. Files can be shared with a link.

Built on Laravel 13, Tailwind CSS 4, Alpine.js and Bootstrap Icons. SQLite, MySQL/MariaDB or Postgres.

## What it does

**For a company** (owner, admin, project manager, team member, finance, viewer)

- Projects with a client, budget, deadline and team. A board of tasks by status.
- Tasks with assignees, checklist, comments, files, estimates. Review submitted work: approve it, or
  send it back with a list of changes. Every approval is written to an audit log.
- Invite freelancers and staff by email link. Per-person roles and default rates.
- Approve weekly timesheets. Create contracts (hourly, fixed price, milestones, monthly retainer).
- Approve or reject invoices, record payments (full or partial), see what is owed and what is overdue.
- Proposals: freelancers suggest work and a price, you counter, and approval turns it into a task.

**For a freelancer**

- One account, any number of companies, with a switcher. Sees only the projects and tasks they are on.
- Timer or manual time, weekly timesheet submission, contract acceptance, invoices built from approved
  hours and milestones (an hour can never be billed twice), earnings and payment history.
- Profile with rate, availability and how to be paid.

**Freelancer workspace and client portal**

- A freelancer who signs up gets their own workspace (solo mode): clients, projects, tasks, work log,
  invoices and payments, with a dashboard of today's tasks, upcoming deadlines and what is owed.
- Invite a client by email from their profile. The client gets a separate portal (their own login,
  connected to an existing account if they have one) showing only their own projects, task status and
  sent invoices, with PDF download. Adding the same client twice is blocked.
- Client logins are confined to the portal in one place (`SetCurrentOrganization::CLIENT_ROUTES`);
  portal queries are pinned to the client's record and select only client-safe fields.
- Per-project messaging (replies, attachments, important flag, search, unread counts, typing and
  online status by polling), message to task (freelancer) and message to request (client).
- Project workspace tabs: overview, tasks, files (private until shared), work log, milestones with
  deliverables for client approval or change requests, invoices, activity timeline.
- Client requests (accept, decline, discuss, convert to task or project), calendar (month, week, day,
  agenda), notifications in the app and by email with per-type preferences, daily reminders.
- Payments: clients see what is due, pay by the details or payment link shown, tell the freelancer
  with a reference, and get a PDF receipt once it is confirmed. Freelancy records payments; it does
  not take card payments or move money.
- Reports, global search (Ctrl or Cmd + K), dashboard widgets, setup wizard, help, password reset,
  email verification (encouraged, not required), and five interface languages for menus and labels.
- Not built yet: online card payments, two-factor sign-in, other-device sign-out, full translation of
  every screen and a fully mirrored right-to-left layout.

**Team, profiles and invoicing**

- Team has six tabs: Overview, Members (cards), Invitations, Roles & permissions (generated from the
  same rules the app enforces), Payment profiles and Invoices.
- Reusable profiles: company (logo, legal name, tax IDs shown per country), freelancer (photo,
  address, tax numbers, signature, invoice prefix), client (logo, billing address, tax info) and
  payment profiles (domestic bank/UPI, international IBAN/SWIFT/routing/sort code/ABA/payment link),
  stored encrypted and masked on screen.
- Invoice generator in three steps (details, services, preview and send), five templates, domestic and
  international modes, GST/VAT/custom tax lines, exchange rate and INR equivalent, PDF download, print,
  duplicate, send (with optional emailed PDF), mark as paid, and a dashboard with filters and search.
- Workora prints the tax settings you choose and flags what looks incomplete ("Review tax settings").
  It does not decide which tax applies to you; check with your accountant.

**Files for everyone**: drag-and-drop upload, previews, rename, folders, and share links with an
optional password, expiry and download limit.

Money is stored as integer minor units (paise, cents). Workora records payments; it does not move money.

## Run it locally

```bash
composer install          # the committed composer.lock needs PHP 8.4; on 8.3 run `composer update`
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install && npm run build
php artisan serve
```

Open http://localhost:8000, choose "I run a company", and invite a second account as a freelancer
(the invitation link is shown on screen; email is optional).

PHP's `upload_max_filesize` and `post_max_size` must be at least `WORKORA_MAX_UPLOAD_MB` (default 100).

## Tests

```bash
php artisan test
```

56 feature tests cover sign-up, invitations, the whole work cycle, time and timesheets, contracts,
invoices and payments, and who may see what (freelancer vs freelancer, company vs company). The suite
runs with Eloquent strict mode on, so a lazy-loaded relation or a misspelled attribute fails a test.

## Deploying

See [docs/DEPLOY-HOSTINGER.md](docs/DEPLOY-HOSTINGER.md) for shared hosting (build script, upload,
MySQL import). A VPS with PHP 8.4, Nginx and a database works the usual Laravel way. Nothing here needs
a queue worker or cron yet.

## Code map

```
app/Http/Controllers/   one controller per area (projects, tasks, time, contracts, invoices, ...)
app/Policies/           row-level rules (Task, Project, Invoice, File); Gates in AppServiceProvider
app/Services/           FileLibrary (storage), Rates (which hourly rate applies), DashboardStats
app/Support/            Tenancy (the current company), Money (minor units <-> text)
app/Models/             tenant models use BelongsToOrganization; UUID keys everywhere
database/migrations/    schema, including optional Postgres row-level security
docs/                   TENANCY.md (isolation), MVP-SCOPE.md, DEPLOY-HOSTINGER.md
scripts/                build-deploy.sh (upload package), export-schema.sh (MySQL import file)
```

## Conventions worth keeping

- **Every tenant model uses `BelongsToOrganization`**, and `SetCurrentOrganization` runs before route
  model binding, so `/tasks/{task}` of another company is a 404, not a leak.
- **A freelancer sees a task only if assigned to it**; budgets and rates are hidden from them.
- **Financial records are never hard-deleted**: invoices are voided or rejected, audit rows are
  immutable, and invoiced time entries are locked.
- **Invoice numbers are per freelancer and financial year** (`INV-2026-27-001` for India's April to
  March year, calendar year elsewhere); contract references are per company.

## Running it on a server

- Run `php artisan schedule:run` every minute (a Hostinger Cron Job) for the daily due-date reminders.
- Set `MAIL_MAILER` and the SMTP settings in `.env` or invitations, reset links and email
  notifications are only written to the log.

## Server settings for the newer features

- `PLATFORM_ADMIN_EMAIL`: the one sign-in that can change workspace plans at `/admin/plans`.
- `INBOUND_MAIL_DOMAIN` and `INBOUND_MAIL_SECRET`: reply-by-email. Point an inbound email service
  (Mailgun, Postmark or SendGrid) at `POST /inbound/email?secret=...`. Without them, emails simply have no reply address.
- The same every-minute cron runs recurring invoices (daily 06:30) and reminders (daily 08:00).
- Languages: `lang/{de,hi,ar,tr}/phrases.json` map whole English phrases to translations. Run
  `scripts/extract-phrases.py` to list phrases and add missing ones. Translations are machine-quality: have a native speaker review them.
- Online payment gateways are not included: clients pay outside Freelancy and report the payment.

## Not built yet

Payment gateways, disputes and escrow, and a public API. See
`docs/MVP-SCOPE.md` for the reasoning behind what was left out.
