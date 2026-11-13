# SRS v2.1 - Travel Booking Platform (English)

Version 2.1 · Nov 13, 2026 (supersedes v2.0 · Nov 2, 2026)

## 1. Introduction

This document defines every requirement of an online travel platform with **five services**: Trips, Nile trips, Packages, Transfers and Accommodation. It also defines a **Partner portal** where approved partners (such as agencies) book the same services at partner prices. It is the agreed reference between the client and the developer for scope, deliverables and acceptance criteria.

**What changed in v2.0:** a new Accommodation service (buildings and rooms); a Partner panel with partner prices and per-category commissions; the base currency is now the euro; trips, Nile trips and packages have two booking types (private and group); add-ons on trips, Nile trips and packages; accommodation is date-scheduled while trips and packages are not inventory-scheduled; transfers are included in packages; strict no-shared-rooms rule; and the frontend moves from Blade to Next.js.

**What changed in v2.1 (client change requests, Nov 13, 2026):**

| # | Change | Sections |
| --- | --- | --- |
| CR-1 | Trips support two **pricing modes**: **per unit** (vehicle/seat options such as Single buggy and Double buggy in the same trip) and **per person** (one price per person with a maximum number of persons per booking, e.g. snorkelling) | 3.1, 3.4, 3.6, 18 |
| CR-2 | Accommodation shows a **"Tax included"** badge: displayed room prices include taxes and city fees | 6.1, 6.3, 18 |
| CR-3 | Home page transfers section: the **image is placed after the form inputs** (form first, image second, on desktop and mobile) | 13.1 |
| CR-4 | **Reviews come from Google** (Google Business Profile via the Places API); the on-site review system is removed | 3.1, 13.1, 14.1, 15.1, 17.3, 18 |
| CR-5 | **No date in trip search**: the date field is removed from the trips, Nile trips and packages search and filters; the booking widget keeps one travel date, used to pick the season price | 3.4, 3.7, 13.2 |
| CR-6 | The **Free cancellation** filter in the mobile filter sheet is restyled as a professional switch row (icon, label, helper text, toggle) | 13.3 |
| CR-7 | **Seasonal price list**: every trip, Nile trip, package and accommodation room has price periods (season from date, season to date, prices) | 3.8 (new), 6.2, 18 |
| CR-8 | Nile trips: the **cabin deck** option is removed (no deck selection or deck field on cabins) | 4.1 |
| CR-9 | The **admin dashboard is built in Laravel** (Blade, Tailwind CSS, vanilla JavaScript, Yajra DataTables, Select2; **no Livewire**) with a Repository + Service + DTO + Enum architecture; the customer site stays on Next.js | 17 |

### 1.1 Purpose

- Describe the whole system before development starts so that scope is unambiguous.
- Let the client review every feature and approve or change it.
- Serve as the basis for time and cost estimates, the test plan and delivery.

### 1.2 Services in scope

| Service | Description | Scheduling | Optional rooms |
| --- | --- | --- | --- |
| Single-day trip | One destination, one day, hourly timeline, priced per person or per unit (3.4) | Private: any day. Group: specific days | No |
| Multi-day trip | One destination, several days, timeline per day | Private: any day. Group: specific days | Yes |
| Nile trip | One or more days on the Nile, same structure as trips | Private: any day. Group: specific days | Yes (cabins) when multi-day |
| Package | Several trips in several destinations, **transfers included** | Private: any day. Group: specific days | Per stage |
| Transfer | Private vehicle ride priced automatically by distance, paid on the site | Date and time chosen by the customer | No |
| Accommodation | Rooms inside buildings, booked by check-in and check-out dates | **Scheduled**: availability calendar per room | The room is the product |

### 1.3 Intended audience

Project owner, operations manager, development and design teams, and the testing team.

### 1.4 Definitions

| Term | Meaning |
| --- | --- |
| Building | A property (hotel, apartment block, villa) that contains rooms |
| Room | A bookable accommodation unit inside a building, exclusive to one booking for the dates booked |
| Unit (trip room) | A generic room option (type + star rating) added optionally to a multi-day trip, Nile trip or package |
| Booking type | **Private** (a party travels alone, any day) or **Group** (joins a departure on a specific day) |
| Pricing mode | **Per person** (each traveller pays the same price, up to a maximum number of persons) or **Per unit** (the customer books units such as a Single or Double buggy, each with its own price and seats) |
| Unit option | A priced option of a per-unit trip: name, seats per unit, price per unit (for example Single buggy, 1 rider, EUR 35; Double buggy, 2 riders, EUR 50) |
| Season / price period | A date range (season from, season to) with its own prices; outside every season the base price applies |
| Tax included | Displayed accommodation prices already include VAT, tourism tax and city fees |
| Add-on | An optional or required extra attached to a trip, Nile trip or package |
| Retail price | The price shown to normal customers |
| Partner price | The price shown to a logged-in approved partner |
| Commission | A percentage the partner earns, applied as a reduction from the retail price |
| Partner | A registered business account approved by the admin to book at partner prices |
| Deposit / Balance | Part of the total paid at booking / the remainder collected outside the site |
| Base currency | The euro (EUR), the currency in which all prices are entered |
| Children policy | Rules for adults and children per room: ages, limits, pricing and discounts |
| Admin | A user of the dashboard |

## 2. General description

### 2.1 User types

| Role | Core permissions |
| --- | --- |
| Visitor | Browse all services, search and filter, book without an account, request a transfer |
| Registered customer | Everything above, plus a personal account with bookings, payment status, proofs, vouchers |
| **Partner** | Same as a customer, but sees and pays partner prices in every service; has a partner panel with bookings, statements and reports (Section 9) |
| Super Admin | Everything in the dashboard including settings, users, gateways and partners |
| Bookings agent | Manage bookings, transfer requests, receipts and record payments |
| Content editor | Create and edit trips, packages, buildings and rooms without access to prices or payments |

Roles are configured through a granular, customizable permission system (Section 14).

### 2.2 Core assumptions

- The site serves an international market. **The base currency for all price entry is the euro (EUR)**; the site also shows USD, GBP and EGP by conversion.
- Trips, Nile trips and packages are booked **per person** (with a maximum persons per booking) or **per unit** (Single / Double options) and are **not seat-inventory scheduled**: availability depends only on the booking type (private any day; group on specific days).
- Accommodation **is scheduled**: each room has an availability calendar and cannot be double-booked.
- The balance after the deposit is paid outside the site and recorded manually by the admin to close the account.
- Transfers are priced by distance and paid on the site; only running the ride (driver and car) is arranged outside the system.
- The two primary languages are Italian and English; more languages can be added from the dashboard (Section 15).
- The frontend is built with **Next.js** and talks to a Laravel API (Section 17).

### 2.3 General constraints

- No native mobile app; the website and partner panel are fully responsive.
- Flight and visa booking are not included.
- Online payment goes through external gateways, and card data is never stored on the system's servers.

## 3. Trips

A trip belongs to a single destination (for example Sharm El Sheikh or Luxor) and is either single-day or multi-day. Nile trips (Section 4) and packages (Section 4.2) reuse the same rules for booking types, availability, add-ons and per-person pricing described here.

### 3.1 Shared data for every trip

| Group | Fields |
| --- | --- |
| Basics | Title, slug, short and full description, destination, category (cultural, marine, safari, diving...), type (single-day or multi-day), status (published or draft) |
| Media | Main image, gallery, video link, image alt text |
| Duration | Duration, number of days and nights for multi-day trips |
| Booking types | Private enabled, Group enabled (one or both), each with its own prices and availability (3.4) |
| Meeting point | Meeting place or hotel pick-up, map location, pick-up time |
| Highlights | Highlights list, what is included, what is not included |
| Customer info | What to bring, difficulty, minimum age, suitability for children and special needs, guide languages |
| Pricing | Pricing mode (per person or per unit, 3.4), private price tiers by party size, group price per person by age band, unit options, maximum persons per booking, seasonal price periods (3.8), partner prices (Section 8) |
| Add-ons | Optional or required extras (3.5) |
| Terms | Cancellation and refund policy, terms and conditions, FAQ |
| Payment | Deposit percentage, gateways available |
| SEO | Page title, description, keywords, sharing image |
| Reviews | Google reviews of the business (Section 13.4); no on-site review form |

### 3.2 Single-day trip

- A timeline for one day, made of time-ordered items: from time, to time, title, description, place and optional image.
- Example: 08:00 hotel pick-up, 09:30 arrival at the site, 12:00 lunch, 15:00 return.
- No rooms can be added.

### 3.3 Multi-day trip

- One destination, with a timeline for each day (title, description, time-ordered items).
- The admin sets days and nights. The customer sees "Day 1", "Day 2", without calendar dates; real dates are computed from the chosen start date and shown in the confirmation and voucher.
- Optional rooms (type + star rating) can be added by the customer when the admin enables them (Section 7).
- Optional per-day data: meals, overnight place, transport.

### 3.4 Booking types: private and group

Each trip, Nile trip and package offers **Private**, **Group** or both. The customer picks the type with a segmented control in the booking widget.

|  | Private | Group |
| --- | --- | --- |
| Who travels | Only the party that booked | A shared departure with other travellers |
| When it can be booked | **Every day**, subject to admin blackout dates and a minimum lead time (for example 24 hours) | **Only on specific days** set by the admin: weekly recurring days (for example every Monday and Thursday) and/or specific dates |
| Pricing | Price per person by **party-size tiers** (for example 1 person, 2, 3-4, 5-8, 9+) | Price per person by age band (adult, child bands) |
| Capacity | No seat inventory; minimum and **maximum persons per booking** | No seat inventory by default; **maximum persons per booking** |

- Trips and packages are **not inventory-scheduled**: the system does not count seats or block dates when seats run out. The only scheduling is the day rule above (open question 6 asks whether group departures need seat limits).
- The admin can close any date for either type (blackout dates) and set a cut-off time before departure.
- The booking records the booking type, so vouchers and operations lists can separate private tours from group departures.
- **No date in search (CR-5):** the date is not asked in the trips, Nile trips and packages search bar or listing filters. The customer chooses a single **travel date** only inside the booking widget; it is validated against the day rules above and selects the season price (3.8).

#### 3.4.1 Pricing modes (CR-1)

Every trip, Nile trip and package has one pricing mode, chosen by the admin.

| | Per person | Per unit |
| --- | --- | --- |
| Example | Snorkelling, Ras Mohammed boat trip, city tour | Beach buggy, quad bike, jet ski, private boat |
| How it is priced | Each traveller pays the per-person price (private tier or group age band) | The customer picks **unit options** and a quantity of each; each unit has its own price |
| Admin data | Price per person (by tier or age band), **maximum persons per booking**, optional minimum | List of unit options: name (Single buggy, Double buggy), **seats per unit** (1, 2...), price per unit in EUR, partner price, maximum quantity, optional children allowed |
| Validation | Party size must be between the minimum and the **maximum persons** | Total seats of the chosen units must be at least the number of travellers; every unit carries at least one traveller; at least one adult per unit |
| Example total | 4 persons x EUR 25 = EUR 100 | 1 Single buggy (EUR 35) + 1 Double buggy (EUR 50) for 3 riders = EUR 85 |

- Both modes support private and group booking types, add-ons, seasonal prices (3.8) and partner prices (Section 8).
- In the booking widget, a per-unit trip shows a **unit selector** (option cards with seats, price and a quantity stepper) instead of the party-size price line; the summary lists each unit as its own line.

### 3.5 Add-ons (trips, Nile trips and packages)

- An add-on has: name, description, image, price in EUR, **pricing basis** (per person, per booking, or per unit with a quantity), **required or optional**, maximum quantity, which booking types it applies to (private, group, both), a partner price (Section 8), and translations.
- Examples: hot-air balloon, lunch upgrade, private guide, photographer, extra night.
- The customer selects add-ons in the booking widget and again at checkout; they appear as separate lines in the price summary, the deposit calculation and the voucher.
- Add-ons can be defined once and attached to many trips, Nile trips and packages, or created for one product only.

### 3.6 Per-person pricing and booking

- Per-person trips, Nile trips and packages are **priced per person**: booking for five people means quantity 5 (adults and children by price band, or the party-size tier for private bookings), never more than the trip's maximum persons. Per-unit trips are priced by the units chosen (3.4.1).
- Each traveller's details are entered separately (name, adult or child, age, and passport details if the admin requires them).
- The trip total is the sum of each person's price (or each unit's price) for the season of the travel date, plus add-ons, plus optional rooms.

### 3.7 Customer booking flow

1. Choose the product and the booking type (private or group). Search and listings do not ask for a date.
2. In the booking widget, choose the travel date (any open day for private, a listed day for group); the season price for that date is shown.
3. Enter adults, children and children's ages (per-unit trips: also choose the unit options and quantities).
4. (Multi-day) optionally add rooms and allocate travellers (Section 7).
5. Choose add-ons.
6. Enter traveller and contact details.
7. Review total, deposit and balance.
8. Pay the deposit online or by manual transfer with proof upload.
9. Confirmation page and an email with the voucher.

### 3.8 Seasonal price list (CR-7)

Every **trip, Nile trip, package and accommodation room** has a price list made of **price periods (seasons)**.

| Field | Description |
| --- | --- |
| Season name | For example Low season, High season, Christmas and New Year, Easter |
| Season from / Season to | Inclusive date range; may cross the year end (20 Dec to 6 Jan) |
| Prices | The same price structure as the product, for that period: private tiers, group age bands, unit option prices (per-unit trips), adult and child prices for packages, price per night for rooms |
| Partner prices | Optional explicit partner price per season (Section 8, method B) |
| Recurring yearly | Optional flag: the period repeats every year on the same days |
| Status | Active or inactive |

**Rules:**

- The price used is the one of the season that contains the **travel date** (trips, Nile trips, packages) or **each night** of the stay (accommodation: a stay crossing two seasons is priced night by night).
- If no season matches, the product's **base price** applies.
- Seasons of one product cannot overlap; the dashboard blocks an overlapping period with a clear message.
- The product page shows "Prices from" using the lowest price of the active and future seasons, and a **price list table** (season, dates, price) in a "Prices and seasons" block.
- Add-ons keep a single price unless the admin enables seasonal prices on them.
- The admin manages seasons in a **Price list** tab of each product, with copy-to-next-year and bulk-copy-to-other-products actions.

## 4. Nile trips and packages

### 4.1 Nile trips

Nile trips (cruises between Luxor and Aswan, felucca rides, Nile dinners) follow the structure of Section 3: single-day or multi-day, private and group booking types, add-ons, per-person pricing. They are a separate type in the dashboard and a separate section on the website.

- Extra fields: vessel name, itinerary (from... to...), vessel facilities (pool, restaurant, sun terrace, Wi-Fi), meal type.
- Multi-day Nile trips can offer optional **cabins** using the room mechanism of Section 7 (cabin type and star rating). **There is no cabin deck selection (CR-8):** cabins are not split or priced by deck, and the deck is not shown on the Nile trip page, booking widget or voucher.
- Filterable by itinerary.

### 4.2 Packages

A package combines several trips in several destinations under one programme and one price, with the same data as a trip (3.1), booking types (3.4), add-ons (3.5) and per-person pricing (3.6).

**Structure:** a package is made of consecutive **stages**. Each stage has a destination and a number of days, and holds trips arranged across its days.

| Level | Content | Example |
| --- | --- | --- |
| Package | Title, description, total duration, price, highlights, images | Classic Egypt, 8 days |
| Stage | Destination, days and nights, accommodation | Cairo (3 days), then Marsa Alam (3 days) |
| Day | Day number within the stage, the trips assigned to it | Day 2: Pyramids trip |
| Trip | An existing trip linked to the package | Wadi El Gamal trip or Blue Hole snorkelling |

**Rules:**

- A package links to trips that already exist, which are reused without re-entering data.
- The package has an overall timeline; more than one trip can be placed in a day.
- The admin sets either a fixed package price or a price computed from its trips with a manual discount.
- **Transfers are included in the package price, not excluded.** Airport pick-up and drop-off and transfers between stages are part of the package: they are listed under "Included", shown as timeline items marked "Transfer included", never charged separately, and cannot be removed by the customer.
- For each package booking the system automatically creates **included-transfer tasks** (from, to, date, time, vehicle type) in the admin's operations list so the admin can arrange the cars outside the system. These tasks carry no price.
- Optional rooms per stage follow Section 7; the total is the package plus each selected room set.
- Currency, deposit, gateways, children policy and partner pricing work as in the other services.
- Packages are booked **per person**, with private and group types, and are not seat-inventory scheduled.

## 5. Transfers

A private-vehicle ride from one point to another. **Booking, pricing and payment all happen on the website**, while **running the ride** (driver and car) happens outside the system and is followed up by the admin. A standalone transfer is a paid service; transfers inside a package are included (4.2).

### 5.1 Departure points and zones

No arbitrary pickup is accepted. Departures are from **predefined hubs or zones** managed by the admin, which are the basis of the pricing algorithm.

- **Hub:** for example Cairo Airport, Sharm El Sheikh Airport, a port or a city, with coordinates, a service radius and optionally a service polygon.
- **Destinations:** free within the coverage area, or from a list of predefined places, depending on each hub's setting.
- Each hub has its own pricing settings: base fare, price per kilometre or mile, available vehicle types, on/off.
- **Fixed routes:** a fixed price for a popular route (for example Cairo Airport to a given hotel) that overrides the distance calculation.

### 5.2 Distance-based pricing algorithm

The price is computed **on the server only**, in EUR, and recomputed at payment time to verify it.

```latex
P = \max\left(M,\; \left(B + \sum_{i} d_i \cdot R_i + W + S\right) \cdot V\right) + F
```

| Symbol | Meaning | Set by |
| --- | --- | --- |
| P | Final retail price in EUR (then converted to the customer's currency) | System |
| M | Minimum fare | Admin, per hub and vehicle |
| B | Base fare | Admin |
| d\_i | Distance inside tier i, in kilometres or miles | Computed automatically |
| R\_i | Price per distance unit in tier i (descending tiers) | Admin |
| W | Waiting charge after a grace period | Admin |
| S | Time and season surcharges: night, holidays, peak | Admin |
| V | Vehicle-type multiplier | Admin |
| F | Fixed fees: airport, tolls, extra stop, child seat | Admin |

**Steps:**

1. The customer selects the hub, destination, date and time, passengers, luggage and vehicle.
2. The system computes the **actual road distance** and travel time through a maps service (Google Routes, Mapbox or OSRM).
3. It applies tiers, minimum, fees and multipliers and adds the selected extras.
4. A matching fixed route overrides the calculation.
5. An itemized quote is shown and stored for a validity period set by the admin; it stays fixed after booking.
6. Round trips price each leg separately, with an optional return discount.
7. For partners, the partner pricing rule of Section 8 is applied to the retail result.

**Quality controls:** the distance unit (km or mile) is a global admin setting; rounding follows admin rules; each request stores distance, time, multipliers and the breakdown; repeated distances are cached; if the maps service fails a fallback estimate is used and the request is flagged for admin review; the pickup must be inside the service area and the maximum distance per ride is capped; a **pricing simulator** in the dashboard lets the admin test any route.

### 5.3 Payment

- Paid on the website with the same online gateways and offline methods with proof upload (Section 10), in the same currencies (Section 11).
- The admin sets per hub or vehicle either full payment or a percentage deposit.
- The request is not confirmed until payment is confirmed (or the proof is approved).
- A transfer-specific cancellation and refund policy with time bands.

### 5.4 Customer request form

Hub and destination on the map with route, distance and time; date and time; one-way or round trip; vehicle type with capacity and price; passengers and luggage; extras (extra stop, child seat, airport sign); contact details and flight number; itemized price summary, then payment.

### 5.5 Operations (outside the system)

| Status | Meaning |
| --- | --- |
| Awaiting payment | Request created, payment not made |
| Awaiting receipt review | Offline proof uploaded, not yet approved |
| Paid | Payment confirmed, ready for execution |
| Being arranged | The admin is arranging the car and driver |
| Confirmed | Driver and car assigned and the customer informed |
| Completed | Carried out |
| Cancelled / refunded | Cancelled with the refund calculated |

The admin records driver name and phone and car type and plate, shown to the customer. Emails go to the admin for every paid request and to the customer on every status change. The admin can adjust a price exceptionally with a logged reason and send a payment link for the difference.

## 6. Accommodation service

A direct accommodation booking service. The admin creates **buildings** and then adds **rooms** inside them. On the website the customer sees **rooms directly**, each card carrying its building's data (name, location, rating), because people choose a room, not a building. A building page also exists for details and SEO. Accommodation is **scheduled**: every room has an availability calendar.

### 6.1 Buildings (dashboard)

| Group | Fields |
| --- | --- |
| Basics | Name, slug, type (hotel, apartment building, villa, hostel), destination, status |
| Location | Address, map pin, area description, distance to key places |
| Rating | Star rating and guest review average |
| Description | Short and full description, translations |
| Media | Main image, gallery |
| Amenities | Wi-Fi, pool, parking, breakfast, reception 24/7, airport shuttle and others from a managed list |
| Stay rules | Check-in and check-out times, house rules |
| Taxes (CR-2) | **Taxes included** flag (default on): every displayed price already includes VAT, tourism tax and city fees, and the site shows a "Tax included" badge. If the admin turns it off, the tax amount per night is entered and shown as a separate line |
| Policies | Cancellation policy, building-level children policy (default for its rooms), deposit percentage |
| Contact | Phone, email (internal) |
| SEO | Title, description, sharing image |

### 6.2 Rooms (dashboard)

- Each room belongs to one building: name or number, room type (single, double, twin, triple, family, suite), **capacity** (maximum adults, maximum children, maximum total), bed configuration, size, view, amenities, gallery, description and translations.
- **Price:** a base price per night in EUR, with a pricing mode of **per room** or **per person**; the **seasonal price list** of Section 3.8 (season from, season to, price per night); weekend rules; price override on any date; extra-bed and cot prices; minimum and maximum nights; a partner price (Section 8). Prices are tax-inclusive when the building's Taxes included flag is on.
- **Availability calendar per room:** open or closed per date, with bookings shown as blocked ranges; the admin can close dates manually (maintenance, owner use).
- A room is an **exclusive unit**: while booked, it cannot be booked by anyone else for those nights. There are no shared rooms or dormitory beds (Section 7).
- Optional: iCal import and export per room to avoid double-booking with other channels.

### 6.3 Website experience

**Accommodation listing (rooms directly):**

- A grid of **room cards**: room photo, room name and type, capacity icons, bed type, key amenities, **building name, building star rating and location**, price per night and total for the selected dates, badges (**Tax included**, Free cancellation, Few left, Breakfast included).
- Search bar: destination or building, check-in and check-out dates, guests (adults and children with ages).
- Filters: destination, building, room type, price per night, rating, amenities, bed type, free cancellation. Sort by recommended, price, rating, distance. A map view and an optional "Group by building" toggle.
- Only rooms that are available for the whole chosen stay and fit the minimum and maximum nights are shown as bookable; others appear greyed with the reason.

**Room page:**

- Gallery, room title, type, capacity, beds, size, amenities, description, price with the **Tax included** badge, the seasonal price list, and availability calendar with a date-range selector.
- A **Building card**: building name, stars, address with map, key amenities, check-in and check-out times, policies, link to the building page.
- "Other rooms in this building" list.
- Booking widget: dates, guests, live price, children policy note, deposit line, CTA "Reserve".

**Building page:** building details, gallery, amenities, policies, Google reviews, map, and all its rooms with availability for the chosen dates; every price shows the **Tax included** badge.

### 6.4 Accommodation booking flow

1. Choose dates and guests (adults, children with ages).
2. Select one or more rooms **from the same building**. A helper shows the best room combinations for the party (for example for 5 people: double + triple, or double + double + single when available), sorted by price.
3. **Allocate every guest to a room** under the room rules of Section 7 (no shared rooms).
4. Review the price: nights x room price, children discounts, extra beds, fees, total, deposit and balance.
5. Enter guest details, choose a payment method (online or manual with proof upload), and confirm.

### 6.5 Scheduling and holds

- The chosen rooms are **held** for a time set by the admin while the customer pays, then released if payment is not made.
- A confirmed booking blocks the room's dates in the calendar; cancellation reopens them.
- Double booking is prevented by database-level locking inside the booking transaction.

### 6.6 Pricing, payment and cancellation

- Price per night depends on the pricing mode: per room (fixed per night within capacity) or per person (each guest per night, with the children policy and age discounts of Section 7).
- Deposit and balance follow Section 10: a deposit percentage per building or room, or full payment; the balance is recorded manually by the admin.
- Cancellation policy per building with time bands before check-in; the refund is suggested by the system and confirmed by the admin.
- Partners see partner prices (Section 8).

## 7. Room allocation rules and children policy

These rules apply to **Accommodation bookings** (Section 6) and to **optional rooms inside multi-day trips, Nile trips and packages** (7.1).

### 7.1 Optional rooms inside trips, Nile trips and packages

- Available only for multi-day products, optional for the customer, and enabled by the admin per product (per stage for packages).
- The admin defines **unit types** (room, suite, cabin...) and **star ratings** (3, 4, 5...) and a price for each combination per product, with capacity in people (for example a room that sleeps 3, 4 or 5).
- Pricing method per product and unit type: **per room** or **per person**, and per night or for the whole stay.
- These rooms are priced add-ons: because trips and packages are not inventory-scheduled, they have no availability calendar. (Real buildings and rooms with calendars belong to the Accommodation service.)
- Booking price = (trip price x people) + add-ons + the sum of the selected rooms' prices.

### 7.2 Room allocation and the no-shared-rooms rule

The customer enters the number of guests, chooses the **number and type of rooms**, and **distributes all guests across the rooms**. The system proposes an automatic allocation that the customer can adjust.

**Rules enforced by the system:**

1. **No shared rooms.** A booked room belongs entirely to the booking. Guests of different bookings never share a room, and there are no dormitory or shared beds. A guest cannot be "squeezed" into a room beyond its capacity.
2. **Every guest is allocated to exactly one room** of the booking, and the total guests in rooms equals the guests booked.
3. A room never exceeds its capacity: maximum total, maximum adults and maximum children.
4. A room cannot hold only children; at least one adult accompanies any child.
5. Every booked room holds at least one guest (a surplus empty room cannot be booked unless the admin allows it).
6. Rooms come from available stock (accommodation) and rooms of one booking belong to the same building.

**Examples, five guests (three adults and two children):**

| Rooms booked | Allowed? | Why |
| --- | --- | --- |
| Double + triple | Yes | Capacity 5, every guest has a bed in a room of the booking |
| Double + double + single (when all three are available) | Yes | Capacity 5, three exclusive rooms, nobody shares |
| Double + double only | **No** | Capacity 4 is less than 5; the fifth guest cannot share or be squeezed in, so the system asks to add a room |
| Double + a room that is already partly taken by another booking | **No** | Rooms are exclusive, no shared rooms |
| Triple with three adults, and a double with the two children alone | **No** | A room cannot hold only children |

Validation messages: "Every guest must be in a room", "Room 1 exceeds its capacity of 3", "A child cannot stay in a room without an adult", "These rooms cannot fit 5 guests, add a room".

### 7.3 Children policy

Configured per building (default) and per room, as in major booking systems:

| Setting | Description |
| --- | --- |
| Children accepted | Does the room accept children, and from what minimum age? |
| Maximum adults, children and total | Per room |
| Age bands | For example 0-3, 4-7, 8-11, 12 and over (treated as adult); editable |
| Price per band | Free, a fixed amount, or a percentage of the adult price |
| **Discount by child age** | For each band the admin sets the discount type: free (100%), a percentage off the adult price, a fixed amount off in EUR, or a fixed child price. Example: 0-3 free, 4-7 50% off, 8-11 25% off, 12+ adult price |
| Age calculation date | Age is computed on the check-in or trip start date (default) or the end date |
| Max children eligible for discount | For example only the first two children; the rest at the next band or adult price |
| Discount conditions | Linked to sharing an existing bed or an extra bed, to the number of adults in the room, and a cap on the total discount |
| Extra bed and cot | Available or not, price, and the bands that need them |
| Stacking with coupons | Admin option: add to coupon codes, or apply only the higher one |

At booking: the customer enters children's ages; the system checks limits, computes each child's price by band, applies the band's discount to the adult price, adds extra-bed fees, and shows **Children discount** as a separate line (for example "Children discount 4-7 years: -EUR 50"). A child's price never goes below zero, and ages are stored with the booking.

### 7.4 Pricing method

| Mode | Calculation | Children policy and discounts |
| --- | --- | --- |
| **Per room** | A fixed price per room (per night or per stay) for any occupancy within capacity, plus optional charges for an extra bed | Only capacity, children-count and age limits apply; age discounts do not apply because the room is priced in full |
| **Per person** | Each guest is priced; the room total is the sum of its occupants | **The full children policy applies**: price by band, age discount and extra-bed fees |

Per-person example (whole stay): adult 150 EUR; a child aged 8 with a 50% discount costs 75 EUR, so a room with two adults and one child costs 375 EUR. A room priced per room at 400 EUR costs 400 EUR whatever the distribution of guests inside it.

The price summary shows each room's occupants, each person's price, the discount applied and the room total.

## 8. Pricing engine and partner prices

Every service has a **retail price** (customers) and a **partner price** (approved partners). The admin can define the partner price in two ways, and the system supports both at the same time.

### 8.1 Two ways to set partner prices

| Method | How it works | Best for |
| --- | --- | --- |
| **A. Commission rule** (recommended default) | The partner price is the retail price reduced by a **commission percentage**. The percentage is set **per partner and per service category** (Trips, Nile trips, Packages, Transfers, Accommodation). Example: Partner Alpha earns 15% on Trips, 10% on Packages, 8% on Accommodation | Most items: no double data entry; changing a retail price updates the partner price automatically |
| **B. Explicit partner price (dual price)** | When the admin enters a price on any item, a second field **Partner price** sits beside it. If filled, it is used for partners instead of the commission rule | Special net rates, contract prices, individual rooms or trips with their own partner rate |

**Priority order (first match wins):** item-level partner price (method B) > partner's category commission (method A) > partner's default commission > retail price.

### 8.2 What is covered

The rule applies to every priced element: trip and package prices (private tiers, group age bands and unit options, in every season), add-ons, optional rooms, accommodation room prices and extras, children fees, and transfer prices (the commission is applied to the computed retail result; fixed routes may carry an explicit partner price).

### 8.3 Calculation

- **Method B items:** the engine uses the partner price fields in the same calculation (including children discounts and tiers).
- **Method A items:** the engine first computes the full retail line (tiers, children discounts, nights, extras), then reduces it by the commission percentage.
- Amounts are rounded to two decimals after each line. The result is stored on the booking: retail total, partner total, commission amount and the rule that applied.
- Coupons do not apply to partner bookings unless the admin enables that per partner.
- Deposit, balance, currencies and payment methods work the same for partners, computed on the partner total.

### 8.4 Display rules

- A logged-in approved partner sees **partner prices everywhere** (listing cards, detail pages, search, checkout) with a "Partner price" badge. The admin can choose to show the retail price struck through beside it.
- Visitors and customers never see partner prices; partner prices are not exposed in public pages, APIs or SEO output.
- A partner can print a **client voucher without prices**, so it can be handed to the end client.

### 8.5 Admin screens

- **Price entry:** every price field in trips, Nile trips, packages, unit options, season prices, add-ons, rooms, transfers has the optional Partner price field next to the retail field, with a preview of the resulting partner price from the commission rule.
- **Partners > Commission matrix:** a table of partners x categories with commission percentages and the default commission.
- **Reports:** sales by partner, commission granted, margin versus retail.

## 9. Partner portal

A partner is the same kind of user as a customer, who reserves the same services but at partner prices, and who has an additional **partner panel**.

### 9.1 Registration and approval

- A partner registers from the website ("Become a partner"): company name, trade or tax number, contact person, email, phone, country, website and optional documents (licence).
- The account stays **Pending** until the admin approves it; then **Active**. The admin can **Suspend** a partner.
- On approval the admin sets the commission percentages per category, the default commission, the price display options and whether coupons are allowed.

### 9.2 What a partner can do

- Use the whole website as a logged-in partner: search and book trips, Nile trips, packages, transfers and accommodation at partner prices, with the same booking rules (booking types, add-ons, room allocation, children policy, availability).
- Enter the end client's details as the lead traveller, while the partner is recorded as the booker.
- Pay with the same online gateways and offline methods with proof upload.
- Download vouchers with or without prices.

### 9.3 Partner panel

| Page | Content |
| --- | --- |
| Dashboard | Bookings this month, total sales at partner price, commission saved, balances due, pending proofs |
| My bookings | List with filters (service, status, date, payment status), booking detail, payment history, upload proof, cancel |
| New booking | Shortcut to the storefront in partner mode |
| Statements | Per period: bookings, partner totals, payments, outstanding balance, export to PDF and Excel |
| Price list | Read-only partner price list per service (optional, if the admin enables it) |
| Company profile | Company data, contacts, password, notification settings |

### 9.4 Admin: Partners module

- Partner applications queue, approve or reject with reason, suspend or reactivate.
- Commission matrix per partner and category, price display options, coupon permission.
- Create or edit a booking on behalf of a partner.
- Partner reports: bookings, sales, commissions, outstanding balances; export.
- Credit terms (pay later) are out of scope for now; all partner bookings follow the normal deposit and balance rules (open question 12).

## 10. Payments

Payment typically happens in two stages: a deposit at booking time (often 20% to 30%), then the balance before the service date. The same model applies to every service and to customers and partners.

### 10.1 Deposit and balance

- The admin sets the **deposit percentage** per product, building or room (for example 30%), with a global default; a fixed amount or **full payment** can be used instead.
- The deposit is computed on the **final total** (retail total for customers, partner total for partners), including rooms, add-ons and child fees.
- The customer pays only the deposit on the site and sees the total, the deposit due now and the balance due later.
- **The balance is paid externally** (cash, transfer, other) and the admin sets its due date. In the dashboard the admin records it manually (amount, date, method, note, optional receipt attachment); the booking becomes "Fully paid" and the account is closed.
- Automatic email reminders about the balance, and alerts to the admin about overdue balances.

### 10.2 Online payment gateways

- An **unlimited number** of gateways, each added from the dashboard without code changes (extensible Gateway Driver architecture).
- Suggested at launch: PayPal and Stripe, plus a local gateway for EGP such as Paymob, Fawry or Kashier; the client makes the final choice.
- Settings per gateway: name, logo, encrypted API keys, test or live mode, order, enable or disable.
- **Gateway currency:** the admin sets the currency or currencies of each gateway. For example PayPal limited to EUR appears only when the customer's selected currency is EUR.
- Optional surcharge per gateway. Confirmation arrives through **webhooks**. Card data is never stored. Every payment attempt is logged. Full and partial refunds from the dashboard where supported.

### 10.3 Offline payment methods and proof of payment

The admin can add unlimited manual methods (bank transfer, InstaPay, Vodafone Cash, e-wallets, deposit to an account, payment through an intermediary). The payment page shows **instant online payment** and **manual transfer** groups.

**Setup per method:** name, logo, order, enable; transfer details shown to the customer (account name, IBAN or number or InstaPay address, bank, instructions such as writing the booking reference); allowed currencies; optional surcharge; payment deadline; whether **proof of payment is mandatory** (default yes).

**Customer flow:**

1. The customer chooses a manual method and sees the details, the amount due in their currency and the booking reference.
2. The booking is created as "Awaiting payment" and, for accommodation, the rooms are held for the deadline.
3. The customer transfers the money and **uploads proof** (image or PDF), optionally entering transaction number, date, amount and sender name.
4. The status becomes "Awaiting receipt review" and the admin is notified.
5. The admin **approves** (the booking is confirmed and the deposit recorded) or **rejects** with a reason; the customer is emailed and can upload a new proof.

**Upload rules:** JPG, PNG, WebP or PDF up to 5 MB each (adjustable), up to 3 files per payment; available to visitors by a secure link and to registered customers and partners from their account; stored outside the public folder with the real content type checked; visible only to the booking owner and authorized admins; the admin can upload on behalf of the customer; every decision is logged. The same mechanism can be used for the balance.

### 10.4 Payment statuses

| Status | Meaning |
| --- | --- |
| Awaiting payment | Booking created, deposit not paid; expires after a time the admin sets |
| Awaiting receipt review | Manual proof uploaded and not yet approved |
| Proof rejected | Rejected with a reason; a new proof can be uploaded |
| Deposit paid | Deposit paid, balance due |
| Fully paid | Balance recorded, account closed |
| Failed | Online payment failed |
| Refunded | Refunded in full or in part |

## 11. Currencies

- Supported: **euro (EUR, the base currency)**, US dollar (USD), British pound (GBP) and Egyptian pound (EGP).
- **All prices are entered in euros** (trips, rooms, add-ons, transfer rates, children fees, partner prices). The site displays them in the customer's chosen currency by automatic conversion; exchange rates are stored relative to EUR.
- Exchange rates update automatically from an external API at an interval set by the admin, with an option to fix a manual rate and an optional margin per currency.
- The currency selector in the interface saves the choice in the session and account; the default is EUR, and an initial currency can be detected from the visitor's location.
- On payment the booking records: the EUR price, the chosen currency, the exchange rate at booking time and the amount charged, so a booking never changes later.
- Only gateways and manual methods that support the selected currency are shown.

## 12. Booking management

### 12.1 Lifecycle

1. **New / awaiting payment:** created. For accommodation the rooms are held for a time set by the admin; trips and packages hold nothing because they are not inventory-scheduled.
2. **Confirmed:** deposit paid (or confirmed manually); email with voucher sent.
3. **Fully paid:** the admin recorded the balance; account closed.
4. **Completed:** the service date passed; review request sent.
5. **Cancelled / refunded:** by the customer, partner or admin under the cancellation policy, with reason and refund amount.

Every status change is logged (who, when, note).

### 12.2 Booking data

- Unique reference; service (trip, Nile trip, package, transfer, accommodation); **booking type (private or group)** for trips and packages; start date, or check-in and check-out for accommodation.
- Booker (customer or partner) and lead traveller; travellers (adults and children with ages); special requests.
- Rooms with allocated guests (trip rooms or accommodation rooms); add-ons.
- Pricing: itemized lines, discounts, **retail total, partner total, commission amount** and the rule applied, currency, exchange rate.
- Payment: deposit, balance, recorded payments, proofs and their review status, due date.
- Included-transfer tasks (packages); internal notes; activity log.

### 12.3 Admin actions

- Search and filter by reference, name, phone, partner, service, booking type, status, date and payment status.
- Edit booking data, change date or rooms (price recalculated), record payment, close account, refund, send or resend voucher, print voucher (with or without prices), export PDF and Excel.
- Review pending proofs; manage included-transfer tasks and standalone transfer requests.
- Accommodation: calendar view of room occupancy, manual room blocks, drag a booking to change room or dates when available.

### 12.4 Cancellation and refunds

A policy per product, building or transfer hub with time bands (for example: more than 7 days before is a full refund, 3 to 7 days half, under 3 days none). The suggested refund is computed automatically and confirmed by the admin.

## 13. Customer website

### 13.1 Pages

| Page | Content |
| --- | --- |
| Home | Hero with a multi-service search (no date field on the Trips, Nile and Packages tabs), destinations, featured trips, packages, accommodation highlights, **transfers section with the booking form first and the image after the inputs** (CR-3), **Google reviews** (CR-4), why choose us, newsletter |
| Trips and Nile trips listing | Card grid, filters, sorting, pagination |
| Trip page | Gallery, price and Google rating, **Private / Group selector**, **unit selector for per-unit trips** (Single / Double...), maximum persons note for per-person trips, travel date inside the booking widget (any day for private, listed days for group), **Prices and seasons** table, description, highlights, included and not included, timeline, add-ons, meeting point and map, policies, FAQ, Google reviews, similar trips, side booking form |
| Packages and package page | Stages and trips within the timeline, **"Transfers included" badge**, add-ons, booking form |
| Destination page | Introduction with trips, packages and accommodation of the destination |
| **Accommodation listing** | **Rooms directly** as cards with building data, search and filters, map view (6.3) |
| **Room page** | Gallery, room details, building card, availability calendar, booking widget |
| **Building page** | Building details, amenities, policies, map, its rooms |
| Transfer request | Request form with live quote (Section 5) |
| Booking and payment | Steps, price summary, room allocation, gateway or manual method with proof upload |
| Booking confirmation | Reference, details, voucher download |
| Customer account | My bookings, booking detail with payments and proofs, profile, favourites |
| **Become a partner** | Partner registration form and information |
| **Partner panel** | Dashboard, bookings, statements, price list, company profile (Section 9) |
| Sign in and register | Email and password, optional Google sign-in |
| Static pages | About, contact, FAQ, privacy, terms, cancellation policy |
| Blog | Travel articles (optional) |
| 404 and maintenance | Custom messages |

### 13.2 Search and filtering

- Multi-service search bar: trips, Nile trips, packages, accommodation, transfers.
- Trip filters: type, booking type, pricing mode, destination, price, duration, category, rating, language, free cancellation. **No date filter** for trips, Nile trips and packages (CR-5).
- Accommodation filters: destination, building, dates, guests, room type, price, rating, amenities, free cancellation.
- Sorting: recommended, price, rating, newest, most booked.

### 13.3 User experience

- Fully responsive on mobile and tablet; supports LTR and RTL so languages such as Arabic can be added.
- Favourites for registered users; WhatsApp quick contact; lazy-loaded modern image formats.
- A logged-in partner sees partner prices and a Partner badge throughout; visitors never do.
- **Mobile filter sheet (CR-6):** boolean filters such as Free cancellation are shown as a full-width switch row: leading icon, label, one-line helper text ("Cancel up to 24 hours before for a full refund"), and a 44 px toggle on the right, inside a bordered card. No bare checkboxes in the mobile sheet. The active filter also appears as a removable chip.

### 13.4 Google reviews (CR-4)

- Reviews are **Google reviews** of the business's Google Business Profile, read through the **Google Places API** by the Laravel API on a schedule (default every 12 hours) and cached in the database; the site never calls Google from the browser.
- The admin sets the Google **Place ID** of the business (and optionally a Place ID per building for accommodation).
- Displayed: overall Google rating, total review count, the latest reviews (author name, profile photo, rating, relative time, text, original language), the Google logo and attribution, and a **"Write a review on Google"** button.
- The admin can hide individual cached reviews from the site (for example off-topic ones) but cannot edit them.
- Schema.org AggregateRating uses the Google rating only where Google's terms allow; otherwise it is omitted.
- There is no on-site review form and no post-trip review moderation; the post-trip email invites the customer to review on Google.

## 14. Admin dashboard

### 14.1 Modules

| Module | Functions |
| --- | --- |
| Statistics | Bookings today and this month, revenue, deposits collected and balance due, bookings awaiting payment, pending receipts, partner sales, best sellers, charts |
| Trips and Nile trips | CRUD, gallery, drag-and-drop timeline builder, **private and group settings** (private tiers, group days and dates, blackout dates, lead time), **pricing mode** (per person with maximum persons, or per unit with unit options), **seasonal price list**, pricing with **retail and partner price fields**, add-ons, optional rooms (Nile cabins without deck), policies, SEO, translations |
| Packages | Stages, link trips, arrange days, price mode, **seasonal price list**, included transfers, add-ons, retail and partner prices |
| Add-ons library | Reusable add-ons with pricing basis, required or optional, booking types, partner price |
| Transfers | Requests, statuses, driver and car details, hubs and zones, pricing rules, fixed routes, vehicles and multipliers, pricing simulator, included-transfer tasks from packages |
| **Buildings** | Create and edit buildings: data, amenities, gallery, policies, children policy, deposit, cancellation, **Taxes included** flag, Google Place ID |
| **Rooms** | Create rooms inside a building: type, capacity, beds, gallery, pricing mode, **seasonal price list**, partner price, min and max nights; availability calendar per room, manual blocks, iCal links |
| **Accommodation bookings calendar** | Occupancy grid of rooms by date, drag to move a booking, hold and release |
| Destinations | Destinations with images and descriptions |
| Unit types and ratings | Trip-room unit types and star ratings |
| Bookings | Browse, filter, edit, record payments, close accounts, voucher with or without prices |
| Receipts review | Pending proofs, preview, approve or reject with reason |
| Payments | Transaction log, gateways, manual methods, refunds |
| Currencies | EUR base, other currencies, exchange rates, automatic or manual |
| Customers | Customer list and history |
| **Partners** | Applications, approval, suspension, **commission matrix per partner and category**, coupon permission, price display options, partner bookings and reports, create bookings on behalf of a partner |
| Google reviews | Place IDs, sync now, last sync status, hide or show individual cached reviews |
| Content | Static pages, blog, banners, FAQ |
| Coupons | Percentage or fixed codes, validity, usage limit, applicability by service |
| Notifications | Email templates and sending log |
| Users and roles | Team accounts and permissions |
| Settings | Company data, SEO, default deposit, hold durations, languages, maintenance, distance unit |
| Reports | Bookings, revenue, payments, balances, best sellers, partner sales and commissions; Excel and PDF export |
| Activity log | Every action by dashboard users |

### 14.2 Permissions

Roles and permissions per module (view, add, edit, delete), secure login and optional two-factor authentication for the Super Admin. Price and partner-price fields can be restricted by permission.

## 15. Notifications, languages and SEO

### 15.1 Notifications

| Event | Recipient | Channel |
| --- | --- | --- |
| New booking | Customer or partner, and admin | Email |
| Payment confirmed (deposit) | Customer or partner, and admin | Email |
| Balance reminder | Customer or partner | Email |
| Balance recorded, account closed | Customer or partner | Email |
| Payment failed or deadline expired | Customer or partner | Email |
| Accommodation hold about to expire | Customer or partner | Email |
| Transfer request and status change | Admin and customer or partner | Email |
| Included transfer task created (package) | Admin | Email and dashboard |
| New proof uploaded | Admin | Email and dashboard |
| Proof approved or rejected | Customer or partner | Email |
| Cancellation or refund | Customer or partner | Email |
| Partner application received / approved / rejected | Admin / partner | Email |
| Post-trip invitation to review on Google | Customer | Email |

Templates are editable and multilingual.

### 15.2 Languages

Italian and English are the primary languages, complete in the interface and content. The system is multilingual, so the admin can add any language (including Arabic) from the dashboard, and the interface switches between LTR and RTL automatically.

- Default language set by the admin; browser detection and a language menu on every page.
- Translatable content: titles, descriptions, timelines, highlights, inclusions, FAQs, policies, buildings, rooms, add-ons, destinations, vehicles, static pages, blog and payment instructions, entered in a tab per language.
- Fallback to English when a translation is missing, with an admin alert.
- Separate URL per language (/it/, /en/) with hreflang and translated slugs; emails, vouchers and PDFs in the customer's booking language; dates, numbers and currency formatting follow the language.

### 15.3 SEO

With Next.js server rendering: clean translated URLs, meta titles and descriptions per page, Schema.org data (TouristTrip, Product, Hotel and HotelRoom, Review, Breadcrumb), automatic XML sitemap, robots.txt, Open Graph, hreflang, 301 redirects, Analytics, Tag Manager and Meta Pixel. Partner prices and partner pages are excluded from indexing.

## 16. Non-functional requirements

| Area | Requirement |
| --- | --- |
| Performance | Page load under 3 seconds on a normal connection; server rendering, incremental static regeneration and caching for public pages; image optimization |
| Capacity | At least 500 concurrent users without noticeable degradation; stateless API and frontend for horizontal scaling |
| Security | HTTPS everywhere, password hashing, protection against SQL injection, XSS and CSRF, login rate limiting, encrypted gateway keys, secure httpOnly session cookies, no card data storage, secure proof-file uploads, partner prices never exposed to public routes |
| Privacy | Privacy policy, cookie consent, deletion on request |
| Availability | 99.5% monthly target and automatic monitoring |
| Backup | Daily database and file backup kept at least 14 days, with a restore plan |
| Compatibility | Latest two versions of Chrome, Safari, Firefox and Edge; iOS and Android devices |
| Usability | Simple, clear interface and basic accessibility (alt text, colour contrast, keyboard navigation) |
| Maintainability | TypeScript on the frontend, documented API (OpenAPI), error logs, and the ability to add gateways, languages and currencies without major changes |
| Data integrity | Booking and payment operations inside transactions; row-level locking on rooms to prevent double booking |

## 17. Architecture and integrations

### 17.1 Architecture: Next.js frontend and Laravel API

The Blade views of the earlier version are **replaced by Next.js**. The system is a decoupled application: Next.js apps render the pages and call a Laravel API that owns all business logic, so prices and rules are always calculated on the server.

&#91;embedded content: architecture · 2 frontends, 1 API, 3 stores, 4 external services\]

Both Next.js apps talk only to the Laravel API, which owns the data and calls the external services.

### 17.2 Technologies

| Layer | Proposed |
| --- | --- |
| Customer and partner site | **Next.js** (App Router, TypeScript, React) with server rendering and ISR for SEO, **next-intl** for /it and /en routing and future languages, Tailwind CSS with Radix-based primitives, React Hook Form with Zod for forms, TanStack Query for data, next/image for optimized images |
| Admin dashboard (CR-9) | **Inside the Laravel application**: Blade views, **Tailwind CSS**, **vanilla JavaScript** (no Livewire, no Inertia), **Yajra DataTables** (server-side tables), **Select2** (searchable and multi selects), SortableJS (drag-and-drop timelines), Flatpickr (dates and season ranges), Chart.js (statistics), built with Vite. Session authentication with roles and permissions |
| Backend API | PHP with the Laravel framework (latest stable), versioned REST JSON API (/api/v1) with OpenAPI documentation and API Resources, Laravel Sanctum, policies for roles and permissions |
| Backend architecture (CR-9) | **Controllers stay thin** and call **Services** (business rules: pricing engine, availability, allocation, bookings); Services read and write through **Repositories** (interfaces bound in a service provider, Eloquent implementations); data crosses layers as immutable **DTOs** built from Form Requests; fixed value sets are **PHP backed Enums** (booking type, pricing mode, booking status, payment status, service type, room type, discount type...). The same Services serve the admin panel and the API |
| Database | MySQL |
| Queues and jobs | Redis with Laravel Queues and Scheduler (emails, reminders, exchange rates, hold expiry, iCal sync) |
| Hosting | Ubuntu VPS with Docker: Nginx reverse proxy, PHP-FPM, MySQL, Redis and the Next.js standalone Node server; SSL and automated backups |
| Storage | Local storage or an S3-compatible service for images and proof files (private bucket for proofs) |

### 17.3 External integrations

- Payment gateways (PayPal, Stripe, a local gateway) through their APIs and webhooks, handled by the Laravel API.
- An exchange-rate service (rates relative to EUR).
- **Google Places API** for Google reviews and rating (CR-4).
- A maps and routing service for transfer distance (Google Routes, Mapbox or OSRM) and Google Maps for meeting points and buildings.
- An email service (SMTP, Mailgun or Amazon SES).
- Google Analytics, Tag Manager, Meta Pixel and reCAPTCHA.
- WhatsApp contact links (WhatsApp Business API is out of scope).
- Optional iCal import and export for room calendars.

## 18. Data model (main entities)

| Entity | Key fields and relations |
| --- | --- |
| users | Name, email, phone, password, role (customer, partner, staff) |
| partners | user\_id, company, tax number, contact, country, status (pending, active, suspended), default commission %, show retail price flag, coupons allowed, approved by and at |
| partner\_category\_commissions | partner\_id, category (trips, nile\_trips, packages, transfers, accommodation), commission % |
| partner\_prices | Polymorphic item type and id, partner price in EUR (explicit partner price per item or room) |
| destinations | Name, description, images, slug |
| trips | Type (single-day, multi-day, Nile), destination, title, description, duration, highlights, private enabled, group enabled, **pricing mode (per person, per unit)**, **min and max persons per booking**, base price, deposit %, cancellation policy, SEO |
| trip\_unit\_options | trip\_id, name, seats per unit, price per unit in EUR, partner price, max quantity, children allowed, sort order (per-unit trips: Single buggy, Double buggy) |
| season\_prices | Polymorphic priceable (trip, package, room), name, season from, season to, recurring yearly, active, **price payload**: per-person price, child price, private tier prices, unit option prices, price per night, partner price |
| trip\_availability\_rules | trip\_id, booking type (private or group), weekdays, specific dates, blackout dates, lead time, cut-off |
| trip\_private\_price\_tiers | trip\_id, min people, max people, price per person in EUR |
| trip\_group\_prices | trip\_id, category (adult or child band), price per person in EUR (base; seasonal values live in season\_prices) |
| trip\_media, trip\_days, trip\_timeline\_items | Gallery, days and timeline items with times |
| addons | name, description, price in EUR, pricing basis, required flag, max quantity, booking types, partner price |
| addon\_assignments | addon\_id, product type and id (trip, Nile trip, package) |
| packages, package\_stages, package\_stage\_trips | Package data, stages with destination and days, trips per day, price mode, transfers included flag |
| package\_included\_transfers | package\_id, stage, from, to, vehicle type, day (template for included-transfer tasks) |
| trip\_units | product id, unit type, rating, price, pricing method (per room or per person), capacity (3, 4, 5), children policy |
| unit\_types, unit\_ratings | Unit type names and star levels |
| buildings | name, slug, type, destination, address, coordinates, stars, description, amenities, check-in and check-out times, policies, deposit %, cancellation policy, **taxes included flag, tax per night (when not included), Google Place ID**, status |
| building\_media, building\_amenities | Gallery and amenity links |
| rooms | building\_id, name or number, room type, max adults, max children, max total, beds, size, amenities, pricing mode, base price per night in EUR, extra bed and cot prices, min and max nights, status |
| room\_prices | room\_id, weekday rule, price (seasons live in season\_prices) |
| room\_availability | room\_id, date, status (open, closed, held, booked), price override |
| room\_ical\_links | room\_id, import and export URLs |
| child\_policies | Owner (building, room or trip unit), max adults and children, age bands, discount type and value per band, discount conditions, extra bed |
| transfer\_hubs, transfer\_pricing\_rules, transfer\_fixed\_routes, vehicle\_types | Hubs and zones, tiers and fees, fixed routes with optional partner price, vehicles and multipliers |
| transfer\_requests | Hub, destination, date, vehicle, passengers, distance, time, pricing breakdown, status, driver and car |
| bookings | reference, service type, booking type (private or group), pricing mode, season applied, start date or check-in and check-out, booker user, partner\_id, lead traveller, **retail total EUR, partner total EUR, commission amount, price rule applied**, currency, exchange rate, deposit, balance, status |
| booking\_travelers | booking\_id, name, type, age |
| booking\_units | booking\_id, trip\_unit\_option\_id, name, seats, quantity, unit price, total (per-unit trips) |
| booking\_rooms | booking\_id, room or unit reference, room number, pricing method, nights, price, total |
| booking\_room\_guests | booking\_room\_id, traveler\_id, guest price, discount applied, extra-bed fee |
| booking\_addons | booking\_id, addon\_id, quantity, price |
| booking\_included\_transfers | booking\_id, from, to, date, time, vehicle, status, driver and car (no price) |
| payments, payment\_gateways | Gateway or manual method, amount, currency, status, transaction ID, type (deposit, balance, refund) |
| manual\_payment\_methods, payment\_proofs | Method details, currencies, proof required; proof file, transaction data, review status, reviewer, rejection reason |
| currencies | Code, symbol, rate relative to EUR, margin, enabled |
| google\_reviews | place\_id, Google review id, author name, author photo URL, rating, text, language, published at, hidden flag, synced at |
| coupons | Coupons with applicability by service |
| roles and permissions, activity\_logs, settings | Access control, audit trail, global settings |

User-facing text fields are stored in translation tables to support multiple languages.

## 19. Delivery plan and deliverables

The durations are estimates, to be fixed after approval of this document and the team is set. Some phases can run in parallel with more than one developer.

| Phase | Content | Deliverable | Estimated duration |
| --- | --- | --- | --- |
| 1. Analysis and design | Requirements sign-off, wireframes, UI/UX for site, partner panel and dashboard | Approved designs and database design | 3 to 4 weeks |
| 2. Foundations | Laravel API, Next.js monorepo, authentication, roles, i18n, currencies (EUR base), settings | Running skeleton of API, site and dashboard | 3 to 4 weeks |
| 3. Trips and Nile trips | Trips, timelines, private and group booking types, add-ons | Trip management and display | 4 to 5 weeks |
| 4. Rooms and children policy | Trip rooms, allocation rules, children policy and age discounts | Optional room booking | 2 to 3 weeks |
| 5. Packages | Stages, linked trips, included transfers and tasks | Package management and display | 2 to 3 weeks |
| 6. Booking and payment | Booking flow, deposit, gateways, manual payments with proof, refunds | Integrated booking and payment | 4 to 5 weeks |
| 7. Transfers | Hubs, distance pricing algorithm, payment, requests | Transfers service | 3 to 4 weeks |
| 8. Accommodation | Buildings, rooms, calendars, listing of rooms with building data, booking flow, holds | Accommodation service | 4 to 5 weeks |
| 9. Partners | Registration and approval, commission matrix, dual prices, partner panel, reports | Partner portal | 3 to 4 weeks |
| 10. Notifications, reports, SEO | Emails, reports, SEO and performance tuning | Complete system | 2 to 3 weeks |
| 11. Testing and launch | Full testing, fixes, data migration, deployment, training | Live site and user guide | 3 weeks |

Estimated total: **33 to 43 weeks**, depending on content volume and how fast designs are approved.

### 19.1 Final deliverables

- Full source code (Next.js apps and Laravel API) in an organized repository.
- Database and initial data; OpenAPI documentation of the API.
- Site, partner panel and dashboard published in production.
- A dashboard and partner user guide, and training for the client team.
- A technical document for developers (environment setup, deployment, gateway integration).
- A warranty period for bug fixes after launch (to be agreed).

## 20. Out of scope, open questions and acceptance

### 20.1 Out of current scope

- Native mobile apps.
- Flight and visa booking, channel-manager integrations with external booking sites (only optional iCal).
- Running transfers, fleet and driver management (outside the system).
- A full accounting system, e-invoicing, and partner credit or pay-later terms.
- Entering initial content, unless agreed separately.
- WhatsApp Business API.

### 20.2 Open questions for the client

| # | Question | Current assumption |
| --- | --- | --- |
| 1 | Languages required | Italian and English primary, others addable |
| 2 | Payment gateways | PayPal, Stripe and a local gateway |
| 3 | Booking without an account | Allowed, account optional |
| 4 | Private price tiers by party size | 1, 2, 3-4, 5-8, 9 and more |
| 5 | Default deposit percentage | 30%, editable per product, building and room |
| 6 | Do group departures need seat limits? | No seat inventory; availability by day rule only |
| 7 | Cancellation and refund policy | Time bands defined by the client |
| 8 | Accommodation hold time while paying | Configurable, default 30 minutes |
| 9 | Child prices: percentage or amount | Both available |
| 10 | Who enters the initial content | The client |
| 11 | Visual identity | A new design will be approved |
| 12 | Partner credit terms (pay later) | Not included; normal deposit and balance rules |
| 13 | Meaning of "departure line" for transfers | Predefined hubs and zones |
| 14 | Transfer distance unit and maps service | Kilometre or mile setting; Google or Mapbox |
| 15 | Transfers: full payment or deposit | Full by default, deposit optional |
| 16 | Who translates content and dashboard language | Client provides translations; dashboard in English |
| 17 | No-shared-rooms rule | Rooms exclusive to one booking; five guests may take double + triple, or double + double + single if all are available; two doubles alone is blocked; no empty booked rooms; all rooms in one building |
| 18 | Partner sees retail price struck through | Admin option, hidden by default |
| 19 | Partner commission categories | Trips, Nile trips, Packages, Transfers, Accommodation, plus a default |
| 20 | Tourism tax or city fee for accommodation | Optional fee line per building |
| 21 | iCal sync for room calendars | Optional, phase 8 |
| 22 | Trip date (CR-5) | Removed from search and listing filters; one travel date stays in the booking widget to validate day rules and pick the season price |
| 23 | Per-unit trips (CR-1) | Unit options with seats and price (Single buggy 1 seat, Double buggy 2 seats); each unit needs at least one adult |
| 24 | Google reviews (CR-4) | Google reviews replace on-site reviews; synced every 12 hours via Places API; Places API returns at most 5 reviews per place |
| 25 | Season overlap (CR-7) | Not allowed per product; base price outside seasons |
| 26 | Taxes (CR-2) | Prices are tax-inclusive by default with a "Tax included" badge |

### 20.3 Acceptance criteria

- Every module in this document works in production.
- A full booking succeeds for each service (trip, Nile trip, package, transfer, accommodation) from first step to confirmation.
- Private bookings can be made on any open day and group bookings only on the admin's listed days; the trip search has no date field.
- Per-unit trips (for example Single and Double buggy) and per-person trips with a maximum number of persons price and validate correctly.
- Seasonal price lists apply the right season by travel date or by night, with the base price outside seasons and no overlapping seasons.
- Accommodation prices show the "Tax included" badge; reviews shown on the site come from Google.
- The admin dashboard runs in Laravel (Blade, Tailwind, vanilla JS, Yajra DataTables, Select2, no Livewire).
- Add-ons can be attached to trips, Nile trips and packages and are included in totals, deposit and voucher.
- Packages show "Transfers included", charge nothing extra for them, and create included-transfer tasks for the admin.
- Accommodation shows rooms directly with building data; a room cannot be double-booked; holds expire correctly.
- Room allocation enforces the no-shared-rooms rules, with correct messages for the examples in 7.2.
- The children policy and age discounts compute correctly in per-person pricing.
- A partner sees partner prices in every service (commission rule and explicit price), customers never do, and each booking stores retail total, partner total and commission.
- Partner registration, approval, commission matrix, panel and statements work.
- All prices are entered in euros and displayed correctly in EUR, USD, GBP and EGP; gateways are filtered by currency.
- Manual payment with proof upload and admin approval or rejection works, with secure file access.
- Transfers are priced from the actual distance from a predefined hub and paid on the site.
- The site is served by Next.js with correct SEO, language URLs and responsive behaviour; basic security tests pass; no critical bugs at handover.

### 20.4 References

- [Multi-day tour booking software features (Touramigo)](https://www.touramigo.com/post/multi-day-tour-booking-software-features)
- [Managing complex itineraries and deposit schedules (Zaui)](https://www.zaui.com/resources/blog/multi-day-tour-software)
- [Children policies and occupancy (Booking.com Developers)](https://developers.booking.com/connectivity/docs/setting-up-children-policies-and-pricing)
- [Partial payments and gateways in booking systems (Bookinglayer)](https://bookinglayer.com/payments)
- [Travel agency payment processing (Stripe)](https://stripe.com/en-gr/resources/more/travel-agency-payment-processing)
