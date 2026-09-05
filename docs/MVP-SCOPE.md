# MVP scope

The original blueprint describes roughly four years of product. This is what v1
should contain, and — more usefully — what it should not.

## In v1

**Company side**
Registration and company profile. Invite staff with the six roles. Freelancer
database with per-company categories, rates and status. Projects with client,
budget, deadline and an explicit member list. Tasks with the full status flow
through to approval. Structured revisions rather than free-text "please fix this".
Comments and file attachments. Approve or reject submitted work.

**Freelancer side**
Registration, one profile shared across companies, list of companies they work
with. Their projects and tasks. Submit work. Comments and files. Manual timesheets.
Raise an invoice. See earnings and payment status.

**Work requests**
The freelancer-initiated flow: propose work, negotiate scope and price in a thread,
company approves and it converts to a task or project. This is the one feature in
the blueprint that most competitors do not have, so it ships in v1 rather than
waiting for v3.

**Finance**
Contracts in all four shapes: hourly, fixed, milestone, retainer. Invoice creation,
approval and rejection. Payment records with status and schedule.

**Platform**
Subscription plans, email and in-app notifications, audit log.

## Deliberately not in v1

Time tracking with a live timer. Desktop app, screenshots, activity monitoring.
Expenses. Project chat. Calendar. Advanced reports. Recurring anything. Custom
permission builder. Marketplace, public profiles, proposals, reviews. Disputes.
Escrow. AI features. Automation engine. Public API. Mobile apps. Integrations.

Cutting these is the single highest-value decision in the plan. Each looks like a
feature and is actually a subsystem.

## Payments: decide this before you build

There is a fork here that changes what the product legally is.

**Tracking payments** — the app records what is owed, what is scheduled and what
was paid. Money moves through the company's own bank or existing tools. No
licensing implications. This is what the schema currently supports.

**Processing payments** — the app collects from companies and pays out to
freelancers. In India this brings you under RBI's payment aggregator rules, and
escrow requires a licensed partner rather than something you build. Cross-border
payouts add FEMA compliance and KYC on every freelancer.

Build the first. Ship it. Only consider the second when a customer is asking and
you have revenue to fund the compliance work. The schema keeps `processor` and
`processor_reference` columns so that door stays open without holding v1 hostage.

## Sequence

1. Auth, organizations, membership, invitations, role plumbing, tenancy tests
2. Freelancer database, profiles, onboarding
3. Projects, project membership, tasks, comments, files
4. Submissions, approvals, structured revisions
5. Work requests and the negotiation thread
6. Contracts and milestones
7. Timesheets, invoices, payment records
8. Notifications, audit log, subscription plans

Each step ends with something demonstrable. Do not start step 3 until the two
tenancy tests in TENANCY.md pass, because retrofitting isolation into a schema
that already has controllers is far more expensive than it looks.

## A note on the timeline

The blueprint's "3 to 5 months for a capable small team" assumes people working on
it full time. Built solo and part time, the honest range is longer — and the way
that estimate goes wrong is almost never the schema or the UI. It is the approval
and money flows, where every state transition needs its own permission check, its
own notification, and its own audit entry.
