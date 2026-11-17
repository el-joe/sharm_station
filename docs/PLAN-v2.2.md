# Plan v2.2: Partner panel, commissions, nationality pricing, Claude MCP

Owner: PM / system analyst. Date: 17 Nov 2026. Source of truth after this plan: **SRS v2.2** (CR-10 to CR-14).

## 1. Decisions (confirmed with the client)

| # | Topic | Decision |
| --- | --- | --- |
| D1 | Nationality pricing | Prices are set per **nationality group** (e.g. Egyptian, Arab countries, Europe, Rest of world). Admin assigns countries to groups; one group is the **default** for unassigned countries. |
| D2 | Price dimensions | Every trip (single-day, multi-day, Nile), package, transfer and room is priced by **nationality group × season** (season = from date, to date). Outside every season the product's base price for that group applies. |
| D3 | Partner commission | **Percentages only.** Global commission settings per type in the admin: Single-day trips, Multi-day trips, Nile trips, Packages, Transfers. **No accommodation commission.** |
| D4 | Commission override | A partner profile in the admin can set its own percentage per type. Resolution: **partner override (if set) → global setting for the type → 0 %**. |
| D5 | Partner rooms | Partners pay the **same accommodation price as customers** (nationality + season), no discount. |
| D6 | Explicit partner prices | **Removed.** Partner price = customer price − commission %. All `partner_price` fields/columns are dropped. |
| D7 | Partner bookings | The partner enters the **end client's nationality**; the price uses that nationality's group + season, then the partner commission applies. |
| D8 | Nationality gate | First visit: a **mandatory modal** (cannot be dismissed, page not scrollable) asks for nationality; the detected country is preselected and editable. Stored in a cookie + the user's account; changeable from the header at any time. |
| D10 | Booking channel | **The customer website stays as it is, with no partner operations** (no partner prices, partner bar or partner checkout on public pages). **Partners book only inside the partner panel** through its own booking flow (catalogue at partner prices → configure → client & payment → confirmation). Signed-in partners browsing the public site see customer prices. |
| D9 | Claude MCP | An MCP server in Laravel (laravel/mcp) that Claude connects to with a token. It **reads** trips, transfers, packages and accommodations, **creates accommodation suggestions**, and **creates SEO suggestions** for any product. Nothing is published directly: admins edit, then approve or reject each suggestion. |

## 2. Data model changes (Laravel)

| Table | Change |
| --- | --- |
| `countries` (new) | `code` (ISO-3166 alpha-2, PK), `name` json {en,it}, `nationality_group_id` FK nullable, `sort`. Seeded with all ISO countries (names from PHP intl). |
| `nationality_groups` (new) | `id`, `code`, `name` json, `is_default` (exactly one), `sort`, `is_active`. Seed: Egyptian (EG), Arab countries, Europe, Rest of world (default). |
| `product_prices` (new) | Base price per group: `priceable` morph (trip, package, room, transfer_fixed_route, transfer_hub), `nationality_group_id`, `adult_price`, `child_price`, `tier_prices` json, `unit_prices` json, `price_per_night`, `price` (transfers). Unique (priceable, group). |
| `season_prices` | **Add** `nationality_group_id` (nullable = all groups). Overlap check becomes per (priceable, group). **Drop** `partner_price`. |
| `commission_settings` (new) | `category` (enum, PK), `percent` decimal(5,2) 0–100. |
| `partner_category_commissions` | Keep as **overrides**; `category` uses the new enum; `percent` 0–100. |
| `partners` | **Drop** `default_commission`. |
| trips, packages, addons, rooms, unit options, tiers, fixed routes | **Drop** every `partner_price` column (D6). |
| `bookings` | **Add** `nationality_code`, `nationality_group_id`, `commission_percent`. Keep retail/partner/commission totals. |
| `users` | **Add** `nationality_code`. |
| `ai_suggestions` (new) | `type` (accommodation_create, seo_update), `subject` morph nullable, `payload` json, `original` json, `status` (pending, approved, rejected), `source` (mcp), `created_by_token_id`, `reviewed_by`, `reviewed_at`, `review_note`. |
| `personal_access_tokens` | Reused for MCP tokens with ability `mcp`. |

New enums: `CommissionCategory` = SingleDayTrips, MultiDayTrips, NileTrips, Packages, Transfers. `SuggestionType`, `SuggestionStatus`.

## 3. Pricing resolution (one place: `PriceResolver` service)

```
group  = NationalityService::groupFor(countryCode)          // country → group, else default group
retail = season price for (product, group, date)            // dated season for this group
       ?? season price for (product, null group, date)      // season valid for all groups
       ?? base price for (product, group)                   // product_prices
       ?? product base price                                // legacy column fallback
if booker is an active partner and category != accommodation:
       percent = partner override(category) ?? commission_settings(category) ?? 0
       partner = round(retail × (1 − percent/100), 2) per line
```

Every price endpoint takes `?nationality=XX` (ISO-2). Missing or unknown → default group. Bookings require `nationality_code`.

## 4. API additions

- `GET /api/v1/nationalities` → countries `{code, name, group}` sorted by name, plus `detected` (from `CF-IPCountry` / `X-Country-Code` headers when present).
- `?nationality=` on `/trips`, `/trips/{slug}`, `/packages*`, `/rooms*`, `/buildings/*`, quotes, `/transfers/quote`, `/home`, `/search/suggest` (no prices there).
- `PATCH /me/profile` accepts `nationality_code`.
- `POST /bookings` requires `nationality_code` (partner: the end client's).
- Partner endpoints return commission % per category from the resolver; `/partner/price-list` shows retail and partner price for a chosen nationality.
- **Partner booking API (D10):** `GET /partner/catalogue?service=&nationality=&destination=&q=` (products with customer price, partner price, commission %), `POST /partner/quotes` (product, client nationality, date, travellers/units, add-ons → quote with customer, commission and partner lines), `POST /partner/bookings` (creates the booking with the partner as booker). Public endpoints (`/trips`, quotes, `/bookings`) **always use customer prices**, even with a partner token.

## 5. Admin panel additions (Blade + Tailwind + vanilla JS + Yajra + Select2; never Livewire)

1. **Nationality groups**: CRUD, default group switch, assign countries (Select2 multi with search), countries without a group listed.
2. **Price matrix** tab on trips (all types), packages, rooms, transfer fixed routes: base price per group + seasons per group (season editor gains a "Nationality group" column, "All groups" option, overlap check per group). Remove partner-price inputs everywhere.
3. **Commission settings** (Settings → Commissions): five percentage inputs.
4. **Partner profile → Commissions**: per-type override (empty = use global), showing the effective % next to each.
5. **Suggestions inbox** (AI suggestions): Yajra list (type, subject, source, created, status), detail page with editable form (accommodation: building + rooms fields; SEO: current vs suggested side by side), Approve (applies through services, in a transaction) / Reject (reason).
6. **MCP access**: create/revoke MCP tokens (shown once), connection instructions for Claude.

## 6. MCP server (`routes/ai.php`, laravel/mcp)

Server name `sharm-station`, HTTP transport at `/mcp`, Sanctum bearer token with ability `mcp`, rate limited.

| Tool | Kind | Purpose |
| --- | --- | --- |
| `list_trips`, `get_trip` | read | Trips of every type with content, seasons, prices per group, SEO |
| `list_packages`, `get_package` | read | Packages with stages and SEO |
| `list_transfers` | read | Hubs, fixed routes, vehicles, prices |
| `list_accommodations`, `get_accommodation` | read | Buildings with rooms, prices, SEO |
| `create_accommodation_suggestion` | write (suggestion) | Building + rooms payload, validated like the admin form, saved as `pending` |
| `suggest_seo` | write (suggestion) | `subject_type` (trip, package, transfer_route, building, room), `subject_id`, meta title/description/keywords per locale, slug, OG text; saved as `pending` with the current values in `original` |

Read tools never expose partner data or customer data. Write tools never publish.

## 7. Website (Next.js)

1. **NationalityGate**: mandatory modal on first visit (focus-trapped, no close button, body scroll locked), searchable country Combobox with flags, detected country preselected (API `detected` → browser `navigator.language` region → none), Continue button. Writes cookie `ssv_nationality` (1 year) and, when signed in, the profile.
2. **Header chip** "🇪🇬 Egyptian" opens the same picker (non-mandatory mode); changing it refreshes prices.
3. All price fetches send `nationality`; server components read the cookie (prices are dynamic per group).
4. **Partner panel** (`/partner/*`, noindex) completed against the real endpoints: dashboard, **New booking** (catalogue at partner prices per client nationality → configure → client & payment → confirmation), bookings (+ detail, vouchers with/without prices), statements, price list (nationality selector), profile (read-only commissions per type).
6. **Public website unchanged for partners (D10):** remove partner mode from the storefront: no PartnerModeBar, no partner prices or badges, no partner checkout on public pages; public endpoints always return customer prices even for a partner token. Signed-in partners get a "Partner panel" link in the account menu.
5. **Customer profile**: nationality field.

## 8. Design (Figma, file `WGqsCzbjhWYrsLXqFranBq`)

New/updated frames, desktop 1440 + mobile 390 unless noted:
- NationalityGate modal (first visit), header nationality chip + picker (changeable mode).
- Partner panel: Dashboard, **New booking** (catalogue, configure, client & payment, confirmation), Bookings list, Booking detail, Statements, Price list (with nationality selector), Company profile (commissions read-only). No partner screens on the public website (D10).
- Admin (1536): Nationality groups, Price matrix tab (base per group + seasons per group), Commission settings, Partner profile → Commissions override, Suggestions inbox + review (accommodation and SEO variants), MCP access tokens.

## 9. Execution order (one teammate at a time)

| Step | Teammate | Prompt section |
| --- | --- | --- |
| 1 | PM | SRS v2.2 + this plan (done here) |
| 2 | Senior UI/UX designer | §10.1 |
| 3 | Senior backend developer | §10.2 (data model, pricing, commissions, nationality, API, admin screens) |
| 4 | Software engineer / team lead | §10.3 (MCP server + suggestions inbox + tokens) and code review of step 3 |
| 5 | Senior frontend developer | §10.4 |
| 6 | Senior QA / QC | §10.5 (Playwright: website, admin, partner panel, customer profile) |
| 7 | PM | Final verification, Canva partner one-pager, commit |

## 10. Teammate prompts

The full prompts are kept in the session; each one restates: read this plan + SRS v2.2, the existing code conventions (Repository + Service + DTO + Enum, Blade admin without Livewire, Next.js 16 docs in `node_modules/next/dist/docs`), work only in its folder, run tests/lint/build, no commits, honest report.

### 10.1 Designer
Build §8 frames in the existing Figma file reusing its components and variables (AdminShell pattern, DataTable, Combobox/SelectMenu style, KpiCard, StatusChip, Toggle, PartnerModeBar, PartnerPriceBadge). Screenshot-verify each frame.

### 10.2 Backend
Implement §2–§5 with migrations (data-preserving: migrate existing partner commission rows to the new categories, copy existing season prices to "all groups"), PriceResolver, NationalityService, CommissionResolver, API changes, admin screens 1–4, seeders (groups, countries, sample group prices: Egyptian cheaper), Pest tests for every resolution branch.

### 10.3 Team lead
Implement §6 + admin screens 5–6, Pest tests for every tool (auth, read scopes, validation, suggestion approval applies changes, rejection does not), review step 3 code for layering and N+1.

### 10.4 Frontend
Implement §7 against the real API, keep EN/IT, accessibility, lint/tsc/build clean.

### 10.5 QA
Playwright (`frontend/e2e`): nationality gate (first visit blocks, detected preselected, choice persists, change from header updates prices), price differences between groups, trip/room booking with nationality, customer sign-in + profile + bookings, partner sign-in + partner prices = retail − commission (override vs global) + client nationality + panel pages, admin login + commission settings + partner override + nationality groups + price matrix + suggestions approve/reject, MCP tools via HTTP. Desktop and 390 px mobile. Report with screenshots of failures.
