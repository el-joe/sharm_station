# Sharm Station Viaggi: Build Plan and Architecture

Source of truth: `SRS v2.1 - Travel Booking Platform (English).md` (business rules, CR-1 to CR-9) and the Figma file
[Egypt Travel - Platform UI/UX](https://www.figma.com/design/WGqsCzbjhWYrsLXqFranBq/Egypt-Travel---Platform-UI-UX)
(pages Customer Site Desktop/Mobile, Admin Desktop/Mobile, Components).

## 1. System overview

| App | Folder | Stack | Serves |
| --- | --- | --- | --- |
| API + Admin panel | `backend/` | Laravel 13, PHP 8.3, MySQL 8, Blade + Tailwind CSS 4 + vanilla JS, Yajra DataTables 13, Select2, Flatpickr, SortableJS, Chart.js, Vite | `/api/v1/*` JSON for Next.js; `/admin/*` back office |
| Customer + partner site | `frontend/` | Next.js 16 (App Router, TypeScript), Tailwind CSS 4, next-intl (`/en`, `/it`), TanStack Query, React Hook Form + Zod | Public website, booking flows, customer account, partner panel |

**Never use Livewire or Inertia.** The admin is classic server-rendered Blade with vanilla JS modules.

## 2. Backend layering (mandatory)

```
HTTP (Controller, FormRequest, API Resource)
   -> DTO (readonly, built from validated request: Dto::fromRequest())
   -> Service (business rules, transactions)
   -> Repository interface (bound in RepositoryServiceProvider)
   -> Eloquent repository implementation -> Model
```

- Controllers are thin: validate (FormRequest) -> build DTO -> call Service -> return Resource / view / redirect.
- Services never touch `Request`; they take DTOs and return models or result DTOs.
- Repositories: `app/Repositories/Contracts/*RepositoryInterface.php` + `app/Repositories/Eloquent/*Repository.php`, base `BaseRepository` (find, paginate, create, update, delete, query()).
- Enums: PHP 8.1 backed enums in `app/Enums`, with `label()` and `options()` helpers for selects.
- The same Services back both the admin panel and the API.

```
app/
  Enums/            BookingType, PricingMode, TripType, ServiceType, BookingStatus, PaymentStatus,
                    RoomType, RoomPricingMode, BuildingType, DiscountType, AddonPricingBasis,
                    PartnerStatus, UserRole, Currency, VehicleType, TransferStatus, CommissionCategory
  DTOs/             Trip/TripData, Trip/UnitOptionData, Pricing/SeasonPriceData, Pricing/QuoteRequestData,
                    Pricing/QuoteResultData, Booking/CreateBookingData, Building/BuildingData, Room/RoomData ...
  Repositories/Contracts, Repositories/Eloquent
  Services/         TripService, SeasonPriceService, PricingEngine (trip, room), AvailabilityService,
                    RoomAllocationService, BookingService, PartnerPricingService, CurrencyService,
                    GoogleReviewService, TransferPricingService, PaymentService
  Http/Controllers/Admin/*      Blade admin (DataTables JSON endpoints included)
  Http/Controllers/Api/V1/*     JSON API
  Http/Requests/Admin/*, Http/Requests/Api/*
  Http/Resources/V1/*
  DataTables/       Yajra DataTable classes per admin list
resources/views/admin/   layouts/app.blade.php (AdminShell), components/, trips/, buildings/ ...
resources/js/admin/      modules: datatables.js, select2.js, season-editor.js, unit-options.js, ...
```

## 3. Data model (MySQL)

Money: `decimal(10,2)` in EUR. Translatable text: JSON columns `{ "en": "...", "it": "..." }` (fallback `en`).

| Table | Key columns |
| --- | --- |
| users | name, email, phone, password, role (UserRole), locale, currency |
| partners | user_id, company, tax_number, country, website, status (PartnerStatus), default_commission, show_retail_price, coupons_allowed, approved_at, approved_by |
| partner_category_commissions | partner_id, category (CommissionCategory), commission_percent |
| destinations | name*, slug, description*, image, sort |
| trips | type (TripType: single_day, multi_day, nile), destination_id, title*, slug, summary*, description*, duration_hours, days, nights, private_enabled, group_enabled, **pricing_mode** (PricingMode), **min_persons, max_persons**, base_price, base_child_price, partner_price, deposit_percent, free_cancellation_hours, highlights* (json), included* (json), excluded* (json), meeting_point*, lat, lng, vessel_name, itinerary_from, itinerary_to, vessel_facilities (json), meal_type, status, seo (json), featured |
| trip_unit_options | trip_id, name*, seats, price, partner_price, max_quantity, children_allowed, sort |
| trip_private_tiers | trip_id, min_people, max_people, price_per_person, partner_price |
| trip_availability_rules | trip_id, booking_type, weekdays (json), dates (json), blackout_dates (json), lead_time_hours |
| trip_days / trip_timeline_items | trip_id, day_number, title*, description*; day_id, time_from, time_to, title*, description*, place, sort |
| trip_media | trip_id, path, alt*, sort |
| **season_prices** | priceable_type, priceable_id, name, starts_on, ends_on, recurring_yearly, is_active, adult_price, child_price, partner_price, unit_prices (json `{unit_option_id: price}`), tier_prices (json `{tier_id: price}`), price_per_night |
| packages, package_stages, package_stage_trips, package_included_transfers | as SRS 4.2 |
| addons, addon_assignments | name*, description*, price, partner_price, pricing_basis, required, max_quantity, booking_types (json) |
| buildings | name*, slug, type, destination_id, address, lat, lng, stars, description*, amenities (json), check_in_from, check_out_until, deposit_percent, cancellation_policy*, **taxes_included** (bool, default 1), **tax_per_night**, **google_place_id**, status |
| rooms | building_id, name*, number, room_type, max_adults, max_children, max_total, beds, size_m2, view, amenities (json), pricing_mode (RoomPricingMode), base_price, partner_price, extra_bed_price, min_nights, max_nights, status |
| room_availability | room_id, date, status (open, closed, held, booked), booking_id, price_override |
| child_policies, child_age_bands | owner morph, age bands with discount_type + value |
| google_reviews | place_id, google_review_id, author_name, author_photo_url, rating, text, language, published_at, is_hidden, synced_at |
| bookings | reference, service_type, booking_type, pricing_mode, season_name, start_date, check_in, check_out, user_id, partner_id, lead_name, lead_email, lead_phone, adults, children, retail_total, partner_total, commission_amount, price_rule, total, deposit_amount, balance_amount, currency, exchange_rate, status, payment_status, locale, notes |
| booking_travelers, booking_units, booking_rooms, booking_room_guests, booking_addons, booking_status_logs | per SRS 18 |
| payments, payment_proofs, manual_payment_methods, payment_gateways | per SRS 10 |
| currencies | code, symbol, rate_to_eur, margin_percent, is_enabled |
| transfer_hubs, vehicle_types, transfer_fixed_routes, transfer_requests | per SRS 5 |
| settings | key, value (json) |

## 4. Pricing rules implemented in `PricingEngine`

1. **Season** (CR-7): `SeasonPriceService::forDate($priceable, $date)` returns the active season whose range contains the date (handles year-crossing ranges and `recurring_yearly`), else `null` -> base price. Overlaps are rejected on save (`SeasonOverlapException`, message per Figma).
2. **Per person** (CR-1): `persons = adults + children`; must satisfy `min_persons <= persons <= max_persons`. Private: price from tier (or season tier price), group: adult/child price (or season). Children discount from age bands.
3. **Per unit** (CR-1): request carries `units: [{unit_option_id, quantity}]`; `sum(seats*qty) >= persons`, every unit >= 1 adult (`adults >= total units`), `qty <= max_quantity`; line = (season unit price or option price) x qty.
4. **Add-ons** per basis (per person / per booking / per unit x qty).
5. **Partner**: item partner price (method B) > category commission > default commission > retail. Store retail_total, partner_total, commission_amount, price_rule.
6. **Deposit** = round(total x deposit_percent / 100, 2); balance = total - deposit.
7. **Accommodation**: each night priced by its season (`price_per_night`) or room base/override; `taxes_included` drives the "Tax included" flag in API output (CR-2).
8. Prices are always computed on the server; the API returns EUR plus the converted amount for the requested currency.

## 5. REST API v1 (`/api/v1`, JSON, `?locale=en|it&currency=EUR|USD|GBP|EGP`)

All prices are computed on the server in EUR; money fields are `{amount_eur, amount, currency}`. Errors are `{message, errors}` (422 validation, 404 `{message}`); with `APP_DEBUG=false` no trace/file/exception class is ever returned. Auth = Sanctum Bearer token.

| Method | Path | Notes |
| --- | --- | --- |
| GET | /meta | currencies, locales, destinations (`image`, `trips_count`), categories, `settings` (dotted keys, unchanged), `site` (same settings nested: `site.name`, `site.whatsapp`...), `whatsapp_digits`, google rating |
| GET | /home | hero stats, featured trips, packages, rooms, nile, destinations, google reviews summary |
| GET | /destinations, /destinations/{slug} | |
| GET | /trips | filters: `type`, destination, booking_type, pricing_mode, price_min, price_max, free_cancellation, category, q, `sort` = recommended, price_asc, price_desc, newest, duration, rating, most_booked. **No date filter (CR-5)**. Cards add `rating`, `reviews_count` (business Google rating, CR-4), `unit_label` |
| GET | /trips/{slug} | detail + `faq[{question,answer}]`, `what_to_bring[]`, `guide_languages[{code,label}]`, `cancellation_policy`, itinerary items `transfer_included`; Nile: `nile.cabins[{type,label,capacity,stars}]` (no deck, CR-8), `facility_labels`, `meal_type_label` |
| POST | /trips/{slug}/quote | `{booking_type, travel_date, adults, children, child_ages[], units[], addons[]}`; child lines carry `meta.age` (first child) and `meta.ages[]` |
| GET | /packages | filters destination (package or any stage), price_min/max, days_min/max, q, sort (recommended, price_asc, price_desc, duration, newest, rating, most_booked). Card: stages, `transfers_included: true`, `from_price`, rating |
| GET | /packages/{slug} | stages (`start_day`, `end_day`, accommodation, trips per stage day), `trips[]`, `included_transfers[]`, `itinerary[]` (one per package day, items type trip/transfer with `transfer_included`), `price_list`, add-ons, child policy, practical info |
| POST | /packages/{slug}/quote | `{booking_type, travel_date, adults, children, child_ages[], addons[]}` -> per-person lines (fixed: season by travel date; computed: sum of linked trips on their day dates minus discount), partner totals for partners, deposit, `included_transfers[]` with dates (no price) |
| GET | /transfers/hubs | hubs with location, radius, extras (with price), predefined `places[]` (fixed-route destinations) |
| GET | /transfers/vehicles | id, name, category, capacity, luggage |
| POST | /transfers/quote | `{hub_id, vehicle_type_id, direction?, destination{name,lat,lng}, pickup_at "Y-m-d H:i", round_trip?, return_at?, passengers, luggage?, extras[]?, waiting_minutes?}` -> legs priced with `P = max(M,(B+Σd·R+W+S)·V)+F`, fixed-route override, return discount, `distance_km`, `duration_minutes`, `valid_until` |
| GET | /buildings/{slug}, /rooms, /rooms/{id} | `/rooms` filters add `check_in` + `check_out` (whole stay available, min/max nights) and `free_cancellation`; cards add `room_type_label`, `amenity_labels[]`, `free_cancellation(_hours)`, `few_left`, `breakfast_included`, `badges[]` |
| POST | /rooms/quote | stay quote night by night |
| GET | /reviews/google | rating, total, reviews[] (`author_country` added), write_review_url |
| POST | /bookings | trip (`trip_slug`), package (`package_slug` or `service: package` + per-person fields; creates included-transfer tasks), accommodation (`room_id`), transfer (`transfer: {quote fields, flight_number}`, `lead.phone` required, travellers optional) -> 201 BookingResource with `access_token`, `payment_options`, `payment_due_at` |
| GET | /bookings/{reference} | `?token=` or owner/partner/staff; adds `payment_due_at`, `payment_summary`, `payments[]`, `proofs[]`, `included_transfers[]`, `transfer` |
| GET | /bookings/{reference}/payment-methods | enabled methods for the booking currency: type online/manual, `group` instant/manual_transfer, instructions, account_details, `amount_due` in booking currency |
| POST | /bookings/{reference}/proofs | multipart `token, payment_method_id, files[] (1-3, jpg/png/webp/pdf, 5 MB), transaction_number?, transaction_date?, amount?, sender_name?` -> 201; booking `payment_status = awaiting_receipt_review` |
| POST | /bookings/{reference}/checkout | `{token, payment_method_id}` online gateway -> `{checkout_url, session_reference}` |
| POST | /payments/webhooks/{driver} | gateway webhook (fake: `X-Fake-Signature` HMAC; stripe: `Stripe-Signature`) |
| POST | /newsletter | `{email, locale?}` -> 201 new, 200 already subscribed (idempotent) |
| POST | /contact | `{name, email, phone?, subject: booking|trip|partner|other, booking_reference?, message, locale?}` -> 201 |
| POST | /auth/register, /auth/login, /auth/logout, GET /auth/me | Sanctum tokens |
| GET | /me/bookings | own bookings, paginated (`per_page` ≤ 100) |
| PATCH | /me/profile | `{name?, phone?, locale?, currency?, newsletter?}` -> UserResource |
| PUT | /me/password | `{current_password, password, password_confirmation}` -> 204 |
| GET | /partner/dashboard, /partner/bookings, /partner/statements, /partner/price-list, GET+PATCH /partner/profile | active partners only (else 403); price list 403 when disabled for the partner |
| POST | /partners/apply | see below |

### POST /partners/apply (Become a partner)

Public, throttled 10/min. Body (JSON):

| Field | Rules |
| --- | --- |
| `company` | required, string, max 255 |
| `name` | required for guests (contact person), max 255; ignored when signed in |
| `email` | required for guests, email, max 255 |
| `password` | required for guests, `password_confirmation` must match (Laravel default password rules) |
| `phone` | optional, max 40 |
| `country` | optional, ISO 3166-1 alpha-2 (2 letters) |
| `tax_number` | optional, max 64 |
| `website` | optional, URL |
| `message` | optional, max 2000 |

A signed-in customer (Bearer token) only sends `company` and the optional fields; the application is attached to their account.

Response 201 `{data: {status: "pending", ...}}`: the partner can sign in but partner prices and the partner panel apply only after an admin approves the account (status `active`). Check `app/Http/Requests/Api/PartnerApplyRequest.php` for the authoritative rules.

### Images

Seeded image paths are `/images/placeholders/*.jpg`, relative to the **API origin**; the files exist in `backend/public/images/placeholders/` (generated by `php artisan media:placeholders`, run automatically by `db:seed`). Real uploads will be absolute URLs or `/storage/...`.

## 6. Admin panel (`/admin`, session auth, role middleware)

Screens follow Figma **Admin Desktop** / **Admin Mobile**: Login, Dashboard, Bookings (Yajra server-side list + detail), Trips and Nile trips (list + editor tabs Basics, Pricing [pricing mode, max persons, unit options, partner price], Price list [season editor with overlap validation], Availability, Timeline, Add-ons, Policies, SEO), Packages, Add-ons, Destinations, Buildings (Taxes included, Google Place ID), Rooms (Price list), Receipts review, Partners + Commission matrix, Google reviews (sync now, hide), Currencies, Settings.

Shared UI: `x-admin.layout` (sidebar 264px collapsible, top bar), `x-admin.form-section`, `x-admin.input`, `x-admin.select2`, `x-admin.switch`, `x-admin.datatable`. Colours/typography from Figma tokens (primary-700 #1B1F5E-ish navy, cta-600 orange, sand-50, ink scale; Fraunces headings, Inter body).

## 7. Frontend routes (`frontend/app/[locale]/...`)

`/` home · `/trips` · `/trips/[slug]` · `/nile` · `/nile/[slug]` · `/packages` · `/packages/[slug]` · `/accommodation` (rooms) · `/rooms/[id]` · `/buildings/[slug]` · `/transfers` · `/destinations/[slug]` · `/checkout` · `/booking/[reference]` · `/account/*` · `/partner/*` · `/sign-in` · static pages.

Key components (names match Figma): Header, Footer, HomeHero + HeroSearchCard (no date on Trips/Nile/Packages), TrustChips (2x2 glass grid on mobile), TripCard, RoomCard (Tax included badge), BookingWidget (per-person with max persons, per-unit with UnitOptionCard + QuantityStepper, travel date with season note), SeasonPriceTable, GoogleReviewSummary + ReviewCard, TransferMiniForm (image after the form), FilterBottomSheet with FilterSwitchRow, PriceSummary.

## 8. Delivery phases (this repository)

1. Foundations: enums, migrations, models, repositories, services, seeders with realistic demo data (Sharm, Luxor, Hurghada, Cairo; buggy trip per-unit, snorkelling per-person, Nile cruise, package, Hotel Nile View rooms, seasons).
2. Pricing engine + API v1 + feature tests (season selection, overlaps, per-unit/per-person validation, partner pricing, tax flag).
3. Admin panel: layout, auth, dashboard, trips CRUD with pricing + season editor, buildings/rooms, bookings DataTable, Google reviews, partners commission.
4. Next.js site: layout, home, listings, trip detail + booking widget + quote, accommodation, checkout skeleton.
5. QA: Pest suite, `npm run build`, lint, browser walkthrough desktop + mobile.

## 9. Assumptions (from CR answers defaulted by PM, change if needed)

- CR-5: date removed from search/filters only; booking widget keeps one travel date for season + day rules.
- CR-1: two pricing modes per trip (per person with max persons; per unit with unit options).
- CR-4: Google reviews replace on-site reviews entirely.
- Admin desktop frames are 1536 px wide (the 1200 px DataTable needs it with the expanded sidebar).

## 10. Design tokens (exported from Figma variables, use as the Tailwind theme in both apps)

| Token | Value | Token | Value |
| --- | --- | --- | --- |
| primary-700 | #101a5c | ink-900 | #0b1038 |
| primary-500 | #3a49b0 | ink-700 | #3a3f66 |
| primary-100 | #e6e8f6 | ink-500 | #6a6f8f |
| cta-600 | #c0550a | ink-300 | #cbcee0 |
| cta-700 | #a24508 | ink-100 | #f2f3f9 |
| gold-500 / brand-500 | #ee8431 | sand-50 | #fdf7f0 |
| brand-100 | #fdebdd | brand-700 | #b2540f |
| brand-navy-900 | #070a39 | white | #ffffff |
| partner-600 | #4b45b8 | partner-100 | #ecebfa |
| success | #1f8f5f | warning | #c98a00 |
| error | #c0392b | info | #2c74b3 |

Radius: control 10, image 12, card 16, panel 24, pill 999. Spacing scale 4/8/12/16/24/32/48/64/96. Container 1200 px desktop, 12 columns, 24 gutter; mobile margin 16, 4 columns. Tap target 44 px. Section padding 96 desktop / 48 mobile.
Typography: **Fraunces SemiBold** for display and H1-H3 (56/40/28 desktop, 36/28/22 mobile); **Inter** for everything else (H4 22/18 semibold, body 16, small 14, caption 12 medium, button 16 semibold, price large 32/28 bold).
Primary buttons use cta-600 (orange) with white text; secondary uses primary-700 navy.
