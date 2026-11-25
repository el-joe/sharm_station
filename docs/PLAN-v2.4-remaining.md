# Plan v2.4: build everything still missing

Date: 25 Nov 2026. Sources: docs/BACKLOG.md, SRS v2.2, and the "not done" notes from the v2.2 and v2.3 rounds.

## 1. Scope

### Phase 1: backend, operations (teammate B1)

| # | Item | SRS |
| --- | --- | --- |
| B1.1 | **Email notifications** for every event in SRS 15.1 (booking created, deposit confirmed, balance reminder, balance recorded, payment failed or expired, hold about to expire, transfer request and status change, included-transfer task (admin), new proof (admin), proof approved/rejected, cancellation request and decision, partner application received/approved/rejected, post-trip invitation to review on Google). Queued Mailables, EN/IT by booking locale, branded HTML layout with the voucher PDF attached where relevant. | 15.1 |
| B1.2 | **Admin → Notifications**: editable templates per event and language (subject + body with placeholders, preview, send test), enable/disable per event, sending log (recipient, event, status, error, time) with resend. | 14.1 |
| B1.3 | **Scheduled jobs**: balance reminders before the due date, overdue balance alerts to admin, accommodation hold expiry + "hold about to expire" email, unpaid booking expiry, exchange-rate refresh from a free API with manual override and margin kept, post-trip review invitation, partner monthly statement email. | 6.5, 10.1, 11, 15.1 |
| B1.4 | **Payment gateways**: real Stripe Checkout driver (official SDK, test mode via env, signed webhooks, refunds) and PayPal Orders v2 driver (REST, sandbox via env, webhook verification, refunds); the existing fake gateway stays for local use. Gateways stay hidden when not configured. | 10.2 |
| B1.5 | **Refunds**: approving a cancellation creates a refund Payment (gateway refund when the deposit was paid online and the gateway supports it, otherwise a manual refund record); admin can record full or partial refunds; booking payment status updates. A declined cancellation can be requested again. | 10.2, 12.4 |
| B1.6 | **Voucher PDF** (with or without prices) generated server-side for customers, partners and admin. | 12.3 |
| B1.7 | **Admin security**: TOTP two-factor authentication for super admins (enrol with QR, recovery codes, enforce on login). | 14.2 |
| B1.8 | **Small backend gaps**: 301 redirects when a slug changes (trips, packages, buildings, blog); statement export of an empty month → 404; partner dashboard/statements aggregated in SQL; trip editor fields for unit label, FAQ, what to bring, guide languages, cancellation text; admin JS code-splitting. | — |

### Phase 2: backend, bookings and accommodation (teammate B2)

| # | Item | SRS |
| --- | --- | --- |
| B2.1 | **Multi-room accommodation booking** with the allocation rules of SRS 7.2 (no shared rooms, every guest in exactly one room, capacity per room, no room with only children, no empty room, same building), "best combinations" helper, per-room children policy and per-person pricing. | 6.4, 7.2–7.4 |
| B2.2 | **Extra bed and cot** pricing and limits per room. | 6.2, 7.3 |
| B2.3 | **Optional rooms inside multi-day trips, Nile trips (cabins) and packages (per stage)**: unit types, star ratings, price per room or per person, per night or per stay, capacity; added to quotes and bookings with allocation. | 7.1 |
| B2.4 | **Rooms listing**: unavailable rooms returned with the reason (booked, minimum stay, closed) instead of hidden; "group by building" support. | 6.3 |
| B2.5 | **Admin bookings**: create a booking from the admin for a customer or on behalf of a partner (same pricing engine), edit dates/rooms with price recalculation. | 9.4, 12.3 |
| B2.6 | **Calendar**: drag a booking to another room or dates when available; manual hold release. | 12.3 |
| B2.7 | **iCal** export per room and import of external calendars (scheduled sync). | 6.2 |
| B2.8 | **Maps leftovers**: address filled when the admin pin is dragged (Geocoding through the browser key, enable "Geocoding API" on it), location picker in the transfer pricing simulator. | — |

### Phase 3: frontend (teammate F)

| # | Item |
| --- | --- |
| F1 | Accommodation booking with several rooms: best combinations, **RoomAllocationBoard** (Figma component 27:10110) with drag/tap travellers into rooms and all validation messages, extra bed/cot. |
| F2 | Optional rooms in the trip/Nile/package booking widget and the partner configure step. |
| F3 | Rooms listing: unavailable rooms greyed with the reason; "Group by building" toggle. |
| F4 | Payment step: real Stripe/PayPal redirects and return pages (success, cancel, pending), refund display, voucher PDF downloads (customer, partner, with/without prices). |
| F5 | Cancellation can be requested again after a decline; refunds shown. |
| F6 | Auth token moved from localStorage to an **httpOnly cookie** via Next route handlers (proxy for authenticated API calls). |
| F7 | Booking page metadata title follows the status; header nationality picker as the anchored popover from Figma; Maps JS language follows the locale switch. |

### Phase 4: QA (teammate Q)

Playwright specs for every new flow (multi-room allocation, trip rooms, extra beds, admin booking on behalf of a partner, calendar drag, refunds, re-request, voucher PDFs, notifications log + template edit, 2FA login, cookie auth), then the full suite on desktop and mobile.

## 2. Out of scope (needs the client)

- Live payment credentials (Stripe, PayPal, a local EGP gateway): drivers are built and tested in test/sandbox mode; live keys are added by the client.
- SMTP/mail provider credentials: emails go to the log locally (`MAIL_MAILER=log`) and to Mailpit-style capture in tests.
- Adding new languages (e.g. Arabic RTL) and legal review of texts.
