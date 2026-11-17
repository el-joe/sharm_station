# Backlog (updated 13 Nov 2026, after the second build round)

## Done and verified

- Domain, pricing engine, API v1 (trips, Nile, packages, rooms, buildings, transfers, bookings, payments, proofs, newsletter, contact, me, partner panel), Google reviews sync, CORS list, safe error responses: **224 Pest tests green**.
- Admin panel (Blade + Tailwind + vanilla JS + Yajra + Select2, no Livewire): dashboard, trips/Nile (pricing modes, unit options, season editor), packages builder, buildings (tax included, Google Place ID), rooms + calendar, bookings + balance payments, receipts review, payments log, payment methods, transfers (hubs, vehicles, fixed routes, requests, simulator), partners + commission matrix, Google reviews, contact messages, destinations, add-ons, currencies, settings.
- Next.js site (EN/IT): all customer pages, account, partner panel, destinations, static pages, blog, payment step with receipt upload.
- End-to-end QA: trip booking -> bank transfer receipt upload -> admin approval -> booking Confirmed / deposit_paid, visible on the customer link (mobile + IT checked).
- QA fixes: booking links with `?token=` now work on any device; confirmation heading follows status; admin approve dialog no longer says "Delete".

## Open

| Priority | Item |
| --- | --- |
| P1 | Room allocation across several rooms (no-shared-rooms rules, SRS 7) and optional rooms inside trips |
| P1 | Email notifications (booking, payment, proof approved/rejected, balance reminders; SRS 15.1) |
| P1 | Real Stripe/PayPal/Paymob credentials and live test; currently fake gateway + Stripe skeleton |
| P2 | Trip editor fields for unit_label, FAQ, what to bring, guide languages, cancellation text (seeded only) |
| P2 | Real maps/routing provider for transfer distance (Haversine estimate now); real maps on destination/contact pages |
| P2 | Partner statement PDF/Excel export; reject partner with reason; booking on behalf of a partner |
| P2 | Unavailable rooms shown greyed with a reason instead of hidden; accommodation map view |
| P2 | Booking page metadata title still says "Booking received" when confirmed |
| P3 | Move the customer token from localStorage to an httpOnly cookie (needs a Next proxy) |
| P3 | Admin two-factor auth for Super Admin; admin JS bundle code-splitting (~550 kB) |
| P3 | Legal texts reviewed by counsel |
| Before deploy | Change demo password in `UserSeeder`, `APP_DEBUG=false`, set `FRONTEND_URLS`, `GOOGLE_PLACES_API_KEY`, gateway keys |
| Before deploy | `APP_DEBUG` must be `false` in production: with `APP_DEBUG=true` the admin DataTables JSON (Yajra) includes the executed SQL queries and bindings (found by the E2E run, 17 Nov 2026) |

## Local run

- API + admin: `cd backend && php artisan serve --host=127.0.0.1 --port=8090` → http://localhost:8090/admin (port 8000 belongs to another project on this machine)
- Site: `cd frontend && npm run build && npm run start -- --port 3000` → http://localhost:3000/en
- Demo logins (local only): `admin@`, `agent@`, `editor@`, `customer@`, `partner@sharmstation.test`, password in `backend/database/seeders/UserSeeder.php`
