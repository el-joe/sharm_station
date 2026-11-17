# Claude + Figma MCP Prompt v2.1 - Build the Travel Website UI/UX in Figma

Version 2.1 · Nov 13, 2026 (supersedes v2.0 · Nov 2, 2026)

**What changed in v2.1:** the design file already exists, so the work is now **change requests CR-1 to CR-8** on the existing file (Section 2A) and the **Admin panel layout** (Section 2B), designed before the Laravel admin is coded. Source of truth for business rules is **SRS v2.2**.

## 1. When to use this file

Use this file if Figma AI does not produce a design that meets the requirements. Instead, run the prompt in Section 2 inside Claude with the **Figma connector (MCP)** enabled. Claude then builds the design system, components and every page directly in your Figma file, checks its own work with screenshots, and fixes what is wrong.

### 1.1 Setup checklist

1. Enable the **Figma** connector in Claude and sign in to your Figma account (Settings, Connectors).
2. Make sure you have edit rights in a Figma team or drafts folder; Claude can create a new design file for you.
3. Start a new Claude chat and attach these files: **Figma AI Design Brief v2.0** (the design source of truth) and **SRS v2.2 (English)** (the business rules).
4. Paste the main prompt from Section 2.
5. Stay in the chat: Claude works in phases and reports after each. Reply "continue" or send corrections. If the chat stops, use the resume prompt in Section 3.

### 1.2 What you get

| Output | Detail |
| --- | --- |
| A Figma Design file | Pages: Cover, Design System, Components, Customer Site Desktop, Customer Site Mobile, Partner Desktop, Partner Mobile, Admin Desktop, Admin Mobile, Prototype Flows |
| Variables and styles | Colour, typography, spacing and radius variables from the brief, named to map to a Tailwind theme |
| Components | Reusable components with variants and states, named in PascalCase for React |
| Screens | Every page in 1440 and 390 widths with the required states |
| A hand-off note | What was built, what was skipped, the link to the file |

Building the whole product is a long job (five services, partner portal and admin). Claude works in phases and verifies each one, so expect several rounds in the chat.

## 2. Main prompt (paste into Claude)

```text
ROLE
You are a senior product designer and Figma power-user. Using the Figma connector (MCP), build the complete UI/UX design of a travel booking platform directly in Figma, for Desktop (1440 px) and Mobile (390 px): the customer website, a Partner portal and an Admin dashboard.

SOURCES OF TRUTH (attached)
1. "Figma AI Design Brief v2.0": design direction, tokens, components, every page with desktop and mobile layout, states, sample content. Follow it exactly.
2. "SRS v2.2 (English)": business rules (including change requests CR-1 to CR-9). Use it to resolve any question about behaviour. Key rules: five services (trips, Nile trips, packages, transfers, accommodation); private vs group booking types on trips, Nile trips and packages (private any day, group only on listed days); add-ons on trips, Nile trips and packages; packages include transfers at no extra charge; accommodation is date-scheduled and shows ROOMS directly with their building's data; strict no-shared-rooms allocation; children policy with age discounts; deposit and balance; online and manual payments with proof upload; transfers priced by distance from predefined hubs; default currency EUR (also USD, GBP, EGP); Italian and English primary, RTL-ready; partner prices (commission rule or explicit partner price) shown only to logged-in approved partners; trips priced per person (with max persons) or per unit (Single / Double options); seasonal price lists on trips, Nile trips, packages and rooms; no date in trip search; tax-included accommodation prices; Google reviews only; no cabin deck on Nile trips.
If the two disagree, the Brief wins for visuals and the SRS wins for business rules. If something is ambiguous, make the most reasonable choice, note it in the hand-off, and keep going; ask me only for decisions that would force a large redo.

HOW TO WORK WITH FIGMA (mandatory)
- Before the first call of each Figma tool family, load and follow the matching skill instructions (file creation, writing to the canvas with use_figma, design-system generation, page generation from a description). Do not call write tools without loading their instructions first.
- Start by checking my Figma identity and available teams or drafts, then create a NEW design file named "Egypt Travel - Platform UI/UX". Never edit any existing file of mine unless I give you its link.
- Search the file and my libraries for an existing design system first; if none, create one from the brief.
- Work in small, verifiable steps: one component or one frame per call, then take a screenshot and compare it with the brief before moving on. Fix problems immediately (overlaps, cut-off text, wrong spacing or colours, missing states). Never assume a write worked without checking.
- Use Figma variables for colours, spacing, radius and text styles; bind fills, strokes and text to them. No hard-coded hex values on frames. Name tokens so they map to a Tailwind theme (primary-700, cta-600, partner-600).
- Use auto layout everywhere, with constraints for responsive resizing. Build reusable components with variant properties and states exactly as listed in the brief, named in PascalCase to match React components (TripCard, RoomCard, BuildingCard, PriceSummary, RoomAllocationBoard, BookingTypeToggle, AddOnCard, DualPriceField, CommissionMatrix). Use instances, never copies.
- Name layers Page/Section/Component. Frames: 1440 x auto (desktop) and 390 x auto (mobile), side by side per page.
- Use the sample content from the brief (EUR prices, no lorem ipsum). Use image placeholders with realistic crops and an alt-text note; do not download copyrighted photos or use photos of real people.
- Do not delete or overwrite anything outside the new file. Report errors honestly.

PHASES (stop after each phase, show screenshots and a short status, then continue unless I ask for changes)
Phase 0: Setup. Create the file and pages: Cover, Design System, Components, Customer Site Desktop, Customer Site Mobile, Partner Desktop, Partner Mobile, Admin Desktop, Admin Mobile, Prototype Flows.
Phase 1: Design system. Variables (colours incl. partner indigo, spacing, radius), text styles, effects, grids, a style-guide frame. EUR as the default currency in all price examples.
Phase 2: Components. The full library from brief Section 3 with variants and states (including BookingTypeToggle, RoomCard, BuildingCard, AvailabilityCalendar, AddOnCard, IncludedTransferBadge, RoomAllocationBoard with all validation states, PaymentMethodOption, FileUpload states, StatusChip, DualPriceField, CommissionMatrix, DataTable, AdminSidebar).
Phase 3: Global layout. Header with Accommodation and Become a partner, mega menus, mobile drawer, partner-mode bar, footer, newsletter band, cookie banner.
Phase 4: Home, Trips listing, Trip page (single-day and multi-day, Private/Group toggle with group-day restriction, add-ons, booking widget variants, mobile sticky bar and bottom sheet, partner-price variant).
Phase 5: Nile trips, Package page (Transfers included badge and included-transfer timeline items), Destination page, Transfer request page (all states).
Phase 6: Accommodation: rooms listing with building data (list and map), Room page with BuildingCard and AvailabilityCalendar, Building page, accommodation booking flow with Best combinations and RoomAllocationBoard (no-shared-rooms validation states), confirmation variants including hold expired.
Phase 7: Trip booking flow (Steps 1 to 4 with payment, manual payment and proof upload states, confirmation variants), My bookings, Booking detail, Profile, Favourites.
Phase 8: Partner portal: landing, registration, pending/approved/rejected, partner-mode storefront variants, dashboard, bookings, booking detail with retail/partner price and commission, statements, price list, profile.
Phase 9: Utility pages: sign in/up, password reset, About, Contact, FAQ, Blog, Legal, 404, Maintenance, empty and error states.
Phase 10: Admin: all screens from brief Section 11 (desktop; mobile for the listed key screens), including Buildings, Rooms, room occupancy calendar, Partners with CommissionMatrix, Trip editor with Private/Group availability and DualPriceField, Package builder with Included transfers, Add-ons library.
Phase 11: Prototype. On the Prototype Flows page, link the flows in brief Section 12.1 with sensible transitions (smart animate for sheets and accordions).
Phase 12: QA. Walk the checklist in brief Section 12.4, fix every gap, then give me the hand-off.

ITALIAN / ENGLISH / CURRENCY
Design in English as the base. For the Home hero, TripCard, RoomCard, price summary, booking CTA and payment step also create an Italian variant using the Italian labels in the brief, and show prices in EUR, USD, GBP and EGP through a variant property.

HAND-OFF (at the end)
Give me: the Figma file link; a table of pages and frames created (desktop and mobile); the list of components and variants; assumptions or deviations from the brief; anything skipped and why; and 5 suggested improvements.

Start now with Phase 0.
```


## 2A. Change-request prompt v2.1 (run on the existing file)

File: https://www.figma.com/design/WGqsCzbjhWYrsLXqFranBq/Egypt-Travel---Platform-UI-UX (must be owned by a Professional team; MCP calls count against the owning team's plan).

```text
Open the existing file "Egypt Travel - Platform UI/UX". Do NOT recreate it. Apply these change requests from SRS v2.2, editing the main components first so every instance updates, then fixing the screens that use detached copies. Screenshot every changed component and screen, desktop and mobile.

CR-1 Trip pricing modes
- New component UnitOptionCard (variants: state=default|selected|disabled, language=EN|IT): option name (Single buggy / Double buggy), seats icon + "1 rider" / "2 riders", price per unit, quantity stepper.
- BookingWidget: add variants pricing=per-unit (unit selector list replaces the travellers price line; summary lines "1 x Single buggy EUR 35", "1 x Double buggy EUR 50", seats check "3 riders in 3 seats") and pricing=per-person with a "Max 12 persons per booking" helper under Travellers, plus a validation state "Maximum 12 persons for this trip".
- TripCard: price line "From EUR 35 per buggy" for per-unit trips; "Max 12 persons" chip for per-person trips.

CR-2 Tax included
- New Badge variant type=tax-included ("Tax included" / "Tasse incluse", check-circle icon, success colours).
- Add it to RoomCard (next to the price), Room detail price block, Building detail rooms list, accommodation booking summary.

CR-3 Home transfers section
- Home/Transfers promo and the Transfers page (desktop and mobile): the airport image comes FIRST, then the "Book an airport transfer" form card (revised 13 Nov 2026).

CR-4 Google reviews
- Replace ReviewSummary with GoogleReviewSummary: Google "G" logo, Google rating 4.8, "Based on 1,284 Google reviews", "Write a review on Google" button, small "Reviews from Google" attribution. Remove the 5-to-1 star breakdown bars and "verified bookings" copy.
- ReviewCard: add Google source mark, author avatar (initials placeholder), relative time ("2 weeks ago"), "Read more" for long text. Update Home, Trip, Nile, Package, Building and Destination review blocks.

CR-5 No date in trip search
- Home/Hero/Search · Trips, Nile, Packages (desktop and mobile, EN and IT): remove the Date field; layout becomes Destination + Travellers + Search. Remove the date filter from trips listing filters. The BookingWidget keeps one "Travel date" field (it picks the season price).

CR-6 Mobile Free cancellation filter
- FilterBottomSheet / mobile filter sheet: replace the checkbox with a FilterSwitchRow component (bordered card, leading shield-check icon, label "Free cancellation", helper "Full refund up to 24 h before", 44 px Toggle on the right; states off|on|disabled).

CR-7 Seasonal price list
- New component SeasonPriceTable (desktop and mobile): columns Season, From, To, Price (per person / per unit / per night), current season highlighted with a "Now" chip.
- Add a "Prices and seasons" block to Trip single-day, Trip multi-day, Nile trip, Package and Room detail pages; BookingWidget price line notes "High season price (20 Dec - 6 Jan)".

CR-8 Nile trips: remove cabin deck
- Remove any cabin deck selector, "Main deck / Upper deck" labels and deck lines from the Nile trip page, Nile booking sheet, cabin options and VesselBlock imagery captions. Keep cabin type + star rating only.

Finish with a hand-off table: component or screen, change, node IDs, screenshot checked yes/no.
```

## 2B. Admin panel layout prompt (before coding the Laravel admin)

```text
In the same file create pages "Admin Desktop" (1440) and "Admin Mobile" (390). The admin is a Laravel Blade + Tailwind + vanilla JS app with Yajra DataTables and Select2, so design patterns that map to them: server-side tables with search, column filters, page-size select and pagination; Select2-style searchable single and multi selects; modal and slide-over forms; tabs for translations (EN / IT).
Reuse existing components: AdminSidebar, AdminNavItem, AdminTopBar, DataTable, TableHeaderCell, TableRow, RowActionsMenu, KpiCard, ChartCards, NeedsAttentionList, StatusChip, DualPriceField, CommissionMatrix, TranslationTabs, TimelineBuilderItem, ProofReviewCard, RoomOccupancyCell, AgeBandDiscountRow, FileUpload, Toggle, SelectMenu, EmptyState.
New components: AdminShell (sidebar 264 px collapsible to 72 px + top bar + content, breadcrumb, page title, primary action), FilterBar (search, Select2 filters, date range, reset), FormSection card, SeasonPriceEditor (repeatable rows: name, from, to, price fields, partner price, recurring, remove; overlap error state), UnitOptionsEditor (name, seats, price, partner price, max qty, drag handle), GoogleReviewsPanel (Place ID, Sync now, last sync, review list with hide toggle).
Screens (desktop; mobile for Login, Dashboard, Bookings list, Booking detail, Receipts review):
1 Login; 2 Dashboard (KPIs, charts, needs attention); 3 Trips list; 4 Trip editor tabs: Basics, Media, Pricing (pricing mode per person / per unit, max persons, private tiers, group bands, UnitOptionsEditor), Price list (SeasonPriceEditor), Availability, Timeline, Add-ons, Rooms, Policies, SEO, Translations; 5 Nile trips list + editor (no cabin deck); 6 Packages list + builder (stages, included transfers, Price list); 7 Add-ons library; 8 Destinations; 9 Buildings list + editor (Taxes included toggle, Google Place ID); 10 Rooms list + editor (Price list) + availability calendar; 11 Occupancy calendar; 12 Bookings list + detail (payments, proofs, travellers, units, rooms, activity); 13 Receipts review; 14 Transfers: requests, hubs, vehicles, fixed routes, pricing simulator; 15 Partners: applications, detail, CommissionMatrix; 16 Customers; 17 Payments: gateways, manual methods, transactions; 18 Currencies; 19 Coupons; 20 Google reviews; 21 Content: pages, blog, FAQ; 22 Users and roles; 23 Settings; 24 Reports; 25 Activity log.
States: table loading, empty, error; form validation errors; delete confirmation modal; success toast.
```

**Tip:** if Claude stops mid-way, start a new message with "Continue from Phase N" using the resume prompt below and the Figma file link.

## 3. Resume prompt, correction prompts and troubleshooting

### 3.1 Resume prompt (new chat or after a stop)

```text
Continue building the travel platform UI/UX in Figma from Phase [N]. The file is here: [paste Figma file link]. First open the file, list the pages and frames that already exist, take screenshots of the last completed phase to confirm its state, and fix anything broken. Then continue with the remaining phases exactly as in the attached Figma AI Design Brief v2.0 and SRS v2.2, with the same rules: variables for all styles, auto layout, reusable PascalCase components with variants, one frame per step with a screenshot check, EUR as the default currency, no edits outside this file. Report status after each phase.
```

### 3.2 Correction prompts (copy the one you need)

| Problem | Prompt |
| --- | --- |
| Design looks generic | Redo the \[page\] with the visual direction from brief Section 2.1: photography-led, warm Nile Teal and Terracotta palette, Fraunces headings, larger imagery, more white space, clearer price hierarchy. Keep the structure. |
| Mobile is a shrunken desktop | Redesign the Mobile frame of \[page\] as a thumb-friendly layout: single column, sticky bottom CTA bar, bottom sheets for filters and booking, horizontal carousels, 44 px tap targets. |
| Missing states | Add the missing states for \[component or page\] listed in the brief: hover, pressed, disabled, loading, empty, error, success, as variants of the same component. |
| Spacing and alignment issues | Audit \[frame\]: convert to auto layout, snap spacing to the 4-pt scale, fix overlaps and cut-off text, align to the 12-column or 4-column grid, then screenshot again. |
| Hard-coded colours | Replace every hard-coded fill, stroke and text colour in \[frame or file\] with the matching variables and list what you changed. |
| Room cards lack building data | Redesign the RoomCard so the building strip (name, star rating, location) is clear and consistent, and the room facts (type, capacity, bed, amenities, price per night, total) stay readable. |
| Room allocation unclear | Redesign the RoomAllocationBoard: room cards with capacity badge, draggable traveller chips (adult or child with age), Auto-assign, and every validation message (every guest in a room, over capacity, child without adult, rooms cannot fit guests - add a room, every room needs a guest). |
| Private vs Group unclear | Redesign the BookingTypeToggle and date picker: Private = any open day with a party-size price tier; Group = only listed departure days highlighted, other days disabled with a tooltip. |
| Partner mode unclear | Make partner mode obvious: indigo bar under the header, Partner price pill on every price, optional struck-through retail price, commission note in the price summary, and a voucher-without-prices toggle. |
| Manual payment unclear | Redesign the payment Step 3 manual panel: account details with copy buttons, amount in the selected currency, booking reference, deadline notice, upload dropzone with all five states and the fields from the brief. |
| Admin looks cluttered | Simplify the Admin \[screen\]: stronger hierarchy, fewer borders, consistent table density, sticky action bar, status chips with colour and icon. |
| Add a language | Add an Arabic RTL variant of \[page\]: mirror the layout, right-align text, flip directional icons, keep numbers and currency readable. |

### 3.3 Troubleshooting

| Symptom | What to do |
| --- | --- |
| Claude says it cannot access Figma | Check that the Figma connector is enabled for this chat and that you are signed in; reconnect it in Settings, Connectors, and send the prompt again. |
| File not found or no permission | Give Claude a link to a file in a team where you have edit rights, or ask it to create a new file in your drafts. |
| Output is slow or stops | Normal for large jobs. Send the resume prompt with the phase number. Ask for one page per message if needed. |
| A frame comes out broken | Reply with the frame name and "fix this, check with a screenshot". |
| You want a smaller first round | Say "Do only Phases 0 to 4 now" to get the design system, components, global layout, Home, Listing and Trip page first. |
| You want to change colours later | Ask Claude to "update the colour variables" so everything updates through the bound variables. |
