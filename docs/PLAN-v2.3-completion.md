# Plan v2.3: complete every page (no placeholders)

Date: 24 Nov 2026. Source audit: Playwright crawl of 98 public pages and 56 admin pages + code search.

## 1. What is missing

| # | Area | Missing | Today |
| --- | --- | --- | --- |
| A1 | Admin | **Accommodation calendar** (room occupancy grid) | "Coming soon" page |
| A2 | Admin | **Customers** (list + detail) | "Coming soon" page |
| A3 | Admin | **Coupons** (CRUD) | "Coming soon" page |
| A4 | Admin | **Content: pages, blog, FAQ** | "Coming soon"; website texts are hard-coded in `frontend/lib/content/*` |
| A5 | Admin | **Users and roles** (staff accounts) | "Coming soon" page |
| A6 | Admin | **Reports** (SRS 14.1) | not built |
| A7 | Admin | **Activity log** (SRS 14.1) | not built |
| A8 | Admin | Partner **reject with reason** | only approve / suspend |
| W1 | Website | **Maps** on destination pages and contact page; **map view** on accommodation listing | "Map coming soon" box |
| W2 | Website | Content pages, blog and FAQ **from the admin** (A4) | hard-coded |
| W3 | Website | **Coupon code** at checkout (A3) | none |
| W4 | Website + account + partner | **Cancellation request** on a booking | link to the contact page |
| P1 | Partner panel | **Statements PDF / Excel export** | buttons without files |

The admin "Coming soon" route and view are deleted once every module exists.

## 2. API contract (backend builds, frontend consumes)

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/api/v1/pages/{slug}` | slug: about, booking-terms, privacy, cancellation-policy, cookie-policy; `{slug, title, intro, body_html, seo, updated_at}` per locale |
| GET | `/api/v1/blog` , `/api/v1/blog/{slug}` | published posts: `{slug, title, excerpt, cover_image, body_html, author, published_at, tags, reading_minutes, seo}` |
| GET | `/api/v1/faqs` | `{data:[{category, items:[{question, answer_html}]}]}` |
| POST | `/api/v1/coupons/validate` | `{code, service_type, total_eur}` → `{valid, code, discount_type, value, discount_eur, message}`; also `coupon_code` accepted on `POST /bookings` and applied server-side (never for partner bookings unless the partner is allowed) |
| POST | `/api/v1/bookings/{reference}/cancellation-request` | `{reason}` with booking token or owner/partner auth → 201, booking gets `cancellation_requested_at` + status log; admin sees and decides (refund suggestion from the cancellation policy time bands) |
| GET | `/api/v1/partner/statements/{period}/export?format=pdf|xlsx` | file download (partner auth) |
| GET | `/api/v1/meta` | adds `contact: {address, lat, lng, phone, email, whatsapp}` for the contact map |
| (existing) | destinations, buildings, rooms | already return lat/lng |

## 3. Admin screens (Blade + Tailwind + vanilla JS + Yajra + Select2, never Livewire)

Same AdminShell and components as the existing screens; Repository → Service → DTO → Enum layering; Pest tests for each.

- **Calendar**: month grid, rows = rooms grouped by building (Select2 building filter), cells coloured by status (booked = booking reference link, held, blocked, free), click a free range to block/unblock, legend, previous/next month.
- **Customers**: Yajra list (name, email, phone, nationality, bookings count, total spent, created), detail with profile, bookings table, notes, activate/deactivate.
- **Coupons**: Yajra list + form (code, description, discount type percent | fixed EUR, value, valid from/to, usage limit total and per customer, minimum total, services multi-select, partners allowed, active), usage count shown.
- **Content**: tabs Pages (fixed slugs, translatable EN/IT rich text with a simple vanilla-JS editor or plain textarea + preview, SEO), Blog posts (CRUD, cover image upload, publish date, status), FAQ (categories + items, drag order). Seed them with the current website texts from `frontend/lib/content/*` so nothing changes visually.
- **Users and roles**: staff users CRUD (name, email, role super_admin | agent | editor, active, send password reset / set password), a read-only permissions matrix per role.
- **Reports**: date range + service filters; KPIs and tables: bookings and revenue by service and month, payments received, balances due, best sellers, partner sales and commissions, nationality groups; CSV export per table; Chart.js charts.
- **Activity log**: every create/update/delete/approve/reject/status change by staff (who, what, subject link, changes, IP, time), Yajra list with filters.
- **Partners**: reject application with a reason (email-ready), shown on the partner page.
- **Bookings**: cancellation requests visible (badge + filter) with approve (refund suggestion) / decline.

## 4. Website and panels (Next.js)

- **Maps**: Leaflet + OpenStreetMap tiles (no API key), lazy loaded client component, markers for destination trips/buildings, contact office, accommodation map view (toggle List / Map with room cards in a side list).
- Content pages, blog index/article and FAQ read the API (`'use cache'` with tags), keep current layout; static fallback only if the API is down.
- Checkout coupon field (apply/remove, shows the discount line).
- Cancellation request button + form on booking confirmation, account booking detail and partner booking detail.
- Partner statements: PDF and Excel buttons download the files.

## 5. Order

1. Backend teammate: §2 + §3 (+ Pest, seeders).
2. Frontend teammate: §4 against the real API.
3. QA teammate: Playwright: a **crawl spec** that visits every website, account, partner and admin route (desktop + mobile) and fails on 4xx/5xx, console errors or placeholder text; feature specs for the new pages; full suite green.
